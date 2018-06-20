<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use RemotelyLiving\Doorkeeper\Rules\Percentage;
use RemotelyLiving\Doorkeeper\Rules\TimeAfter;
use RemotelyLiving\Doorkeeper\Rules\TimeBefore;
use WMDE\Fundraising\Frontend\Infrastructure\Campaign;
use WMDE\Fundraising\Frontend\Infrastructure\CampaignFeatureBuilder;
use WMDE\Fundraising\Frontend\Infrastructure\Group;

/**
 * @covers \WMDE\Fundraising\Frontend\Infrastructure\CampaignFeatureBuilder
 */
class CampaignFeatureBuilderTest extends TestCase {

	public function testWhenNoCampaignsAreDefined_featureSetIsEmpty() {
		$factory = new CampaignFeatureBuilder();
		$result = $factory->getFeatures()->jsonSerialize();
		$this->assertSame( [ 'features' => null ], $result );
	}

	public function testCampaignAndGroupNamesAreConvertedIntoFeatureNames() {
		$factory = new CampaignFeatureBuilder( $this->newInactiveCampaign(), $this->newActiveCampaign() );

		$features = $factory->getFeatures();

		$this->assertTrue( $features->offsetExists( 'campaigns.test_inactive.group1' ) );
		$this->assertTrue( $features->offsetExists( 'campaigns.test_inactive.group2' ) );
		$this->assertTrue( $features->offsetExists( 'campaigns.test_active.group1' ) );
		$this->assertTrue( $features->offsetExists( 'campaigns.test_active.group2' ) );
	}

	public function testWhenCampaignIsInactive_AllFeaturesExceptTheDefaultAreInactive() {
		$factory = new CampaignFeatureBuilder( $this->newInactiveCampaign() );

		$features = $factory->getFeatures();

		$this->assertTrue( $features->getFeatureByName( 'campaigns.test_inactive.group1' )->isEnabled() );
		$this->assertFalse( $features->getFeatureByName( 'campaigns.test_inactive.group2' )->isEnabled() );
	}

	public function testWhenCampaignIsActive_AllFeaturesAreEnabled() {
		$factory = new CampaignFeatureBuilder( $this->newActiveCampaign() );

		$features = $factory->getFeatures();

		$this->assertTrue( $features->getFeatureByName( 'campaigns.test_active.group1' )->isEnabled() );
		$this->assertTrue( $features->getFeatureByName( 'campaigns.test_active.group2' )->isEnabled() );
	}

	public function testWhenCampaignIsActive_AllFeaturesExceptTheDefaultHaveDatePreconditions() {
		$this->markTestIncomplete( 'TODO: assert nested rules instead of sequential ones.' );
		$factory = new CampaignFeatureBuilder( $this->newActiveCampaign() );

		$features = $factory->getFeatures();
		$rules = $features->getFeatureByName( 'campaigns.test_active.group2' )->getRules();

		$this->assertCount( 0, $features->getFeatureByName( 'campaigns.test_active.group1' )->getRules(), 'Default group feature should have no rules' );

		$this->assertEquals( new TimeAfter( '2000-01-01' ), $rules[0] );
		$this->assertEquals( new TimeBefore( '2099-01-01' ), $rules[1] );
	}

	public function testWhenCampaignWithTwoGroupsIsActive_AllFeaturesExceptTheDefaultHaveStringHashRulesBasedOnGroupName() {
		$this->markTestIncomplete( 'TODO: implement test.' );
	}

	private function newInactiveCampaign(): Campaign {
		$campaign = new Campaign(
			'test_inactive',
			't1',
			new \DateTime(),
			new \DateTime(),
			Campaign::INACTIVE
		);
		$campaign->addGroup( new Group( 'group1', $campaign, Group::DEFAULT ) )
			->addGroup( new Group( 'group2', $campaign, Group::NON_DEFAULT ) );
		return $campaign;
	}

	private function newActiveCampaign(): Campaign {
		$campaign = new Campaign(
			'test_active',
			't1',
			new \DateTime(),
			new \DateTime(),
			Campaign::ACTIVE
		);
		$campaign->addGroup( new Group( 'group1', $campaign, Group::DEFAULT ) )
			->addGroup( new Group( 'group2', $campaign, Group::NON_DEFAULT ) );
		return $campaign;
	}


}
