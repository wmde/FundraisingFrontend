<?php

namespace WMDE\Fundraising\Frontend\PageRetriever;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Kai Nissen
 * @author Christoph Fischer
 */
interface PageRetriever {

	/**
	 * @param string $pageName
	 * @return string
	 */
	public function fetchPage( string $pageName ): string;

}
