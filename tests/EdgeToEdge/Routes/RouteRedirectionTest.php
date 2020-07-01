<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge\Routes;

use WMDE\Fundraising\Frontend\Tests\EdgeToEdge\WebRouteTestCase;

/**
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class RouteRedirectionTest extends WebRouteTestCase {

	public function simplePageDisplayProvider(): array {
		return [
			[ '/spenden/Mitgliedschaft', '/page/Membership_Application' ],
			[ '/spenden/Fördermitgliedschaft', '/page/Fördermitgliedschaft' ],
			[ '/spenden/Mitgliedschaft?bar=baz&foo=bar', '/page/Membership_Application?bar=baz&foo=bar' ],
		];
	}

	/** @dataProvider simplePageDisplayProvider */
	public function testPageDisplayRequestsAreRedirected( string $requestedUrl, string $expectedRedirection ): void {
		$client = $this->createClient();
		$client->followRedirects( false );
		$client->request( 'GET', $requestedUrl );
		$response = $client->getResponse();

		$this->assertTrue( $response->isRedirect() );
		$this->assertSame( $expectedRedirection, $response->headers->get( 'Location' ) );
	}

	public function shouldRedirectToDefaultRouteProvider(): array {
		return [
			[ '/spenden/spende.php', '/' ],
			[ '/spenden/contact.php', '/' ],
			[ '/spenden/?', '/' ],
			[ '/spenden/?bar=baz&foo=bar', '/?bar=baz&foo=bar' ],
			[ '/spenden?bar=baz&foo=bar', '/?bar=baz&foo=bar' ],
			[ '/spenden/', '/' ],
			[ '/spenden', '/' ],
		];
	}

	/** @dataProvider shouldRedirectToDefaultRouteProvider */
	public function testRequestsAreRedirectedToDefaultRoute( string $requestedUrl, string $expectedRedirection ): void {
		$client = $this->createClient();
		$client->followRedirects( false );
		$client->request( 'GET', $requestedUrl );
		$response = $client->getResponse();

		$this->assertTrue( $response->isRedirect() );
		$this->assertSame( $expectedRedirection, $response->headers->get( 'Location' ) );
	}

	public function commentListUrlProvider(): array {
		return [
			[ '/spenden/list.php', '/list-comments.html' ],
			[ '/spenden/rss.php', '/list-comments.rss' ],
			[ '/spenden/json.php', '/list-comments.json' ]
		];
	}

	/** @dataProvider commentListUrlProvider */
	public function testCallsToCommentListIsRedirected( string $requestedUrl, string $expectedRedirection ): void {
		$client = $this->createClient();
		$client->followRedirects( false );
		$client->request( 'GET', $requestedUrl );
		$response = $client->getResponse();

		$this->assertTrue( $response->isRedirect() );
		$this->assertSame( $expectedRedirection, $response->headers->get( 'Location' ) );
	}

}
