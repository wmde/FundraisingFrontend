<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\BucketTesting;

use DateTime;
use DateTimeZone;

/**
 * @license GNU GPL v2+
 */
class CampaignBuilder {

	private $timezone;
	private $utc;

	public function __construct( DateTimeZone $timezone ) {
		$this->timezone = $timezone;
		$this->utc = new DateTimeZone( 'UTC' );
	}

	public function getCampaigns( array $campaignConfig ): array {
		$campaigns = [];
		foreach( $campaignConfig as $name => $config ) {
			$campaign = new Campaign(
				$name,
				$config['url_key'],
				CampaignDate::createFromString( $config['start'], $this->timezone ),
				CampaignDate::createFromString( $config['end'], $this->timezone ),
				$config['active'],
				$config['param_only'] ?? Campaign::NEEDS_NO_URL_KEY
			);
			foreach ( $config['buckets'] as $bucketName ) {
				$campaign->addBucket( new Bucket( $bucketName, $campaign, $bucketName === $config['default_bucket'] ) );
			}

			$campaigns[] = $campaign;
		}
		return $campaigns;
	}
}