<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\MembershipContext\Tests\Integration\UseCases\HandleSubscriptionPaymentNotification;

use Psr\Log\NullLogger;
use WMDE\Fundraising\Frontend\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\Frontend\DonationContext\Tests\Data\ValidPayPalNotificationRequest;
use WMDE\Fundraising\Frontend\Infrastructure\TemplateBasedMailer;
use WMDE\Fundraising\Frontend\MembershipContext\DataAccess\DoctrineApplicationRepository;
use WMDE\Fundraising\Frontend\MembershipContext\Tests\Fixtures\FailingAuthorizer;
use WMDE\Fundraising\Frontend\MembershipContext\Tests\Fixtures\FakeApplicationRepository;
use WMDE\Fundraising\Frontend\MembershipContext\Tests\Fixtures\SucceedingAuthorizer;
use WMDE\Fundraising\Frontend\MembershipContext\UseCases\HandleSubscriptionPaymentNotification\HandleSubscriptionPaymentNotificationUseCase;
use WMDE\Fundraising\Frontend\MembershipContext\Tests\Data\ValidMembershipApplication;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\Frontend\Tests\Fixtures\ThrowingEntityManager;

/**
 * @covers WMDE\Fundraising\Frontend\MembershipContext\UseCases\HandleSubscriptionPaymentNotification\HandleSubscriptionPaymentNotificationUseCase
 *
 * @licence GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class HandleSubscriptionPaymentNotificationUseCaseTest extends \PHPUnit\Framework\TestCase {

	public function testWhenRepositoryThrowsException_requestIsNotHandled() {
		$useCase = new HandleSubscriptionPaymentNotificationUseCase(
			new DoctrineApplicationRepository( ThrowingEntityManager::newInstance( $this ) ),
			new SucceedingAuthorizer(),
			$this->getMailer(),
			new NullLogger()
		);
		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$response = $useCase->handleNotification( $request );
		$this->assertFalse( $response->notificationWasHandled() );
		$this->assertTrue( $response->hasErrors() );
	}

	public function testWhenApplicationDoesNotExist_requestIsNotHandled() {
		$fakeRepository = new FakeApplicationRepository();
		$fakeRepository->storeApplication( ValidMembershipApplication::newDomainEntityUsingPayPal() );

		$useCase = new HandleSubscriptionPaymentNotificationUseCase(
			$fakeRepository,
			new FailingAuthorizer(),
			$this->getMailer(),
			new NullLogger(),
			$this->getEventLogger()
		);

		$request = ValidPayPalNotificationRequest::newInstantPayment( 667 );
		$response = $useCase->handleNotification( $request );
		$this->assertFalse( $response->notificationWasHandled() );
	}

	public function testWhenAuthorizationFails_requestIsNotHandled() {
		$fakeRepository = new FakeApplicationRepository();
		$fakeRepository->storeApplication( ValidMembershipApplication::newDomainEntityUsingPayPal() );

		$useCase = new HandleSubscriptionPaymentNotificationUseCase(
			$fakeRepository,
			new FailingAuthorizer(),
			$this->getMailer(),
			new NullLogger(),
			$this->getEventLogger()
		);

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$response = $useCase->handleNotification( $request );
		$this->assertFalse( $response->notificationWasHandled() );
	}

	public function testWhenTransactionTypeIsForSubscriptionChanges_requestIsNotHandled() {
		$request = ValidPayPalNotificationRequest::newSubscriptionModification();

		$useCase = new HandleSubscriptionPaymentNotificationUseCase(
			new FakeApplicationRepository(),
			new SucceedingAuthorizer(),
			$this->getMailer(),
			new NullLogger(),
			$this->getEventLogger()
		);
		$response = $useCase->handleNotification( $request );
		$this->assertFalse( $response->notificationWasHandled() );
	}

	public function testGivenSubscriptionPaymentRequest_childDataSetIsCreated() {
		$application = ValidMembershipApplication::newConfirmedSubscriptionDomainEntity();

		$fakeRepository = new FakeApplicationRepository();
		$fakeRepository->storeApplication( $application );

		$request = ValidPayPalNotificationRequest::newRecurringPayment( $application->getId() );

		$useCase = new HandleSubscriptionPaymentNotificationUseCase(
			$fakeRepository,
			new SucceedingAuthorizer(),
			$this->getMailer(),
			new NullLogger(),
			$this->getEventLogger()
		);

		$response = $useCase->handleNotification( $request );
		$this->assertTrue( $response->notificationWasHandled() );
		$this->assertFalse( $response->hasErrors() );

		$application = $fakeRepository->getApplicationById( $application->getId() );
		/** @var \WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\PayPalPayment $payment */
		$payment = $application->getPayment()->getPaymentMethod();
		$childApplication = $fakeRepository->getApplicationById( $payment->getPayPalData()->getChildPaymentEntityId( ValidPayPalNotificationRequest::TRANSACTION_ID ) );
		$this->assertNotNull( $childApplication );
		/** @var \WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\PayPalPayment $childPayment */
		$childPayment = $childApplication->getPayment()->getPaymentMethod();
		$this->assertEquals( ValidPayPalNotificationRequest::TRANSACTION_ID, $childPayment->getPayPalData()->getPaymentId() );
		$this->assertEquals( $application->getPayment()->getAmount(), $childApplication->getPayment()->getAmount() );
		$this->assertEquals( $application->getApplicant(), $childApplication->getApplicant() );
		$this->assertEquals( $application->getPayment()->getIntervalInMonths(), $childApplication->getPayment()->getIntervalInMonths() );
		$this->assertTrue( $childApplication->isConfirmed() );
	}

	public function testGivenExistingTransactionId_requestIsNotHandled() {
		$application = ValidMembershipApplication::newConfirmedSubscriptionDomainEntity();
		/** @var PayPalPayment $payment */
		$payment = $application->getPayment()->getPaymentMethod();
		$payment->getPayPalData()->addChildPayment( ValidPayPalNotificationRequest::TRANSACTION_ID, 1 );

		$fakeRepository = new FakeApplicationRepository();
		$fakeRepository->storeApplication( $application );

		$request = ValidPayPalNotificationRequest::newRecurringPayment( 1 );

		$useCase = new HandleSubscriptionPaymentNotificationUseCase(
			$fakeRepository,
			new SucceedingAuthorizer(),
			$this->getMailer(),
			new NullLogger(),
			$this->getEventLogger()
		);

		$response = $useCase->handleNotification( $request );
		$this->assertFalse( $response->notificationWasHandled() );
	}

	/**
	 * @return TemplateBasedMailer|\PHPUnit_Framework_MockObject_MockObject
	 */
	private function getMailer(): TemplateBasedMailer {
		return $this->getMockBuilder( TemplateBasedMailer::class )->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return DonationEventLogger|\PHPUnit_Framework_MockObject_MockObject
	 */
	private function getEventLogger(): DonationEventLogger {
		return $this->createMock( DonationEventLogger::class );
	}

	public function testGivenSubscriptionPaymentRequest_parentDataSetReferencesChildPaymentId() {
		$application = ValidMembershipApplication::newConfirmedSubscriptionDomainEntity();

		$fakeRepository = new FakeApplicationRepository();
		$fakeRepository->storeApplication( $application );

		$request = ValidPayPalNotificationRequest::newRecurringPayment( $application->getId() );

		$useCase = new HandleSubscriptionPaymentNotificationUseCase(
			$fakeRepository,
			new SucceedingAuthorizer(),
			$this->getMailer(),
			new NullLogger(),
			$this->getEventLogger()
		);
		$useCase->handleNotification( $request );

		/** @var PayPalPayment $payment */
		$payment = $application->getPayment()->getPaymentMethod();

		$storedApplication = $fakeRepository->getApplicationById( $application->getId() );
		/** @var PayPalPayment $storedpayment */
		$storedpayment = $storedApplication->getPayment()->getPaymentMethod();

		$this->assertSame(
			2,
			$storedpayment->getPayPalData()->getChildPaymentEntityId( ValidPayPalNotificationRequest::TRANSACTION_ID )
		);

		$this->assertEquals(
			$payment->getPayPalData()->addChildPayment( ValidPayPalNotificationRequest::TRANSACTION_ID, 2 ),
			$storedpayment->getPayPalData()
		);
	}

}
