<?php
/**
 * Stand-ins for the WordPress functions and classes the tested code calls.
 *
 * Each one does what WordPress does for the inputs the tests use, nothing
 * more. Keep them small: a stand-in that grows its own logic is a second
 * implementation to keep in step with WordPress. The fake geocoder is in
 * fakes/class-vandrekalender-geocoder.php.
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
