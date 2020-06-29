<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Factories;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use WMDE\Euro\Euro;
use WMDE\Fundraising\Frontend\BucketTesting\FeatureToggle;
use WMDE\Fundraising\Frontend\Infrastructure\UrlGenerator;

/**
 * Factory for generating classes whose implementations differ due to A/B testing.
 *
 * @license GPL-2.0-or-later
 */
class ChoiceFactory {

	private $featureToggle;

	public function __construct( FeatureToggle $featureToggle ) {
		$this->featureToggle = $featureToggle;
	}

	public function getMembershipCallToActionTemplate(): string {
		if ( $this->featureToggle->featureIsActive( 'campaigns.membership_call_to_action.regular' ) ) {
			return 'partials/donation_confirmation/feature_toggle/call_to_action_regular.html.twig';
		} elseif ( $this->featureToggle->featureIsActive( 'campaigns.membership_call_to_action.video' ) ) {
			return 'partials/donation_confirmation/feature_toggle/call_to_action_video.html.twig';
		}
		throw new UnknownChoiceDefinition( 'Membership Call to Action Template configuration failure.' );
	}

	public function getAmountOption(): array {
		if ( $this->featureToggle->featureIsActive( 'campaigns.amount_options.5to300_0' ) ) {
			return $this->getAmountOptionInEuros( [ 500, 1500, 2500, 5000, 7500, 10000, 25000, 30000 ] );
		} elseif ( $this->featureToggle->featureIsActive( 'campaigns.amount_options.5to300' ) ) {
			return $this->getAmountOptionInEuros( [ 500, 1500, 2500, 5000, 7500, 10000, 25000, 30000 ] );
		} elseif ( $this->featureToggle->featureIsActive( 'campaigns.amount_options.5to100' ) ) {
			return $this->getAmountOptionInEuros( [ 500, 1000, 1500, 2000, 3000, 5000, 7500, 10000 ] );
		} elseif ( $this->featureToggle->featureIsActive( 'campaigns.amount_options.15to250' ) ) {
			return $this->getAmountOptionInEuros( [ 1500, 2000, 2500, 3000, 5000, 7500, 10000, 25000 ] );
		} elseif ( $this->featureToggle->featureIsActive( 'campaigns.amount_options.30to250' ) ) {
			return $this->getAmountOptionInEuros( [ 3000, 4000, 5000, 7500, 10000, 15000, 20000, 25000 ] );
		} elseif ( $this->featureToggle->featureIsActive( 'campaigns.amount_options.50to500' ) ) {
			return $this->getAmountOptionInEuros( [ 5000, 10000, 15000, 20000, 25000, 30000, 50000 ] );
		}
		throw new UnknownChoiceDefinition( 'Amount option selection configuration failure.' );
	}

	public function getAmountOptionInEuros( array $amountOption ): array {
		return array_map( function ( int $amount ) {
			return Euro::newFromCents( $amount );
		}, $amountOption );
	}

	public function getAddressType(): ?string {
		if ( $this->featureToggle->featureIsActive( 'campaigns.address_type.no_preselection' ) ) {
			return null;
		} elseif ( $this->featureToggle->featureIsActive( 'campaigns.address_type.preselection' ) ) {
			return 'person';
		}
		throw new UnknownChoiceDefinition( 'Address type configuration failure.' );
	}
}
