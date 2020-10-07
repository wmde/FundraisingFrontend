<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\App;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentRequest;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\ListComments\CommentListingRequest;
use WMDE\Fundraising\Frontend\App\Controllers\AddDonationController;
use WMDE\Fundraising\Frontend\App\Controllers\AddSubscriptionController;
use WMDE\Fundraising\Frontend\App\Controllers\ApplyForMembershipController;
use WMDE\Fundraising\Frontend\App\Controllers\CreditCardPaymentNotificationController;
use WMDE\Fundraising\Frontend\App\Controllers\IbanController;
use WMDE\Fundraising\Frontend\App\Controllers\NewDonationController;
use WMDE\Fundraising\Frontend\App\Controllers\PageDisplayController;
use WMDE\Fundraising\Frontend\App\Controllers\PaypalNotificationController;
use WMDE\Fundraising\Frontend\App\Controllers\PaypalNotificationControllerForMembershipFee;
use WMDE\Fundraising\Frontend\App\Controllers\ShowDonationConfirmationController;
use WMDE\Fundraising\Frontend\App\Controllers\ShowUpdateAddressController;
use WMDE\Fundraising\Frontend\App\Controllers\UpdateAddressController;
use WMDE\Fundraising\Frontend\App\Controllers\UpdateDonorController;
use WMDE\Fundraising\Frontend\App\Controllers\ValidateAddressController;
use WMDE\Fundraising\Frontend\App\Controllers\ValidateDonationAmountController;
use WMDE\Fundraising\Frontend\App\Controllers\ValidateFeeController;
use WMDE\Fundraising\Frontend\App\Controllers\ValidationController;
use WMDE\Fundraising\Frontend\App\RouteHandlers\RouteRedirectionHandler;
use WMDE\Fundraising\Frontend\App\RouteHandlers\SofortNotificationHandler;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Infrastructure\Cache\AuthorizedCachePurger;
use WMDE\Fundraising\Frontend\Infrastructure\UrlGenerator;
use WMDE\Fundraising\Frontend\UseCases\GetInTouch\GetInTouchRequest;
use WMDE\Fundraising\MembershipContext\UseCases\CancelMembershipApplication\CancellationRequest;
use WMDE\Fundraising\MembershipContext\UseCases\ShowApplicationConfirmation\ShowAppConfirmationRequest;

class Routes {

	public const ADD_COMMENT_PAGE = 'AddCommentPage';
	public const CANCEL_DONATION = 'cancel-donation';
	public const CANCEL_MEMBERSHIP = 'cancel-membership-application';
	public const CONFIRM_SUBSCRIPTION = 'confirm-subscription';
	public const CONVERT_BANKDATA = 'generate-iban';
	public const GET_IN_TOUCH = 'contact';
	public const INDEX = '/';
	public const LIST_COMMENTS_HTML = 'list-comments.html';
	public const LIST_COMMENTS_RSS = 'list-comments.rss';
	public const POST_COMMENT = 'PostComment';
	public const SHOW_DONATION_CONFIRMATION = 'show-donation-confirmation';
	public const SHOW_DONATION_FORM = 'show-donation-form';
	public const SHOW_FAQ = 'faq';
	public const SHOW_MEMBERSHIP_CONFIRMATION = 'show-membership-confirmation';
	public const SHOW_PAGE = 'page';
	public const SHOW_UPDATE_ADDRESS = 'update-address-show-form';
	public const SHOW_USE_OF_FUNDS = 'use-of-funds';
	public const SUBSCRIBE = 'subscribe';
	public const UPDATE_ADDRESS = 'update-address';
	public const UPDATE_DONOR = 'update-donor';
	public const VALIDATE_ADDRESS = 'validate-donor-address';
	public const VALIDATE_DONATION_AMOUNT = 'validate-donation-amount';
	public const VALIDATE_EMAIL = 'validate-email';
	public const VALIDATE_MEMBERSHIP_FEE = 'validate-fee';
	public const VALIDATE_IBAN = 'check-iban';

	public static function initializeRoutes( Application $app, FunFunFactory $ffFactory ): Application {
		$app->post(
			'validate-email',
			ValidationController::class . '::validateEmail'
		)->bind( self::VALIDATE_EMAIL );

		$app->post(
			// This route is named badly, it validates **donor** information.
			'validate-address',
			ValidateAddressController::class . '::validate'
		)->bind( self::VALIDATE_ADDRESS );

		$app->post(
			'validate-donation-amount',
			ValidateDonationAmountController::class . '::validate'
		)->bind( self::VALIDATE_DONATION_AMOUNT );

		$app->post(
			'validate-fee',
			ValidateFeeController::class . '::validateFee'
		)->bind( self::VALIDATE_MEMBERSHIP_FEE );

		$app->get(
			'list-comments.json',
			function ( Request $request ) use ( $app, $ffFactory ) {
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
			function () use ( $ffFactory ) {
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
		)->bind( self::LIST_COMMENTS_RSS );

		$app->get(
			'list-comments.html',
			function ( Request $request ) use ( $ffFactory ) {
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
		)->bind( self::LIST_COMMENTS_HTML );

		$app->get(
			'page/{pageName}',
			PageDisplayController::class . '::handle'
		)->bind( self::SHOW_PAGE );

		$app->match(
			'contact/subscribe',
			AddSubscriptionController::class . '::addSubscription'
		)
			->method( 'GET|POST' )
			->bind( self::SUBSCRIBE );

		$app->get(
			'contact/confirm-subscription/{confirmationCode}',
			function ( $confirmationCode ) use ( $ffFactory ) {
				$useCase = $ffFactory->newConfirmSubscriptionUseCase();
				$response = $useCase->confirmSubscription( $confirmationCode );
				return $ffFactory->newConfirmSubscriptionHtmlPresenter()->present( $response );
			}
		)
			->assert( 'confirmationCode', '^[0-9a-f]+$' )
			->bind( self::CONFIRM_SUBSCRIPTION );

		$app->get(
			'check-iban',
			IbanController::class . '::validateIban'
		)->bind( self::VALIDATE_IBAN );

		$app->get(
			'generate-iban',
			IbanController::class . '::convertBankDataToIban'
		)->bind( self::CONVERT_BANKDATA );

		$app->post(
			'add-comment',
			function ( Request $request ) use ( $app, $ffFactory ) {
				$addCommentRequest = new AddCommentRequest();
				$addCommentRequest->setCommentText( trim( $request->request->get( 'comment', '' ) ) );
				$addCommentRequest->setIsPublic( $request->request->getBoolean( 'public' ) );
				$addCommentRequest->setDonationId( (int)$request->request->get( 'donationId', '' ) );

				if ( $request->request->getBoolean( 'isAnonymous' ) ) {
					$addCommentRequest->setIsAnonymous();
				} else {
					$addCommentRequest->setIsNamed();
				}

				$addCommentRequest->freeze()->assertNoNullFields();

				$updateToken = $request->request->get( 'updateToken', '' );

				if ( $updateToken === '' ) {
					return $app->json(
						[
							'status' => 'ERR',
							'message' => 'comment_failure_access_denied',
						]
					);
				}

				$response = $ffFactory->newAddCommentUseCase( $updateToken )->addComment( $addCommentRequest );

				if ( $response->isSuccessful() ) {
					return $app->json(
						[
							'status' => 'OK',
							'message' => $response->getSuccessMessage(),
						]
					);
				}

				return $app->json(
					[
						'status' => 'ERR',
						'message' => $response->getErrorMessage(),
					]
				);
			}
		)->bind( self::POST_COMMENT );

		$app->get(
			'add-comment',
			function ( Request $request ) use ( $app, $ffFactory ) {
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
		)->bind( self::ADD_COMMENT_PAGE );

		$app->post(
			'contact/get-in-touch',
			function ( Request $request ) use ( $app, $ffFactory ) {
				$contactFormRequest = new GetInTouchRequest(
					$request->get( 'firstname', '' ),
					$request->get( 'lastname', '' ),
					$request->get( 'email', '' ),
					$request->get( 'donationNumber', '' ),
					$request->get( 'subject', '' ),
					$request->get( 'category', '' ),
					$request->get( 'messageBody', '' )
				);

				$contactFormResponse = $ffFactory->newGetInTouchUseCase()->processContactRequest( $contactFormRequest );
				if ( $contactFormResponse->isSuccessful() ) {
					return $app->redirect(
						$app['url_generator']->generate( 'page', [ 'pageName' => 'Kontakt_Bestaetigung' ] )
					);
				}

				return $ffFactory->newGetInTouchHtmlPresenter()->present(
					$contactFormResponse,
					$request->request->all()
				);
			}
		);

		$app->get(
			'contact/get-in-touch',
			function () use ( $ffFactory ) {
				return $ffFactory->getLayoutTemplate( 'Contact_Form.html.twig' )->render(
					[
						'contact_categories' => $ffFactory->getGetInTouchCategories(),
						'contactFormValidationPatterns' => $ffFactory->getValidationRules()->contactForm,
					]
				);
			}
		)->bind( self::GET_IN_TOUCH );

		$app->get(
			'faq',
			function () use ( $ffFactory ) {
				return $ffFactory->getLayoutTemplate( 'Frequent_Questions.html.twig' )->render(
					[
						'faq_content' => $ffFactory->getFaqContent(),
					]
				);
			}
		)->bind( self::SHOW_FAQ );

		$app->get(
			self::UPDATE_ADDRESS,
			ShowUpdateAddressController::class . '::showForm'
		)->bind( self::SHOW_UPDATE_ADDRESS );

		$app->post(
			self::UPDATE_ADDRESS,
			UpdateAddressController::class . '::updateAddress'

		)->bind( self::UPDATE_ADDRESS );

		$app->get(
			'use-of-funds',
			function ( Request $request ) use ( $ffFactory ) {
				$renderer = $ffFactory->getUseOfFundsRenderer();
				return $renderer( $request );
			}
		)->bind( self::SHOW_USE_OF_FUNDS );

		$app->post(
			'donation/cancel',
			function ( Request $request ) use ( $ffFactory ) {
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
		)->bind( self::CANCEL_DONATION );

		$app->post(
			'donation/add',
			AddDonationController::class . '::handle'
		);

		$app->post(
			'donation/update',
			UpdateDonorController::class . '::updateDonor'
		)->bind( self::UPDATE_DONOR );

		// Show a donation form with pre-filled payment values, e.g. when coming from a banner
		$app->match(
			'donation/new',
			NewDonationController::class . '::handle'
		)->method( 'POST|GET' )
			->bind( self::SHOW_DONATION_FORM );

		$app->post(
			'apply-for-membership',
			ApplyForMembershipController::class . '::applyForMembership'
		);

		$app->get(
			'apply-for-membership',
			ApplyForMembershipController::class . '::showApplicationForm'
		);

		$app->get(
			'show-membership-confirmation',
			function ( Request $request ) use ( $ffFactory ) {
				$presenter = $ffFactory->newMembershipApplicationConfirmationHtmlPresenter();

				$useCase = $ffFactory->newMembershipApplicationConfirmationUseCase(
					$presenter,
					$request->query->get( 'accessToken', '' )
				);

				$useCase->showConfirmation( new ShowAppConfirmationRequest( (int)$request->query->get( 'id', 0 ) ) );

				return $presenter->getHtml();
			}
		)->bind( self::SHOW_MEMBERSHIP_CONFIRMATION );

		$app->get(
			'cancel-membership-application',
			function ( Request $request ) use ( $ffFactory ) {
				$cancellationRequest = new CancellationRequest(
					(int)$request->query->get( 'id', '' )
				);

				return $ffFactory->newCancelMembershipApplicationHtmlPresenter()->present(
					$ffFactory->newCancelMembershipApplicationUseCase( $request->query->get( 'updateToken', '' ) )
						->cancelApplication( $cancellationRequest )
				);
			}
		)->bind( self::CANCEL_MEMBERSHIP );

		$app->match(
			'show-donation-confirmation',
			ShowDonationConfirmationController::class . '::show'
		)->bind( self::SHOW_DONATION_CONFIRMATION )
			->method( 'GET|POST' );

		$app->post(
			'handle-paypal-payment-notification',
			PayPalNotificationController::class . '::handle'
		);

		$app->post(
			'sofort-payment-notification',
			function ( Request $request ) use ( $ffFactory ) {
				return ( new SofortNotificationHandler( $ffFactory ) )->handle( $request );
			}
		);

		$app->get(
			'handle-creditcard-payment-notification',
			CreditCardPaymentNotificationController::class . '::handleNotification'
		);

		$app->get(
			'donation-accepted',
			function ( Request $request ) use ( $app, $ffFactory ) {
				$eventHandler = $ffFactory->newDonationAcceptedEventHandler(
					$request->query->get( 'update_token', '' )
				);
				$result = $eventHandler->onDonationAccepted( (int)$request->query->get( 'donation_id', '' ) );

				return $app->json(
					$result === null ? [ 'status' => 'OK' ] : [ 'status' => 'ERR', 'message' => $result ]
				);
			}
		);

		$app->post(
			'handle-paypal-membership-fee-payments',
			PayPalNotificationControllerForMembershipFee::class . '::handle'
		);

		$app->get(
			'/',
			function ( Application $app, Request $request ) {
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
			}
		)->bind( self::INDEX );

		// TODO Figure out how to rewrite with Nginx
		// See https://serverfault.com/questions/805881/nginx-populate-request-uri-with-rewritten-url
		$app->post(
			'/spenden/paypal_handler.php',
			PayPalNotificationController::class . '::handle'
		);

		// redirect display page requests from old URLs
		$app->get(
			'/spenden/{page}',
			function ( Application $app, Request $request, string $page ) {
				// Poor man's rewrite until someone has figured out how to do this with Nginx without breaking REQUEST_URI
				// See https://serverfault.com/questions/805881/nginx-populate-request-uri-with-rewritten-url
				switch ( $page ) {
					case 'Mitgliedschaft':
						return ( new RouteRedirectionHandler( $app, $request->getQueryString() ) )->handle(
							'/page/Membership_Application'
						);
					default:
						return ( new RouteRedirectionHandler( $app, $request->getQueryString() ) )->handle(
							'/page/' . $page
						);
				}
			}
		)->assert( 'page', '[a-zA-Z_\-\s\x7f-\xff]+' );

		// redirect different formats of comment lists
		$app->get(
			'/spenden/{outputFormat}.php',
			function ( Application $app, Request $request, string $outputFormat ) {
				return ( new RouteRedirectionHandler( $app, $request->getQueryString() ) )->handle(
					'/list-comments.' . ( $outputFormat === 'list' ? 'html' : $outputFormat )
				);
			}
		)->assert( 'outputFormat', 'list|rss|json' );

		// redirect all other calls to default route
		$app->get(
			'/spenden{page}',
			function ( Application $app, Request $request ) {
				return ( new RouteRedirectionHandler( $app, $request->getQueryString() ) )->handle( '/' );
			}
		)->assert( 'page', '/?([a-z]+\.php)?' );

		$app->get(
			'/purge-cache',
			function ( Request $request ) use ( $ffFactory ) {
				$response = $ffFactory->newAuthorizedCachePurger()->purgeCache( $request->query->get( 'secret', '' ) );

				return new Response(
					[
						AuthorizedCachePurger::RESULT_SUCCESS => 'SUCCESS',
						AuthorizedCachePurger::RESULT_ERROR => 'ERROR',
						AuthorizedCachePurger::RESULT_ACCESS_DENIED => 'ACCESS DENIED'
					][$response]
				);
			}
		);

		$app->get(
			'status',
			function () {
				return 'Status: OK (Online)';
			}
		);

		return $app;
	}

	public static function getNamedRouteUrls( UrlGenerator $urlGenerator ): array {
		return [
			'validateDonationAmount' => $urlGenerator->generateAbsoluteUrl( self::VALIDATE_DONATION_AMOUNT ),
			'validateAddress' => $urlGenerator->generateAbsoluteUrl( self::VALIDATE_ADDRESS ),
			'validateEmail' => $urlGenerator->generateAbsoluteUrl( self::VALIDATE_EMAIL ),
			'validateIban' => $urlGenerator->generateAbsoluteUrl( self::VALIDATE_IBAN ),
			'validateMembershipFee' => $urlGenerator->generateAbsoluteUrl( self::VALIDATE_MEMBERSHIP_FEE ),
			'convertBankData' => $urlGenerator->generateAbsoluteUrl( self::CONVERT_BANKDATA ),
			'cancelDonation' => $urlGenerator->generateAbsoluteUrl( self::CANCEL_DONATION ),
			'cancelMembership' => $urlGenerator->generateAbsoluteUrl( self::CANCEL_MEMBERSHIP ),
			'postComment' => $urlGenerator->generateAbsoluteUrl( self::POST_COMMENT ),
		];
	}
}
