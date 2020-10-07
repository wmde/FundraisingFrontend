<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\App\Controllers;

use DateTime;
use Psr\Log\LogLevel;
use Sofort\SofortLib\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;
use WMDE\Fundraising\DonationContext\UseCases\SofortPaymentNotification\SofortPaymentNotificationUseCase;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\PaymentContext\RequestModel\SofortNotificationRequest;
use WMDE\Fundraising\PaymentContext\ResponseModel\SofortNotificationResponse;

class SofortNotificationController {

	private FunFunFactory $ffFactory;
	private Request $request;

	public function handle( FunFunFactory $ffFactory, Request $request ): Response {
		$this->ffFactory = $ffFactory;
		$this->request = $request;

		try {
			$useCaseRequest = $this->newUseCaseRequest();
		} catch ( UnexpectedValueException $e ) {
			$this->logWebRequest( [ 'message' => $e->getMessage() ], LogLevel::ERROR );
			return new Response( 'Bad request', Response::HTTP_BAD_REQUEST );
		}

		$response = $this->newUseCase()->handleNotification( $useCaseRequest );

		$this->logResponseIfNeeded( $response );

		if ( $response->hasErrors() ) {
			return new Response( 'Error', Response::HTTP_INTERNAL_SERVER_ERROR );
		}

		if ( $response->notificationWasHandled() ) {
			return new Response( 'Ok', Response::HTTP_OK );
		}

		return new Response( 'Bad request', Response::HTTP_BAD_REQUEST );
	}

	private function newUseCase(): SofortPaymentNotificationUseCase {
		return $this->ffFactory->newHandleSofortPaymentNotificationUseCase( $this->request->query->get( 'updateToken' ) );
	}

	private function newUseCaseRequest(): SofortNotificationRequest {
		$useCaseRequest = self::fromUseCaseRequestFromRequestContent( $this->request->getContent() );

		if ( !( $useCaseRequest instanceof SofortNotificationRequest ) ) {
			throw new UnexpectedValueException( 'Invalid notification request' );
		}

		$useCaseRequest->setDonationId( $this->request->query->getInt( 'id' ) );

		return $useCaseRequest;
	}

	public static function fromUseCaseRequestFromRequestContent( string $content ): ?SofortNotificationRequest {
		$vendorNotification = new Notification();
		$result = $vendorNotification->getNotification( $content );

		if ( $result === false ) {
			return null;
		}

		$time = DateTime::createFromFormat( DateTime::ATOM, $vendorNotification->getTime() );

		if ( $time === false ) {
			return null;
		}

		$notification = new SofortNotificationRequest();
		$notification->setTime( $time );
		$notification->setTransactionId( $vendorNotification->getTransactionId() );

		return $notification;
	}

	private function logResponseIfNeeded( SofortNotificationResponse $response ): void {
		if ( $response->notificationWasHandled() ) {
			return;
		}

		$this->logWebRequest(
			$response->getContext(),
			$response->hasErrors() ? LogLevel::ERROR : LogLevel::INFO
		);
	}

	private function logWebRequest( array $context, string $logLevel ): void {
		$message = $context['message'] ?? 'Sofort request not handled';
		unset( $context['message'] );

		$context['request_content'] = $this->request->getContent();
		$context['query_vars'] = $this->request->query->all();
		$this->ffFactory->getSofortLogger()->log( $logLevel, $message, $context );
	}
}
