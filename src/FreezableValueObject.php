<?php

namespace WMDE\Fundraising\Frontend;

trait FreezableValueObject {

	private $isFrozen = false;

	public function freeze() {
		$this->isFrozen = true;
	}

	/**
	 * @throws \RuntimeException
	 */
	protected function assertIsWritable() {
		if ( $this->isFrozen ) {
			throw new \RuntimeException( 'Cannot write to a frozen object!' );
		}
	}

	/**
	 * Throws an exception if any of the fields have null as value.
	 *
	 * @throws \RuntimeException
	 */
	public function assertNoNullFields() {
		foreach ( get_object_vars( $this ) as $fieldName => $fieldValue ) {
			if ( $fieldValue === null ) {
				throw new \RuntimeException( "Field '$fieldName' cannot be null" );
			}
		}
	}

}