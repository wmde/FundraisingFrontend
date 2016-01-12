<?php

/**
 * These variables need to be in scope when this file is included:
 *
 * @var \Silex\Application $app
 * @var \WMDE\Fundraising\Frontend\FunFunFactory $ffFactory
 */

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use WMDE\Fundraising\Frontend\UseCases\DisplayPage\PageDisplayRequest;
use WMDE\Fundraising\Frontend\UseCases\ListComments\CommentListingRequest;
use WMDE\Fundraising\Frontend\UseCases\AddSubscription\SubscriptionRequest;

$app->get(
	'validate-email',
	function( Request $request ) use ( $app, $ffFactory ) {
		$useCase = $ffFactory->newValidateEmailUseCase();
		$responseModel = $useCase->validateEmail( $request->get( 'email', '' ) );

		// Presenter code:
		return $app->json( [ 'status' => $responseModel ? 'OK' : 'ERR' ] );
	}
);

$app->get(
	'list.rss',
	function( Request $request ) use ( $app, $ffFactory ) {
		$useCase = $ffFactory->newListCommentsUseCase();
		$responseModel = $useCase->listComments( new CommentListingRequest( 10 /* TODO: get real limit */ ) );

		// Presenter code:
		return 'TODO';
	}
);

$app->get(
	'page/{pageName}',
	function( Application $app, $pageName ) use ( $ffFactory ) {
		return $ffFactory->newDisplayPageUseCase()->getPage( new PageDisplayRequest( $pageName ) );
	}
);

$app->post(
	'contact/subscribe',
	function( Request $request ) use ( $ffFactory ) {
		$useCase = $ffFactory->newAddSubscriptionUseCase();
		// TODO: Create SubscriptionRequest from $request params
		$subscriptionRequest = new SubscriptionRequest();
		$responseModel = $useCase->addSubscription( $subscriptionRequest );
		// TODO forward/dispatch to matching 'page/name' route, depending on $responseModel->getType();
		return 'TODO';
	}
);

return $app;