<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge\Routes;

use WMDE\Fundraising\Frontend\Tests\EdgeToEdge\WebRouteTestCase;

/**
 * @license GPL-2.0-or-later
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class ValidateAddressRouteTest extends WebRouteTestCase {

	public function testGivenValidAddress_validationReturnsSuccess(): void {
		$client = $this->createClient();
		$client->followRedirects( false );

		$client->request(
			'POST',
			'/validate-address',
			$this->newPersonFormInput()
		);

		$response = $client->getResponse();

		$this->assertJsonSuccessResponse( [ 'status' => 'OK' ], $response );
	}

	public function testGivenInvalidCompanyAddress_validationReturnsErrorMessage(): void {
		$client = $this->createClient();
		$client->followRedirects( false );

		$client->request(
			'POST',
			'/validate-address',
			$this->newCompanyWithMissingNameFormInput()
		);

		$response = $client->getResponse();

		$expectedResponse = [
			'status' => 'ERR',
			'messages' => [
				'companyName' => 'missing'
			]
		];
		$this->assertJsonSuccessResponse( $expectedResponse, $response );
	}

	public function testGivenAnonymousAddress_validationReturnsErrorMessage(): void {
		$client = $this->createClient();
		$client->followRedirects( false );

		$client->request(
			'POST',
			'/validate-address',
			$this->newAnonymousFormInput()
		);

		$response = $client->getResponse();

		$expectedResponse = [
			'status' => 'ERR',
			'messages' => [
				'addressType' => 'address_form_error'
			]
		];
		$this->assertJsonSuccessResponse( $expectedResponse, $response );
	}

	private function newPersonFormInput(): array {
		return [
			'addressType' => 'person',
			'salutation' => 'Frau',
			'title' => 'Prof. Dr.',
			'company' => '',
			'firstName' => 'Karla',
			'lastName' => 'Kennichnich',
			'street' => 'Lehmgasse 12',
			'postcode' => '12345',
			'city' => 'Einort',
			'country' => 'DE',
			'email' => 'karla@kennichnich.de',
		];
	}

	private function newCompanyWithMissingNameFormInput(): array {
		return [
			'addressType' => 'firma',
			'salutation' => 'Frau',
			'title' => 'Prof. Dr.',
			'company' => '',
			'firstName' => 'Karla',
			'lastName' => 'Kennichnich',
			'street' => 'Lehmgasse 12',
			'postcode' => '12345',
			'city' => 'Einort',
			'country' => 'DE',
			'email' => 'karla@kennichnich.de',
		];
	}

	private function newAnonymousFormInput(): array {
		return [
			'addressType' => 'anonymous'
		];
	}

}
