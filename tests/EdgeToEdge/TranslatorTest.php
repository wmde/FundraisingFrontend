<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge;

use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;

/**
 * @licence GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class TranslatorTest extends WebRouteTestCase {

	public function testGivenDefinedMessageKey_responseContainsTranslatedMessages(): void {
		$client = $this->createClient(
			[],
			function ( FunFunFactory $factory ): void {
				$factory->setTranslator( $this->newTranslator( [ 'page_not_found' => 'Seite nicht gefunden' ], 'de' ) );
			}
		);
		$client->request( 'GET', '/anything' );
		$this->assertStringContainsString( 'Seite nicht gefunden', $client->getResponse()->getContent() );
	}

	public function testGivenUndefinedMessageKey_responseContainsMessageKey(): void {
		$client = $this->createClient();
		$client->request( 'GET', '/anything' );
		$this->assertStringContainsString( 'page_not_found', $client->getResponse()->getContent() );
	}

	private function newTranslator( array $translatableMessages, string $locale ): Translator {
		$translator = new Translator( $locale );
		$translator->addLoader( 'array', new ArrayLoader() );
		$translator->addResource( 'array', $translatableMessages, $locale );
		return $translator;
	}

}
