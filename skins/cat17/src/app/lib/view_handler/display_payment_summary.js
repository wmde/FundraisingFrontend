'use strict';

var objectAssign = require( 'object-assign' ),
	DOM_SELECTORS = {
		data: {
			emtpyText: 'empty-text',
			displayError: 'display-error'
		},
		classes: {
			errorIcon: 'icon-error'
		}
	},
	PaymentSummaryDisplayHandler = {
		intervalTextElement: null,
		amountElement: null,
		paymentTypeElement: null,
		intervalTranslations: null,
		paymentTypeTranslations: null,
		numberFormatter: null,
		intervalIconElement: null,
		intervalIcons: null,
		paymentIconsElement: null,
		paymentIcons: null,
		periodicityTextElement: null,
		periodicityText: null,
		paymentElement: null,
		paymentText: null,
		addressTypeIconElement: null,
		addressTypeIcon: null,
		addressTypeElement: null,
		addressType: null,
		addressTypeTextElement: null,
		countryTranslations: null,
		memberShipTypeElement: null,
		memberShipType: null,
		memberShipTypeIconElement: null,
		memberShipTypeIcon: null,
		memberShipTypeTextElement: null,
		memberShipTypeText: null,
		update: function ( formContent ) {
			this.intervalTextElement.text( this.intervalTranslations[ formContent.paymentIntervalInMonths ] );

			this.updateAmoutIndicators( formContent.amount );

			this.updatePaymentTypeIndicators( formContent.paymentType );

			this.setSummaryIcon( this.intervalIconElement, formContent.paymentIntervalInMonths, this.intervalIcons );
			this.setSummaryIcon( this.paymentIconsElement, formContent.paymentType, this.paymentIcons );
			this.periodicityTextElement.html( this.periodicityText[formContent.paymentIntervalInMonths] );

			var paymentTextFormatted = this.paymentText[formContent.paymentType];
			if( formContent.paymentType == "BEZ" ) {
				paymentTextFormatted = '<div class="col-lg-6 no-gutter">' + paymentTextFormatted + "</div>";
				paymentTextFormatted = paymentTextFormatted.replace( '<br />', '</div><div class="col-lg-6 no-gutter">' );

				if( formContent.accountNumber && formContent.bankCode ) {
					paymentTextFormatted = "<dl class='bank-info'><div><dt>Kontonummer</dt><dd>" + formContent.accountNumber + "</dd></div>" +
						"<div><dt>Bankleitzahl</dt><dd>" + formContent.bankCode + "</dd></div></dl>" + paymentTextFormatted;
				}
				else if( formContent.iban && formContent.bic ) {
					paymentTextFormatted = "<dl class='bank-info'><div><dt>IBAN</dt><dd>" + formContent.iban + "</dd></div>" +
						"<div><dt>BIC</dt><dd>" + formContent.bic + "</dd></div></dl>" + paymentTextFormatted;
				}
			}
			this.paymentElement.html( paymentTextFormatted );
			this.setSummaryIcon( this.addressTypeIconElement, formContent.addressType, this.addressTypeIcon );

			this.updateAddressTypeIndicators( formContent.addressType );

			this.addressTypeTextElement.html( this.getAddressSummaryContent( formContent ) );

			if( this.memberShipTypeElement ) {
				var textMemberShipType = this.memberShipType[formContent.membershipType];
				// @fixme Don't use jQuery here. Instead, interact with the store values and the elements.
				this.memberShipTypeElement.each( function () {
					if( formContent.membershipType ) {
						$( this ).text( textMemberShipType );
					}
				} );

				this.setSummaryIcon( this.memberShipTypeIconElement, formContent.membershipType, this.memberShipTypeIcon );

				this.memberShipTypeTextElement.text( this.memberShipTypeText[formContent.membershipType] );
			}
		},
		updateAmoutIndicators: function ( amount ) {
			var self = this,
				$guiElement;

			this.amountElement.each( function () {
				$guiElement = $( this );
				$guiElement.text( amount === 0 ? $guiElement.data( DOM_SELECTORS.data.emtpyText ) : self.numberFormatter.format( amount ) );
			} );
		},
		updatePaymentTypeIndicators: function ( paymentType ) {
			var self = this,
				$guiElement;

			this.paymentTypeElement.each( function () {
				$guiElement = $( this );
				$guiElement.text( paymentType === '' ? $guiElement.data( DOM_SELECTORS.data.emtpyText ) : self.paymentTypeTranslations[ paymentType ] );
			} );
		},
		updateAddressTypeIndicators: function ( addressType ) {
			var self = this,
				$guiElement;

			this.addressTypeElement.each( function () {
				$guiElement = $( this );
				$guiElement.text( addressType === '' ? $guiElement.data( DOM_SELECTORS.data.emtpyText ) : self.addressType[ addressType ] );
			} );
		},
		getAddressSummaryContent: function ( formContent ) {
			if( formContent.addressType === "person" ) {
				// TODO Escape HTML (T180215)
				// TODO Reuse AddressDisplayHandler
				return (
						formContent.firstName && formContent.lastName ?
							formContent.salutation + ' ' + formContent.title + ' ' + formContent.firstName + ' ' + formContent.lastName + "<br />"
							: ''
					) +
					(formContent.street ? formContent.street + "<br />" : "") +
					(formContent.postcode && formContent.city ? formContent.postcode + " " + formContent.city + "<br />" : "") +
					( formContent.country ? this.countryTranslations[ formContent.country ] + "<br />" : "") +
					formContent.email;
			}
			else if( formContent.addressType === 'firma' ) {
				return (formContent.companyName ? formContent.companyName + "<br />" : "") +
					(formContent.street ? formContent.street + "<br />" : "") +
					(formContent.postcode && formContent.city ? formContent.postcode + " " + formContent.city + "<br />" : "") +
					( formContent.country ? this.countryTranslations[ formContent.country ] + "<br />" : "") +
					formContent.email;
			}

			return "";
		},
		setSummaryIcon: function ( elements, value, iconsDictionary ) {
			var icon = iconsDictionary[ value ];

			elements.removeClass( DOM_SELECTORS.classes.errorIcon );

			// determine the (dynamic) class matching the previous value, remove it from all elements
			if( elements.length && elements.get( 0 ).className.split( ' ' ).length > 1 ) {
				elements.removeClass( elements.get( 0 ).className.split( ' ' ).pop() );
			}

			if( icon === undefined ) {
				elements
					// only configured icons are supposed to communicate validation problems
					.filter( function () {
						return $( this ).data( DOM_SELECTORS.data.displayError ) === true;
					} )
					.addClass( DOM_SELECTORS.classes.errorIcon )
			}
			else {
				elements.addClass( icon );
			}
		}
	};

module.exports = {
	createPaymentSummaryDisplayHandler: function ( intervalTextElement, amountElement, paymentTypeElement,
		intervalTranslations, paymentTypeTranslations, numberFormatter,
		intervalIconElement, intervalIcons, paymentIconsElement, paymentIcons,
		periodicityTextElement, periodicityText, paymentElement, paymentText,
		addressTypeIconElement, addressTypeIcon, addressTypeElement, addressType,
		addressTypeTextElement, countryTranslations,
		memberShipTypeElement, memberShipType, memberShipTypeIconElement,
		memberShipTypeIcon, memberShipTypeTextElement, memberShipTypeText ) {
		return objectAssign( Object.create( PaymentSummaryDisplayHandler ), {
			intervalTextElement: intervalTextElement,
			amountElement: amountElement,
			paymentTypeElement: paymentTypeElement,
			intervalTranslations: intervalTranslations,
			paymentTypeTranslations: paymentTypeTranslations,
			numberFormatter: numberFormatter,
			intervalIconElement: intervalIconElement,
			intervalIcons: intervalIcons,
			paymentIconsElement: paymentIconsElement,
			paymentIcons: paymentIcons,
			periodicityTextElement: periodicityTextElement,
			periodicityText: periodicityText,
			paymentElement: paymentElement,
			paymentText: paymentText,
			addressTypeIconElement: addressTypeIconElement,
			addressTypeIcon: addressTypeIcon,
			addressTypeElement: addressTypeElement,
			addressType: addressType,
			addressTypeTextElement: addressTypeTextElement,
			countryTranslations: countryTranslations,
			memberShipTypeElement: memberShipTypeElement,
			memberShipType: memberShipType,
			memberShipTypeIconElement: memberShipTypeIconElement,
			memberShipTypeIcon: memberShipTypeIcon,
			memberShipTypeTextElement: memberShipTypeTextElement,
			memberShipTypeText: memberShipTypeText
		} );
	}
};
