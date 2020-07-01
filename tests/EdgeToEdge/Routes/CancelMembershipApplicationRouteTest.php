<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge\Routes;

use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpKernel\Client;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Tests\EdgeToEdge\WebRouteTestCase;
use WMDE\Fundraising\MembershipContext\DataAccess\MembershipApplicationData;
use WMDE\Fundraising\MembershipContext\Tests\Data\ValidMembershipApplication;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class CancelMembershipApplicationRouteTest extends WebRouteTestCase {

	const CORRECT_UPDATE_TOKEN = 'b5b249c8beefb986faf8d186a3f16e86ef509ab2';

	public function testGivenValidUpdateToken_confirmationPageIsShown(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ): void {
			$applicationId = $this->storeApplication( $factory->getEntityManager() );

			$client->request(
				'GET',
				'cancel-membership-application',
				[
					'id' => (string)$applicationId,
					'updateToken' => self::CORRECT_UPDATE_TOKEN,
				]
			);

			$this->assertStringContainsString( 'membership-cancellation-text', $client->getResponse()->getContent() );
		} );
	}

	public function testGivenInvalidUpdateToken_resultIsError(): void {
		$this->createEnvironment( [], function ( Client $client, FunFunFactory $factory ): void {
			$applicationId = $this->storeApplication( $factory->getEntityManager() );

			$client->request(
				'GET',
				'cancel-membership-application',
				[
					'id' => (string)$applicationId,
					'updateToken' => 'Not the correct update token',
				]
			);

			$this->assertStringContainsString( 'membership-cancellation-failed-text', $client->getResponse()->getContent() );
		} );
	}

	private function storeApplication( EntityManager $entityManager ): int {
		$application = ValidMembershipApplication::newDoctrineEntity();

		$application->modifyDataObject( function ( MembershipApplicationData $data ): void {
			$data->setUpdateToken( self::CORRECT_UPDATE_TOKEN );
		} );

		$entityManager->persist( $application );
		$entityManager->flush();

		return $application->getId();
	}

}
