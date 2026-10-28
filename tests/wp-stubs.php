<?php
/**
 * Stand-ins for the WordPress functions and classes the tested code calls.
 *
 * Each one does what WordPress does for the inputs the tests use, nothing
 * more. Keep them small: a stand-in that grows its own logic is a second
 * implementation to keep in step with WordPress. WP_Error is in
 * class-wp-error.php, the fake geocoder in fakes/class-vandrekalender-geocoder.php.
 *
 * @package Vandrekalender
 */

/**
 * The site timezone (Settings → General on production).
 *
 * @return DateTimeZone
 */
function wp_timezone(): DateTimeZone {
	return new DateTimeZone( 'Europe/Copenhagen' );
}

/**
 * Translation: returns the text untranslated.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function __( string $text, string $domain = 'default' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- same signature as WordPress.
	return $text;
}

/**
 * Remove trailing slashes and backslashes (as WordPress).
 *
 * @param string $value Value.
 * @return string
 */
function untrailingslashit( string $value ): string {
	return rtrim( $value, '/\\' );
}

/**
 * Escape for HTML, without double-encoding existing entities (as WordPress).
 *
 * @param string $text Text.
 * @return string
 */
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8', false );
}

/**
 * Same steps as WordPress: drop script/style with their content, strip tags,
 * optionally collapse line breaks, trim.
 *
 * @param string $text          Markup.
 * @param bool   $remove_breaks Collapse whitespace runs to one space.
 * @return string
 */
function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
	$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
	$text = strip_tags( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- this is the wp_strip_all_tags() stand-in.
	if ( $remove_breaks ) {
		$text = (string) preg_replace( '/[\r\n\t ]+/', ' ', $text );
	}
	return trim( $text );
}

/**
 * Trim to a number of words, appending $more when cut (Danish counts words,
 * like the default "words" mode in WordPress).
 *
 * @param string $text      Text.
 * @param int    $num_words Word limit.
 * @param string $more      Appended when cut.
 * @return string
 */
function wp_trim_words( string $text, int $num_words = 55, string $more = '…' ): string {
	$words = preg_split( '/[\n\r\t ]+/', wp_strip_all_tags( $text ), -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $words ) <= $num_words ) {
		return implode( ' ', $words );
	}
	return implode( ' ', array_slice( $words, 0, $num_words ) ) . $more;
}

/**
 * Current time in the site timezone, for the date formats the code passes.
 *
 * @param string $type PHP date format, e.g. 'Y-m-d'.
 * @return string
 */
function current_time( string $type ): string {
	return ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( $type );
}

/**
 * Sanitize a plain-text value: strip tags, collapse whitespace, trim.
 *
 * @param string $text Text.
 * @return string
 */
function sanitize_text_field( string $text ): string {
	return wp_strip_all_tags( $text, true );
}

/**
 * Turn text into a slug: lowercase, spaces to hyphens, drop anything else
 * (enough for the ASCII slugs the tests use; WordPress also removes accents).
 *
 * @param string $title Text.
 * @return string
 */
function sanitize_title( string $title ): string {
	$slug = strtolower( wp_strip_all_tags( $title ) );
	$slug = (string) preg_replace( '/[\s_]+/', '-', $slug );
	return trim( (string) preg_replace( '/[^a-z0-9-]/', '', $slug ), '-' );
}

/**
 * Post query on an empty database: no posts match.
 *
 * @param array $args Query arguments.
 * @return array
 */
function get_posts( array $args = [] ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- same signature as WordPress.
	return [];
}

define( 'MONTH_IN_SECONDS', 30 * 24 * 60 * 60 );

/**
 * Transient on an empty cache: never set.
 *
 * @param string $transient Name.
 * @return false
 */
function get_transient( string $transient ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- same signature as WordPress.
	return false;
}

/**
 * Store a transient: accepted and forgotten, so every test starts uncached.
 *
 * @param string $transient  Name.
 * @param mixed  $value      Value.
 * @param int    $expiration Seconds.
 * @return bool
 */
function set_transient( string $transient, $value, int $expiration = 0 ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- same signature as WordPress.
	return true;
}

/**
 * JSON encode (WordPress adds a sanity check on top of json_encode()).
 *
 * @param mixed $value Value.
 * @return string|false
 */
function wp_json_encode( $value ) {
	return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- this is the stand-in.
}

/**
 * Whether a value is an error result.
 *
 * @param mixed $thing Value.
 * @return bool
 */
function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

/**
 * Response headers per URL for wp_remote_head(), set by the test fakes from
 * saved headers. A URL not listed fails, as without a network.
 *
 * @var array<string, array<string, string>>
 */
$GLOBALS['vandrekalender_test_head_responses'] = [];

/**
 * HEAD request: the saved headers for the URL, or an error.
 *
 * @param string $url  URL.
 * @param array  $args Request arguments.
 * @return array|WP_Error
 */
function wp_remote_head( string $url, array $args = [] ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- same signature as WordPress.
	if ( ! isset( $GLOBALS['vandrekalender_test_head_responses'][ $url ] ) ) {
		return new WP_Error();
	}
	return [ 'headers' => $GLOBALS['vandrekalender_test_head_responses'][ $url ] ];
}

/**
 * One header of a response, by lowercase name ('' when missing).
 *
 * @param array|WP_Error $response Response.
 * @param string         $header   Header name.
 * @return string
 */
function wp_remote_retrieve_header( $response, string $header ): string {
	return is_array( $response ) ? (string) ( $response['headers'][ strtolower( $header ) ] ?? '' ) : '';
}
