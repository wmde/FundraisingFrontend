<?php


namespace WMDE\Fundraising\Frontend\Domain;

use WMDE\Fundraising\Entities\Request;
use WMDE\Fundraising\Frontend\MailValidator;

/**
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class RequestValidator
{
	private $mailValidator;
	private $obligatoryFields = [
		'firstName', 'lastName', 'title'
	];
	private $validationErrors = [];

	public function __construct( MailValidator $mailValidator ) {
		$this->mailValidator = $mailValidator;
	}

	public function validate( Request $request ) {
		// TODO use a proper validator interface on the sub-validators for each field, with a config array
		// TODO use sub-validators to generate violation messages
		// TODO store violation messages
		$errors = [];
		if ( ! $this->mailValidator->validateMail( $request->getEmail() ) ) {
			$errors['email'] = 'invalid';
		}
		foreach ( $this->obligatoryFields as $fld ) {
			$accessor = 'get' . ucfirst( $fld );
			if ( empty( $request->$accessor() ) ) {
				$errors[$fld] = 'missing';
			}

		}
		$this->validationErrors = $errors;
		return count( $errors ) == 0;
	}

	/**
	 * @return array
	 */
	public function getValidationErrors() {
		return $this->validationErrors;
	}

	/**
	 * @param array $validationErrors
	 */
	public function setValidationErrors( $validationErrors ) {
		$this->validationErrors = $validationErrors;
	}
}