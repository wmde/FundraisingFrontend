<?php

namespace WMDE\Fundraising\Frontend\Tests\Integration\Presentation\Presenters;

use WMDE\Fundraising\Frontend\MembershipContext\Tests\Data\ValidMembershipApplication;
use WMDE\Fundraising\Frontend\MembershipContext\UseCases\ShowMembershipApplicationConfirmation\ShowMembershipAppConfirmationResponse;
use WMDE\Fundraising\Frontend\Presentation\Presenters\MembershipApplicationConfirmationHtmlPresenter;
use WMDE\Fundraising\Frontend\Presentation\TwigTemplate;

/**
 * @covers WMDE\Fundraising\Frontend\Presentation\Presenters\MembershipApplicationConfirmationHtmlPresenter
 *
 * @licence GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class MembershipApplicationConfirmationHtmlPresenterTest extends \PHPUnit\Framework\TestCase {

	private const STATUS_BOOKED = 'status-booked';
	private const STATUS_UNCONFIRMED = 'status-unconfirmed';

	/** @dataProvider applicationStatusProvider */
	public function testWhenPresenterPresents_itPassesParametersToTemplate( $isConfirmed, $expectedMappedStatus ) {
		$twig = $this->getMockBuilder( TwigTemplate::class )->disableOriginalConstructor()->getMock();
		$twig->expects( $this->once() )
			->method( 'render' )
			->with( $this->getExpectedRenderParams( $expectedMappedStatus ) );

		$membershipApplication = ValidMembershipApplication::newDomainEntityUsingPayPal();
		if ( $isConfirmed === true ) {
			$membershipApplication->confirm();
		}

		$presenter = new MembershipApplicationConfirmationHtmlPresenter( $twig );
		$presenter->present(
			ShowMembershipAppConfirmationResponse::newValidResponse(
				$membershipApplication,
				'update_token'
			)
		);
	}

	public function applicationStatusProvider() {
		return [
			[ true, self::STATUS_BOOKED ],
			[ false, self::STATUS_UNCONFIRMED ]
		];
	}

	private function getExpectedRenderParams( string $mappedStatus ): array {
		return [
			'membershipApplication' => [
				'id' => null,
				'paymentType' => 'PPL',
				'status' => $mappedStatus,
				'membershipFee' => '10.00',
				'paymentIntervalInMonths' => 3,
				'updateToken' => 'update_token'
			],
			'person' => [
				'salutation' => 'Herr',
				'title' => '',
				'fullName' => 'Potato The Great',
				'streetAddress' => 'Nyan street',
				'postalCode' => '1234',
				'city' => 'Berlin',
				'email' => 'jeroendedauw@gmail.com',
			],
			'bankData' => []
		];
	}

}
