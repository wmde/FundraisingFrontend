<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

use WMDE\Fundraising\Frontend\Infrastructure\Campaign;
use WMDE\Fundraising\Frontend\Infrastructure\CampaignBuilder;
use WMDE\Fundraising\Frontend\Infrastructure\Group;

/**
 * @covers \WMDE\Fundraising\Frontend\Infrastructure\CampaignBuilder
 */
class CampaignBuilderTest extends TestCase {

	public function testCampaignsAreBuiltFromConfiguration() {
		$firstExpectedCampaign = new Campaign(
			'first',
			'f',
			new \DateTime( '2018-10-10', new \DateTimeZone( 'UTC' ) ),
			new \DateTime( '2018-12-12', new \DateTimeZone( 'UTC' ) ),
			true
		);
		$firstExpectedCampaign
			->addGroup( new Group( 'group1', $firstExpectedCampaign, Group::DEFAULT ) )
			->addGroup( new Group( 'group2', $firstExpectedCampaign, Group::NON_DEFAULT ) );
		$secondExpectedCampaign = new Campaign(
			'second',
			's',
			new \DateTime( '2019-01-01', new \DateTimeZone( 'UTC' ) ),
			new \DateTime( '2025-12-31', new \DateTimeZone( 'UTC' ) ),
			false
		);
		$secondExpectedCampaign
			->addGroup( new Group( 'example1', $secondExpectedCampaign, Group::NON_DEFAULT ) )
			->addGroup( new Group( 'example2', $secondExpectedCampaign, Group::NON_DEFAULT ) )
			->addGroup( new Group( 'default', $secondExpectedCampaign, Group::DEFAULT ) )
			;

		$builder = new CampaignBuilder( new \DateTimeZone( 'UTC' ) );
		$campaigns = $builder->getCampaigns( [
			'first' => [
				'start' => '2018-10-10',
				'end' => '2018-12-12',
				'active' => true,
				'groups' => [ 'group1', 'group2' ],
				'default_group' => 'group1',
				'url_key' => 'f'
			],
			'second'  => [
				'start' => '2019-01-01',
				'end' => '2025-12-31',
				'active' => false,
				'groups' => [ 'example1', 'example2', 'default' ],
				'default_group' => 'default',
				'url_key' => 's'
			],
		] );

		$this->assertEquals( [ $firstExpectedCampaign, $secondExpectedCampaign ], $campaigns );
	}


	public function testTimeRangeIsConvertedToUtcFromTimezone() {
		$firstExpectedCampaign = new Campaign(
			'first',
			'f',
			new \DateTime( '2018-10-10 2:00:00', new \DateTimeZone( 'UTC' ) ),
			new \DateTime( '2018-12-12 2:00:00', new \DateTimeZone( 'UTC' ) ),
			true
		);
		$firstExpectedCampaign
			->addGroup( new Group( 'group1', $firstExpectedCampaign, Group::DEFAULT ) )
			->addGroup( new Group( 'group2', $firstExpectedCampaign, Group::NON_DEFAULT ) );
		$secondExpectedCampaign = new Campaign(
			'second',
			's',
			new \DateTime( '2019-01-01 2:00:00', new \DateTimeZone( 'UTC' ) ),
			new \DateTime( '2025-12-31 2:00:00', new \DateTimeZone( 'UTC' ) ),
			false
		);
		$secondExpectedCampaign
			->addGroup( new Group( 'example1', $secondExpectedCampaign, Group::NON_DEFAULT ) )
			->addGroup( new Group( 'example2', $secondExpectedCampaign, Group::NON_DEFAULT ) )
			->addGroup( new Group( 'default', $secondExpectedCampaign, Group::DEFAULT ) )
		;

		$builder = new CampaignBuilder( new \DateTimeZone( '-200' ) );

		$campaigns = $builder->getCampaigns( [
			'first' => [
				'start' => '2018-10-10',
				'end' => '2018-12-12',
				'active' => true,
				'groups' => [ 'group1', 'group2' ],
				'default_group' => 'group1',
				'url_key' => 'f'
			],
			'second'  => [
				'start' => '2019-01-01',
				'end' => '2025-12-31',
				'active' => false,
				'groups' => [ 'example1', 'example2', 'default' ],
				'default_group' => 'default',
				'url_key' => 's'
			],
		] );

		$this->assertEquals( [ $firstExpectedCampaign, $secondExpectedCampaign ], $campaigns );
	}
}
