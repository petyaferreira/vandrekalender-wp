<?php
/**
 * Reading Datafordeler's DAR and DAGI answers for the reverse lookup (#293).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Dar.
 */
class DarTest extends TestCase {

	/**
	 * A current house number brings its address text and municipality
	 * division, so no Adressevælger lookup is needed.
	 */
	public function test_current_house_number_has_address_and_division(): void {
		$this->assertSame(
			[
				'a1' => [
					'address'          => 'Brobølvej 14, Blans, 6400 Sønderborg',
					'kommuneinddeling' => '389167',
				],
			],
			Vandrekalender_Dar::current_husnumre(
				[
					[
						'id_lokalId'               => '0a3f508c-4c0e-32b8-e044-0003ba298018',
						'adgangspunkt'             => 'a1',
						'status'                   => 3,
						'adgangsadressebetegnelse' => 'Brobølvej 14, Blans, 6400 Sønderborg',
						'kommuneinddeling'         => '389167',
					],
				]
			)
		);
	}

	/**
	 * Retired house numbers and ones without a municipality division are left out.
	 */
	public function test_skips_retired_and_incomplete(): void {
		$this->assertSame(
			[],
			Vandrekalender_Dar::current_husnumre(
				[
					[
						'id_lokalId'               => 'h1',
						'adgangspunkt'             => 'a1',
						'status'                   => 4,
						'adgangsadressebetegnelse' => 'Gammelvej 1, 6400 Sønderborg',
						'kommuneinddeling'         => '389167',
					],
					[
						'id_lokalId'   => 'h2',
						'adgangspunkt' => 'a2',
						'status'       => 3,
					],
				]
			)
		);
	}

	/**
	 * The municipality code comes from DAGI as four digits.
	 */
	public function test_municipality_code(): void {
		$this->assertSame( '0540', Vandrekalender_Dar::municipality_code( [ [ 'kommunekode' => '0540' ] ] ) );
	}

	/**
	 * No division found, or a code that is not four digits, gives ''.
	 */
	public function test_no_municipality_code(): void {
		$this->assertSame( '', Vandrekalender_Dar::municipality_code( [] ) );
		$this->assertSame( '', Vandrekalender_Dar::municipality_code( [ [ 'kommunekode' => '540' ] ] ) );
	}
}
