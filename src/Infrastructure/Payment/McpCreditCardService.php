<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Infrastructure\Payment;

use IMcpCreditcardService_v1_5;
use TNvpServiceDispatcher;
use WMDE\Fundraising\PaymentContext\Infrastructure\CreditCardExpiry;
use WMDE\Fundraising\PaymentContext\Infrastructure\CreditCardExpiryFetchingException;
use WMDE\Fundraising\PaymentContext\Infrastructure\CreditCardService;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class McpCreditCardService implements CreditCardService {

	private $microPaymentDispatcher;
	private $accessKey;
	private $useTestMode;

	/**
	 * @param IMcpCreditcardService_v1_5|TNvpServiceDispatcher $microPaymentDispatcher
	 * @param string $accessKey
	 * @param bool $useTestMode
	 */
	public function __construct( $microPaymentDispatcher, string $accessKey, bool $useTestMode ) {
		$this->microPaymentDispatcher = $microPaymentDispatcher;
		$this->accessKey = $accessKey;
		$this->useTestMode = $useTestMode;
	}

	/**
	 * @param string $customerId
	 * @return CreditCardExpiry
	 * @throws CreditCardExpiryFetchingException
	 */
	public function getExpirationDate( string $customerId ): CreditCardExpiry {
		try {
			$customerData = $this->microPaymentDispatcher->creditcardDataGet( $this->accessKey, $this->useTestMode, $customerId );
		}
		catch ( \Exception $ex ) {
			throw new CreditCardExpiryFetchingException( 'MCP-API: Request failed', $ex );
		}

		try {
			$expiryDate = new CreditCardExpiry(
				(int)$customerData['expiryMonth'],
				(int)$customerData['expiryYear']
			);
		}
		catch ( \InvalidArgumentException $ex ) {
			throw new CreditCardExpiryFetchingException( 'Malformed expiry date', $ex );
		}

		return $expiryDate;
	}

}
