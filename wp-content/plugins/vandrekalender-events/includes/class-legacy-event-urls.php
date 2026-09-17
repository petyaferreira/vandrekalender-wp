<?php

defined( 'ABSPATH' ) || exit;

/**
 * Permanently redirects the old /event/{slug}/ URL base to the current
 * /begivenhed/{slug}/ one.
 *
 * Events used to live under /event/ before Polylang mapped the Danish
 * (default-language) slug to /begivenhed/. Google still has hundreds of the
 * old URLs indexed, and they 404 today. The English base (/en/event/) is
 * unchanged and needs no redirect. See docs/past-events-brief.md.
 *
 * Implemented as a 404-time check rather than a new rewrite rule, so it
 * needs no rewrite-rule flush to take effect on deploy.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Legacy_Event_Urls {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'maybe_redirect' ] );
	}

	/**
	 * On a 404 for /event/{slug}/, redirect to the event's current permalink
	 * if a post with that slug exists. A second hop to a next occurrence
	 * (Vandrekalender_Event_Past_Events) happens naturally from there.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		if ( ! is_404() ) {
			return;
		}

		global $wp;

		if ( ! isset( $wp->request ) || ! preg_match( '#^event/([^/]+)/?$#', $wp->request, $matches ) ) {
			return;
		}

		$found = get_posts(
			[
				'name'           => sanitize_title( $matches[1] ),
				'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);

		if ( $found ) {
			wp_safe_redirect( get_permalink( $found[0] ), 301 );
			exit;
		}
	}
}
