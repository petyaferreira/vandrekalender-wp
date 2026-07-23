<?php
/**
 * Emails sent when someone joins an event.
 *
 * Two plain-text messages per join: a confirmation to the attendee and a
 * notification to the organiser. Both go through wp_mail(), so whatever SMTP
 * transport the site configures (FluentSMTP is bundled) applies automatically —
 * nothing here talks to a mail server directly.
 *
 * The From address defaults to the site's admin email rather than WordPress's
 * wordpress@<domain> default, because that synthetic address rarely passes SPF
 * and lands the mail in spam. Reply-To is crossed over: the attendee can reply
 * to the organiser and the organiser can reply to the attendee.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds and sends the join emails.
 */
class Vandrekalender_Event_Join_Mailer {

	/**
	 * Email the attendee a confirmation that they are going.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $user_id  Attendee user ID.
	 * @return bool Whether the mail was accepted for delivery.
	 */
	public static function send_attendee_confirmation( int $event_id, int $user_id ): bool {
		$user = get_userdata( $user_id );
		$post = get_post( $event_id );

		if ( ! $user || ! $post || ! is_email( $user->user_email ) ) {
			return false;
		}

		/* translators: %s: event title */
		$subject = sprintf( __( 'You are going to %s', 'vandrekalender-events' ), $post->post_title );

		$lines = [
			/* translators: %s: attendee first name or display name */
			sprintf( __( 'Hi %s', 'vandrekalender-events' ), self::user_name( $user ) ),
			'',
			/* translators: %s: event title */
			sprintf( __( 'You have signed up for %s. The organiser has been notified.', 'vandrekalender-events' ), $post->post_title ),
			'',
			self::event_summary( $event_id ),
			'',
			__( 'See you on the trail!', 'vandrekalender-events' ),
			get_bloginfo( 'name' ),
		];

		return self::send(
			$user->user_email,
			$subject,
			implode( "\n", $lines ),
			self::organiser_email( $event_id )
		);
	}

	/**
	 * Email the organiser that someone joined.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $user_id  Attendee user ID.
	 * @return bool Whether the mail was accepted for delivery.
	 */
	public static function send_organiser_notification( int $event_id, int $user_id ): bool {
		$user      = get_userdata( $user_id );
		$post      = get_post( $event_id );
		$recipient = self::organiser_email( $event_id );

		if ( ! $user || ! $post || ! is_email( $recipient ) ) {
			return false;
		}

		/* translators: 1: attendee name, 2: event title */
		$subject = sprintf( __( '%1$s is coming to %2$s', 'vandrekalender-events' ), self::user_name( $user ), $post->post_title );

		$count = Vandrekalender_Event_Attendees::count( $event_id );

		$lines = [
			/* translators: 1: attendee name, 2: attendee email, 3: event title */
			sprintf( __( '%1$s (%2$s) has signed up for %3$s.', 'vandrekalender-events' ), self::user_name( $user ), $user->user_email, $post->post_title ),
			'',
			/* translators: %d: number of people signed up */
			sprintf( _n( '%d person has signed up so far.', '%d people have signed up so far.', $count, 'vandrekalender-events' ), $count ),
			'',
			self::event_summary( $event_id ),
			'',
			get_bloginfo( 'name' ),
		];

		return self::send(
			$recipient,
			$subject,
			implode( "\n", $lines ),
			$user->user_email
		);
	}

	/**
	 * Email the attendee that their sign-up is cancelled.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $user_id  Former attendee user ID.
	 * @return bool Whether the mail was accepted for delivery.
	 */
	public static function send_attendee_cancellation( int $event_id, int $user_id ): bool {
		$user = get_userdata( $user_id );
		$post = get_post( $event_id );

		if ( ! $user || ! $post || ! is_email( $user->user_email ) ) {
			return false;
		}

		/* translators: %s: event title */
		$subject = sprintf( __( 'Your sign-up for %s is cancelled', 'vandrekalender-events' ), $post->post_title );

		$lines = [
			/* translators: %s: attendee first name or display name */
			sprintf( __( 'Hi %s', 'vandrekalender-events' ), self::user_name( $user ) ),
			'',
			/* translators: %s: event title */
			sprintf( __( 'We have taken you off the list for %s. The organiser has been notified.', 'vandrekalender-events' ), $post->post_title ),
			'',
			self::event_summary( $event_id ),
			'',
			__( 'Changed your mind? You are welcome to sign up again.', 'vandrekalender-events' ),
			get_bloginfo( 'name' ),
		];

		return self::send(
			$user->user_email,
			$subject,
			implode( "\n", $lines ),
			self::organiser_email( $event_id )
		);
	}

	/**
	 * Email the organiser that someone cancelled.
	 *
	 * Carries the updated head count, which is the number the organiser
	 * actually plans around — the point of the email.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $user_id  Former attendee user ID.
	 * @return bool Whether the mail was accepted for delivery.
	 */
	public static function send_organiser_cancellation( int $event_id, int $user_id ): bool {
		$user      = get_userdata( $user_id );
		$post      = get_post( $event_id );
		$recipient = self::organiser_email( $event_id );

		if ( ! $user || ! $post || ! is_email( $recipient ) ) {
			return false;
		}

		/* translators: 1: attendee name, 2: event title */
		$subject = sprintf( __( '%1$s has cancelled for %2$s', 'vandrekalender-events' ), self::user_name( $user ), $post->post_title );

		$count = Vandrekalender_Event_Attendees::count( $event_id );

		$lines = [
			/* translators: 1: attendee name, 2: attendee email, 3: event title */
			sprintf( __( '%1$s (%2$s) has cancelled their sign-up for %3$s.', 'vandrekalender-events' ), self::user_name( $user ), $user->user_email, $post->post_title ),
			'',
			/* translators: %d: number of people signed up */
			sprintf( _n( '%d person is signed up now.', '%d people are signed up now.', $count, 'vandrekalender-events' ), $count ),
			'',
			self::event_summary( $event_id ),
			'',
			get_bloginfo( 'name' ),
		];

		return self::send(
			$recipient,
			$subject,
			implode( "\n", $lines ),
			$user->user_email
		);
	}

	/**
	 * Where the organiser notification goes.
	 *
	 * `event_organiser_email` is an admin-only field and is often empty on
	 * events people create themselves, so the event's author is the fallback —
	 * on a manually created event that is the person running the walk.
	 *
	 * @param int $event_id Event post ID.
	 * @return string Email address, or an empty string when none is known.
	 */
	public static function organiser_email( int $event_id ): string {
		$email = (string) get_post_meta( $event_id, \Vandrekalender\Event::META_ORGANISER_EMAIL, true );

		if ( ! is_email( $email ) ) {
			$author = get_userdata( (int) get_post_field( 'post_author', $event_id ) );
			$email  = $author ? $author->user_email : '';
		}

		/**
		 * Filter the organiser address a join notification is sent to.
		 *
		 * @param string $email    Resolved recipient address.
		 * @param int    $event_id Event post ID.
		 */
		$email = (string) apply_filters( 'vandrekalender_join_organiser_email', $email, $event_id );

		return is_email( $email ) ? $email : '';
	}

	/**
	 * The date/time/place block both emails repeat.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	private static function event_summary( int $event_id ): string {
		$date   = get_post_meta( $event_id, \Vandrekalender\Event::META_DATE, true );
		$place  = get_post_meta( $event_id, \Vandrekalender\Event::META_PLACE_NAME, true );
		$place  = $place ? $place : get_post_meta( $event_id, \Vandrekalender\Event::META_MUNICIPALITY, true );
		$routes = get_post_meta( $event_id, \Vandrekalender\Event::META_ROUTES, true );
		$routes = is_array( $routes ) ? array_values( array_filter( $routes ) ) : [];
		$start  = isset( $routes[0]['start_time'] ) ? $routes[0]['start_time'] : '';

		$lines = [];

		if ( $date ) {
			/* translators: %s: event date */
			$lines[] = sprintf( __( 'Date: %s', 'vandrekalender-events' ), date_i18n( get_option( 'date_format' ), strtotime( $date ) ) );
		}

		if ( $start ) {
			/* translators: %s: start time, e.g. 09:00 */
			$lines[] = sprintf( __( 'Start time: %s', 'vandrekalender-events' ), $start );
		}

		if ( $place ) {
			/* translators: %s: place name */
			$lines[] = sprintf( __( 'Place: %s', 'vandrekalender-events' ), $place );
		}

		$lines[] = get_permalink( $event_id );

		return implode( "\n", $lines );
	}

	/**
	 * A person's name for use in an email.
	 *
	 * @param WP_User $user User object.
	 * @return string
	 */
	private static function user_name( WP_User $user ): string {
		$name = trim( $user->first_name . ' ' . $user->last_name );

		return $name ? $name : $user->display_name;
	}

	/**
	 * Send one plain-text mail with our From and Reply-To headers.
	 *
	 * The From filters are added and removed around this single wp_mail() call
	 * so the site's other mail (password resets, form notifications) keeps
	 * whatever From address it already uses.
	 *
	 * @param string $to       Recipient address.
	 * @param string $subject  Subject line.
	 * @param string $body     Plain-text body.
	 * @param string $reply_to Optional Reply-To address.
	 * @return bool
	 */
	private static function send( string $to, string $subject, string $body, string $reply_to = '' ): bool {
		/**
		 * Filter the From address of the join emails.
		 *
		 * @param string $from Email address.
		 */
		$from = (string) apply_filters( 'vandrekalender_join_email_from', get_option( 'admin_email' ) );

		/**
		 * Filter the From name of the join emails.
		 *
		 * @param string $from_name Display name.
		 */
		$from_name = (string) apply_filters( 'vandrekalender_join_email_from_name', get_bloginfo( 'name' ) );

		$set_from      = static fn() => $from;
		$set_from_name = static fn() => $from_name;

		add_filter( 'wp_mail_from', $set_from );
		add_filter( 'wp_mail_from_name', $set_from_name );

		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$sent = wp_mail( $to, $subject, $body, $headers );

		remove_filter( 'wp_mail_from', $set_from );
		remove_filter( 'wp_mail_from_name', $set_from_name );

		return (bool) $sent;
	}
}
