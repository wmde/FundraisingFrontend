<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Domain\Model;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class MembershipApplicant {

	private $personName;
	private $physicalAddress;
	private $email;
	private $phone;
	private $dateOfBirth;

	/**
	 * MembershipApplicant constructor.
	 * @param PersonName $name
	 * @param PhysicalAddress $address
	 * @param EmailAddress $email
	 * @param PhoneNumber $phone
	 * @param \DateTime|null $dateOfBirth
	 */
	public function __construct( PersonName $name, PhysicalAddress $address, EmailAddress $email,
		PhoneNumber $phone, $dateOfBirth ) {

		$this->personName = $name;
		$this->physicalAddress = $address;
		$this->email = $email;
		$this->phone = $phone;
		$this->dateOfBirth = $dateOfBirth;
	}

	// TODO: $applicant->getPersonName->getFirstName() is odd compared to // TODO: $applicant->getFirstName()
	public function getPersonName(): PersonName {
		return $this->personName;
	}

	public function getPhysicalAddress(): PhysicalAddress {
		return $this->physicalAddress;
	}

	public function getEmailAddress(): EmailAddress {
		return $this->email;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getDateOfBirth() {
		return $this->dateOfBirth;
	}

	public function changeEmailAddress( EmailAddress $email ) {
		$this->email = $email;
	}

	public function getPhoneNumber(): PhoneNumber {
		return $this->phone;
	}

}
