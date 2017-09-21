<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge;

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SkinTest extends WebRouteTestCase {

	private const SKIN_1 = '10h16';
	private const SKIN_2 = 'cat17';
	private const DEFAULT_SKIN = self::SKIN_1;

	public function testDefaultSkinUsed(): void {
		$client = $this->createClient( $this->getDummyConfig(), null, self::DISABLE_DEBUG );
		$client->request( 'GET', '/' );

		$this->assertContains( self::DEFAULT_SKIN, $client->getResponse()->getContent() );
		$this->assertNoSkinResponseCookie( $client->getResponse() );
	}

	public function testDefaultSkinNotSavedInCookie(): void {
		$client = $this->createClient( $this->getDummyConfig(), null, self::DISABLE_DEBUG );
		$client->request( 'GET', '/', [ 'skin' => self::DEFAULT_SKIN ] );

		$this->assertContains( self::DEFAULT_SKIN, $client->getResponse()->getContent() );
		$this->assertNoSkinResponseCookie( $client->getResponse() );
	}

	public function testSkinChoosableViaCookie(): void {
		$client = $this->createClient( $this->getDummyConfig(), null, self::DISABLE_DEBUG );
		$client->getCookieJar()->set( new Cookie( 'skin', self::SKIN_2 ) );
		$client->request( 'GET', '/' );

		$this->assertContains( self::SKIN_2, $client->getResponse()->getContent() );
		$this->assertNoSkinResponseCookie( $client->getResponse() );
	}

	public function testSkinChoosableViaQuery(): void {
		$client = $this->createClient( $this->getDummyConfig(), null, self::DISABLE_DEBUG );
		$client->request( 'GET', '/', [ 'skin' => self::SKIN_2 ] );

		$this->assertContains( self::SKIN_2, $client->getResponse()->getContent() );
		$this->assertSkinResponseCookie( self::SKIN_2, $client->getResponse() );
	}

	public function testSkinViaQuerySuperseedsCookie(): void {
		$client = $this->createClient( $this->getDummyConfig(), null, self::DISABLE_DEBUG );
		$client->getCookieJar()->set( new Cookie( 'skin', self::SKIN_1 ) );
		$client->request( 'GET', '/', [ 'skin' => self::SKIN_2 ] );

		$this->assertContains( self::SKIN_2, $client->getResponse()->getContent() );
		$this->assertSkinResponseCookie( self::SKIN_2, $client->getResponse() );
	}

	public function testInvalidQueryIgnored(): void {
		$client = $this->createClient( $this->getDummyConfig(), null, self::DISABLE_DEBUG );
		$client->request( 'GET', '/', [ 'skin' => 'fff' ] );

		$this->assertContains( self::DEFAULT_SKIN, $client->getResponse()->getContent() );
		$this->assertNoSkinResponseCookie( $client->getResponse() );
	}

	public function testInvalidCookieIgnored(): void {
		$client = $this->createClient( $this->getDummyConfig(), null, self::DISABLE_DEBUG );
		$client->getCookieJar()->set( new Cookie( 'skin', 'ggg' ) );
		$client->request( 'GET', '/' );

		$this->assertContains( self::DEFAULT_SKIN, $client->getResponse()->getContent() );
		$this->assertNoSkinResponseCookie( $client->getResponse() );
	}

	/**
	 * While this can return skin config as it pleases, the skin files are not faked but needed to generate response content
	 */
	private function getDummyConfig(): array {
		return [
			'skin' => [
				'options' => [ self::SKIN_1, self::SKIN_2 ],
				'default' => self::DEFAULT_SKIN
			]
		];
	}

	private function assertSkinResponseCookie( string $expected, Response $response ): void {
		$cookies = $response->headers->getCookies();
		foreach ( $cookies as $cookie ) {
			/**
			 * @var Cookie $cookie
			 */
			if ( $cookie->getName() === 'skin' ) {
				$this->assertSame( $expected, $cookie->getValue() );
				return;
			}
		}
		$this->fail( 'Could not find the "skin" response cookie.' );
	}

	private function assertNoSkinResponseCookie( Response $response ): void {
		$cookies = $response->headers->getCookies();
		foreach ( $cookies as $cookie ) {
			/**
			 * @var Cookie $cookie
			 */
			if ( $cookie->getName() === 'skin' ) {
				$this->fail( 'Found an unexpected "skin" response cookie.' );
			}
		}
	}
}
