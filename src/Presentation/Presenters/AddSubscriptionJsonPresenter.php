<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Presentation\Presenters;

use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\ValidationResponse;

/**
 * @license GPL-2.0-or-later
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class AddSubscriptionJsonPresenter {

	public function present( ValidationResponse $subscriptionResponse ): array {
		if ( $subscriptionResponse->isSuccessful() ) {
			return $this->newSuccessResponse();
		}

		return $this->newErrorResponse( $subscriptionResponse );
	}

	private function newSuccessResponse(): array {
		return [ 'status' => 'OK' ];
	}

	private function newErrorResponse( ValidationResponse $response ): array {
		$errors = [];
		/** @var ConstraintViolation $constraintViolation */
		foreach ( $response->getValidationErrors() as $constraintViolation ) {
			$errors[$constraintViolation->getSource()] = $constraintViolation->getMessageIdentifier();
		}
		return [ 'status' => 'ERR', 'errors' => $errors ];
	}
}
