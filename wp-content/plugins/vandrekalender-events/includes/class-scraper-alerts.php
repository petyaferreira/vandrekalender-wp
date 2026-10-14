<?php

defined( 'ABSPATH' ) || exit;

/**
 * Emails an alert when a scraper source stops working, or works again.
 *
 * A source "stops working" when its scraper throws, or when the run finds no
 * events at the source (see Vandrekalender_Scraper_Base::source_problem()).
 * Both used to show up only as "0 events" in the Scraper Log, which is how two
 * broken sources went unnoticed for a month in autumn 2026.
 *
 * Only changes are emailed: one email when a source breaks and one when it
 * recovers, not one every night while it stays broken. The sources that are
 * currently broken are kept in an option to compare against.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Alerts {

	const OPTION = 'vandrekalender_scraper_alerts';
	const EMAIL  = 'kontakt@allevandreture.dk';

	/**
	 * Compare a run with the previous one and email any change.
	 *
	 * @param array $entry Run summary from Vandrekalender_Scraper_Scheduler::execute().
	 * @return void
	 */
	public static function notify( array $entry ): void {
		$broken_before = get_option( self::OPTION, [] );
		$broken_before = is_array( $broken_before ) ? $broken_before : [];

		$broken  = [];
		$working = [];
		foreach ( $entry['scrapers'] as $scraper ) {
			if ( '' !== (string) ( $scraper['problem'] ?? '' ) ) {
				$broken[ $scraper['name'] ] = (string) $scraper['problem'];
			} else {
				$working[ $scraper['name'] ] = (int) $scraper['count'];
			}
		}

		$new_problems = array_diff_key( $broken, $broken_before );
		$recovered    = array_intersect_key( $working, $broken_before );

		if ( empty( $new_problems ) && empty( $recovered ) ) {
			return;
		}

		// When the email fails, keep the old state so the next run tries again.
		if ( self::send( $new_problems, $recovered ) ) {
			update_option( self::OPTION, $broken, false );
		}
	}

	/**
	 * Send the alert email.
	 *
	 * @param array<string, string> $new_problems Source name => problem.
	 * @param array<string, int>    $recovered    Source name => events updated.
	 * @return bool Whether wp_mail() accepted the email.
	 */
	private static function send( array $new_problems, array $recovered ): bool {
		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

		$lines = [];
		if ( $new_problems ) {
			$lines[] = __( 'These sources stopped working in the nightly scraper run:', 'vandrekalender-events' );
			$lines[] = '';
			foreach ( $new_problems as $name => $problem ) {
				$lines[] = '- ' . $name . ': ' . $problem;
			}
			$lines[] = '';
			$lines[] = __( 'Their events stay as they are until the scraper is fixed, but new and changed events from these sources are not picked up.', 'vandrekalender-events' );
			$lines[] = '';
		}

		if ( $recovered ) {
			$lines[] = __( 'These sources work again:', 'vandrekalender-events' );
			$lines[] = '';
			foreach ( $recovered as $name => $count ) {
				/* translators: 1: source name, 2: number of events. */
				$lines[] = '- ' . sprintf( _n( '%1$s: %2$d event updated', '%1$s: %2$d events updated', $count, 'vandrekalender-events' ), $name, $count );
			}
			$lines[] = '';
		}

		$lines[] = __( 'You get this email only when a source stops or starts working, not every night.', 'vandrekalender-events' );
		/* translators: %s: URL of the Scraper Log admin page. */
		$lines[] = sprintf( __( 'Scraper Log: %s', 'vandrekalender-events' ), admin_url( 'edit.php?post_type=' . \Vandrekalender\Event::CUSTOMPOSTTYPE . '&page=' . Vandrekalender_Scraper_Admin::PAGE_SLUG ) );

		$subject = $new_problems
			/* translators: 1: site name, 2: source names. */
			? sprintf( __( '[%1$s] Scraper stopped working: %2$s', 'vandrekalender-events' ), $site, implode( ', ', array_keys( $new_problems ) ) )
			/* translators: 1: site name, 2: source names. */
			: sprintf( __( '[%1$s] Scraper works again: %2$s', 'vandrekalender-events' ), $site, implode( ', ', array_keys( $recovered ) ) );

		$to = (string) apply_filters( 'vandrekalender_scraper_alert_email', self::EMAIL );

		// Core's default From is wordpress@<host>, which PHPMailer rejects
		// locally (no TLD), so send from the admin email like the join emails.
		$headers = [ sprintf( 'From: %s <%s>', $site, get_option( 'admin_email' ) ) ];

		return wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
	}
}
