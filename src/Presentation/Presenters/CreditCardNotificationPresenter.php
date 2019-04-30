<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Presentation\Presenters;

use WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationResponse;

/**
 * @licence GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class CreditCardNotificationPresenter {

	private const VALUE_ASSIGNMENT = '=';
	private const ARG_SEPARATOR = "\n";

	private $returnUrl;

	public function __construct( string $returnUrl ) {
		$this->returnUrl = $returnUrl;
	}

	public function present( CreditCardNotificationResponse $response, string $donationId, string $accessToken ): string {
		if ( !$response->isSuccessful() ) {
			return $this->render( [
				'status' => 'error',
				'msg' => $response->getErrorMessage()
			] );
		}

		return $this->render( [
			'status' => 'ok',
			'url' => $this->returnUrl . '?' . http_build_query( [
					'id' => $donationId,
					'accessToken' => $accessToken
				] ),
			'target' => '_top',
			'forward' => '1',
		] );
	}

	/**
	 * Response format expected by 3rd party
	 *
	 * @see https://www.micropayment.de/help/documentation/
	 */
	private function render( array $result ): string {
		return implode(
			'',
			array_map(
				function( $key, $value ): string {
					return $key . self::VALUE_ASSIGNMENT . $value . self::ARG_SEPARATOR;
				},
				array_keys( $result ),
				array_values( $result )
			)
		);
	}
}
