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
use WMDE\Euro\Euro;
use WMDE\Fundraising\Frontend\App\RouteHandlers\AddDonationHandler;
use WMDE\Fundraising\Frontend\App\RouteHandlers\ApplyForMembershipHandler;
use WMDE\Fundraising\Frontend\App\RouteHandlers\PayPalNotificationHandler;
use WMDE\Fundraising\Frontend\App\RouteHandlers\RouteRedirectionHandler;
use WMDE\Fundraising\Frontend\App\RouteHandlers\ShowDonationConfirmationHandler;
use WMDE\Fundraising\Frontend\ApplicationContext\UseCases\GetInTouch\GetInTouchRequest;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Model\DonorAddress;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Model\DonorName;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddComment\AddCommentRequest;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\CancelDonation\CancelDonationRequest;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationResponse;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardPaymentHandlerException;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardPaymentNotificationRequest;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\ListComments\CommentListingRequest;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Infrastructure\AmountParser;
use WMDE\Fundraising\Frontend\Infrastructure\Cache\AuthorizedCachePurger;
use WMDE\Fundraising\Frontend\MembershipContext\UseCases\CancelMembershipApplication\CancellationRequest;
use WMDE\Fundraising\Frontend\MembershipContext\UseCases\ShowMembershipApplicationConfirmation\ShowMembershipAppConfirmationRequest;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\Frontend\PaymentContext\UseCases\GenerateIban\GenerateIbanRequest;
use WMDE\Fundraising\Frontend\SubscriptionContext\UseCases\AddSubscription\SubscriptionRequest;
use WMDE\Fundraising\Frontend\Validation\MembershipFeeValidator;

$app->before( function( Request $request ) {
	foreach ( [ $request->request, $request->query ] as $parameterBag ) {
		foreach ( $parameterBag->keys() as $key ) {
			if ( is_string( $parameterBag->get( $key ) ) ) {
				$parameterBag->set( $key, trim( $parameterBag->get( $key ) ) );
			}
		}
	}
}, Application::EARLY_EVENT );

$app->post(
	'validate-email',
	function( Request $request ) use ( $app, $ffFactory ) {
		$validationResult = $ffFactory->getEmailValidator()->validate( $request->request->get( 'email', '' ) );
		return $app->json( [ 'status' => $validationResult->isSuccessful() ? 'OK' : 'ERR' ] );
	}
);

$app->post(
	'validate-payment-data',
	function( Request $request ) use ( $app, $ffFactory ) {

		$amount = (float) $ffFactory->newDecimalNumberFormatter()->parse( $request->get( 'amount', '0' ) );
		$validator = $ffFactory->newPaymentDataValidator();
		$validationResult = $validator->validate( $amount, (string) $request->get( 'paymentType', '' ) );

		if ( $validationResult->isSuccessful() ) {
			return $app->json( [ 'status' => 'OK' ] );
		} else {
			$errors = [];
			foreach( $validationResult->getViolations() as $violation ) {
				$errors[ $violation->getSource() ] = $ffFactory->getTranslator()->trans( $violation->getMessageIdentifier() );
			}
			return $app->json( [ 'status' => 'ERR', 'messages' => $errors ] );
		}
	}
);

$app->post(
	'validate-address',
	function( Request $request ) use ( $app, $ffFactory ) {
		$routeHandler = new class() {

			public function handle( FunFunFactory $ffFactory, Application $app, Request $request ) {
				if ( $request->get( 'adressType', '' ) === 'anonym' ) {
					return $app->json( [ 'status' => 'OK' ] );
				}

				$personalInfo = $this->getPersonalInfoFromRequest( $request );
				$personalInfoValidator = $ffFactory->newPersonalInfoValidator();
				$validationResult = $personalInfoValidator->validate( $personalInfo );

				if ( $validationResult->isSuccessful() ) {
					return $app->json( [ 'status' => 'OK' ] );
				} else {
					$errors = [];
					foreach( $validationResult->getViolations() as $violation ) {
						$errors[$violation->getSource()] = $ffFactory->getTranslator()->trans( $violation->getMessageIdentifier() );
					}
					return $app->json( [ 'status' => 'ERR', 'messages' => $errors ] );
				}
			}

			private function getPersonalInfoFromRequest( Request $request ): Donor {
				return new Donor(
					$this->getNameFromRequest( $request ),
					$this->getPhysicalAddressFromRequest( $request ),
					$request->get( 'email', '' )
				);
			}

			private function getPhysicalAddressFromRequest( Request $request ): DonorAddress {
				$address = new DonorAddress();

				$address->setStreetAddress( $request->get( 'street', '' ) );
				$address->setPostalCode( $request->get( 'postcode', '' ) );
				$address->setCity( $request->get( 'city', '' ) );
				$address->setCountryCode( $request->get( 'country', '' ) );

				return $address->freeze()->assertNoNullFields();
			}

			private function getNameFromRequest( Request $request ): DonorName {
				$name = $request->get( 'addressType', '' ) === 'firma'
					? DonorName::newCompanyName() : DonorName::newPrivatePersonName();

				$name->setSalutation( $request->get( 'salutation', '' ) );
				$name->setTitle( $request->get( 'title', '' ) );
				$name->setCompanyName( $request->get( 'companyName', '' ) );
				$name->setFirstName( $request->get( 'firstName', '' ) );
				$name->setLastName( $request->get( 'lastName', '' ) );

				return $name->freeze()->assertNoNullFields();
			}
		};

		return $routeHandler->handle( $ffFactory, $app, $request );
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
			return $app->json( [ 'status' => 'ERR', 'message' => implode( "\n", $errors ) ] );
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
);

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
);

$app->get(
	'page/{pageName}',
	function( $pageName ) use ( $ffFactory ) {
		$templateExists = $ffFactory->getTemplateNameValidator()->validate( $pageName )->isSuccessful();

		return $ffFactory->getLayoutTemplate( 'DisplayPageLayout.twig' )->render( [
			'main_template' => $templateExists ? $pageName : '404message.html.twig',
		] );
	}
)
->bind( 'page' );

// Form for this is provided by route page/Subscription_Form
$app->post(
	'contact/subscribe',
	function( Application $app, Request $request ) use ( $ffFactory ) {
		$useCase = $ffFactory->newAddSubscriptionUseCase();

		$subscriptionRequest = new SubscriptionRequest();
		$subscriptionRequest->setAddress( $request->get( 'address', '' ) );
		$subscriptionRequest->setCity( $request->get( 'city', '' ) );
		$subscriptionRequest->setPostcode( $request->get( 'postcode', '' ) );

		$subscriptionRequest->setFirstName( $request->get( 'firstName', '' ) );
		$subscriptionRequest->setLastName( $request->get( 'lastName', '' ) );
		$subscriptionRequest->setSalutation( $request->get( 'salutation', '' ) );
		$subscriptionRequest->setTitle( $request->get( 'title', '' ) );

		$subscriptionRequest->setEmail( $request->get( 'email', '' ) );

		$subscriptionRequest->setWikiloginFromValues( [
			$request->request->get( 'wikilogin' ),
			$request->cookies->get( 'spenden_wikilogin' ),
		] );

		$responseModel = $useCase->addSubscription( $subscriptionRequest );
		if ( $app['request_stack.is_json'] ) {
			return $app->json( $ffFactory->newAddSubscriptionJSONPresenter()->present( $responseModel ) );
		}
		if ( $responseModel->isSuccessful() ) {
			if ( $responseModel->needsModeration() ) {
				return $app->redirect( $app['url_generator']->generate('page', [ 'pageName' => 'Subscription_Moderation' ] ) );
			}
			return $app->redirect( $app['url_generator']->generate('page', [ 'pageName' => 'Subscription_Success' ] ) );
		}
		return $ffFactory->newAddSubscriptionHTMLPresenter()->present( $responseModel, $request->request->all() );
	}
)
->bind( 'subscribe' );

$app->get( 'contact/confirm-subscription/{confirmationCode}', function ( $confirmationCode ) use ( $ffFactory ) {
	$useCase = $ffFactory->newConfirmSubscriptionUseCase();
	$response = $useCase->confirmSubscription( $confirmationCode );
	return $ffFactory->newConfirmSubscriptionHtmlPresenter()->present( $response );
} )
->assert( 'confirmationCode', '^[0-9a-f]+$' );

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
		$addCommentRequest->setCommentText( trim( $request->request->get( 'kommentar', '' ) ) );
		$addCommentRequest->setIsPublic( $request->request->get( 'public', '0' ) === '1' );
		$addCommentRequest->setAuthorDisplayName( trim( $request->request->get( 'eintrag', '' ) ) );
		$addCommentRequest->setDonationId( (int)$request->request->get( 'sid', '' ) );
		$addCommentRequest->freeze()->assertNoNullFields();

		$updateToken = $request->request->get( 'utoken', '' );

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
);

$app->post(
	'contact/get-in-touch',
	function( Request $request ) use ( $app, $ffFactory ) {
		$contactFormRequest = new GetInTouchRequest(
			$request->get( 'firstname', '' ),
			$request->get( 'lastname', '' ),
			$request->get( 'email', '' ),
			$request->get( 'subject', '' ),
			$request->get( 'messageBody', '' )
		);

		$contactFormResponse = $ffFactory->newGetInTouchUseCase()->processContactRequest( $contactFormRequest );
		if ( $contactFormResponse->isSuccessful() ) {
			return $app->redirect( $app['url_generator']->generate( 'page', [ 'pageName' => 'KontaktBestaetigung' ] ) );
		}
		return $ffFactory->newGetInTouchHTMLPresenter()->present( $contactFormResponse, $request->request->all() );
	}
);

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
$app->get( 'donation/new', function ( Request $request ) use ( $ffFactory ) {
	try {
		$amount = Euro::newFromFloat( ( new AmountParser( 'de_DE' ) )->parseAsFloat(
			$request->get( 'betrag_auswahl', $request->get( 'betrag', '' ) ) )
		);
	} catch ( \InvalidArgumentException $ex ) {
		$amount = Euro::newFromCents( 0 );
	}
	$validationResult = $ffFactory->newPaymentDataValidator()->validate( $amount, (string) $request->get( 'zahlweise', '' ) );

	// TODO: don't we want to use newDonationFormViolationPresenter when !$validationResult->isSuccessful()?

	return new Response(
		$ffFactory->newDonationFormPresenter()->present(
			$amount,
			$request->get( 'zahlweise', '' ),
			intval( $request->get( 'periode', 0 ) ),
			$validationResult->isSuccessful()
		)
	);
} )->method( 'POST|GET' );

$app->post(
	'apply-for-membership',
	function( Application $app, Request $httpRequest ) use ( $ffFactory ) {
		return ( new ApplyForMembershipHandler( $ffFactory, $app ) )->handle( $httpRequest );
	}
);

$app->get(
	'show-membership-confirmation',
	function( Request $request ) use ( $ffFactory ) {
		$confirmationRequest = new ShowMembershipAppConfirmationRequest( (int)$request->query->get( 'id', 0 ) );

		return $ffFactory->newMembershipApplicationConfirmationHtmlPresenter()->present(
			$ffFactory->newMembershipApplicationConfirmationUseCase( $request->query->get( 'token', '' ) )
				->showConfirmation( $confirmationRequest )
		);
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
)->bind( 'show-donation-confirmation' )->method( 'GET|POST' );

$app->post(
	'handle-paypal-payment-notification',
	function ( Request $request ) use ( $ffFactory ) {
		return ( new PayPalNotificationHandler( $ffFactory ) )->handle( $request );
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

$app->get( '/', function ( Application $app, Request $request ) {
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
} );

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
			return ( new RouteRedirectionHandler( $app, $request->getQueryString() ) )->handle( '/page/MembershipApplication' );
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
