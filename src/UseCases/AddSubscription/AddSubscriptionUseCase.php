<?php

declare(strict_types = 1);

namespace WMDE\Fundraising\Frontend\UseCases\AddSubscription;

use WMDE\Fundraising\Entities\Address;
use WMDE\Fundraising\Entities\Subscription;
use WMDE\Fundraising\Frontend\Domain\SubscriptionRepository;
use WMDE\Fundraising\Frontend\MailAddress;
use WMDE\Fundraising\Frontend\ResponseModel\ValidationResponse;
use WMDE\Fundraising\Frontend\TemplateBasedMailer;
use WMDE\Fundraising\Frontend\Validation\SubscriptionValidator;

/**
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class AddSubscriptionUseCase {

	private $subscriptionRepository;
	private $subscriptionValidator;
	private $mailer;

	public function __construct( SubscriptionRepository $subscriptionRepository, SubscriptionValidator $subscriptionValidator,
		TemplateBasedMailer $mailer ) {

		$this->subscriptionRepository = $subscriptionRepository;
		$this->subscriptionValidator = $subscriptionValidator;
		$this->mailer = $mailer;
	}

	public function addSubscription( SubscriptionRequest $subscriptionRequest ) {
		$subscription = $this->createSubscriptionFromRequest( $subscriptionRequest );

		if ( ! $this->subscriptionValidator->validate( $subscription ) ) {
			return ValidationResponse::newFailureResponse( $this->subscriptionValidator->getConstraintViolations() );
		}

		if ( $this->subscriptionValidator->needsModeration( $subscription ) ) {
			$subscription->setStatus( Subscription::STATUS_MODERATION );
		}

		$this->subscriptionRepository->storeSubscription( $subscription );

		if ( $this->subscriptionValidator->needsModeration( $subscription ) ) {
			return ValidationResponse::newModerationNeededResponse();
		}

		$this->sendSubscriptionNotification( $subscription );

		return ValidationResponse::newSuccessResponse();
	}

	private function sendSubscriptionNotification( Subscription $subscription ) {
		$this->mailer->sendMail(
			$this->newMailAddressFromSubscription( $subscription ),
			// FIXME: this is an output similar to the main response model and should similarly not be an entity
			[ 'subscription' => $subscription ]
		);
	}

	private function newMailAddressFromSubscription( Subscription $subscription ): MailAddress {
		return new MailAddress(
			$subscription->getEmail(),
			implode(
				' ',
				[
					$subscription->getAddress()->getFirstName(),
					$subscription->getAddress()->getLastName()
				]
			)
		);
	}

	private function createSubscriptionFromRequest( SubscriptionRequest $subscriptionRequest ): Subscription {
		$request = new Subscription();

		$request->setAddress( $this->addressFromSubscriptionRequest( $subscriptionRequest ) );
		$request->setEmail( $subscriptionRequest->getEmail() );
		$request->setConfirmationCode( random_bytes( 16 ) ); // No need to use uuid library here

		return $request;
	}

	private function addressFromSubscriptionRequest( SubscriptionRequest $subscriptionRequest ): Address {
		$address = new Address();

		$address->setSalutation( $subscriptionRequest->getSalutation() );
		$address->setTitle( $subscriptionRequest->getTitle() );
		$address->setFirstName( $subscriptionRequest->getFirstName() );
		$address->setLastName( $subscriptionRequest->getLastName() );
		$address->setAddress( $subscriptionRequest->getAddress() );
		$address->setPostcode( $subscriptionRequest->getPostcode() );
		$address->setCity( $subscriptionRequest->getCity() );

		return $address;
	}

}