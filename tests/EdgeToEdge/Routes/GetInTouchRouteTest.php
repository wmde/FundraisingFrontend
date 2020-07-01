<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge\Routes;

use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Infrastructure\Mail\Messenger;
use WMDE\Fundraising\Frontend\Tests\EdgeToEdge\WebRouteTestCase;
use WMDE\Fundraising\Frontend\Tests\Fixtures\SucceedingEmailValidator;

/**
 * @covers \WMDE\Fundraising\Frontend\App\Routes
 */
class GetInTouchRouteTest extends WebRouteTestCase {

	public function testGivenValidRequest_contactRequestIsProperlyProcessed(): void {
		$client = $this->createClient( [], function ( FunFunFactory $factory ): void {
			$factory->setEmailValidator( new SucceedingEmailValidator() );
		} );

		$client->followRedirects( false );

		$client->request(
			'POST',
			'/contact/get-in-touch',
			[
				'firstname' => 'Curious',
				'lastname' => 'Guy',
				'email' => 'curious.guy@gmail.com',
				'donationNumber' => '123456',
				'subject' => 'What is it you are doing?!',
				'category' => 'Other',
				'messageBody' => 'Just tell me'
			]
		);
		$response = $client->getResponse();
		$this->assertTrue( $response->isRedirect(), 'Is redirect response' );
		$this->assertSame( '/page/Kontakt_Bestaetigung', $response->headers->get( 'Location' ) );
	}

	public function testGivenInvalidRequest_validationFails(): void {
		$client = $this->createClient();

		$crawler = $client->request(
			'POST',
			'/contact/get-in-touch',
			[
				'firstname' => 'Curious',
				'lastname' => 'Guy',
				'email' => 'no.email.format',
				'subject' => '',
				'messageBody' => ''
			]
		);

		$this->assertStringContainsString( 'text/html', $client->getResponse()->headers->get( 'Content-Type' ) );

		$this->assertCount(
			3,
			$crawler->filter( 'span.form-error' )
		);
		$this->assertCount(
			1,
			$crawler->filter( 'input[name="firstname"][value="Curious"]' )
		);
		$this->assertCount(
			1,
			$crawler->filter( 'input[name="lastname"][value="Guy"]' )
		);
	}

	public function testGivenGetRequest_formShownWithoutErrors(): void {
		$client = $this->createClient();

		$crawler = $client->request(
			'GET',
			'/contact/get-in-touch'
		);

		$this->assertStringContainsString( 'text/html', $client->getResponse()->headers->get( 'Content-Type' ) );
		$this->assertCount(
			0,
			$crawler->filter( 'span.form-error' )
		);
	}

	public function testOnException_errorPageIsRendered(): void {
		$client = $this->createClient(
			[],
			function ( FunFunFactory $factory ): void {
				$messenger = $this->getMockBuilder( Messenger::class )
					->disableOriginalConstructor()
					->getMock();

				$messenger->expects( $this->any() )
					->method( 'sendMessageToUser' )
					->willThrowException( new \RuntimeException( 'Something unexpected happened' ) );

				$factory->setSuborganizationMessenger( $messenger );
				$factory->setEmailValidator( new SucceedingEmailValidator() );
			},
			self::DISABLE_DEBUG
		);

		$client->request(
			'POST',
			'/contact/get-in-touch',
			[
				'firstname' => 'Some Other',
				'lastname' => 'Guy',
				'email' => 'someother@alltheguys.com',
				'donationNumber' => '123456',
				'subject' => 'Give me an error page',
				'category' => 'Other',
				'messageBody' => 'Let me see if I can raise an exception'
			]
		);

		$response = $client->getResponse();
		$content = $response->getContent();

		$this->assertStringContainsString( 'Internal Error: Something unexpected happened', $content );
	}
}
