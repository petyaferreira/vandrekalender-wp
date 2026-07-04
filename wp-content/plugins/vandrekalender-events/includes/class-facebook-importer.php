<?php

defined( 'ABSPATH' ) || exit;

/**
 * "Add from Facebook" admin screen under the Events menu.
 *
 * Paste-a-link importer for Facebook events. The admin pastes a public
 * facebook.com/events/... URL; the importer fetches the page server-side,
 * reads its Open Graph tags (title, description, image), and creates a
 * prefilled draft event. The admin then completes the required fields the
 * page cannot provide structured — date, routes, and address (geocoded via
 * the editor's DAWA autocomplete) — and publishes.
 *
 * Facebook often serves a login wall to anonymous requests, so the prefill
 * is best-effort: when no usable Open Graph data comes back, the draft is
 * still created with the source URL set, and the admin fills everything in.
 *
 * Drafts deduplicate against `event_source_url`, the same key the scraping
 * pipeline uses, so an event can never be imported twice.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Facebook_Importer {

	const PAGE_SLUG = 'vandrekalender-facebook-import';
	const ACTION    = 'vk_facebook_import';
	const MAX_BATCH = 30;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_page' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_import' ] );
	}

	/**
	 * Register the Add from Facebook submenu under the Events post type.
	 *
	 * @return void
	 */
	public function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . \Vandrekalender\Event::CUSTOMPOSTTYPE,
			__( 'Add from Facebook', 'vandrekalender-events' ),
			__( 'Add from Facebook', 'vandrekalender-events' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render the importer page: notice from the last import, the URL form,
	 * and a list of recent Facebook imports.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Add from Facebook', 'vandrekalender-events' ) . '</h1>';

		$this->render_notice();

		echo '<p>' . esc_html__( 'Paste one or more public Facebook event URLs, one per line (e.g. https://www.facebook.com/events/1234567890). Each new URL becomes a draft event prefilled with whatever Facebook exposes (title, date, organiser, cover image); you complete the routes and address in the editor and publish. URLs that were imported before are skipped, so it is safe to re-paste a whole list.', 'vandrekalender-events' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		wp_nonce_field( self::ACTION );
		echo '<p>';
		echo '<label class="screen-reader-text" for="vk-fb-urls">' . esc_html__( 'Facebook event URLs, one per line', 'vandrekalender-events' ) . '</label>';
		echo '<textarea id="vk-fb-urls" name="vk_fb_urls" class="large-text code" rows="8" placeholder="https://www.facebook.com/events/…&#10;https://www.facebook.com/events/…" required></textarea>';
		echo '</p>';
		/* translators: %d: maximum number of URLs per import. */
		echo '<p class="description">' . esc_html( sprintf( __( 'Up to %d links per import. Each event is fetched from Facebook, so a large batch takes a little while.', 'vandrekalender-events' ), self::MAX_BATCH ) ) . '</p>';
		echo '<p>';
		submit_button( __( 'Create draft events', 'vandrekalender-events' ), 'primary', 'submit', false );
		echo '</p>';
		echo '</form>';

		$this->render_bookmarklet();
		$this->render_recent_imports();

		echo '</div>';
	}

	/**
	 * Handle the form submission: normalise, deduplicate, fetch, create drafts.
	 *
	 * Accepts one URL per line. Every URL is handled independently — already
	 * imported events are skipped untouched (create-or-skip, never update),
	 * so the same list can be re-pasted safely every week. Redirects back to
	 * the importer page with a summary of what happened.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to import events.', 'vandrekalender-events' ) );
		}

		check_admin_referer( self::ACTION );

		$raw   = isset( $_POST['vk_fb_urls'] ) ? sanitize_textarea_field( wp_unslash( $_POST['vk_fb_urls'] ) ) : '';
		$lines = preg_split( '/\s+/', trim( $raw ), -1, PREG_SPLIT_NO_EMPTY );
		$lines = array_slice( $lines, 0, self::MAX_BATCH );

		if ( empty( $lines ) ) {
			$this->redirect( [ 'vk_fb_invalid' => 1 ] );
		}

		// Each event needs up to two remote requests (page + image).
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 60 + 40 * count( $lines ) );
		}

		$counts = [
			'created' => 0,
			'empty'   => 0,
			'exists'  => 0,
			'invalid' => 0,
			'failed'  => 0,
		];
		$seen   = [];

		foreach ( $lines as $line ) {
			$url = $this->normalise_url( $line );

			if ( '' === $url ) {
				++$counts['invalid'];
				continue;
			}

			if ( isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;

			if ( $this->find_existing( $url ) > 0 ) {
				++$counts['exists'];
				continue;
			}

			$data    = $this->fetch_open_graph( $url );
			$post_id = $this->create_draft( $url, $data );

			if ( 0 === $post_id ) {
				++$counts['failed'];
			} elseif ( '' === $data['title'] ) {
				++$counts['empty'];
			} else {
				++$counts['created'];
			}
		}

		$args = [];
		foreach ( $counts as $key => $count ) {
			if ( $count > 0 ) {
				$args[ 'vk_fb_' . $key ] = $count;
			}
		}

		$this->redirect( $args );
	}

	/**
	 * Normalise a Facebook event URL to its canonical form.
	 *
	 * Accepts www/m/web subdomains and ignores query strings and fragments.
	 *
	 * @param string $url URL as submitted.
	 * @return string Canonical https://www.facebook.com/events/{id}/ URL, or empty string if not a Facebook event URL.
	 */
	private function normalise_url( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		if ( ! preg_match( '~^https?://~i', $url ) ) {
			$url = 'https://' . $url;
		}

		$parts = wp_parse_url( $url );
		$host  = strtolower( $parts['host'] ?? '' );

		$allowed_hosts = [ 'facebook.com', 'www.facebook.com', 'm.facebook.com', 'web.facebook.com' ];
		if ( ! in_array( $host, $allowed_hosts, true ) ) {
			return '';
		}

		if ( ! preg_match( '~/events/(\d+)~', $parts['path'] ?? '', $matches ) ) {
			return '';
		}

		return 'https://www.facebook.com/events/' . $matches[1] . '/';
	}

	/**
	 * Find an existing event with the given source URL.
	 *
	 * @param string $url Canonical source URL.
	 * @return int Post ID, or 0 if none exists.
	 */
	private function find_existing( string $url ): int {
		$existing = get_posts(
			[
				'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- dedup lookup on a single key, same as the scraper base class.
					[
						'key'   => \Vandrekalender\Event::META_SOURCE_URL,
						'value' => $url,
					],
				],
			]
		);

		return $existing ? (int) $existing[0] : 0;
	}

	/**
	 * Fetch the Facebook event page and extract Open Graph data.
	 *
	 * Facebook serves Open Graph tags to plain, honest user-agents (this is
	 * the same data it hands to link-preview crawlers) but rejects spoofed
	 * browser user-agents with HTTP 400. Anonymous access is still not
	 * guaranteed — when the response is a login wall, all fields come back
	 * empty.
	 *
	 * For public events, og:description is a generated metadata sentence
	 * ("Begivenhed af {organiser} {weekday}, {month} {day} {year} med …")
	 * rather than the event's real description text, so the date and
	 * organiser name are parsed out of it where possible.
	 *
	 * @param string $url Canonical event URL.
	 * @return array{title: string, description: string, image: string, date: string, organiser: string, place: string} Extracted fields, each possibly empty. `description` is empty when og:description was only the generated metadata sentence.
	 */
	private function fetch_open_graph( string $url ): array {
		$empty = [
			'title'       => '',
			'description' => '',
			'image'       => '',
			'date'        => '',
			'organiser'   => '',
			'place'       => '',
		];

		$response = wp_remote_get(
			$url,
			[
				'timeout'     => 15,
				'redirection' => 5,
				'user-agent'  => 'Vandrekalender/1.0 (+https://vandrekalender.dk)',
				'headers'     => [
					'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Language' => 'da-DK,da;q=0.9,en;q=0.8',
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $empty;
		}

		$html = wp_remote_retrieve_body( $response );

		$title = $this->og_content( $html, 'title' );

		// A login wall reports itself as the generic Facebook page or a
		// "log in to see this content" prompt (in either language, e.g.
		// "Log på, eller opret en profil for at se indholdet").
		$lower = mb_strtolower( $title );
		if ( 'facebook' === $lower || preg_match( '~^log\s+(ind|på|in|into)\b~u', $lower ) ) {
			return $empty;
		}

		$description = $this->og_content( $html, 'description' );
		$parsed      = $this->parse_meta_sentence( $description );

		if ( '' !== $parsed['date'] || '' !== $parsed['organiser'] ) {
			// The description was only Facebook's generated metadata sentence
			// — junk as an excerpt, so keep the parsed fields and drop the text.
			$description = '';
		}

		return [
			'title'       => $title,
			'description' => $description,
			'image'       => $this->og_content( $html, 'image' ),
			'date'        => $parsed['date'],
			'organiser'   => $parsed['organiser'],
			'place'       => $parsed['place'],
		];
	}

	/**
	 * Parse the event date, organiser name, and place out of Facebook's
	 * generated og:description sentence.
	 *
	 * Handles the variants Facebook serves in both languages, e.g.
	 * "Begivenhed af Fur Rundt lørdag, juli 11 2026 med 2,5 tusind …",
	 * "Begivenhed i Faaborg, Region Syddanmark af Gitte Fogelberg Mangal den
	 * lørdag, juni 20 2026", and "Event by Fur Rundt on Saturday, July 11
	 * 2026 with 2.5K …".
	 *
	 * @param string $description og:description content.
	 * @return array{date: string, organiser: string, place: string} Date as Y-m-d, organiser name, and place name, each empty when not found.
	 */
	private function parse_meta_sentence( string $description ): array {
		$result = [
			'date'      => '',
			'organiser' => '',
			'place'     => '',
		];

		if ( '' === $description ) {
			return $result;
		}

		$months = [
			'januar'    => 1,
			'january'   => 1,
			'februar'   => 2,
			'february'  => 2,
			'marts'     => 3,
			'march'     => 3,
			'april'     => 4,
			'maj'       => 5,
			'may'       => 5,
			'juni'      => 6,
			'june'      => 6,
			'juli'      => 7,
			'july'      => 7,
			'august'    => 8,
			'september' => 9,
			'oktober'   => 10,
			'october'   => 10,
			'november'  => 11,
			'december'  => 12,
		];

		$month_pattern = implode( '|', array_keys( $months ) );
		if ( preg_match( '~(' . $month_pattern . ')\s+(\d{1,2}),?\s+(\d{4})~iu', $description, $matches ) ) {
			$month          = $months[ mb_strtolower( $matches[1] ) ];
			$result['date'] = sprintf( '%04d-%02d-%02d', (int) $matches[3], $month, (int) $matches[2] );
		}

		$weekdays = 'mandag|tirsdag|onsdag|torsdag|fredag|lørdag|søndag|monday|tuesday|wednesday|thursday|friday|saturday|sunday';
		if ( preg_match( '~^Begivenhed (?:i (.+?) )?af (.+?)(?:\s+den)?\s+(?:' . $weekdays . ')~iu', $description, $matches ) ) {
			$result['place']     = trim( $matches[1] );
			$result['organiser'] = trim( $matches[2] );
		} elseif ( preg_match( '~^Event (?:in (.+?) )?by (.+?)\s+on\s+~iu', $description, $matches ) ) {
			$result['place']     = trim( $matches[1] );
			$result['organiser'] = trim( $matches[2] );
		}

		// The place often carries a ", Region …" suffix — the region is
		// derived from the address on save, so keep just the place name.
		$result['place'] = trim( (string) preg_replace( '~,\s*Region\s+.*$~iu', '', $result['place'] ) );

		return $result;
	}

	/**
	 * Extract one Open Graph meta tag's content from raw HTML.
	 *
	 * Matches both attribute orders (property before content and vice versa).
	 *
	 * @param string $html     Raw page HTML.
	 * @param string $property Open Graph property without the og: prefix, e.g. "title".
	 * @return string Decoded content, or empty string if the tag is absent.
	 */
	private function og_content( string $html, string $property ): string {
		$quoted = preg_quote( 'og:' . $property, '~' );

		if ( ! preg_match( '~<meta[^>]+property=["\']' . $quoted . '["\'][^>]+content=["\']([^"\']*)["\']~i', $html, $matches )
			&& ! preg_match( '~<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']' . $quoted . '["\']~i', $html, $matches ) ) {
			return '';
		}

		return trim( html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Create the prefilled draft event.
	 *
	 * @param string $url  Canonical source URL.
	 * @param array  $data Open Graph data from fetch_open_graph().
	 * @return int New post ID, or 0 on failure.
	 */
	private function create_draft( string $url, array $data ): int {
		$title       = sanitize_text_field( $data['title'] );
		$description = sanitize_textarea_field( $data['description'] );

		$content = '';
		if ( '' !== $description ) {
			foreach ( preg_split( '/\n+/', $description ) as $paragraph ) {
				$paragraph = trim( $paragraph );
				if ( '' !== $paragraph ) {
					$content .= '<!-- wp:paragraph --><p>' . esc_html( $paragraph ) . '</p><!-- /wp:paragraph -->';
				}
			}
		}

		$post_id = wp_insert_post(
			[
				'post_type'    => \Vandrekalender\Event::CUSTOMPOSTTYPE,
				'post_status'  => 'draft',
				'post_title'   => '' !== $title ? $title : __( 'Facebook event (untitled import)', 'vandrekalender-events' ),
				'post_content' => $content,
				'post_excerpt' => $description,
				'post_author'  => get_current_user_id(),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		// Seed one route (same shape the editor's route list creates) so the
		// event surfaces in the calendar views straight away; the admin fills
		// in distance, start time, and price before publishing.
		update_post_meta(
			$post_id,
			\Vandrekalender\Event::META_ROUTES,
			[
				[
					'id'          => 'route_' . wp_generate_uuid4(),
					'distance_km' => '',
					'start_time'  => '',
					'cutoff_time' => '',
					'price'       => '',
				],
			]
		);

		update_post_meta( $post_id, \Vandrekalender\Event::META_SOURCE, 'facebook' );
		update_post_meta( $post_id, \Vandrekalender\Event::META_SOURCE_URL, $url );
		update_post_meta( $post_id, \Vandrekalender\Event::META_SOURCE_NAME, 'Facebook' );
		update_post_meta( $post_id, \Vandrekalender\Event::META_SCRAPED_AT, current_time( 'mysql' ) );
		update_post_meta( $post_id, \Vandrekalender\Event::META_CLAIMED, false );
		update_post_meta( $post_id, \Vandrekalender\Event::META_ORGANISER_URL, $url );

		if ( '' !== $data['date'] ) {
			update_post_meta( $post_id, \Vandrekalender\Event::META_DATE, $data['date'] );
		}

		if ( '' !== $data['organiser'] ) {
			update_post_meta( $post_id, \Vandrekalender\Event::META_ORGANISER_NAME, sanitize_text_field( $data['organiser'] ) );
		}

		if ( '' !== $data['place'] ) {
			update_post_meta( $post_id, \Vandrekalender\Event::META_PLACE_NAME, sanitize_text_field( $data['place'] ) );
		}

		if ( '' !== $data['image'] ) {
			$this->set_featured_image_from_url( $post_id, $data['image'] );
		}

		return (int) $post_id;
	}

	/**
	 * Download the event's cover image and set it as the featured image.
	 *
	 * Facebook's og:image points at its lookaside crawler-media endpoint,
	 * which has no file extension in the URL and only serves the actual
	 * image bytes to link-preview crawler user-agents (other agents get a
	 * JS redirect to a login-walled photo page). So this downloads with the
	 * crawler user-agent — the endpoint exists precisely to hand cover
	 * images to link previews — and names the file from the response's
	 * content type instead of the URL.
	 *
	 * @param int    $post_id Post to attach the image to.
	 * @param string $url     Image URL.
	 * @return void
	 */
	private function set_featured_image_from_url( int $post_id, string $url ): void {
		$response = wp_remote_get(
			$url,
			[
				'timeout'     => 20,
				'redirection' => 5,
				'user-agent'  => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return;
		}

		$extensions = [
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
		];

		$content_type = strtolower( trim( strtok( (string) wp_remote_retrieve_header( $response, 'content-type' ), ';' ) ) );
		if ( ! isset( $extensions[ $content_type ] ) ) {
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return;
		}

		$filename = sanitize_file_name( 'facebook-event-' . $post_id . '.' . $extensions[ $content_type ] );
		$upload   = wp_upload_bits( $filename, null, $body );

		if ( ! empty( $upload['error'] ) ) {
			return;
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = wp_insert_attachment(
			[
				'post_mime_type' => $content_type,
				'post_title'     => sanitize_text_field( get_the_title( $post_id ) ),
				'post_status'    => 'inherit',
			],
			$upload['file'],
			$post_id
		);

		if ( is_wp_error( $attachment_id ) || 0 === $attachment_id ) {
			return;
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		set_post_thumbnail( $post_id, (int) $attachment_id );
	}

	/**
	 * Render the summary notice for the previous import batch, if any.
	 *
	 * @return void
	 */
	private function render_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only counters from our own redirect.
		$created = isset( $_GET['vk_fb_created'] ) ? absint( wp_unslash( $_GET['vk_fb_created'] ) ) : 0;
		$empty   = isset( $_GET['vk_fb_empty'] ) ? absint( wp_unslash( $_GET['vk_fb_empty'] ) ) : 0;
		$exists  = isset( $_GET['vk_fb_exists'] ) ? absint( wp_unslash( $_GET['vk_fb_exists'] ) ) : 0;
		$invalid = isset( $_GET['vk_fb_invalid'] ) ? absint( wp_unslash( $_GET['vk_fb_invalid'] ) ) : 0;
		$failed  = isset( $_GET['vk_fb_failed'] ) ? absint( wp_unslash( $_GET['vk_fb_failed'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 0 === $created + $empty + $exists + $invalid + $failed ) {
			return;
		}

		$parts = [];
		if ( $created > 0 ) {
			/* translators: %d: number of drafts. */
			$parts[] = sprintf( _n( '%d draft created with details from Facebook', '%d drafts created with details from Facebook', $created, 'vandrekalender-events' ), $created );
		}
		if ( $empty > 0 ) {
			/* translators: %d: number of drafts. */
			$parts[] = sprintf( _n( '%d empty draft created (Facebook exposed no details — fill in manually)', '%d empty drafts created (Facebook exposed no details — fill in manually)', $empty, 'vandrekalender-events' ), $empty );
		}
		if ( $exists > 0 ) {
			/* translators: %d: number of events. */
			$parts[] = sprintf( _n( '%d already imported and left untouched', '%d already imported and left untouched', $exists, 'vandrekalender-events' ), $exists );
		}
		if ( $invalid > 0 ) {
			/* translators: %d: number of URLs. */
			$parts[] = sprintf( _n( '%d line was not a Facebook event URL', '%d lines were not Facebook event URLs', $invalid, 'vandrekalender-events' ), $invalid );
		}
		if ( $failed > 0 ) {
			/* translators: %d: number of events. */
			$parts[] = sprintf( _n( '%d draft could not be created', '%d drafts could not be created', $failed, 'vandrekalender-events' ), $failed );
		}

		$class = ( $created + $empty ) > 0 ? 'notice-success' : ( $exists > 0 ? 'notice-warning' : 'notice-error' );

		echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( implode( ' · ', $parts ) . '.' );
		if ( ( $created + $empty ) > 0 ) {
			echo ' ' . esc_html__( 'The new drafts are listed below — complete and publish each one.', 'vandrekalender-events' );
		}
		echo '</p></div>';
	}

	/**
	 * Render the link-collector bookmarklet with instructions.
	 *
	 * The bookmarklet runs in the admin's own logged-in browser on a Facebook
	 * group's events page — where the event list is visible to them but not
	 * to any server-side fetch — and copies every event URL on the page to
	 * the clipboard for pasting into the form above. Nothing is automated
	 * and no credentials are involved; it is a shortcut for "copy all event
	 * links on the page I am looking at".
	 *
	 * @return void
	 */
	private function render_bookmarklet(): void {
		$js = "javascript:(function(){var s=new Set();document.querySelectorAll('a[href*=\"/events/\"]').forEach(function(a){var m=a.href.match(/facebook\\.com\\/events\\/(\\d+)/);if(m){s.add('https://www.facebook.com/events/'+m[1]+'/');}});var t=Array.from(s).join('\\n');if(!t){alert('No Facebook event links found on this page.');return;}if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(function(){alert(s.size+' event link(s) copied. Paste them into Vandrekalender.');},function(){prompt('Copy these links:',t);});}else{prompt('Copy these links:',t);}})();";

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Collect links from a Facebook group', 'vandrekalender-events' ) . '</h2>';
		echo '<p>' . esc_html__( 'Group event lists are only visible when you are logged in to Facebook, so they cannot be fetched automatically. Instead, drag this button to your browser\'s bookmarks bar once:', 'vandrekalender-events' ) . '</p>';
		echo '<p><a href="' . esc_attr( $js ) . '" class="button button-secondary" onclick="alert(\'' . esc_js( __( 'Drag this button to your bookmarks bar instead of clicking it. Then use it while viewing a Facebook events page.', 'vandrekalender-events' ) ) . '\');return false;">' . esc_html__( '★ Collect Facebook event links', 'vandrekalender-events' ) . '</a></p>';
		echo '<p>' . esc_html__( 'Then, whenever you want to import: open the group\'s events page on Facebook (e.g. facebook.com/groups/…/events), scroll so the events you want are loaded, click the bookmarklet, and paste the copied links into the field above.', 'vandrekalender-events' ) . '</p>';
	}

	/**
	 * Render a table of the most recent Facebook imports with their status.
	 *
	 * @return void
	 */
	private function render_recent_imports(): void {
		$imports = get_posts(
			[
				'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
				'post_status'    => 'any',
				'posts_per_page' => 20,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin-only screen, small bounded query.
					[
						'key'   => \Vandrekalender\Event::META_SOURCE,
						'value' => 'facebook',
					],
				],
			]
		);

		if ( empty( $imports ) ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Recent Facebook imports', 'vandrekalender-events' ) . '</h2>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Event', 'vandrekalender-events' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'vandrekalender-events' ) . '</th>';
		echo '<th>' . esc_html__( 'Event date', 'vandrekalender-events' ) . '</th>';
		echo '<th>' . esc_html__( 'Source', 'vandrekalender-events' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $imports as $import ) {
			$edit_link  = get_edit_post_link( $import->ID );
			$event_date = get_post_meta( $import->ID, \Vandrekalender\Event::META_DATE, true );
			$source_url = get_post_meta( $import->ID, \Vandrekalender\Event::META_SOURCE_URL, true );

			echo '<tr>';
			echo '<td>';
			if ( $edit_link ) {
				echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( get_the_title( $import ) ) . '</a>';
			} else {
				echo esc_html( get_the_title( $import ) );
			}
			echo '</td>';
			echo '<td>' . esc_html( get_post_status( $import ) ) . '</td>';
			echo '<td>' . esc_html( $event_date ? $event_date : '—' ) . '</td>';
			echo '<td>';
			if ( $source_url ) {
				echo '<a href="' . esc_url( $source_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on Facebook', 'vandrekalender-events' ) . '</a>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Redirect back to the importer page with the given query args and exit.
	 *
	 * @param array $args Query args to append.
	 * @return void
	 */
	private function redirect( array $args ): void {
		$url = add_query_arg(
			$args,
			admin_url( 'edit.php?post_type=' . \Vandrekalender\Event::CUSTOMPOSTTYPE . '&page=' . self::PAGE_SLUG )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
