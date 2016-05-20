'use strict';

var objectAssign = require( 'object-assign' ),

	/**
	 * View Handler for displaying a field value validity indicator
	 * @class
	 */
	FieldValueValidityIndicator = {
		element: {},

		update: function ( validationState ) {
			if ( validationState === true ) {
				this.element.addClass( 'valid' ).removeClass( 'invalid' )
					.next().addClass( 'icon-ok' ).removeClass( 'icon-bug icon-placeholder' );
			} else if ( validationState === false ) {
				this.element.addClass( 'invalid' ).removeClass( 'valid' )
					.next().addClass( 'icon-bug' ).removeClass( 'icon-ok icon-placeholder' );
			}
		}
	};

module.exports = {
	/**
	 * @param {jQuery} element
	 * @return {FieldValueValidityIndicator}
	 */
	createFieldValueValidityIndicator: function ( element ) {
		return objectAssign( Object.create( FieldValueValidityIndicator ), { element: element } );
	},

	FieldValueValidityIndicator: FieldValueValidityIndicator
};
