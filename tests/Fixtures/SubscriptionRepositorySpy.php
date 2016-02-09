<?php

declare(strict_types = 1);

namespace WMDE\Fundraising\Frontend\Tests\Fixtures;

use WMDE\Fundraising\Entities\Subscription;
use WMDE\Fundraising\Frontend\Domain\SubscriptionRepository;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SubscriptionRepositorySpy implements SubscriptionRepository {

	private $subscriptions = [];

	public function storeSubscription( Subscription $subscription ) {
		$this->subscriptions[] = $subscription;
	}

	/**
	 * @return Subscription[]
	 */
	public function getSubscriptions(): array {
		return $this->subscriptions;
	}

	public function findByConfirmationCode( string $confirmationCode )
	{
		foreach( $this->subscriptions as $subscription ) {
			if ( $subscription->getConfirmationCode() === $confirmationCode ) {
				return $subscription;
			}
		}
	}


}