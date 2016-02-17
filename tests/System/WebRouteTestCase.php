<?php

declare(strict_types = 1);

namespace WMDE\Fundraising\Frontend\Tests\System;

use Silex\Application;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use WMDE\Fundraising\Frontend\FunFunFactory;
use WMDE\Fundraising\Frontend\Tests\TestEnvironment;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class WebRouteTestCase extends \PHPUnit_Framework_TestCase {

	const DISABLE_DEBUG = false;

	/**
	 * Initializes a new test environment and Silex Application and returns a HttpKernel client to
	 * make requests to the application. The initialized test environment gets set to the
	 * $testEnvironment field.
	 *
	 * @param array $config
	 * @param callable|null $onEnvironmentCreated Gets called after onTestEnvironmentCreated, same signature
	 * @param bool $debug
	 *
	 * @return Client
	 */
	public function createClient( array $config = [], callable $onEnvironmentCreated = null, bool $debug = true ): Client {
		$testEnvironment = TestEnvironment::newInstance( $config );

		$this->onTestEnvironmentCreated( $testEnvironment->getFactory(), $testEnvironment->getConfig() );

		if ( is_callable( $onEnvironmentCreated ) ) {
			call_user_func( $onEnvironmentCreated, $testEnvironment->getFactory(), $testEnvironment->getConfig() );
		}

		return new Client( $this->createApplication( $testEnvironment->getFactory(), $debug ) );
	}

	/**
	 * Template method. No need to call the definition here from overriding methods as
	 * this one will always be empty.
	 *
	 * @param FunFunFactory $factory
	 * @param array $config
	 */
	protected function onTestEnvironmentCreated( FunFunFactory $factory, array $config ) {
		// No-op
	}

	// @codingStandardsIgnoreStart
	private function createApplication( FunFunFactory $ffFactory, bool $debug ) : Application {
		// @codingStandardsIgnoreEnd
		$app = require __DIR__ . ' /../../app/bootstrap.php';

		if ( $debug ) {
			$app['debug'] = true;
			unset( $app['exception_handler'] );
		}

		return $app;
	}

	protected function assert404( Response $response ) {
		$this->assertSame( 404, $response->getStatusCode() );
	}

	protected function assertJsonResponse( $expected, Response $response ) {
		$this->assertSame(
			json_encode( $expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			$response->getContent()
		);
	}

	protected function assertJsonSuccessResponse( $expected, Response $response ) {
		$this->assertTrue( $response->isSuccessful(), 'request is successful' );
		$this->assertJson( $response->getContent(), 'response is json' );
		$this->assertJsonResponse( $expected, $response );
	}

	protected function assertGetRequestCausesMethodNotAllowedResponse( string $route, array $params ) {
		$client = $this->createClient();

		$this->expectException( MethodNotAllowedHttpException::class );
		$client->request( 'GET', $route, $params );
	}

	protected function assertErrorJsonResponse( Response $response ) {
		$responseData = $this->getJsonFromResponse( $response );
		$this->assertArrayHasKey( 'status', $responseData );
		$this->assertEquals( $responseData['status'], 'ERR' );
		$this->assertArrayHasKey( 'message', $responseData );
	}

	protected function getJsonFromResponse( Response $response ) {
		$this->assertJson( $response->getContent(), 'response is json' );
		return json_decode( $response->getContent(), true );
	}

	protected function assertSuccessJsonResponse( Response $response ) {
		$responseData = $this->getJsonFromResponse( $response );
		$this->assertArrayHasKey( 'status', $responseData );
		$this->assertEquals( $responseData['status'], 'OK' );
		$this->assertArrayHasKey( 'message', $responseData );
	}

}