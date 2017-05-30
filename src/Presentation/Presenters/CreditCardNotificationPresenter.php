<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Presentation\Presenters;

use WMDE\Fundraising\Frontend\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationResponse;
use WMDE\Fundraising\Frontend\Presentation\TwigTemplate;

/**
 * @licence GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class CreditCardNotificationPresenter {

	private $template;

	public function __construct( TwigTemplate $template ) {
		$this->template = $template;
	}

	public function present( CreditCardNotificationResponse $response ): string {
		return $this->template->render( [
			'donationId' => $response->getDonationId(),
			'accessToken' => $response->getAccessToken(),
			'successful' => $response->isSuccessful(),
			'errorMessage' => $response->getErrorMessage()
		] );
	}

}
