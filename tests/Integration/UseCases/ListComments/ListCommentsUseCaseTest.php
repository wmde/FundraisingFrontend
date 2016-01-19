<?php

declare(strict_types=1);

namespace WMDE\Fundraising\Frontend\Tests\Integration\UseCases\ListComments;

use WMDE\Fundraising\Frontend\Domain\Comment;
use WMDE\Fundraising\Frontend\Domain\InMemoryCommentRepository;
use WMDE\Fundraising\Frontend\UseCases\ListComments\CommentListItem;
use WMDE\Fundraising\Frontend\UseCases\ListComments\CommentList;
use WMDE\Fundraising\Frontend\UseCases\ListComments\CommentListingRequest;
use WMDE\Fundraising\Frontend\UseCases\ListComments\CommentListPresenter;
use WMDE\Fundraising\Frontend\UseCases\ListComments\ListCommentsUseCase;

/**
 * @covers WMDE\Fundraising\Frontend\UseCases\ListComments\ListCommentsUseCase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ListCommentsUseCaseTest extends \PHPUnit_Framework_TestCase {

	public function testWhenThereAreNoComments_anEmptyListIsPresented() {
		$useCase = new ListCommentsUseCase( new InMemoryCommentRepository() );

		$this->assertEquals(
			new CommentList(),
			$useCase->listComments( new CommentListingRequest( 10 ) )
		);
	}

	public function testWhenThereAreLessCommentsThanTheLimit_theyAreAllPresented() {
		$useCase = new ListCommentsUseCase( new InMemoryCommentRepository(
				new Comment( 'name0', 'comment', 42, 0 ),
				new Comment( 'name1', 'comment', 42, 0 ),
				new Comment( 'name2', 'comment', 42, 0 )
		) );

		$this->assertEquals(
			new CommentList(
				new CommentListItem( 'name0', 'comment', 42, 0 ),
				new CommentListItem( 'name1', 'comment', 42, 0 ),
				new CommentListItem( 'name2', 'comment', 42, 0 )
			),
			$useCase->listComments( new CommentListingRequest( 10 ) )
		);
	}

	public function testWhenThereAreMoreCommentsThanTheLimit_onlyTheFirstFewArePresented() {
		$useCase = new ListCommentsUseCase( new InMemoryCommentRepository(
			new Comment( 'name0', 'comment', 42, 0 ),
			new Comment( 'name1', 'comment', 42, 0 ),
			new Comment( 'name2', 'comment', 42, 0 ),
			new Comment( 'name3', 'comment', 42, 0 )
		) );

		$this->assertEquals(
			new CommentList(
				new CommentListItem( 'name0', 'comment', 42, 0 ),
				new CommentListItem( 'name1', 'comment', 42, 0 )
			),
			$useCase->listComments( new CommentListingRequest( 2 ) )
		);
	}

}
