<?php
/**
 * The "Jeg kommer" join flow, including the login gate.
 *
 * Three entry points, one join routine:
 *
 * 1. admin-post.php — the block's form target. Works without JavaScript, and is
 *    the only path a logged-out visitor takes, because setting the pending
 *    cookie and redirecting to the login screen has to happen server-side.
 * 2. wp_login — fires for native logins *and* for Login with Google, which runs
 *    through the standard `authenticate` filter on wp-login.php. Hooking it
 *    once therefore covers both providers; there is no separate OAuth callback
 *    handler to maintain.
 * 3. REST — what the block calls once JavaScript has taken over, so an
 *    already-logged-in visitor flips the button without a page load.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles join requests and the login gate around them.
 */
class Vandrekalender_Event_Join {

	/**
	 * Cookie remembering which event a logged-out visitor wanted to join.
	 *
	 * Short lived on purpose: it only has to survive the round trip through
	 * wp-login.php or Google's consent screen.
	 */
	public const COOKIE = 'vk_pending_join';

	/**
	 * Cookie lifetime in seconds.
	 */
	private const COOKIE_LIFETIME = 30 * MINUTE_IN_SECONDS;

	/**
	 * Query arg added to the event URL after a successful join.
	 */
	public const JOINED_ARG = 'joined';

	/**
	 * Event joined during this request via the pending cookie, if any.
	 *
	 * Set on wp_login and read a moment later on login_redirect, in the same
	 * request — by then the cookie itself has already been cleared.
	 *
	 * @var int
	 */
	private int $joined_event_id = 0;

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( 'admin_post_vk_join_event', [ $this, 'handle_join_request' ] );
		add_action( 'admin_post_nopriv_vk_join_event', [ $this, 'handle_join_request' ] );
		// Priority 5: before Login with Google's own wp_login handler, which
		// redirects and ends the request.
		add_action( 'wp_login', [ $this, 'process_pending_join' ], 5, 2 );
		// Priority 20: after the organizer dashboard's login_redirect filter,
		// so a pending join wins over the default wp-admin destination.
		add_filter( 'login_redirect', [ $this, 'redirect_to_joined_event' ], 20, 3 );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Nonce action for one event's join form.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	public static function nonce_action( int $event_id ): string {
		return 'vk_join_event_' . $event_id;
	}

	/**
	 * The event URL a completed join lands on.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	public static function joined_url( int $event_id ): string {
		$permalink = get_permalink( $event_id );

		if ( ! $permalink ) {
			return home_url( '/' );
		}

		return add_query_arg( self::JOINED_ARG, '1', $permalink ) . '#vk-join';
	}

	/**
	 * Handle a form submission from the join button.
	 *
	 * This is the no-JavaScript path, so the single button has to serve both
	 * directions: it joins when the user is not attending and cancels when they
	 * are. With JavaScript the submit never reaches here — the block intercepts
	 * it and asks for confirmation before cancelling.
	 *
	 * @return void
	 */
	public function handle_join_request(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked below, only for the state-changing branches.
		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
		$target   = $event_id ? get_permalink( $event_id ) : '';

		if ( ! $target ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( ! is_user_logged_in() ) {
			if ( ! Vandrekalender_Event_Attendees::is_joinable( $event_id ) ) {
				wp_safe_redirect( $target );
				exit;
			}

			// Deliberately no nonce check on this branch. It changes nothing —
			// it sets a cookie and sends the visitor to the login screen — and
			// a logged-out nonce would be baked into page-cached HTML, so a
			// stale one would greet real visitors with "Are you sure?".
			$this->set_pending_cookie( $event_id );
			wp_safe_redirect( wp_login_url( $target ) );
			exit;
		}

		check_admin_referer( self::nonce_action( $event_id ) );

		$user_id = get_current_user_id();

		// Cancelling is checked first and never gated on is_joinable(): if an
		// event stops accepting sign-ups, the people already on the list must
		// still be able to get off it.
		if ( Vandrekalender_Event_Attendees::is_attending( $event_id, $user_id ) ) {
			$this->leave( $event_id, $user_id );
			wp_safe_redirect( $target );
			exit;
		}

		if ( ! Vandrekalender_Event_Attendees::is_joinable( $event_id ) ) {
			wp_safe_redirect( $target );
			exit;
		}

		$this->join( $event_id, $user_id );

		wp_safe_redirect( self::joined_url( $event_id ) );
		exit;
	}

	/**
	 * Complete a pending join right after login, whatever the provider.
	 *
	 * @param string       $user_login Username of the user who logged in.
	 * @param WP_User|null $user       User object, as passed by wp_login.
	 * @return void
	 */
	public function process_pending_join( string $user_login, $user = null ): void {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return;
		}

		$event_id = absint( wp_unslash( $_COOKIE[ self::COOKIE ] ) );

		$this->clear_pending_cookie();

		if ( ! $event_id ) {
			return;
		}

		if ( ! $user instanceof WP_User ) {
			$user = get_user_by( 'login', $user_login );
		}

		if ( ! $user || ! Vandrekalender_Event_Attendees::is_joinable( $event_id ) ) {
			return;
		}

		$this->join( $event_id, $user->ID );

		// Set even when the row already existed: the visitor asked to go to
		// this event, so that is where login should drop them either way.
		$this->joined_event_id = $event_id;
	}

	/**
	 * Send a just-logged-in joiner to the event instead of wp-admin.
	 *
	 * @param string $redirect_to           Destination WordPress settled on.
	 * @param string $requested_redirect_to Destination requested by the form.
	 * @param mixed  $user                  WP_User on success, WP_Error otherwise.
	 * @return string
	 */
	public function redirect_to_joined_event( string $redirect_to, string $requested_redirect_to, $user ): string {
		if ( $this->joined_event_id && $user instanceof WP_User ) {
			return self::joined_url( $this->joined_event_id );
		}

		return $redirect_to;
	}

	/**
	 * Register the REST route the block posts to.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$args = [
			'id' => [
				'type'     => 'integer',
				'required' => true,
			],
		];

		register_rest_route(
			Vandrekalender_Event_Rest_Api::NAMESPACE,
			'/events/(?P<id>\d+)/join',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'rest_join' ],
					'permission_callback' => static fn() => is_user_logged_in(),
					'args'                => $args,
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'rest_leave' ],
					'permission_callback' => static fn() => is_user_logged_in(),
					'args'                => $args,
				],
			]
		);
	}

	/**
	 * REST handler: join the current user to an event.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_join( WP_REST_Request $request ) {
		$event_id = (int) $request['id'];

		if ( ! Vandrekalender_Event_Attendees::is_joinable( $event_id ) ) {
			return new WP_Error(
				'vandrekalender_event_not_joinable',
				__( 'This event does not accept sign-ups.', 'vandrekalender-events' ),
				[ 'status' => 403 ]
			);
		}

		$created = $this->join( $event_id, get_current_user_id() );

		return rest_ensure_response(
			[
				'attending' => true,
				'created'   => $created,
				'count'     => Vandrekalender_Event_Attendees::count( $event_id ),
			]
		);
	}

	/**
	 * REST handler: cancel the current user's sign-up.
	 *
	 * No is_joinable() check on purpose — see leave().
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_leave( WP_REST_Request $request ) {
		$event_id = (int) $request['id'];

		$removed = $this->leave( $event_id, get_current_user_id() );

		return rest_ensure_response(
			[
				'attending' => false,
				'removed'   => $removed,
				'count'     => Vandrekalender_Event_Attendees::count( $event_id ),
			]
		);
	}

	/**
	 * Remove the join and, if there was one, send both emails.
	 *
	 * Mirrors join(): only a real change sends mail, so a repeated cancel is
	 * silent. The organiser's copy carries the updated head count — an
	 * organiser told "X is coming" and never told X left plans for the wrong
	 * number of people.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $user_id  User ID.
	 * @return bool True when a row was actually removed.
	 */
	private function leave( int $event_id, int $user_id ): bool {
		$removed = Vandrekalender_Event_Attendees::remove( $event_id, $user_id );

		if ( $removed ) {
			Vandrekalender_Event_Join_Mailer::send_attendee_cancellation( $event_id, $user_id );
			Vandrekalender_Event_Join_Mailer::send_organiser_cancellation( $event_id, $user_id );

			/**
			 * Fires once a user has cancelled their sign-up.
			 *
			 * @param int $event_id Event post ID.
			 * @param int $user_id  User ID.
			 */
			do_action( 'vandrekalender_event_left', $event_id, $user_id );
		}

		return $removed;
	}

	/**
	 * Record the join and, if it is new, send both emails.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $user_id  User ID.
	 * @return bool True when this was a new join.
	 */
	private function join( int $event_id, int $user_id ): bool {
		if ( ! Vandrekalender_Event_Attendees::is_joinable( $event_id ) ) {
			return false;
		}

		$created = Vandrekalender_Event_Attendees::add( $event_id, $user_id );

		if ( ! $created ) {
			return false;
		}

		Vandrekalender_Event_Join_Mailer::send_attendee_confirmation( $event_id, $user_id );
		Vandrekalender_Event_Join_Mailer::send_organiser_notification( $event_id, $user_id );

		/**
		 * Fires once a user has newly joined an event.
		 *
		 * @param int $event_id Event post ID.
		 * @param int $user_id  User ID.
		 */
		do_action( 'vandrekalender_event_joined', $event_id, $user_id );

		return true;
	}

	/**
	 * Remember the event a logged-out visitor wanted to join.
	 *
	 * SameSite=Lax matters here: Google's OAuth callback is a cross-site
	 * top-level navigation back to our domain, and Lax is the strictest
	 * setting that still sends the cookie on one.
	 *
	 * @param int $event_id Event post ID.
	 * @return void
	 */
	private function set_pending_cookie( int $event_id ): void {
		setcookie( self::COOKIE, (string) $event_id, self::cookie_options( time() + self::COOKIE_LIFETIME ) );
	}

	/**
	 * Drop the pending cookie once it has been acted on.
	 *
	 * @return void
	 */
	private function clear_pending_cookie(): void {
		unset( $_COOKIE[ self::COOKIE ] );
		setcookie( self::COOKIE, '', self::cookie_options( time() - YEAR_IN_SECONDS ) );
	}

	/**
	 * Cookie options shared by setting and clearing.
	 *
	 * @param int $expires Expiry timestamp.
	 * @return array<string, mixed>
	 */
	private static function cookie_options( int $expires ): array {
		return [
			'expires'  => $expires,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		];
	}
}
