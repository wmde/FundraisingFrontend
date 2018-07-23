var _ = require( 'underscore' ),
	validationResult = require( './../validation_result' )
;

module.exports = function ( state ) {
	var result = validationResult.newUndefinedResult(),
		fieldSets = {
			person: [ 'salutation', 'firstName', 'lastName', 'street', 'postcode', 'city', 'email' ],
			firma: [ 'companyName', 'street', 'postcode', 'city', 'email' ]
		},
		respectiveValidators,
		validity
	;

	if ( state.donorUpdateFormContent.addressType === 'person' || state.donorUpdateFormContent.addressType === 'firma' ) {
		respectiveValidators = _.pick( state.donationInputValidation, fieldSets [ state.donorUpdateFormContent.addressType ] );

		result.dataEntered = _.contains( _.pluck( respectiveValidators, 'dataEntered' ), true );

		validity = _.pluck( respectiveValidators, 'isValid' );
		if ( _.contains( validity, false ) ) {
			result.isValid = false;
		} else if ( _.contains( validity, null ) ) {
			result.isValid = null;
		} else {
			result.isValid = true;
		}
	} else if ( state.donorUpdateFormContent.addressType === 'anonym' ) {
		result.dataEntered = true;
		result.isValid = true;
	}

	return result;
};
