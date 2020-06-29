<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Infrastructure;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Note: the data is made accessible to the template via getter magic,
 * NOT via the $data array field. The result of getFoo is accessible via
 * collector.foo in the template.
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ProfilerDataCollector extends DataCollector {

	public function __construct() {
		$this->data['calls'] = [];
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 * @param Request $request
	 * @param Response $response
	 * @param \Exception|null $exception
	 */
	public function collect( Request $request, Response $response, \Exception $exception = null ): void {
	}

	public function getName(): string {
		return 'fundraising';
	}

	public function getCalls(): array {
		return $this->data['calls'];
	}

	public function addCall( string $serviceName, string $functionName, array $arguments ): void {
		$this->data['calls'][] = [
			'service' => $serviceName,
			'function' => $functionName,
			'arguments' => array_map(
				function ( $argument ) {
					if ( strstr( json_encode( $argument ), 'password' ) === false ) {
						return $argument;
					}

					return 'contains password';
				},
				$arguments
			),
		];
	}

}
