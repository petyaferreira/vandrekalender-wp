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

	use Vandrekalender_Polylang_Language;

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

		$target = $this->find_event_by_slug( sanitize_title( $matches[1] ) );

		if ( $target ) {
			wp_safe_redirect( get_permalink( $target ), 301 );
			exit;
		}
	}

	/**
	 * Find the published event with the given slug, preferring the site's
	 * default language when more than one matches.
	 *
	 * Polylang allows translations of the same post type to share a
	 * post_name, so this can have more than one result — e.g. a Danish and
	 * an English event both slugged `my-event`. The /event/ base predates
	 * the /en/ split (see docs/i18n.md), so an unprefixed /event/{slug}/
	 * request always meant the default-language (Danish) page; that's what
	 * a bare slug match should resolve to here, not whichever post the
	 * database happens to return first.
	 *
	 * @param string $slug Sanitized post_name to match.
	 * @return int Post ID, or 0 if none found.
	 */
	private function find_event_by_slug( string $slug ): int {
		$candidates = get_posts(
			[
				'name'           => $slug,
				'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		if ( ! $candidates ) {
			return 0;
		}

		if ( count( $candidates ) > 1 && function_exists( 'pll_default_language' ) ) {
			$default_lang = pll_default_language();

			foreach ( $candidates as $candidate_id ) {
				if ( $default_lang && $default_lang === $this->post_language( (int) $candidate_id ) ) {
					return (int) $candidate_id;
				}
			}
		}

		return (int) $candidates[0];
	}
}
