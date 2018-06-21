<?php

declare(strict_types = 1);

namespace WMDE\Fundraising\Frontend\BucketTesting;

/**
 * @license GNU GPL v2+
 */
interface CampaignConfigurationLoaderInterface {
	public function loadCampaignConfiguration( string ...$configFiles ): array;
}