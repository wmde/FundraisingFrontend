<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\Unit\MembershipContext\Domain\Model;

use RuntimeException;
use WMDE\Fundraising\Frontend\MembershipContext\Tests\Data\ValidMembershipApplication;

/**
 * @covers WMDE\Fundraising\Frontend\MembershipContext\Domain\Model\Application
 *
 * @license GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ApplicationTest extends \PHPUnit\Framework\TestCase {

	public function testIdIsNullWhenNotAssigned() {
		$this->assertNull( ValidMembershipApplication::newDomainEntity()->getId() );
	}

	public function testCanAssignIdToNewDonation() {
		$donation = ValidMembershipApplication::newDomainEntity();

		$donation->assignId( 42 );
		$this->assertSame( 42, $donation->getId() );
	}

	public function testCannotAssignIdToDonationWithIdentity() {
		$donation = ValidMembershipApplication::newDomainEntity();
		$donation->assignId( 42 );

		$this->expectException( RuntimeException::class );
		$donation->assignId( 43 );
	}

	public function testNewApplicationHasExpectedDefaults() {
		$application = ValidMembershipApplication::newDomainEntity();

		$this->assertNull( $application->getId() );
		$this->assertFalse( $application->isCancelled() );
		$this->assertFalse( $application->needsModeration() );
	}

	public function testCancellationResultsInCancelledApplication() {
		$application = ValidMembershipApplication::newDomainEntity();
		$application->cancel();

		$this->assertTrue( $application->isCancelled() );
	}

	public function testMarkForModerationResultsInApplicationThatNeedsModeration() {
		$application = ValidMembershipApplication::newDomainEntity();
		$application->markForModeration();

		$this->assertTrue( $application->needsModeration() );
	}

}