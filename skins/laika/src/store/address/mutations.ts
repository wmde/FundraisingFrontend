import { MutationTree } from 'vuex';
import { Validity } from '@/view_models/Validity';
import { Helper } from '@/store/util';
import {
	BEGIN_ADDRESS_VALIDATION,
	BEGIN_EMAIL_VALIDATION,
	FINISH_ADDRESS_VALIDATION,
	FINISH_EMAIL_VALIDATION,
	INITIALIZE_ADDRESS,
	MARK_EMPTY_FIELDS_INVALID,
	SET_ADDRESS_FIELD,
	SET_ADDRESS_FIELDS,
	SET_ADDRESS_TYPE,
	SET_NEWSLETTER_OPTIN,
	SET_RECEIPT_OPTOUT,
	SET_VALIDITY,
	VALIDATE_INPUT,
} from '@/store/address/mutationTypes';
import { AddressState, InputField } from '@/view_models/Address';
import { FieldInitialization } from '@/view_models/FieldInitialization';

export const mutations: MutationTree<AddressState> = {
	[ VALIDATE_INPUT ]( state: AddressState, field: InputField ) {
		if ( field.value === '' && field.optionalField ) {
			state.validity[ field.name ] = Validity.VALID;
		} else {
			state.validity[ field.name ] = Helper.inputIsValid( field.value, field.pattern );
		}
	},
	[ MARK_EMPTY_FIELDS_INVALID ]( state: AddressState ) {
		state.requiredFields[ state.addressType ].forEach( ( fieldName: string ) => {
			let addressTypeRequirements = state.requiredFields[ state.addressType ];
			if ( state.validity[ fieldName ] === Validity.INCOMPLETE &&
				addressTypeRequirements[ addressTypeRequirements.indexOf( fieldName ) ] ) {
				state.validity[ fieldName ] = Validity.INVALID;
			}
		} );
		if ( state.validity.addressType === Validity.INCOMPLETE ) {
			state.validity.addressType = Validity.INVALID;
		}
	},
	[ BEGIN_ADDRESS_VALIDATION ]( state: AddressState ) {
		state.serverSideValidationCount++;
	},
	[ FINISH_ADDRESS_VALIDATION ]( state: AddressState, payload ) {
		state.serverSideValidationCount--;
		if ( payload.status === 'OK' ) {
			return;
		}
		state.requiredFields[ state.addressType ].forEach( name => {
			if ( payload.messages[ name ] ) {
				state.validity[ name ] = Validity.INVALID;
			}
		} );
	},
	[ BEGIN_EMAIL_VALIDATION ]( state: AddressState ) {
		state.serverSideValidationCount++;
	},
	[ FINISH_EMAIL_VALIDATION ]( state: AddressState, payload ) {
		state.serverSideValidationCount--;
		if ( payload.status === 'OK' ) {
			return;
		}
		if ( state.requiredFields[ state.addressType ].indexOf( 'email' ) > -1 ) {
			state.validity.email = Validity.INVALID;
		}
	},
	[ SET_ADDRESS_TYPE ]( state: AddressState, type ) {
		state.addressType = type;
	},
	[ SET_ADDRESS_FIELDS ]( state: AddressState, fields ) {
		Object.keys( fields ).forEach( ( field: string ) => {
			const fieldName = fields[ field ];
			if ( state.validity[ fieldName.name ] !== Validity.INVALID ) {
				state.values[ fieldName.name ] = fieldName.value;
			}
		} );
	},
	[ SET_ADDRESS_FIELD ]( state: AddressState, field: InputField ) {
		state.values[ field.name ] = field.value;
	},
	[ SET_NEWSLETTER_OPTIN ]( state: AddressState, optIn ) {
		state.newsletterOptIn = optIn;
	},
	[ SET_RECEIPT_OPTOUT ]( state: AddressState, optOut ) {
		state.receiptOptOut = optOut;
	},
	[ SET_VALIDITY ]( state: AddressState, { name, value } ) {
		state.validity[ name ] = value;
	},
	[ INITIALIZE_ADDRESS ]( state: AddressState, fields: FieldInitialization[] ) {
		fields.forEach( ( field: FieldInitialization ) => {
			state.validity[ field.name ] = field.validity;
			state.values[ field.name ] = field.value;
		} );
	},
};
