<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Presentation\Presenters;

use WMDE\Fundraising\PaymentContext\Domain\Model\BankData;
use WMDE\Fundraising\PaymentContext\ResponseModel\IbanResponse;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class IbanPresenter {

	public function present( IbanResponse $iban ): array {
		if ( $iban->isSuccessful() ) {
			return $this->newSuccessResponse( $iban->getBankData() );
		}

		return $this->newErrorResponse();
	}

	private function newSuccessResponse( BankData $bankData ): array {
		return array_filter( [
			'status' => 'OK',
			'bic' => $bankData->getBic(),
			'iban' => $bankData->getIban()->toString(),
			'account' => $bankData->getAccount(),
			'bankCode' => $bankData->getBankCode(),
			'bankName' => $bankData->getBankName(),
		] );
	}

	private function newErrorResponse(): array {
		return [ 'status' => 'ERR' ];
	}

}
