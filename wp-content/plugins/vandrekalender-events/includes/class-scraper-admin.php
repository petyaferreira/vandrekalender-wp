<?php

defined( 'ABSPATH' ) || exit;

/**
 * "Scraper Log" admin screen under the Events menu.
 *
 * Read-only view of recent scraper runs (from Vandrekalender_Scraper_Log) plus
 * the current automatic-scheduling status, so the same history is visible after
 * a manual local run and after a production cron run.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_page' ] );
	}

	/**
	 * Register the Scraper Log submenu under the Events post type.
	 *
	 * @return void
	 */
	public function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . \Vandrekalender\Event::CUSTOMPOSTTYPE,
			__( 'Scraper Log', 'vandrekalender-events' ),
			__( 'Scraper Log', 'vandrekalender-events' ),
			'manage_options',
			'vandrekalender-scraper-log',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render the Scraper Log page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$runs = Vandrekalender_Scraper_Log::all();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Scraper Log', 'vandrekalender-events' ) . '</h1>';

		$this->render_status();

		if ( empty( $runs ) ) {
			echo '<p>' . esc_html__( 'No scraper runs recorded yet.', 'vandrekalender-events' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'When', 'vandrekalender-events' ) . '</th>';
		echo '<th>' . esc_html__( 'Trigger', 'vandrekalender-events' ) . '</th>';
		echo '<th>' . esc_html__( 'Duration', 'vandrekalender-events' ) . '</th>';
		echo '<th>' . esc_html__( 'Events updated', 'vandrekalender-events' ) . '</th>';
		echo '<th>' . esc_html__( 'Per scraper', 'vandrekalender-events' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $runs as $run ) {
			$this->render_row( is_array( $run ) ? $run : [] );
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Render the scheduling-status notice above the table.
	 *
	 * @return void
	 */
	private function render_status(): void {
		if ( Vandrekalender_Scraper_Scheduler::scraping_enabled() ) {
			$next    = wp_next_scheduled( Vandrekalender_Scraper_Scheduler::CRON_HOOK );
			$message = $next
				? sprintf(
					/* translators: %s: next scheduled run, formatted in the site timezone. */
					esc_html__( 'Automatic scraping is enabled. Next run: %s.', 'vandrekalender-events' ),
					esc_html( wp_date( 'Y-m-d H:i', $next ) )
				)
				: esc_html__( 'Automatic scraping is enabled, but no run is scheduled yet.', 'vandrekalender-events' );
		} else {
			$message = esc_html__( 'Automatic scraping is disabled here. Runs are triggered manually (./scrape.sh).', 'vandrekalender-events' );
		}

		echo '<p><em>' . wp_kses_post( $message ) . '</em></p>';
	}

	/**
	 * Render a single run row.
	 *
	 * @param array $run One run summary.
	 * @return void
	 */
	private function render_row( array $run ): void {
		$time     = isset( $run['time'] ) ? (string) $run['time'] : '';
		$trigger  = isset( $run['trigger'] ) ? (string) $run['trigger'] : '';
		$duration = isset( $run['duration'] ) ? (string) $run['duration'] : '0';
		$total    = isset( $run['total'] ) ? (int) $run['total'] : 0;
		$scrapers = isset( $run['scrapers'] ) && is_array( $run['scrapers'] ) ? $run['scrapers'] : [];

		$parts = [];
		foreach ( $scrapers as $scraper ) {
			$name = isset( $scraper['name'] ) ? (string) $scraper['name'] : '';
			if ( isset( $scraper['status'] ) && 'error' === $scraper['status'] ) {
				$error = isset( $scraper['error'] ) ? (string) $scraper['error'] : '';
				/* translators: 1: scraper name, 2: error message. */
				$parts[] = sprintf( __( '%1$s: error — %2$s', 'vandrekalender-events' ), $name, $error );
			} else {
				$count = isset( $scraper['count'] ) ? (int) $scraper['count'] : 0;
				/* translators: 1: scraper name, 2: number of events. */
				$parts[] = sprintf( __( '%1$s: %2$d', 'vandrekalender-events' ), $name, $count );
			}
		}

		echo '<tr>';
		echo '<td>' . esc_html( $time ) . '</td>';
		echo '<td>' . esc_html( $trigger ) . '</td>';
		echo '<td>' . esc_html( $duration ) . 's</td>';
		echo '<td>' . esc_html( (string) $total ) . '</td>';
		echo '<td>' . esc_html( implode( ' · ', $parts ) ) . '</td>';
		echo '</tr>';
	}
}
