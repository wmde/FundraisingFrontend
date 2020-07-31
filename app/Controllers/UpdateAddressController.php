<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\App\Controllers;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WMDE\Fundraising\AddressChangeContext\UseCases\ChangeAddress\ChangeAddressRequest;
use WMDE\Fundraising\Frontend\App\AccessDeniedException;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;

/**
 * @license GPL-2.0-or-later
 */
class UpdateAddressController {

	public function updateAddress( Request $request, FunFunFactory $ffFactory ): Response {
		$addressToken = $request->get( 'addressToken', '' );
		if ( $addressToken === '' ) {
			throw new AccessDeniedException();
		}

		$addressChangeRequest = $this->newAddressChangeRequestFromParams( $addressToken, $request->request );

		$useCase = $ffFactory->newChangeAddressUseCase();
		$response = $useCase->changeAddress( $addressChangeRequest );

		if ( !$response->isSuccess() ) {
			$ffFactory->getLogger()->error( 'Address change failed', [ 'domain_errors' => $response->getErrors() ] );
			return new Response( $ffFactory->newErrorPageHtmlPresenter()->present( implode( "\n", $response->getErrors() ) ) );
		}

		$this->sendUnknownCityTrackingDataIfNeeded( $ffFactory, $addressChangeRequest );

		return new Response( $ffFactory->getLayoutTemplate( 'AddressUpdateSuccess.html.twig' )->render( $request->request->all() ) );
	}

	private function sendUnknownCityTrackingDataIfNeeded( FunFunFactory $ffFactory, ChangeAddressRequest $request ) {
		if ( !$this->shouldTrackUnknownCity( $ffFactory, $request ) ) {
			return;
		}

		$city = $request->getCity();
		$postcode = $request->getPostcode();

		$ffFactory->getEventTracker()->trackEvent(
			'Form Submission Event',
			'Unknown City',
			"{$city}|{$postcode}"
		);
	}

	private function shouldTrackUnknownCity( FunFunFactory $ffFactory, ChangeAddressRequest $request ): bool {
		if ( $request->getCountry() != 'DE' ) {
			return false;
		}

		$localities = array_filter(
			$ffFactory->getPostalLocalities(),
			function ( $entry ) use ( $request ) {
				return $entry->postcode === $request->getPostcode()
					&& $entry->locality === $request->getCity();
			}
		);

		if ( count( $localities ) > 0 ) {
			return false;
		}

		return true;
	}

	private function newAddressChangeRequestFromParams( string $addressToken, ParameterBag $params ): ChangeAddressRequest {
		$request = new ChangeAddressRequest();
		$request->setIdentifier( $addressToken );

		if ( $params->get( 'addressType', '' ) === 'person' ) {
			$request->setAddressType( 'person' );
			$this->addPersonNameParams( $request, $params );
		} else {
			$request->setAddressType( 'company' );
			$this->addCompanyNameParams( $request, $params );
		}

		$this->addPostalParams( $request, $params );
		$this->addReceiptParams( $request, $params );

		$request->assertNoNullFields()->freeze();
		return $request;
	}

	private function addPersonNameParams( ChangeAddressRequest $request, ParameterBag $params ): void {
		$request->setFirstName( $params->get( 'firstName', '' ) )
			->setLastName( $params->get( 'lastName', '' ) )
			->setSalutation( $params->get( 'salutation', '' ) )
			->setTitle( $params->get( 'title', '' ) )
			->setCompany( '' );
	}

	private function addCompanyNameParams( ChangeAddressRequest $request, ParameterBag $params ): void {
		$request->setFirstName( '' )
			->setLastName( '' )
			->setSalutation( '' )
			->setTitle( '' )
			->setCompany( $params->get( 'company', '' ) );
	}

	private function addPostalParams( ChangeAddressRequest $request, ParameterBag $params ): void {
		$request->setAddress( $params->get( 'street', '' ) )
			->setPostcode( $params->get( 'postcode', '' ) )
			->setCity( $params->get( 'city', '' ) )
			->setCountry( $params->get( 'country', 'DE' ) );
	}

	private function addReceiptParams( ChangeAddressRequest $request, ParameterBag $params ) {
		if ( $params->get( 'receiptOptOut', '' ) ) {
			$request->setDonationReceipt( false );
			$request->setIsOptOutOnly( $this->isOptOutOnly( $params ) );
			return;
		}
		$request->setIsOptOutOnly( false );
		$request->setDonationReceipt( true );
	}

	private function isOptOutOnly( ParameterBag $params ): bool {
		$requirePostalFields = [ 'street', 'postcode', 'city' ];
		$requiredNameFiels = $params->get( 'addressType', '' ) == 'person' ?
			[ 'firstName', 'lastName' ] : [ 'company' ];
		$requiredFields = array_merge( $requiredNameFiels, $requirePostalFields );
		$allFieldsAreEmpty = true;
		foreach ( $requiredFields as $field ) {
			if ( $params->get( $field, '' ) !== '' ) {
				$allFieldsAreEmpty = false;
				break;
			}
		}
		return $allFieldsAreEmpty;
	}

}
