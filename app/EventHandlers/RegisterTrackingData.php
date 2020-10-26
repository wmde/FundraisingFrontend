<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\App\EventHandlers;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use WMDE\Fundraising\Frontend\App\CookieNames;
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
	}

	public function onKernelResponse( FilterResponseEvent $event ): void {
		$request = $event->getRequest();
		$response = $event->getResponse();

		if ( $request->attributes->has( 'trackingCode' ) ) {
			$response->headers->setCookie( new Cookie(
				CookieNames::TRACKING,
				$request->attributes->get( 'trackingCode' )
			) );
		}

		if ( $request->attributes->has( 'trackingSource' ) ) {
			$response->headers->setCookie( new Cookie(
				'spenden_source',
				$request->attributes->get( 'trackingSource' )
			) );
		}
	}

}
