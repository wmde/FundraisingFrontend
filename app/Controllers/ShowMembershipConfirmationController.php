<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\App\Controllers;

use Symfony\Component\HttpFoundation\Request;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\MembershipContext\UseCases\ShowApplicationConfirmation\ShowAppConfirmationRequest;

class ShowMembershipConfirmationController {

	public function handle( FunFunFactory $ffFactory, Request $request ): string {
		$presenter = $ffFactory->newMembershipApplicationConfirmationHtmlPresenter();

		$useCase = $ffFactory->newMembershipApplicationConfirmationUseCase(
			$presenter,
			$request->query->get( 'accessToken', '' )
		);

		$useCase->showConfirmation( new ShowAppConfirmationRequest( (int)$request->query->get( 'id', 0 ) ) );

		return $presenter->getHtml();
	}
}
