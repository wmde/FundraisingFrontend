<?php

namespace WMDE\Fundraising\Frontend\Presentation\Presenters;

use WMDE\Fundraising\Frontend\Domain\Model\MailAddress;
use WMDE\Fundraising\Frontend\Domain\Model\PersonalInfo;
use WMDE\Fundraising\Frontend\Domain\Model\PersonName;
use WMDE\Fundraising\Frontend\Domain\Model\PhysicalAddress;
use WMDE\Fundraising\Frontend\Presentation\TwigTemplate;
use WMDE\Fundraising\Frontend\UseCases\AddDonation\AddDonationRequest;

/**
 * @licence GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class DonationFormViolationPresenter {

	private $template;

	public function __construct( TwigTemplate $template ) {
		$this->template = $template;
	}

	public function present( AddDonationRequest $request ): string {
		return $this->template->render( $this->getDonationFormArguments( $request ) );
	}

	private function getDonationFormArguments( AddDonationRequest $request ) {
		return array_merge(
			[
				'betrag' => $request->getAmount(),
				'zahlweise' => $request->getPaymentType(),
				'periode' => $request->getInterval(),
				'iban' => $request->getIban(),
				'bic' => $request->getBic(),
				'bankName' => $request->getBankName()
			],
			$this->getPersonalInfo( $request->getPersonalInfo() ) );
	}

	/**
	 * @param PersonalInfo|null $personalInfo
	 * @return array
	 */
	private function getPersonalInfo( $personalInfo ) {
		if ( $personalInfo !== null ) {
			return array_merge(
				$this->getPersonName( $personalInfo->getPersonName() ),
				$this->getPhysicalAddress( $personalInfo->getPhysicalAddress() ),
				[ 'email' => $personalInfo->getEmailAddress() ]
			);
		}

		return [];
	}

	/**
	 * @param PersonName|null $personName
	 * @return array
	 */
	private function getPersonName( $personName ) {
		if ( $personName !== null ) {
			return [
				'adresstyp' => $personName->getPersonType(),
				'anrede' => $personName->getSalutation(),
				'titel' => $personName->getTitle(),
				'firma' => $personName->getCompanyName(),
				'vorname' => $personName->getFirstName(),
				'nachname' => $personName->getLastName(),
			];
		}

		return [];
	}

	/**
	 * @param PhysicalAddress|null $address
	 * @return array
	 */
	private function getPhysicalAddress( $address ) {
		if ( $address !== null ) {
			return [
				'strasse' => $address->getStreetAddress(),
				'plz' => $address->getPostalCode(),
				'ort' => $address->getCity(),
				'country' => $address->getCountryCode(),
			];
		}

		return [];
	}

}
