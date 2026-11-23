<?php

defined( 'ABSPATH' ) || exit;

/**
 * Read the DAR and DAGI answers that Vandrekalender_Geocoder::reverse() asks
 * Datafordeler for. Kept apart from the geocoder so they can be tested
 * without a network.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Dar {

	/**
	 * Current house numbers, keyed by the access point they belong to.
	 *
	 * @param array $nodes DAR_Husnummer nodes: id_lokalId, adgangspunkt, status,
	 *                     adgangsadressebetegnelse, kommuneinddeling.
	 * @return array<string, array{address: string, kommuneinddeling: string}>
	 */
	public static function current_husnumre( array $nodes ): array {
		$map = [];
		foreach ( $nodes as $node ) {
			// Status 3 is a current house number (the ones Adressevælger returns).
			if ( isset( $node['id_lokalId'], $node['adgangspunkt'], $node['kommuneinddeling'] )
				&& '3' === (string) ( $node['status'] ?? '' ) ) {
				$map[ (string) $node['adgangspunkt'] ] = [
					'address'          => (string) ( $node['adgangsadressebetegnelse'] ?? '' ),
					'kommuneinddeling' => (string) $node['kommuneinddeling'],
				];
			}
		}

		return $map;
	}

	/**
	 * The four-digit municipality code of a DAGI_Kommuneinddeling answer.
	 *
	 * @param array $nodes DAGI_Kommuneinddeling nodes, with kommunekode.
	 * @return string The code, e.g. "0540", or '' when there is none.
	 */
	public static function municipality_code( array $nodes ): string {
		$code = (string) ( $nodes[0]['kommunekode'] ?? '' );

		return preg_match( '/^\d{4}$/', $code ) ? $code : '';
	}
}
