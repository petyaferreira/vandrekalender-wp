<?php

defined( 'ABSPATH' ) || exit;

/**
 * The months behind the Month Quick Filter block (#198): "Spring til måned"
 * chips that set the calendar's `date_from` and `date_to` to one month.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Month_Quick_Filter {

	const BLOCK  = 'vandrekalender/month-quick-filter';
	const CHIPS  = 6;
	const PAST   = 'past';
	const FUTURE = 'future';

	/**
	 * The 6 months a chip row shows, worked out from $today, never stored.
	 *
	 * Past: the month of $today and the 5 before it, newest first, leaving
	 * out months that end before Vandrekalender_Past_Walks::retention_cutoff()
	 * (older walks are archived and answer 410). Future (anything else): the
	 * month of $today and the next 5, soonest first.
	 *
	 * @param string $direction 'past' or 'future'.
	 * @param string $today     Today as Y-m-d.
	 * @return array<int,array{from: string, to: string, label_time: int}> `from` and `to` are the first and last day (Y-m-d); `label_time` is a timestamp inside the month for wp_date().
	 */
	public static function month_options( string $direction, string $today ): array {
		$utc   = new DateTimeZone( 'UTC' );
		$first = ( new DateTimeImmutable( $today, $utc ) )->modify( 'first day of this month' );
		$past  = self::PAST === $direction;
		$sign  = $past ? '-' : '+';
		$min   = $past ? Vandrekalender_Past_Walks::retention_cutoff( $today ) : '';

		$months = [];
		for ( $i = 0; $i < self::CHIPS; $i++ ) {
			$month = $first->modify( $sign . $i . ' months' );
			$to    = $month->modify( 'last day of this month' )->format( 'Y-m-d' );
			if ( $past && $to < $min ) {
				break;
			}
			$months[] = [
				'from'       => $month->format( 'Y-m-d' ),
				'to'         => $to,
				// Midday on the 1st, so a site timezone never moves it into another month.
				'label_time' => $month->setTime( 12, 0 )->getTimestamp(),
			];
		}

		return $months;
	}

	/**
	 * A chip's label: "Okt 2026". wp_date() gives "okt. 2026" in Danish;
	 * capitalise it and drop the dot.
	 *
	 * @param int $label_time Timestamp inside the month (month_options()).
	 * @return string
	 */
	public static function label( int $label_time ): string {
		$text = str_replace( '.', '', (string) wp_date( 'M Y', $label_time ) );
		return mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 );
	}
}
