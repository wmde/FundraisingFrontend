<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\UseCases\ConfirmSubscription;

use WMDE\Fundraising\Entities\Subscription;
use WMDE\Fundraising\Frontend\Domain\Repositories\SubscriptionRepository;
use WMDE\Fundraising\Frontend\Domain\Model\MailAddress;
use WMDE\Fundraising\Frontend\ResponseModel\ValidationResponse;
use WMDE\Fundraising\Frontend\TemplateBasedMailer;
use WMDE\Fundraising\Frontend\Validation\ConstraintViolation;

/**
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class ConfirmSubscriptionUseCase {

	private $subscriptionRepository;

	private $mailer;

	public function __construct( SubscriptionRepository $subscriptionRepository, TemplateBasedMailer $mailer ) {
		$this->subscriptionRepository = $subscriptionRepository;
		$this->mailer = $mailer;
	}

	public function confirmSubscription( string $confirmationCode ): ValidationResponse {

		$subscription = $this->subscriptionRepository->findByConfirmationCode( $confirmationCode );

		if ( $subscription === null ) {
			$errorMsg = 'subscription_confirmation_code_not_found';
			return ValidationResponse::newFailureResponse( [ new ConstraintViolation( $confirmationCode, $errorMsg ) ] );
		}

		if ( $subscription->getStatus() === Subscription::STATUS_NEUTRAL ) {
			$subscription->setStatus( Subscription::STATUS_CONFIRMED );
			$this->subscriptionRepository->storeSubscription( $subscription );
			$this->mailer->sendMail( new MailAddress( $subscription->getEmail() ), [ 'subscription' => $subscription ] );
			return ValidationResponse::newSuccessResponse();
		}

		$errorMsg = 'subscription_already_confirmed';
		return ValidationResponse::newFailureResponse( [ new ConstraintViolation( $confirmationCode, $errorMsg ) ] );
	}
}