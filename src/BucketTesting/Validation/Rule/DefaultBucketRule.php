<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\BucketTesting\Validation\Rule;

use WMDE\Fundraising\Frontend\BucketTesting\Campaign;
use WMDE\Fundraising\Frontend\BucketTesting\Validation\ValidationErrorLogger;

/**
 * @license GPL-2.0-or-later
 */
class DefaultBucketRule implements CampaignValidationRuleInterface {

	public function validate( Campaign $campaign, ValidationErrorLogger $errorLogger ): bool {
		try {
			$campaign->getDefaultBucket();
		}
		catch ( \LogicException $e ) {
			$errorLogger->addError( 'Must have a valid default bucket.', $campaign );
			return false;
		}
		return true;
	}

}
