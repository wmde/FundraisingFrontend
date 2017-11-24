$( function () {
  /** global: WMDE */

  var initData = $( '#init-form' ),
    store = WMDE.Store.createDonationStore( WMDE.createInitialStateFromViolatedFields(
        initData.data( 'violatedFields' ),
        initData.data( 'initial-validation-result' ) )
    ),
    actions = WMDE.Actions
		currencyFormatter = WMDE.CurrencyFormatter.createCurrencyFormatter( 'de' )
	;

  WMDE.StoreUpdates.connectComponentsToStore(
    [
      WMDE.Components.createAmountComponent( store, $('#amount-typed'), $( '.wrap-amounts input[type="radio"]' ), $( '#amount-hidden' ) ),
      WMDE.Components.createRadioComponent( store, $('input[name="zahlweise"]'), 'paymentType' ),
      WMDE.Components.createPaymentIntervalComponent( store, $('input[name="intervalType"]'), $('input[name="periode"]') ),
      WMDE.Components.createBankDataComponent( store, {
        ibanElement: $( '#iban' ),
        bicElement: $( '#bic' ),
        accountNumberElement: $( '#account-number' ),
        bankCodeElement: $( '#bank-code' ),
        bankNameFieldElement: $( '#field-bank-name' ),
        bankNameDisplayElement: $( '#bank-name' )
      } ),
      WMDE.Components.createRadioComponent( store, $( 'input[name="addressType"]' ), 'addressType' ),
      WMDE.Components.createSelectMenuComponent( store, $( 'select[name="salutation"]' ), 'salutation' ),
      WMDE.Components.createSelectMenuComponent( store, $( '#title' ), 'title' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#first-name' ), 'firstName' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#last-name' ), 'lastName' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#company-name' ), 'companyName' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#street' ), 'street' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#adress-company' ), 'street' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#post-code' ), 'postcode' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#post-code-company' ), 'postcode' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#city' ), 'city' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#city-company' ), 'city' ),
      WMDE.Components.createSelectMenuComponent( store, $( '#country' ), 'country' ),
      WMDE.Components.createSelectMenuComponent( store, $( '#country-company' ), 'country' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#email' ), 'email' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#email-company' ), 'email' ),
      WMDE.Components.createCheckboxComponent( store, $( '#newsletter' ), 'confirmNewsletter' ),
      WMDE.Components.createCheckboxComponent( store, $( '#newsletter-company' ), 'confirmNewsletter' )
    ],
    store,
    'donationFormContent'
  );

  WMDE.StoreUpdates.connectValidatorsToStore(
    function ( initialValues ) {
      return [
        WMDE.ValidationDispatchers.createAmountValidationDispatcher(
          WMDE.FormValidation.createAmountValidator( initData.data( 'validate-amount-url' ) ),
          initialValues
        ),
        WMDE.ValidationDispatchers.createAddressValidationDispatcher(
          WMDE.FormValidation.createAddressValidator(
            initData.data( 'validate-address-url' ),
            WMDE.FormValidation.DefaultRequiredFieldsForAddressType
          ),
          initialValues
        ),
        WMDE.ValidationDispatchers.createEmailValidationDispatcher(
          WMDE.FormValidation.createEmailAddressValidator( initData.data( 'validate-email-address-url' ) ),
          initialValues
        ),
        WMDE.ValidationDispatchers.createBankDataValidationDispatcher(
          WMDE.FormValidation.createBankDataValidator(
            initData.data( 'validate-iban-url' ),
            initData.data( 'generate-iban-url' )
          ),
          initialValues
        )
      ];
    },
    store,
    initData.data( 'initial-form-values' ),
    'donationFormContent'
  );

  // Connect view handlers to changes in specific parts in the global state, designated by 'stateKey'
  WMDE.StoreUpdates.connectViewHandlersToStore(
    [
      {
        viewHandler: WMDE.View.createErrorBoxHandler( $( '#validation-errors' ), {
          amount: 'Betrag',
          paymentType: 'Zahlungsart',
          salutation: 'Anrede',
          title: 'Titel',
          firstName: 'Vorname',
          lastName: 'Nachname',
          companyName: 'Firma',
          street: 'Straße',
          postcode: 'PLZ',
          city: 'Ort',
          country: 'Land',
          email: 'E-Mail',
          iban: 'IBAN',
          bic: 'BIC',
          accountNumber: 'Kontonummer',
          bankCode: 'Bankleitzahl'
        } ),
        stateKey: 'donationInputValidation'
      },
		// Hide anonymous payment when doing direct debit
		{
			viewHandler: WMDE.View.createElementClassSwitcher( $( '#type-donator .wrap-field.anonym .wrap-input' ), /BEZ/, 'disabled' ),
			stateKey: 'donationFormContent.paymentType'
		},
		// Show "needs to support recurring debiting" notice for payments types that provide that info (payment_type_*_recurrent_info)
		{
			viewHandler: WMDE.View.createSimpleVisibilitySwitcher( $( '#payment-method .info-text .info-recurrent' ), /^(1|3|6|12)/ ),
			stateKey: 'donationFormContent.paymentIntervalInMonths'
		},
		{
			viewHandler: WMDE.View.createSuboptionDisplayHandler(
				$( '#recurrence' )
			),
			stateKey: 'donationFormContent.paymentIntervalInMonths'
		},
		{
			viewHandler: WMDE.View.createSuboptionDisplayHandler(
				$( '#donation-payment' )
			),
			stateKey: 'donationFormContent.paymentType'
		},
		{
			viewHandler: WMDE.View.createSuboptionDisplayHandler(
				$( '#type-donator' )
			),
			stateKey: 'donationFormContent.addressType'
		},
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#first-name' ) ),
        stateKey: 'donationInputValidation.firstName'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#last-name' ) ),
        stateKey: 'donationInputValidation.lastName'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#street' ) ),
        stateKey: 'donationInputValidation.street'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $('#adress-company') ),
        stateKey: 'donationInputValidation.street'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#post-code' ) ),
        stateKey: 'donationInputValidation.postcode'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $('#post-code-company') ),
        stateKey: 'donationInputValidation.postcode'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#post-code' ) ),
        stateKey: 'donationInputValidation.postcode'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#city' ) ),
        stateKey: 'donationInputValidation.city'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#city-company' ) ),
        stateKey: 'donationInputValidation.city'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#email' ) ),
        stateKey: 'donationInputValidation.email'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#email-company' ) ),
        stateKey: 'donationInputValidation.email'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#company-name' ) ),
        stateKey: 'donationInputValidation.companyName'
      },
      {

        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#iban' ) ),
        stateKey: 'donationInputValidation.iban'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#bic' ) ),
        stateKey: 'donationInputValidation.bic'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#account-number' ) ),
        stateKey: 'donationInputValidation.accountNumber'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#bank-code' ) ),
        stateKey: 'donationInputValidation.bankCode'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $('#amount-typed') ),
        stateKey: 'donationInputValidation.amount'
      },
		{
			viewHandler: WMDE.View.createCustomAmountField( $('#amount-typed') ),
			stateKey: 'donationInputValidation.amount'
		},
		{
			viewHandler: WMDE.View.SectionInfo.createFrequencySectionInfo(
				$( '.banner .frequency' ),
				{
					'0': 'icon-unique',
					'1': 'icon-repeat_1',
					'3': 'icon-repeat_3',
					'6': 'icon-repeat_6',
					'12': 'icon-repeat_12'
				},
				WMDE.FormDataExtractor.mapFromLabeledRadios( $( '#recurrence .wrap-input' ) ),
				WMDE.FormDataExtractor.mapFromRadioInfoTexts( $( '#recurrence .wrap-field' ) )
			),
			stateKey: [
				'donationFormContent.paymentIntervalInMonths'
			]
		},
		{
			viewHandler: WMDE.View.SectionInfo.createAmountFrequencySectionInfo(
				$( '.amount' ),
				{
					'0': 'icon-unique',
					'1': 'icon-repeat_1',
					'3': 'icon-repeat_3',
					'6': 'icon-repeat_6',
					'12': 'icon-repeat_12'
				},
				WMDE.FormDataExtractor.mapFromLabeledRadios( $( '#recurrence .wrap-input' ) ),
				WMDE.FormDataExtractor.mapFromRadioInfoTexts( $( '#recurrence .wrap-field' ) ),
				currencyFormatter
			),
			stateKey: [
				'donationFormContent.amount',
				'donationFormContent.paymentIntervalInMonths',
				'donationInputValidation.amount' // @todo should contain amount and interval. Add neutral state
			]
		},
		{
			viewHandler: WMDE.View.SectionInfo.createPaymentTypeSectionInfo(
				$( '.payment-method' ),
				{
					'PPL': 'icon-paypal',
					'MCP': 'icon-credit_card2',
					'BEZ': 'icon-SEPA-2',
					'UEB': 'icon-ubeiwsung-1',
					'SUB': 'icon-TODO' // @todo Find icon for SUB
				},
				WMDE.FormDataExtractor.mapFromLabeledRadios( $( '#payment-method .wrap-input' ) ),
				WMDE.FormDataExtractor.mapFromRadioInfoTexts( $( '#payment-method .wrap-field' ) )
			),
			stateKey: [
				'donationFormContent.paymentType',
				'donationFormContent.iban',
				'donationFormContent.bic',
				'validity.paymentData' // @todo should contain bankData validity if applicable, too. Add neutral state
			]
		},
		{
			viewHandler: WMDE.View.SectionInfo.createDonorTypeSectionInfo(
				$( '.donator-type' ),
				{
					'person': 'icon-account_circle',
					'firma': 'icon-work',
					'anonym': 'icon-visibility_off'
				},
				WMDE.FormDataExtractor.mapFromLabeledRadios( $( '#type-donator .wrap-input' ) ),
				WMDE.FormDataExtractor.mapFromSelectOptions( $( '#country' ) )
			),
			stateKey: [
				'donationFormContent.addressType',
				'donationFormContent.salutation',
				'donationFormContent.title',
				'donationFormContent.firstName',
				'donationFormContent.lastName',
				'donationFormContent.companyName',
				'donationFormContent.street',
				'donationFormContent.postcode',
				'donationFormContent.city',
				'donationFormContent.country',
				'donationFormContent.email',
				'validity.address' // @todo This currently does not know "dataEntered", i.e. can not show neutral state
			]
		}
    ],
    store
  );

  // Validity checks for different form parts

  function addressIsValid() {
    var validity = store.getState().validity,
      formContent = store.getState().donationFormContent;
    return formContent.addressType === 'anonym' || validity.address;
  }

  function bankDataIsValid() {
    var state = store.getState();
	  // @fixme: Move special handling of BEZ into reducer/store/validator
    return state.donationFormContent.paymentType !== 'BEZ' ||
    (
    state.donationInputValidation.bic.dataEntered && state.donationInputValidation.bic.isValid &&
    state.donationInputValidation.iban.dataEntered && state.donationInputValidation.iban.isValid
    ) ||
    (
    state.donationInputValidation.accountNumber.dataEntered && state.donationInputValidation.accountNumber.isValid &&
    state.donationInputValidation.bankCode.dataEntered && state.donationInputValidation.bankCode.isValid
    );
  }

  function formDataIsValid() {
    var validity = store.getState().validity;
    return validity.paymentData && addressIsValid() && bankDataIsValid();
  }

  function triggerValidityCheckForPersonalData() {
    var formContent = store.getState().donationFormContent;

    if ( !addressIsValid() ) {
      if ( formContent.addressType === 'person' ) {
        store.dispatch( actions.newMarkEmptyFieldsInvalidAction(
          [ 'salutation', 'firstName', 'lastName', 'street', 'postcode', 'city', 'email' ],
          [ 'companyName' ]
        ) );
      } else if ( formContent.addressType === 'firma' ) {
        store.dispatch( actions.newMarkEmptyFieldsInvalidAction(
          [ 'companyName', 'street', 'postcode', 'city', 'email' ],
          [ 'salutation', 'firstName', 'lastName' ]
        ) );
      }
    }

    if ( !bankDataIsValid() ) {
      store.dispatch( actions.newMarkEmptyFieldsInvalidAction(
        [ 'iban', 'bic' ]
      ) );
    }
  }

  function paymentDataIsValid() {
    var currentState = store.getState();
    return currentState.validity.paymentData;
  }

  // connect DOM elements to actions

  $('form').on('submit', function () {
    triggerValidityCheckForPersonalData();

    if (formDataIsValid()) {
      return true;
    }
    return false;
  });

  // Set initial form values
  var initSetup = initData.data( 'initial-form-values' );
  // backend delivers amount as a german-formatted "float" string
  initSetup.amount = currencyFormatter.parse( initSetup.amount );
  store.dispatch( actions.newInitializeContentAction( initSetup ) );

  var $introBanner = $('.introduction.banner');
  var $introDefault = $('.introduction.default');

  // @todo Check if this are all conditions that would be considered "successful deeplink", warrant the special header
  if (initSetup.amount && initSetup.paymentIntervalInMonths && initSetup.paymentType) {
    $introBanner.removeClass('hidden');
    $introDefault.addClass('hidden');
  }

} );
