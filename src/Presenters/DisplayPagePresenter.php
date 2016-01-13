<?php

namespace WMDE\Fundraising\Frontend\Presenters;

use WMDE\Fundraising\Frontend\TwigTemplate;
use WMDE\Fundraising\Frontend\UseCases\DisplayPage\PageDisplayResponse;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DisplayPagePresenter {

	private $template;

	public function __construct( TwigTemplate $template ) {
		$this->template = $template;
	}

	public function present( PageDisplayResponse $displayResponse ): string {
		return $this->template->render( [
			'header' => $displayResponse->getHeaderContent(),
			'main' => $displayResponse->getMainContent(),
			'footer' => $displayResponse->getFooterContent(),
			'isMobile' => true, // TODO
			'subFooter' => '', // TODO
			'noJsNotice' => '', // TODO
		] );
	}

}