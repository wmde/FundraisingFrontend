<?php

namespace WMDE\Fundraising\Frontend\Presenters;

use WMDE\Fundraising\Frontend\Domain\Comment;
use WMDE\Fundraising\Frontend\TwigTemplate;
use WMDE\Fundraising\Frontend\UseCases\ListComments\CommentList;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class CommentListRssPresenter {

	private $template;

	public function __construct( TwigTemplate $template ) {
		$this->template = $template;
	}

	public function present( CommentList $commentList ): string {
		return $this->template->render( [
			'rssLink' => 'TODO',
			'rssPublicationDate' => $this->getPublicationTime( $commentList ),
			'comments' => $this->getCommentsViewModel( $commentList ),
		] );
	}

	private function getCommentsViewModel( CommentList $commentList ) {
		return array_map(
			function( Comment $comment ) {
				return [
					'amount' => $comment->getDonationAmount(),
					'author' => $comment->getAuthorName(),
					'text' => $comment->getCommentText(),
					'publicationDate' => $comment->getPostingTime()->format( 'r' ),
					'link' => 'TODO',
				];
			},
			$commentList->toArray()
		);
	}

	private function getPublicationTime( CommentList $commentList ) {
		if ( !array_key_exists( 0, $commentList->toArray() ) ) {
			return '';
		}

		return $commentList->toArray()[0]->getPostingTime()->format( 'r' );
	}


}
