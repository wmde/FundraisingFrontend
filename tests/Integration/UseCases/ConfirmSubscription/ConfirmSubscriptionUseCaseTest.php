<?php

declare(strict_types = 1);

namespace WMDE\Fundraising\Frontend\Tests\Integration\UseCases\ConfirmSubscription;

use PHPUnit_Framework_MockObject_MockObject;
use WMDE\Fundraising\Entities\Address;
use WMDE\Fundraising\Entities\Subscription;
use WMDE\Fundraising\Frontend\TemplateBasedMailer;
use WMDE\Fundraising\Frontend\Tests\Fixtures\InMemorySubscriptionRepository;
use WMDE\Fundraising\Frontend\UseCases\ConfirmSubscription\ConfirmSubscriptionUseCase;

/**
 * @covers WMDE\Fundraising\Frontend\UseCases\ConfirmSubscription\ConfirmSubscriptionUseCase
 *
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class ConfirmSubscriptionUseCaseTest extends \PHPUnit_Framework_TestCase {

	const CONFIRMATION_CODE = 'deadbeef';

	private function newSubscriptionAddress(): Address {
		$address = new Address();

		$address->setSalutation( 'Herr' );
		$address->setFirstName( 'Nyan' );
		$address->setLastName( 'Cat' );
		$address->setTitle( 'Dr.' );

		return $address;
	}

	private function newSubscription(): Subscription {
		$subscription = new Subscription();

		$subscription->setHexConfirmationCode( self::CONFIRMATION_CODE );
		$subscription->setEmail( 'nyan@awesomecats.com' );
		$subscription->setAddress( $this->newSubscriptionAddress() );

		return $subscription;
	}

	/**
	 * @return PHPUnit_Framework_MockObject_MockObject|TemplateBasedMailer
	 */
	private function newMailer() {
		return $this->getMockBuilder( TemplateBasedMailer::class )
			->disableOriginalConstructor()
			->getMock();
	}

	public function testGivenNoSubscriptions_anErrorResponseIsCreated() {
		$mailer = $this->newMailer();
		$mailer->expects( $this->never() )->method( 'sendMail' );
		$useCase = new ConfirmSubscriptionUseCase( new InMemorySubscriptionRepository(), $mailer );
		$result = $useCase->confirmSubscription( self::CONFIRMATION_CODE );
		$this->assertFalse( $result->isSuccessful() );
	}

	public function testGivenASubscriptionWithWrongStatus_anErrorResponseIsCreated() {
		$subscription = $this->newSubscription();
		$subscription->setStatus( Subscription::STATUS_CONFIRMED );

		$repo = new InMemorySubscriptionRepository();
		$repo->storeSubscription( $subscription );

		$mailer = $this->newMailer();
		$mailer->expects( $this->never() )->method( 'sendMail' );

		$useCase = new ConfirmSubscriptionUseCase( $repo, $mailer );

		$this->assertFalse( $useCase->confirmSubscription( self::CONFIRMATION_CODE )->isSuccessful() );
	}

	public function testGivenASubscription_aSuccessIsCreated() {
		$repo = new InMemorySubscriptionRepository();
		$repo->storeSubscription( $this->newSubscription() );

		$useCase = new ConfirmSubscriptionUseCase( $repo, $this->newMailer() );

		$this->assertTrue( $useCase->confirmSubscription( self::CONFIRMATION_CODE )->isSuccessful() );
	}

	public function testGivenASubscription_statusIsSetToConfirmed() {
		$repo = new InMemorySubscriptionRepository();
		$repo->storeSubscription( $this->newSubscription() );

		$useCase = new ConfirmSubscriptionUseCase( $repo, $this->newMailer() );
		$useCase->confirmSubscription( self::CONFIRMATION_CODE );

		$this->assertSame(
			Subscription::STATUS_CONFIRMED,
			$repo->getSubscriptions()[0]->getStatus(),
			'Confirmed status was not set.'
		);
	}

	public function testGivenASubscription_aConfirmationMailIsSent() {
		$repo = new InMemorySubscriptionRepository();
		$repo->storeSubscription( $this->newSubscription() );

		$mailer = $this->newMailer();
		$mailer->expects( $this->once() )->method( 'sendMail' );

		$useCase = new ConfirmSubscriptionUseCase( $repo, $mailer );

		$this->assertTrue( $useCase->confirmSubscription( self::CONFIRMATION_CODE )->isSuccessful() );
	}
}
