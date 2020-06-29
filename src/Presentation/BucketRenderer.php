<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Presentation;

use WMDE\Fundraising\Frontend\BucketTesting\Bucket;

/**
 * Prepares a list of buckets into template variables
 * @license GPL-2.0-or-later
 */
class BucketRenderer {

	public static function renderBuckets( Bucket ...$buckets ): array {
		return array_map(
			function ( Bucket $b ) {
				return $b->getId();
			},
			$buckets
		);
	}

}
