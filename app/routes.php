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
use WMDE\Fundraising\Frontend\Domain\Iban;
use WMDE\Fundraising\Frontend\Domain\Model\PaymentType;
use WMDE\Fundraising\Frontend\Domain\Model\PersonalInfo;
use WMDE\Fundraising\Frontend\Domain\Model\PersonName;
use WMDE\Fundraising\Frontend\Domain\Model\PhysicalAddress;
use WMDE\Fundraising\Frontend\Domain\PayPalUrlGenerator;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\UseCases\AddComment\AddCommentRequest;
use WMDE\Fundraising\Frontend\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\Frontend\UseCases\DisplayPage\PageDisplayRequest;
use WMDE\Fundraising\Frontend\UseCases\GenerateIban\GenerateIbanRequest;
use WMDE\Fundraising\Frontend\UseCases\GetInTouch\GetInTouchRequest;
use WMDE\Fundraising\Frontend\UseCases\CancelDonation\CancelDonationRequest;
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

$app->post(
	'validate-amount',
	function( Request $request ) use ( $app, $ffFactory ) {

		$amount = (float) $ffFactory->newDecimalNumberFormatter()->parse( $request->get( 'amount', '0' ) );
		$amountValidator = $ffFactory->newAmountValidator();
		$validationResult = $amountValidator->validate( $amount, (string) $request->get( 'paymentType', '' ) );

		if ( $validationResult->isSuccessful() ) {
			return $app->json( [ 'status' => 'OK' ] );
		} else {
			$errors = [];
			foreach( $validationResult->getViolations() as $violation ) {
				$errors[] = $ffFactory->getTranslator()->trans( $violation->getMessageIdentifier() );
			}
			return $app->json( [ 'status' => 'ERR', 'message' => implode( "\n", $errors ) ] );
		}
	}
);

$app->post(
	'validate-address',
	function( Request $request ) use ( $app, $ffFactory ) {
		$routeHandler = new class() {

			public function handle( FunFunFactory $ffFactory, Application $app, Request $request ) {
				if ( $request->get( 'adresstyp', '' ) === 'anonym' ) {
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

			private function getPersonalInfoFromRequest( Request $request ): PersonalInfo {
				$personalInfo = new PersonalInfo();

				$personalInfo->setEmailAddress( $request->get( 'email', '' ) );
				$personalInfo->setPhysicalAddress( $this->getPhysicalAddressFromRequest( $request ) );
				$personalInfo->setPersonName( $this->getNameFromRequest( $request ) );

				return $personalInfo->freeze()->assertNoNullFields();
			}

			private function getPhysicalAddressFromRequest( Request $request ): PhysicalAddress {
				$address = new PhysicalAddress();

				$address->setStreetAddress( $request->get( 'strasse', '' ) );
				$address->setPostalCode( $request->get( 'plz', '' ) );
				$address->setCity( $request->get( 'ort', '' ) );
				$address->setCountryCode( $request->get( 'country', '' ) );

				return $address->freeze()->assertNoNullFields();
			}

			private function getNameFromRequest( Request $request ): PersonName {
				$name = $request->get( 'adresstyp', '' ) === 'firma'
					? PersonName::newCompanyName() : PersonName::newPrivatePersonName();

				$name->setSalutation( $request->get( 'anrede', '' ) );
				$name->setTitle( $request->get( 'titel', '' ) );
				$name->setCompanyName( $request->get( 'firma', '' ) );
				$name->setFirstName( $request->get( 'vorname', '' ) );
				$name->setLastName( $request->get( 'nachname', '' ) );

				return $name->freeze()->assertNoNullFields();
			}
		};

		return $routeHandler->handle( $ffFactory, $app, $request );
	}
);

$app->get(
	'list-comments.json',
	function( Request $request ) use ( $app, $ffFactory ) {
		$response = $app->json(
			$ffFactory->newCommentListJsonPresenter()->present(
				$ffFactory->newListCommentsUseCase()->listComments(
					new CommentListingRequest( (int)$request->get( 'n' ) )
				)
			)
		);

		if ( $request->get( 'f' ) ) {
			$response->setCallback( $request->get( 'f' ) );
		}

		return $response;
	}
);

$app->get(
	'list-comments.rss',
	function() use ( $app, $ffFactory ) {
		$rss = $ffFactory->newCommentListRssPresenter()->present(
			$ffFactory->newListCommentsUseCase()->listComments(
				new CommentListingRequest( 100 )
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
	function() use ( $app, $ffFactory ) {
		return new Response(
			$ffFactory->newCommentListHtmlPresenter()->present(
				$ffFactory->newListCommentsUseCase()->listComments(
					new CommentListingRequest( 10 )
				)
			)
		);
	}
);

$app->get(
	'page/{pageName}',
	function( Application $app, $pageName ) use ( $ffFactory ) {
		return $ffFactory->newDisplayPagePresenter()->present(
			$ffFactory->newDisplayPageUseCase()->getPage( new PageDisplayRequest( $pageName ) )
		);
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
		if ( $app['request.is_json'] ) {
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
		$checkIbanResponse = $useCase->checkIban( new Iban( $request->get( 'iban', '' ) ) );
		return $app->json( $ffFactory->newIbanPresenter()->present( $checkIbanResponse ) );
	}
);

$app->get(
	'generate-iban',
	function( Request $request ) use ( $app, $ffFactory ) {
		$generateIbanRequest = new GenerateIbanRequest(
			$request->get( 'accountNumber', '' ),
			$request->get( 'bankCode', '' )
		);

		$generateIbanResponse = $ffFactory->newGenerateIbanUseCase()->generateIban( $generateIbanRequest );
		return $app->json( $ffFactory->newIbanPresenter()->present( $generateIbanResponse ) );
	}
);

$app->post(
	'add-comment',
	function( Request $request ) use ( $app, $ffFactory ) {
		$addCommentRequest = new AddCommentRequest();
		$addCommentRequest->setCommentText( $request->get( 'kommentar', '' ) );
		$addCommentRequest->setIsPublic( $request->get( 'public', '0' ) === '1' );
		$addCommentRequest->setAuthorDisplayName( $request->get( 'eintrag', '' ) );
		$addCommentRequest->setDonationId( (int)$request->get( 'sid', '' ) );
		$addCommentRequest->freeze()->assertNoNullFields();

		$token = $request->get( 'token', '' );
		$updateToken = $request->get( 'utoken', '' );

		if ( $token === '' || $updateToken === '' ) {
			return $app->json( [
				'status' => 'ERR',
				'message' => 'Required token is missing',
			] );
		}

		$response = $ffFactory->newAddCommentUseCase( $updateToken )->addComment( $addCommentRequest );

		if ( $response->isSuccessful() ) {
			return $app->json( [
				'status' => 'OK',
				'message' => '',
			] );
		}

		return $app->json( [
			'status' => 'ERR',
			'message' => $response->getErrorMessage(),
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
			(int)$request->get( 'sid', '' )
		);

		$response = $ffFactory->newCancelDonationUseCase( $request->get( 'utoken', '' ) )->cancelDonation( $cancellationRequest );

		// TODO: reset spenden_stamp in cookie

		return $ffFactory->newCancelDonationHtmlPresenter()->present(
			$response->getDonationId(),
			$response->cancellationWasSuccessful()
		);
	}
);


$app->post(
	'donation/add',
	function( Application $app, Request $request ) use ( $ffFactory ) {
		$routeHandler = new class() {

			public function handle( FunFunFactory $ffFactory, Application $app, Request $request ) {
				$responseModel = $ffFactory->newAddDonationUseCase()->addDonation(
					$this->createDonationRequest( $request )
				);

				if ( $responseModel->isSuccessful() ) {
					$donation = $responseModel->getDonation();

					switch( $donation->getPaymentType() ) {
						case PaymentType::DIRECT_DEBIT:
						case PaymentType::BANK_TRANSFER:
							return $ffFactory->newAddDonationHtmlPresenter()->present( $responseModel->getDonation() );
						case PaymentType::PAYPAL:
							return $app->redirect(
								$ffFactory->newPayPalUrlGenerator()->generateUrl(
									$donation->getId(),
									$donation->getAmount(),
									$donation->getInterval(),
									'token',
									'utoken'
								)
							);
					}
					// TODO: show page embedding iframe (MCP)
					// TODO: take over confirmation page selection functionality from old application
					// TODO: return update token
				}

				return 'TODO';
			}

			private function createDonationRequest( Request $request ): AddDonationRequest {
				$donationRequest = new AddDonationRequest();
				$locale = 'de_DE'; // TODO: make this configurable for multilanguage support
				$donationRequest->setAmountFromString( $request->get( 'betrag', '' ), $locale );
				$donationRequest->setPaymentType( $request->get( 'zahlweise', '' ) );
				$donationRequest->setInterval( intval( $request->get( 'periode', 0 ) ) );

				$donationRequest->setPersonalInfo(
					$request->get( 'adresstyp', '' ) === 'anonym' ? null :  $this->getPersonalInfoFromRequest( $request )
				);

				$donationRequest->setIban( $request->get( 'iban', '' ) );
				$donationRequest->setBic( $request->get( 'bic', '' ) );
				$donationRequest->setBankAccount( $request->get( 'konto', '' ) );
				$donationRequest->setBankCode( $request->get( 'blz', '' ) );
				$donationRequest->setBankName( $request->get( 'bankname', '' ) );

				$donationRequest->setTracking(
					AddDonationRequest::getPreferredValue( [
						$request->cookies->get( 'spenden_tracking' ),
						$request->request->get( 'tracking' ),
						AddDonationRequest::concatTrackingFromVarCouple(
							$request->get( 'piwik_campaign', '' ),
							$request->get( 'piwik_kwd', '' )
						)
					] )
				);

				$donationRequest->setOptIn( $request->get( 'info', '' ) );
				$donationRequest->setSource(
					AddDonationRequest::getPreferredValue( [
						$request->cookies->get( 'spenden_source' ),
						$request->request->get( 'source' ),
						$request->server->get( 'HTTP_REFERER' )
					] )
				);
				$donationRequest->setTotalImpressionCount( intval( $request->get( 'impCount', 0 ) ) );
				$donationRequest->setSingleBannerImpressionCount( intval( $request->get( 'bImpCount', 0 ) ) );
				$donationRequest->setColor( $request->get( 'color', '' ) );
				$donationRequest->setSkin( $request->get( 'skin', '' ) );
				$donationRequest->setLayout( $request->get( 'layout', '' ) );

				return $donationRequest;
			}

			private function getPersonalInfoFromRequest( Request $request ): PersonalInfo {
				$personalInfo = new PersonalInfo();

				$personalInfo->setEmailAddress( $request->get( 'email', '' ) );
				$personalInfo->setPhysicalAddress( $this->getPhysicalAddressFromRequest( $request ) );
				$personalInfo->setPersonName( $this->getNameFromRequest( $request ) );

				return $personalInfo->freeze()->assertNoNullFields();
			}

			private function getPhysicalAddressFromRequest( Request $request ): PhysicalAddress {
				$address = new PhysicalAddress();

				$address->setStreetAddress( $request->get( 'strasse', '' ) );
				$address->setPostalCode( $request->get( 'plz', '' ) );
				$address->setCity( $request->get( 'ort', '' ) );
				$address->setCountryCode( $request->get( 'country', '' ) );

				return $address->freeze()->assertNoNullFields();
			}

			private function getNameFromRequest( Request $request ): PersonName {
				$name = $request->get( 'adresstyp', '' ) === 'firma'
					? PersonName::newCompanyName() : PersonName::newPrivatePersonName();

				$name->setSalutation( $request->get( 'anrede', '' ) );
				$name->setTitle( $request->get( 'titel', '' ) );
				$name->setCompanyName( $request->get( 'firma', '' ) );
				$name->setFirstName( $request->get( 'vorname', '' ) );
				$name->setLastName( $request->get( 'nachname', '' ) );

				return $name->freeze()->assertNoNullFields();
			}
		};

		return $routeHandler->handle( $ffFactory, $app, $request );
	}
);

return $app;
