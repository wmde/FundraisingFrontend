// main module to expose all submodules
/**
 * Uppercase keys designate namespaces, lowercase keys designate global objects/functions
 */
module.exports = {
	FormValidation: require( './lib/form_validation' ),
	ValidationDispatchers: require( './lib/validation_dispatchers' ),
	Components: require( './lib/form_components' ),
	Store: require( './lib/store' ),
	StoreUpdates: require( './lib/store_update_handling' ),
	View: {
		createSlidingVisibilitySwitcher: require( './lib/view_handler/element_visibility_switcher' ).createSlidingVisibilitySwitcher,
		createSimpleVisibilitySwitcher: require( './lib/view_handler/element_visibility_switcher' ).createSimpleVisibilitySwitcher,
		createElementClassSwitcher: require( './lib/view_handler/element_class_switcher' ).createElementClassSwitcher,
		createErrorBoxHandler:  require( './lib/view_handler/error_box' ).createHandler,
		createFeeOptionSwitcher: require( './lib/view_handler/fee_option_switcher' ).createFeeOptionSwitcher,
		createFieldValueValidityIndicator: require( './lib/view_handler/field_value_validity_indicator' ).createFieldValueValidityIndicator,
		createCountrySpecificAttributesHandler: require( './lib/view_handler/country_specific_attributes' ).createCountrySpecificAttributesHandler,
		createSuboptionDisplayHandler: require( './lib/view_handler/display_field_suboptions' ).createSuboptionDisplayHandler,
		createCustomAmountField: require( './lib/view_handler/custom_amount_field' ).createCustomAmountField,
		SectionInfo: require( './lib/view_handler/section_info' ),
		Animator: require( './lib/view_handler/animator' )
	},
	Actions: require( './lib/actions' ),
	CurrencyFormatter: require( './lib/simple_currency_formatter' ),
	createInitialStateFromViolatedFields: require( './lib/validation_conversion' ).createInitialStateFromViolatedFields,
	FormDataExtractor: require( './lib/form_data_extractor' )
};
