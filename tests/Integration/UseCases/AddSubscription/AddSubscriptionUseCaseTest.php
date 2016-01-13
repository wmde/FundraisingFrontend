<?php


namespace WMDE\Fundraising\Frontend\Tests\Integration\UseCases\AddSubscription;

use WMDE\Fundraising\Entities\Request;
use WMDE\Fundraising\Frontend\Domain\RequestRepository;
use WMDE\Fundraising\Frontend\Domain\RequestValidator;
use WMDE\Fundraising\Frontend\UseCases\AddSubscription\AddSubscriptionResponse;
use WMDE\Fundraising\Frontend\UseCases\AddSubscription\AddSubscriptionUseCase;
use WMDE\Fundraising\Frontend\UseCases\AddSubscription\SubscriptionRequest;

/**
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class AddSubscriptionUseCaseTest extends \PHPUnit_Framework_TestCase
{
	private $repo;
	private $validator;

	public function setUp() {
		parent::setUp();
		$this->repo = $this->getMock( RequestRepository::class );
		$this->validator = $this->getMockBuilder( RequestValidator::class )
			->disableOriginalConstructor()
			->getMock();
	}

	public function testGivenValidData_aValidResponseIsCreated() {
		$this->validator->method( 'validate' )->willReturn( true );
		$usecase = new AddSubscriptionUseCase( $this->repo, $this->validator );
		$request = $this->getMock( SubscriptionRequest::class );
		$result = $usecase->addSubscription( $request );
		$this->assertEquals( AddSubscriptionResponse::TYPE_VALID, $result->getType() );
	}

	public function testGivenInvalidData_invalidResponseTypeIsCreated() {
		$this->validator->method( 'validate' )->willReturn( false );
		$this->validator->method( 'getValidationErrors' )->willReturn( [] );
		$usecase = new AddSubscriptionUseCase( $this->repo, $this->validator );
		$request = $this->getMock( SubscriptionRequest::class );
		$result = $usecase->addSubscription( $request );
		$this->assertEquals( AddSubscriptionResponse::TYPE_INVALID, $result->getType() );
	}

	public function testGivenValidData_requestWillBeStored() {
		$this->validator->method( 'validate' )->willReturn( true );
		$this->repo->expects( $this->once() )
			->method( 'storeRequest' )
			->with( $this->isInstanceOf( Request::class ) );
		$usecase = new AddSubscriptionUseCase( $this->repo, $this->validator );
		$request = $this->getMock( SubscriptionRequest::class );
		$usecase->addSubscription( $request );
	}

	public function testGivenInvalidData_requestWillNotBeStored() {
		$this->validator->method( 'validate' )->willReturn( false );
		$this->validator->method( 'getValidationErrors' )->willReturn( [] );
		$this->repo->expects( $this->never() )->method( 'store' );
		$usecase = new AddSubscriptionUseCase( $this->repo, $this->validator );
		$request = $this->getMock( SubscriptionRequest::class );
		$usecase->addSubscription( $request );
	}
}