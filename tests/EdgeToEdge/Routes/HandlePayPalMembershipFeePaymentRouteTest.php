<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge\Routes;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use Symfony\Component\HttpKernel\Client;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Infrastructure\Payment\PayPalPaymentNotificationVerifier;
use WMDE\Fundraising\Frontend\Tests\EdgeToEdge\WebRouteTestCase;
use WMDE\Fundraising\MembershipContext\Domain\Repositories\ApplicationRepository;
use WMDE\Fundraising\MembershipContext\Tests\Data\ValidMembershipApplication;
use WMDE\Fundraising\MembershipContext\Tests\Fixtures\FixedMembershipTokenGenerator;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;

/**
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class HandlePayPalMembershipFeePaymentRouteTest extends WebRouteTestCase {

	const MEMBERSHIP_APPLICATION_ID = 1;
	const UPDATE_TOKEN = 'some token';
	const BASE_URL = 'https://that.paymentprovider.com/';
	const EMAIL_ADDRESS = 'paypaldev-facilitator@wikimedia.de';
	const ITEM_NAME = 'Your membership';
	const VERIFICATION_SUCCESSFUL = 'VERIFIED';
	const VERIFICATION_FAILED = 'INVALID';

	public function testGivenValidSubscriptionSignupRequest_applicationIndicatesSuccess(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ): void {
			$factory->setMembershipTokenGenerator( new FixedMembershipTokenGenerator(
				self::UPDATE_TOKEN,
				\DateTime::createFromFormat( 'Y-m-d H:i:s', '2039-12-31 23:59:59' )
			) );

			$factory->setNullMessenger();

			$factory->getMembershipApplicationRepository()->storeApplication( ValidMembershipApplication::newDomainEntityUsingPayPal() );

			$request = $this->newSubscriptionSignupRequest();
			$factory->setPayPalMembershipFeeNotificationVerifier(
				$this->newSucceedingVerifier()
			);

			$client->request(
				'POST',
				'/handle-paypal-membership-fee-payments',
				$request
			);

			$this->assertSame( 200, $client->getResponse()->getStatusCode() );
			$this->assertPayPalDataGotPersisted( $factory->getMembershipApplicationRepository(), $request );
		} );
	}

	private function newSucceedingVerifier(): PayPalPaymentNotificationVerifier {
		return $this->newVerifierMock( self::VERIFICATION_SUCCESSFUL );
	}

	private function newFailingVerifier(): PayPalPaymentNotificationVerifier {
		return $this->newVerifierMock( self::VERIFICATION_FAILED );
	}

	private function newVerifierMock( string $responseBody ): PayPalPaymentNotificationVerifier {
		return new PayPalPaymentNotificationVerifier(
			$this->newGuzzleClientMock( $responseBody ),
			self::BASE_URL,
			self::EMAIL_ADDRESS
		);
	}

	private function newGuzzleClientMock( string $responseBody ): GuzzleClient {
		$mock = new MockHandler( [
			new Response( 200, [], $responseBody )
		] );
		$handlerStack = HandlerStack::create( $mock );
		return new GuzzleClient( [ 'handler' => $handlerStack ] );
	}

	public function testWhenPaymentProviderDoesNotVerify_errorCodeIsReturned(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ): void {
			$request = $this->newSubscriptionSignupRequest();

			$factory->setPayPalMembershipFeeNotificationVerifier( $this->newFailingVerifier() );

			$client->request( 'POST', '/handle-paypal-membership-fee-payments', $request );

			$this->assertSame( 403, $client->getResponse()->getStatusCode() );
			$this->assertSame( 'Payment provider did not confirm the sent data', $client->getResponse()->getContent() );
		} );
	}

	public function testGivenWrongTransactionType_applicationIgnoresRequest(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ): void {
			$invalidRequest = $this->newInvalidTransactionRequest();

			$factory->setPayPalMembershipFeeNotificationVerifier( $this->newSucceedingVerifier() );

			$client->request( 'POST', '/handle-paypal-membership-fee-payments', $invalidRequest );

			$this->assertSame( 200, $client->getResponse()->getStatusCode() );
			$this->assertSame( '', $client->getResponse()->getContent() );
		} );
	}

	private function newSubscriptionSignupRequest(): array {
		return [
			'txn_type' => 'subscr_signup',

			'receiver_email' => 'paypaldev-facilitator@wikimedia.de',
			'item_number' => 1,
			'item_name' => 'Your membership',
			'payment_type' => 'instant',
			'mc_currency' => 'EUR',

			'subscr_id' => '8RHHUM3W3PRH7QY6B59',
			'subscr_date' => '20:12:59 Jan 13, 2009 PST',
			'payer_id' => 'LPLWNMTBWMFAY',
			'payer_status' => 'verified',
			'address_status' => 'confirmed',
			'first_name' => 'Generous',
			'last_name' => 'Donor',
			'address_name' => 'Generous Donor',

			'custom' => '{"id": "1", "utoken": "some token"}'
		];
	}

	private function newInvalidTransactionRequest(): array {
		return [
			'txn_type' => 'invalid_transaction',
			'receiver_email' => 'paypaldev-facilitator@wikimedia.de',
			'mc_currency' => 'EUR'
		];
	}

	private function assertPayPalDataGotPersisted( ApplicationRepository $applicationRepo, array $request ): void {
		$membershipApplication = $applicationRepo->getApplicationById( self::MEMBERSHIP_APPLICATION_ID );

		/** @var PayPalPayment $paymentMethod */
		$paymentMethod = $membershipApplication->getPayment()->getPaymentMethod();
		$pplData = $paymentMethod->getPayPalData();

		$this->assertSame( $request['payer_id'], $pplData->getPayerId() );
		$this->assertSame( $request['subscr_id'], $pplData->getSubscriberId() );
		$this->assertSame( $request['payer_status'], $pplData->getPayerStatus() );
		$this->assertSame( $request['first_name'], $pplData->getFirstName() );
		$this->assertSame( $request['last_name'], $pplData->getLastName() );
		$this->assertSame( $request['address_name'], $pplData->getAddressName() );
		$this->assertSame( $request['address_status'], $pplData->getAddressStatus() );
		$this->assertSame( $request['mc_currency'], $pplData->getCurrencyCode() );
		$this->assertSame( $request['payment_type'], $pplData->getPaymentType() );
	}

	public function testGivenPaymentNotificationForInvalidMembershipId_applicationReturnsError(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ): void {
			$invalidRequest = $this->newInvalidMembershipIdRequest();

			$factory->setPayPalMembershipFeeNotificationVerifier(
				$this->newSucceedingVerifier()
			);

			$client->request( 'POST', '/handle-paypal-membership-fee-payments', $invalidRequest );

			$this->assertSame( 200, $client->getResponse()->getStatusCode() );
			$this->assertSame( '', $client->getResponse()->getContent() );
		} );
	}

	private function newInvalidMembershipIdRequest(): array {
		return [
			'txn_type' => 'subscr_payment',

			'receiver_email' => 'paypaldev-facilitator@wikimedia.de',
			'item_number' => 12245589,
			'item_name' => 'Your membership',
			'payment_type' => 'instant',
			'mc_currency' => 'EUR',

			'subscr_id' => '8RHHUM3W3PRH7QY6B59',
			'subscr_date' => '20:12:59 Jan 13, 2009 PST',
			'payer_id' => 'LPLWNMTBWMFAY',
			'payer_status' => 'verified',
			'address_status' => 'confirmed',
			'first_name' => 'Generous',
			'last_name' => 'Donor',
			'address_name' => 'Generous Donor',

			'custom' => '{"id": "1", "utoken": "some token"}'
		];
	}

}
