<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\App\EventHandlers;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use WMDE\Fundraising\Frontend\App\CookieNames;
use WMDE\Fundraising\Frontend\Infrastructure\CookieBuilder;
use WMDE\Fundraising\Frontend\Infrastructure\TrackingDataSelector;

/**
 * Inject the request object with the current tracking data, stored in cookie or coming from URL params.
 *
 * Cookie values take precedence over
 */
class RegisterTrackingData implements EventSubscriberInterface {

	public static function getSubscribedEvents() {
		return [
			KernelEvents::REQUEST => 'onKernelRequest',
			KernelEvents::RESPONSE => 'onKernelResponse'
		];
	}

	private CookieBuilder $cookieBuilder;

	public function __construct( CookieBuilder $cookieBuilder ) {
		$this->cookieBuilder = $cookieBuilder;
	}

	public function onKernelRequest( GetResponseEvent $event ): void {
		$request = $event->getRequest();

		if ( $request->cookies->get( 'cookie_consent' ) !== 'yes' ) {
			return;
		}

		$request->attributes->set( 'trackingCode', TrackingDataSelector::getFirstNonEmptyValue( [
			$request->cookies->get( CookieNames::TRACKING ),
			TrackingDataSelector::concatTrackingFromVarTuple(
				$request->get( 'piwik_campaign', '' ),
				$request->get( 'piwik_kwd', '' )
			)
		] ) );

		// Remove when https://phabricator.wikimedia.org/T134327 is done
		$request->attributes->set( 'trackingSource', TrackingDataSelector::getFirstNonEmptyValue( [
			$request->cookies->get( 'spenden_source' ),
			$request->request->get( 'source' ),
			$request->server->get( 'HTTP_REFERER' )
		] ) );
	}

	public function onKernelResponse( FilterResponseEvent $event ): void {
		$request = $event->getRequest();
		$response = $event->getResponse();

		$trackingCode = $request->attributes->get( 'trackingCode', '' );
		if ( $trackingCode !== '' ) {
			$response->headers->setCookie( $this->cookieBuilder->newCookie(
				CookieNames::TRACKING,
				$trackingCode
			) );
		}

		$trackingSource = $request->attributes->get( 'trackingSource', '' );
		if ( $trackingSource !== '' ) {
			$response->headers->setCookie( $this->cookieBuilder->newCookie(
				'spenden_source',
				$trackingSource
			) );
		}
	}

}
