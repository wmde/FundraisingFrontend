<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\BucketTesting\Logging;

use WMDE\Fundraising\Frontend\BucketTesting\Domain\Model\Bucket;

interface BucketLogger {
	public function writeEvent( LoggingEvent $event, Bucket ...$buckets ): void;
}
