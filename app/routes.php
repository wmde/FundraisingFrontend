<?php

/**
 * These variables need to be in scope when this file is included:
 *
 * @var \Silex\Application $app
 * @var \WMDE\Fundraising\Frontend\Factories\FunFunFactory $ffFactory
 */

declare( strict_types = 1 );

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Validation;
use WMDE\Euro\Euro;
use WMDE\Fundraising\Frontend\App\Controllers\UpdateDonorController;
use WMDE\Fundraising\Frontend\App\Controllers\ValidateDonorController;
use WMDE\Fundraising\Frontend\App\Controllers\ValidationController;
use WMDE\Fundraising\Frontend\App\RouteHandlers\AddDonationHandler;
use WMDE\Fundraising\Frontend\App\RouteHandlers\AddSubscriptionHandler;
use WMDE\Fundraising\Frontend\App\RouteHandlers\ApplyForMembershipHandler;
use WMDE\Fundraising\Frontend\App\RouteHandlers\PayPalNotificationHandler;
use WMDE\Fundraising\Frontend\App\RouteHandlers\PayPalNotificationHandlerForMembershipFee;
use WMDE\Fundraising\Frontend\App\RouteHandlers\RouteRedirectionHandler;
use WMDE\Fundraising\Frontend\App\RouteHandlers\ShowDonationConfirmationHandler;
use WMDE\Fundraising\Frontend\App\RouteHandlers\SofortNotificationHandler;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentRequest;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationResponse;
use WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardPaymentHandlerException;
use WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardPaymentNotificationRequest;
use WMDE\Fundraising\DonationContext\UseCases\GetDonation\GetDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\ListComments\CommentListingRequest;
use WMDE\Fundraising\Frontend\Infrastructure\AmountParser;
use WMDE\Fundraising\Frontend\Infrastructure\Cache\AuthorizedCachePurger;
use WMDE\Fundraising\MembershipContext\UseCases\ApplyForMembership\MembershipFeeValidator;
use WMDE\Fundraising\MembershipContext\UseCases\CancelMembershipApplication\CancellationRequest;
use WMDE\Fundraising\MembershipContext\UseCases\ShowApplicationConfirmation\ShowAppConfirmationRequest;
use WMDE\Fundraising\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\PaymentContext\UseCases\GenerateIban\GenerateIbanRequest;
use WMDE\Fundraising\Frontend\Presentation\DonationMembershipApplicationAdapter;
use WMDE\Fundraising\Frontend\UseCases\GetInTouch\GetInTouchRequest;
use WMDE\Fundraising\Frontend\Validation\ConstraintViolationListMapper;

$app->post(
	'validate-email', ValidationController::class . '::validateEmail'
);

$app->post(
	'validate-payment-data', ValidationController::class . '::validateDonationPayment'
);

$app->post(
	'validate-address', // Validates donor information. This route is named badly.
	ValidateDonorController::class . '::validate'
);

$app->post(
	'validate-donation-amount',
	function( Request $httpRequest ) use ( $app, $ffFactory ) {

		$constraint = new Collection( [
			'allowExtraFields' => false,
			'fields' => [
				'amount' => $ffFactory->newDonationAmountConstraint()
			]
		] );

		$violations = Validation::createValidator()->validate( $httpRequest->request->all(), $constraint );

		if ( $violations->count() > 0 ) {
			$mapper = new ConstraintViolationListMapper();
			return $app->json( [ 'status' => 'ERR', 'messages' => $mapper->map( $violations ) ] );
		}

		return $app->json( [ 'status' => 'OK' ] );
	}
);

$app->post(
	'validate-fee',
	function( Request $httpRequest ) use ( $app, $ffFactory ) {
		$validator = new MembershipFeeValidator();
		$result = $validator->validate(
			str_replace( ',', '.', $httpRequest->request->get( 'amount', '' ) ),
			(int) $httpRequest->request->get( 'paymentIntervalInMonths', '0' ),
			$httpRequest->request->get( 'addressType', '' )
		);

		if ( $result->isSuccessful() ) {
			return $app->json( [ 'status' => 'OK' ] );
		} else {
			$errors = $result->getViolations();
			return $app->json( [ 'status' => 'ERR', 'messages' => $errors ] );
		}
	}
);

$app->get(
	'list-comments.json',
	function( Request $request ) use ( $app, $ffFactory ) {
		$response = $app->json(
			$ffFactory->newCommentListJsonPresenter()->present(
				$ffFactory->newListCommentsUseCase()->listComments(
					new CommentListingRequest(
						(int)$request->query->get( 'n', '10' ),
						(int)$request->query->get( 'page', '1' )
					)
				)
			)
		);

		if ( $request->query->get( 'f' ) ) {
			$response->setCallback( $request->query->get( 'f' ) );
		}

		return $response;
	}
);

$app->get(
	'list-comments.rss',
	function() use ( $app, $ffFactory ) {
		$rss = $ffFactory->newCommentListRssPresenter()->present(
			$ffFactory->newListCommentsUseCase()->listComments(
				new CommentListingRequest( 100, CommentListingRequest::FIRST_PAGE )
			)
		);

		return new Response(
			$rss,
			200,
			[
				'Content-Type' => 'text/xml; charset=utf-8',
				'X-Moz-Is-Feed' => '1'
			]
		);
	}
)->bind( 'list-comments.rss' );

$app->get(
	'list-comments.html',
	function( Request $request ) use ( $app, $ffFactory ) {
		return new Response(
			$ffFactory->newCommentListHtmlPresenter()->present(
				$ffFactory->newListCommentsUseCase()->listComments(
					new CommentListingRequest(
						10,
						(int)$request->query->get( 'page', '1' )
					)
				),
				(int)$request->query->get( 'page', '1' )
			)
		);
	}
)->bind( 'list-comments.html' );

$app->get(
	'page/{pageName}',
	function( Application $app, $pageName ) use ( $ffFactory ) {
		return ( new \WMDE\Fundraising\Frontend\App\RouteHandlers\PageDisplayHandler( $ffFactory, $app ) )
			->handle( $pageName );
	}
)
->bind( 'page' );

// Form for this is provided by route page/Subscription_Form
$app->match(
	'contact/subscribe',
	function( Application $app, Request $request ) use ( $ffFactory ) {
		return ( new AddSubscriptionHandler( $ffFactory, $app ) )
			->handle( $request );
	}
)
->method( 'GET|POST' )
->bind( 'subscribe' );

$app->get( 'contact/confirm-subscription/{confirmationCode}', function ( $confirmationCode ) use ( $ffFactory ) {
	$useCase = $ffFactory->newConfirmSubscriptionUseCase();
	$response = $useCase->confirmSubscription( $confirmationCode );
	return $ffFactory->newConfirmSubscriptionHtmlPresenter()->present( $response );
} )
->assert( 'confirmationCode', '^[0-9a-f]+$' )
->bind( 'confirm-subscription' );

$app->get(
	'check-iban',
	function( Request $request ) use ( $app, $ffFactory ) {
		$useCase = $ffFactory->newCheckIbanUseCase();
		$checkIbanResponse = $useCase->checkIban( new Iban( $request->query->get( 'iban', '' ) ) );
		return $app->json( $ffFactory->newIbanPresenter()->present( $checkIbanResponse ) );
	}
);

$app->get(
	'generate-iban',
	function( Request $request ) use ( $app, $ffFactory ) {
		$generateIbanRequest = new GenerateIbanRequest(
			$request->query->get( 'accountNumber', '' ),
			$request->query->get( 'bankCode', '' )
		);

		$generateIbanResponse = $ffFactory->newGenerateIbanUseCase()->generateIban( $generateIbanRequest );
		return $app->json( $ffFactory->newIbanPresenter()->present( $generateIbanResponse ) );
	}
);

$app->post(
	'add-comment',
	function( Request $request ) use ( $app, $ffFactory ) {
		$addCommentRequest = new AddCommentRequest();
		$addCommentRequest->setCommentText( trim( $request->request->get( 'comment', '' ) ) );
		$addCommentRequest->setIsPublic( $request->request->get( 'public', '0' ) === '1' );
		$addCommentRequest->setDonationId( (int)$request->request->get( 'donationId', '' ) );

		if ( $request->request->get( 'isAnonymous', '0' ) === '1' ) {
			$addCommentRequest->setIsAnonymous();
		}
		else {
			$addCommentRequest->setIsNamed();
		}

		$addCommentRequest->freeze()->assertNoNullFields();

		$updateToken = $request->request->get( 'updateToken', '' );

		if ( $updateToken === '' ) {
			return $app->json( [
				'status' => 'ERR',
				'message' => $ffFactory->getTranslator()->trans( 'comment_failure_access_denied' ),
			] );
		}

		$response = $ffFactory->newAddCommentUseCase( $updateToken )->addComment( $addCommentRequest );

		if ( $response->isSuccessful() ) {
			return $app->json( [
				'status' => 'OK',
				'message' => $ffFactory->getTranslator()->trans( $response->getSuccessMessage() ),
			] );
		}

		return $app->json( [
			'status' => 'ERR',
			'message' => $ffFactory->getTranslator()->trans( $response->getErrorMessage() ),
		] );
	}
)->bind( 'PostComment' );

$app->get(
	'add-comment',
	function( Request $request ) use ( $app, $ffFactory ) {
		$template = $ffFactory->getLayoutTemplate(
			'Donation_Comment.html.twig'
		);

		return new Response(
			$template->render(
				[
					'donationId' => (int)$request->query->get( 'donationId', '' ),
					'updateToken' => $request->query->get( 'updateToken', '' ),
					'cancelUrl' => $app['url_generator']->generate(
						'show-donation-confirmation',
						[
							'id' => (int)$request->query->get( 'donationId', '' ),
							'accessToken' => $request->query->get( 'accessToken', '' )
						]
					)
				]
			)
		);
	}
)->bind( 'AddCommentPage' );

$app->post(
	'contact/get-in-touch',
	function( Request $request ) use ( $app, $ffFactory ) {
		$contactFormRequest = new GetInTouchRequest(
			$request->get( 'firstname', '' ),
			$request->get( 'lastname', '' ),
			$request->get( 'email', '' ),
			$request->get( 'donationNumber', ''),
			$request->get( 'subject', '' ),
			$request->get( 'category', '' ),
			$request->get( 'messageBody', '' )
		);

		$contactFormResponse = $ffFactory->newGetInTouchUseCase()->processContactRequest( $contactFormRequest );
		if ( $contactFormResponse->isSuccessful() ) {
			return $app->redirect( $app['url_generator']->generate( 'page', [ 'pageName' => 'Kontakt_Bestaetigung' ] ) );
		}

		return $ffFactory->newGetInTouchHtmlPresenter()->present( $contactFormResponse, $request->request->all() );
	}
);

$app->get(
	'contact/get-in-touch',
	function() use ( $app, $ffFactory ) {
		return $ffFactory->getLayoutTemplate( 'contact_form.html.twig' )->render( [ 'contact_categories' => $ffFactory->getGetInTouchCategories() ] );
	}
)->bind('contact');

$app->post(
	'donation/cancel',
	function( Request $request ) use ( $app, $ffFactory ) {
		$cancellationRequest = new CancelDonationRequest(
			(int)$request->request->get( 'sid', '' )
		);

		$responseModel = $ffFactory->newCancelDonationUseCase( $request->request->get( 'utoken', '' ) )
			->cancelDonation( $cancellationRequest );

		$httpResponse = new Response( $ffFactory->newCancelDonationHtmlPresenter()->present( $responseModel ) );
		if ( $responseModel->cancellationSucceeded() ) {
			$httpResponse->headers->clearCookie( 'donation_timestamp' );
		}

		return $httpResponse;
	}
);

$app->post(
	'donation/add',
	function( Application $app, Request $request ) use ( $ffFactory ) {
		return ( new AddDonationHandler( $ffFactory, $app ) )
			->handle( $request );
	}
);

// Show a donation form with pre-filled payment values, e.g. when coming from a banner
$app->get( 'donation/new', function ( Application $app, Request $request ) use ( $ffFactory ) {
	$app['session']->set( 'piwikTracking', array_filter(
			[
				'paymentType' => $request->get( 'zahlweise', '' ),
				'paymentAmount' => $request->get( 'betrag', '' ),
				'paymentInterval' => $request->get( 'periode', '' )
			],
			function ( string $value ) {
				return $value !== '' && strlen( $value ) < 20;
			} )
	);

	try {
		$amount = Euro::newFromFloat( ( new AmountParser( 'en_EN' ) )->parseAsFloat(
			$request->get( 'betrag_auswahl', $request->get( 'amountGiven', '' ) ) )
		);
	} catch ( \InvalidArgumentException $ex ) {
		$amount = Euro::newFromCents( 0 );
	}
	$validationResult = $ffFactory->newPaymentDataValidator()->validate( $amount, (string) $request->get( 'zahlweise', '' ) );

	$trackingInfo = new DonationTrackingInfo();
	$trackingInfo->setTotalImpressionCount( intval( $request->get( 'impCount' ) ) );
	$trackingInfo->setSingleBannerImpressionCount( intval( $request->get( 'bImpCount' ) ) );

	// TODO: don't we want to use newDonationFormViolationPresenter when !$validationResult->isSuccessful()?

	return new Response(
		$ffFactory->newDonationFormPresenter()->present(
			$amount,
			$request->get( 'zahlweise', '' ),
			intval( $request->get( 'periode', 0 ) ),
			$validationResult->isSuccessful(),
			$trackingInfo,
			$request->get( 'addressType', 'person' )
		)
	);
} )->method( 'POST|GET' );

$app->post(
	'donation/update', UpdateDonorController::class . '::updateDonor'
);

$app->post(
	'apply-for-membership',
	function( Application $app, Request $httpRequest ) use ( $ffFactory ) {
		return ( new ApplyForMembershipHandler( $ffFactory, $app ) )->handle( $httpRequest );
	}
);

$app->get(
	'apply-for-membership',
	function( Request $request ) use ( $ffFactory ) {
		$params = [];

		if ( $request->query->get('type' ) === 'sustaining' ) {
			$params['showMembershipTypeOption'] = false ;
		}

		try {
			$useCase = $ffFactory->newGetDonationUseCase( $request->query->get( 'donationAccessToken', '' ) );
			$responseModel = $useCase->showConfirmation( new GetDonationRequest(
				$request->query->getInt( 'donationId' )
			) );

			if ( $responseModel->accessIsPermitted() ) {
				$adapter = new DonationMembershipApplicationAdapter();
				$params['initialFormValues'] = $adapter->getInitialMembershipFormValues( $responseModel->getDonation() );
				$params['initialValidationResult'] = $adapter->getInitialValidationState( $responseModel->getDonation() );
			}
		} catch ( Exception $e ) {
		}

		return $ffFactory->getMembershipApplicationFormTemplate()->render( $params );
	}
);

$app->get(
	'show-membership-confirmation',
	function( Request $request ) use ( $ffFactory ) {
		$presenter = $ffFactory->newMembershipApplicationConfirmationHtmlPresenter();

		$useCase = $ffFactory->newMembershipApplicationConfirmationUseCase(
			$presenter,
			$request->query->get( 'accessToken', '' )
		);

		$useCase->showConfirmation( new ShowAppConfirmationRequest( (int)$request->query->get( 'id', 0 ) ) );

		return $presenter->getHtml();
	}
)->bind( 'show-membership-confirmation' );

$app->get(
	'cancel-membership-application',
	function( Request $request ) use ( $ffFactory ) {
		$cancellationRequest = new CancellationRequest(
			(int)$request->query->get( 'id', '' )
		);

		return $ffFactory->newCancelMembershipApplicationHtmlPresenter()->present(
			$ffFactory->newCancelMembershipApplicationUseCase( $request->query->get( 'updateToken', '' ) )
				->cancelApplication( $cancellationRequest )
		);
	}
);

$app->match(
	'show-donation-confirmation',
	function( Application $app, Request $request ) use ( $ffFactory ) {
		return ( new ShowDonationConfirmationHandler( $ffFactory ) )->handle(
			$request,
			$app['session']->get( 'piwikTracking', [] )
		);
	}
)->bind( 'show-donation-confirmation' )
->method( 'GET|POST' );

$app->post(
	'handle-paypal-payment-notification',
	function ( Request $request ) use ( $ffFactory ) {
		return ( new PayPalNotificationHandler( $ffFactory ) )->handle( $request );
	}
);

$app->post(
	'sofort-payment-notification',
	function ( Request $request ) use ( $ffFactory ) {
		return ( new SofortNotificationHandler( $ffFactory ) )->handle( $request );
	}
);

$app->get(
	'handle-creditcard-payment-notification',
	function ( Request $request ) use ( $ffFactory ) {
		try {
			$ffFactory->newCreditCardNotificationUseCase( $request->query->get( 'utoken', '' ) )
				->handleNotification(
					( new CreditCardPaymentNotificationRequest() )
						->setTransactionId( $request->query->get( 'transactionId', '' ) )
						->setDonationId( (int)$request->query->get( 'donation_id', '' ) )
						->setAmount( Euro::newFromCents( (int)$request->query->get( 'amount' ) ) )
						->setCustomerId( $request->query->get( 'customerId', '' ) )
						->setSessionId( $request->query->get( 'sessionId', '' ) )
						->setAuthId( $request->query->get(  'auth', '' ) )
						->setTitle( $request->query->get( 'title', '' ) )
						->setCountry( $request->query->get( 'country', '' ) )
						->setCurrency( $request->query->get( 'currency', '' ) )
				);

			$response = CreditCardNotificationResponse::newSuccessResponse(
				(int)$request->query->get( 'donation_id', '' ),
				$request->query->get( 'token', '' )
 			);
		} catch ( CreditCardPaymentHandlerException $e ) {
			$response = CreditCardNotificationResponse::newFailureResponse( $e->getMessage() );
		}

		return new Response( $ffFactory->newCreditCardNotificationPresenter()->present( $response ) );
	}
);

$app->get(
	'donation-accepted',
	function( Request $request ) use ( $app, $ffFactory ) {

		$eventHandler = $ffFactory->newDonationAcceptedEventHandler( $request->query->get( 'update_token', '' ) );
		$result = $eventHandler->onDonationAccepted( (int)$request->query->get( 'donation_id', '' ) );

		return $app->json(
			$result === null ? [ 'status' => 'OK' ] : [ 'status' => 'ERR', 'message' => $result ]
		);
	}
);

$app->post(
	'handle-paypal-membership-fee-payments',
	function ( Request $request ) use ( $ffFactory ) {
		return ( new PayPalNotificationHandlerForMembershipFee( $ffFactory ) )->handle( $request->request );
	}
);

$app->get( '/', function ( Application $app, Request $request ) {
	return $app->handle(
		Request::create(
			'/donation/new',
			'GET',
			$request->query->all(),
			$request->cookies->all(),
			[],
			$request->server->all()
		),
		HttpKernelInterface::SUB_REQUEST
	);
} )->bind( '/' );

// TODO Figure out how to rewrite with Nginx
// See https://serverfault.com/questions/805881/nginx-populate-request-uri-with-rewritten-url
$app->post(
	'/spenden/paypal_handler.php',
	function ( Request $request ) use ( $ffFactory ) {
		return ( new PayPalNotificationHandler( $ffFactory ) )->handle( $request );
	}
);

// redirect display page requests from old URLs
$app->get( '/spenden/{page}', function( Application $app, Request $request, string $page ) {
	// Poor man's rewrite until someone has figured out how to do this with Nginx without breaking REQUEST_URI
	// See https://serverfault.com/questions/805881/nginx-populate-request-uri-with-rewritten-url
	switch ( $page ) {
		case 'Mitgliedschaft':
			return ( new RouteRedirectionHandler( $app, $request->getQueryString() ) )->handle( '/page/Membership_Application' );
		default:
			return ( new RouteRedirectionHandler( $app, $request->getQueryString() ) )->handle( '/page/' . $page );
	}
} )->assert( 'page', '[a-zA-Z_\-\s\x7f-\xff]+' );

// redirect different formats of comment lists
$app->get( '/spenden/{outputFormat}.php', function( Application $app, Request $request, string $outputFormat ) {
	return ( new RouteRedirectionHandler( $app, $request->getQueryString() ) )->handle(
		'/list-comments.' . ( $outputFormat === 'list' ? 'html' : $outputFormat )
	);
} )->assert( 'outputFormat', 'list|rss|json' );

// redirect all other calls to default route
$app->get( '/spenden{page}', function( Application $app, Request $request ) {
	return ( new RouteRedirectionHandler( $app, $request->getQueryString() ) )->handle( '/' );
} )->assert( 'page', '/?([a-z]+\.php)?' );

$app->get( '/purge-cache', function( Request $request ) use ( $ffFactory ) {
	$response = $ffFactory->newAuthorizedCachePurger()->purgeCache( $request->query->get( 'secret', '' ) );

	return new Response(
		[
			AuthorizedCachePurger::RESULT_SUCCESS => 'SUCCESS',
			AuthorizedCachePurger::RESULT_ERROR => 'ERROR',
			AuthorizedCachePurger::RESULT_ACCESS_DENIED=> 'ACCESS DENIED'
		][$response]
	);
} );

$app->get( 'status', function() {
	return 'Status: OK (Online)';
} );

return $app;
