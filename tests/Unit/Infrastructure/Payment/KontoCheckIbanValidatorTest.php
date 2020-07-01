<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\Unit\Infrastructure\Payment;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\Frontend\Infrastructure\Payment\KontoCheckIbanValidator;
use WMDE\Fundraising\PaymentContext\Domain\Model\Iban;

/**
 * Valid IBAN number examples taken from http://www.iban-rechner.eu/ibancalculator/iban.html#examples.
 *
 * @covers \WMDE\Fundraising\Frontend\Infrastructure\Payment\KontoCheckIbanValidator
 *
 * @license GPL-2.0-or-later
 * @author Leszek Manicki <leszek.manicki@wikimedia.de>
 *
 * @requires extension konto_check
 */
class KontoCheckIbanValidatorTest extends TestCase {

	private function newValidator(): KontoCheckIbanValidator {
		return new KontoCheckIbanValidator();
	}

	public function validIbanProvider(): array {
		return [
			[ 'DE89370400440532013000' ],
			[ 'AT611904300234573201' ],
			[ 'CH9300762011623852957' ],
			[ 'BE68539007547034' ],
			[ 'IT60X0542811101000000123456' ],
			[ 'LI21088100002324013AA' ],
			[ 'LU280019400644750000' ],
		];
	}

	/**
	 * @dataProvider validIbanProvider
	 */
	public function testGivenValidIban_validateReturnsTrue( string $iban ): void {
		$validator = $this->newValidator();
		$this->assertTrue( $validator->validate( new Iban( $iban ) )->isSuccessful() );
	}

	public function wellFormedInvalidIbanProvider(): array {
		return [
			[ 'DE01234567890123456789' ],
			[ 'AT012345678901234567' ],
			[ 'CH0123456Ab0123456789' ],
			[ 'BE01234567890123' ],
			[ 'IT01A0123456789Ab0123456789' ],
			[ 'LI0123456Ab0123456789' ],
			[ 'LU01234Abc0123456789' ],
		];
	}

	/**
	 * @dataProvider wellFormedInvalidIbanProvider
	 */
	public function testGivenWellFormedButInvalidIban_validateReturnsFalse( string $iban ): void {
		$validator = $this->newValidator();
		$this->assertFalse( $validator->validate( new Iban( $iban ) )->isSuccessful() );
	}

	public function notWellFormedIbanProvider(): array {
		return [
			[ 'DE0123456789012345678' ],
			[ 'DE012345678901234567890' ],
			[ 'DEa0123456789012345678' ],
			[ 'DE0123456789a012345678' ]
		];
	}

	/**
	 * @dataProvider notWellFormedIbanProvider
	 */
	public function testGivenNotWellFormedIban_validateReturnsFalse( string $iban ): void {
		$validator = $this->newValidator();
		$this->assertFalse( $validator->validate( new Iban( $iban ) )->isSuccessful() );
	}
}
