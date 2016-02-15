<?php

declare(strict_types = 1);

namespace WMDE\Fundraising\Frontend\Validation;

use WMDE\Fundraising\Entities\Subscription;
use WMDE\Fundraising\Frontend\Domain\SubscriptionRepository;
use WMDE\Fundraising\Frontend\Domain\SubscriptionRepositoryException;

/**
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class SubscriptionDuplicateValidator {

	private $repository;
	private $cutoffDateTime;

	public function __construct( SubscriptionRepository $repository, \DateTime $cutoffDateTime ) {
		$this->repository = $repository;
		$this->cutoffDateTime = $cutoffDateTime;
	}

	/**
	 * @param Subscription $subscription
	 * @return ValidationResult
	 * @throws SubscriptionRepositoryException
	 */
	public function validate( Subscription $subscription ): ValidationResult {
		$constraintViolations = [];

		if ( $this->repository->countSimilar( $subscription, $this->cutoffDateTime ) > 0 ) {
			$constraintViolations[] = new ConstraintViolation(
				$subscription->getEmail(),
				'The data was already inserted'
			);
		}

		return new ValidationResult( ...$constraintViolations );
	}

}