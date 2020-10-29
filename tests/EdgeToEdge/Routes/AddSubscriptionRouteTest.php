<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge\Routes;

use Symfony\Component\DomCrawler\Crawler;
use WMDE\Fundraising\Frontend\App\Controllers\SetCookiePreferencesController;
use WMDE\Fundraising\Frontend\App\CookieNames;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Tests\EdgeToEdge\WebRouteTestCase;
use WMDE\Fundraising\SubscriptionContext\Tests\Fixtures\SubscriptionRepositorySpy;

/**
 * @license GPL-2.0-or-later
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 *
 * @covers \WMDE\Fundraising\Frontend\App\Controllers\Subscription\AddSubscriptionController
 * @covers \WMDE\Fundraising\Frontend\Presentation\Presenters\AddSubscriptionJsonPresenter
 * @covers \WMDE\Fundraising\Frontend\Presentation\Presenters\AddSubscriptionHtmlPresenter
 */
class AddSubscriptionRouteTest extends WebRouteTestCase {

	private array $validFormInput = [
		'email' => 'jeroendedauw@gmail.com',
		'wikilogin' => true,
		'source' => 'testCampaign',
	];

	private array $invalidFormInput = [
		'email' => 'not an email',
		'wikilogin' => true
	];

	public function testValidSubscriptionRequestGetsPersisted(): void {
		$subscriptionRepository = new SubscriptionRepositorySpy();

		$client = $this->createClient(
			[ 'skin' => 'laika' ],
			function ( FunFunFactory $factory ) use ( $subscriptionRepository ): void {
				$factory->setSubscriptionRepository( $subscriptionRepository );
			},
			[ CookieNames::CONSENT => 'yes' ]
		);

		$client->followRedirects( false );

		$client->request(
			'POST',
			'/contact/subscribe?piwik_campaign=test&piwik_kwd=blue',
			$this->validFormInput
		);

		$this->assertCount( 1, $subscriptionRepository->getSubscriptions() );

		$subscription = $subscriptionRepository->getSubscriptions()[0];

		$this->assertSame( 'jeroendedauw@gmail.com', $subscription->getEmail() );
		$this->assertSame( 'test/blue', $subscription->getTracking() );
		$this->assertSame( 'testCampaign', $subscription->getSource() );
	}

	public function testGivenValidDataAndNoContentType_routeReturnsRedirectToSucccessPage(): void {
		$client = $this->createClient( [ 'skin' => 'laika' ] );
		$client->followRedirects( false );
		$client->request(
			'POST',
			'/contact/subscribe',
			$this->validFormInput
		);
		$response = $client->getResponse();
		$this->assertTrue( $response->isRedirect(), 'Is redirect response' );
		$this->assertSame( 'https://such.a.url/page?pageName=Subscription_Success', $response->headers->get( 'Location' ) );
	}

	public function testGivenInvalidDataAndNoContentType_routeDisplaysFormPage(): void {
		$client = $this->createClient( [ 'skin' => 'laika' ] );

		$crawler = $client->request(
			'POST',
			'/contact/subscribe',
			$this->invalidFormInput
		);

		$this->assertStringContainsString( 'text/html', $client->getResponse()->headers->get( 'Content-Type' ) );

		$applicationVars = $this->getDataApplicationVars( $crawler );
		$this->assertSame( 'email_address_wrong_format', $applicationVars->errors->email );
		$this->assertSame( 'not an email', $applicationVars->email );
	}

	public function testGivenInvalidDataAndJSONContentType_routeReturnsSuccessResult(): void {
		$client = $this->createClient( [ 'skin' => 'laika' ] );
		$client->followRedirects( false );
		$client->request(
			'POST',
			'/contact/subscribe',
			$this->validFormInput,
			[],
			[ 'HTTP_ACCEPT' => 'application/json' ]
		);
		$response = $client->getResponse();
		$this->assertJsonSuccessResponse( [ 'status' => 'OK' ], $response );
	}

	public function testGivenInvalidDataAndJSONContentType_routeReturnsErrorResult(): void {
		$client = $this->createClient( [ 'skin' => 'laika' ] );

		$client->request(
			'POST',
			'/contact/subscribe',
			$this->invalidFormInput,
			[],
			[ 'HTTP_ACCEPT' => 'application/json' ]
		);

		$response = $client->getResponse();
		$this->assertTrue( $response->isSuccessful(), 'request is successful' );
		$this->assertJson( $response->getContent(), 'response is json' );
		$responseData = json_decode( $response->getContent(), true );
		$this->assertSame( 'ERR', $responseData['status'] );
		$this->assertGreaterThan( 0, count( $responseData['errors'] ) );
		$this->assertSame( 'email_address_wrong_format', $responseData['errors']['email'] );
	}

	public function testGivenValidDataAndJSONPRequest_routeReturnsResult(): void {
		$client = $this->createClient( [ 'skin' => 'laika' ] );
		$client->request(
			'GET',
			'/contact/subscribe',
			array_merge(
				$this->validFormInput,
				[ 'callback' => 'test' ]
			),
			[],
			[ 'HTTP_ACCEPT' => 'application/javascript' ]
		);

		$response = $client->getResponse();
		$this->assertTrue( $response->isSuccessful(), 'request is successful' );
		$this->assertSame(
			file_get_contents( __DIR__ . '/../../Data/files/addSubscriptionResponse.js' ),
			$response->getContent()
		);
	}

	private function getDataApplicationVars( Crawler $crawler ): object {
		/** @var \DOMElement $appElement */
		$appElement = $crawler->filter( '#app' )->getNode( 0 );
		return json_decode( $appElement->getAttribute( 'data-application-vars' ) );
	}

}
