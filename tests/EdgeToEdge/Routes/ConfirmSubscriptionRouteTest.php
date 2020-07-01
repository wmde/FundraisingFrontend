<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge\Routes;

use Symfony\Component\HttpKernel\Client;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Tests\EdgeToEdge\WebRouteTestCase;
use WMDE\Fundraising\SubscriptionContext\Domain\Model\Subscription;

/**
 * @covers \WMDE\Fundraising\Frontend\App\Routes
 */
class ConfirmSubscriptionRouteTest extends WebRouteTestCase {

	public function testGivenAnUnconfirmedSubscriptionRequest_successPageIsDisplayed(): void {
		$this->createEnvironment( [ 'skin' => 'laika' ], function ( Client $client, FunFunFactory $factory ): void {
			$subscription = new Subscription();
			$subscription->setConfirmationCode( 'deadbeef' );
			$subscription->setEmail( 'tester@example.com' );

			$factory->getSubscriptionRepository()->storeSubscription( $subscription );

			$client->request(
				'GET',
				'/contact/confirm-subscription/deadbeef'
			);
			$response = $client->getResponse();

			$this->assertSame( 200, $response->getStatusCode() );
			$this->assertStringContainsString( 'Vielen Dank für die Verifizierung Ihrer E-Mailadresse', $response->getContent() );
		} );
	}

	public function testGivenANonHexadecimalConfirmationCode_confirmationPageIsNotFound(): void {
		$client = $this->createClient( [ 'skin' => 'laika' ], null, self::DISABLE_DEBUG );

		$client->request(
			'GET',
			'/contact/confirm-subscription/kittens'
		);

		$this->assert404( $client->getResponse() );
	}

	public function testGivenNoSubscription_anErrorIsDisplayed(): void {
		$client = $this->createClient();

		$client->request(
			'GET',
			'/contact/confirm-subscription/deadbeef'
		);
		$response = $client->getResponse();

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertStringContainsString( 'subscription_confirmation_code_not_found', $response->getContent() );
	}

	public function testGivenAConfirmedSubscriptionRequest_successPageIsDisplayed(): void {
		$this->createEnvironment( [ 'skin' => 'laika' ], function ( Client $client, FunFunFactory $factory ): void {
			$subscription = new Subscription();
			$subscription->setConfirmationCode( 'deadbeef' );
			$subscription->setEmail( 'tester@example.com' );
			$subscription->markAsConfirmed();

			$factory->getSubscriptionRepository()->storeSubscription( $subscription );

			$client->request(
				'GET',
				'/contact/confirm-subscription/deadbeef'
			);
			$response = $client->getResponse();

			$this->assertSame( 200, $response->getStatusCode() );
			$this->assertStringContainsString( 'subscription_already_confirmed', $response->getContent() );
		} );
	}
}
