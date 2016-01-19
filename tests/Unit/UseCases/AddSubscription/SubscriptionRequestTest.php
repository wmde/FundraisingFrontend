<?php

declare(strict_types=1);

namespace WMDE\Fundraising\Frontend\Tests\Unit\UseCases\AddSubscription;

use WMDE\Fundraising\Frontend\UseCases\AddSubscription\SubscriptionRequest;

/**
 * @covers WMDE\Fundraising\Frontend\UseCases\AddSubscription\SubscriptionRequest
 *
 * @licence GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class SubscriptionRequestTest extends \PHPUnit_Framework_TestCase {

	public function testGivenInvalidValues_WikiloginIsFalse() {
		$request = new SubscriptionRequest();
		$request->setWikiloginFromValues( ['', 'foo', 'bar' ] );
		$this->assertFalse( $request->getWikilogin() );
	}

	public function testGivenValues_WikiloginChoosesTheFirstValidValue() {
		$request = new SubscriptionRequest();
		$request->setWikiloginFromValues( ['', 'yes' ] );
		$this->assertTrue( $request->getWikilogin() );

		$request->setWikiloginFromValues( ['0', 'yes' ] );
		$this->assertFalse( $request->getWikilogin() );
	}

}
