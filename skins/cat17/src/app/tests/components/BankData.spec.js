import test from 'tape';
import { shallowMount } from '@vue/test-utils';
import BankData from '../../components/BankData.vue';

function newTestProperties( overrides ) {
	return Object.assign(
		{
			changeIban: function () {},
			changeBic: function () {},
			validateBankData: function () { return Promise.resolve(); },
			changeBankDataValidity: function () {},
			iban: '',
			bic: '',
			isValid: true
		},
		overrides
	);
}

test( 'BankData.vue renders', t => {
	t.plan( 1 );
	const wrapper = shallowMount( BankData );
	t.equal( typeof wrapper, 'object' );
} );

test( 'Given IBAN value, BankData.vue calls changeIban if value looks like IBAN', t => {
	t.plan( 1 );
	let testIban = '';
	const wrapper = shallowMount( BankData, {
		propsData: newTestProperties( {
			changeIban: function ( iban ) { testIban = iban; }
		} )
	} );

	const ibanInput = wrapper.find( '#iban' );
	ibanInput.setValue( 'DE123' );
	ibanInput.trigger( 'input' );

	t.equal( testIban, 'DE123' );
} );

test( 'Given non-German IBAN value and BIC, BankData.vue calls changeBIC if IBAN value looks like IBAN', t => {
	t.plan( 1 );
	let testBIC = '';
	const wrapper = shallowMount( BankData, {
		propsData: newTestProperties( {
			changeBic: function ( bic ) { testBIC = bic; }
		} )
	} );

	const ibanInput = wrapper.find( '#iban' );
	ibanInput.setValue( 'AT123' );
	ibanInput.trigger( 'input' );
	const bicInput = wrapper.find( '#bic' );
	bicInput.setValue( '98765' );
	bicInput.trigger( 'input' );

	t.equal( testBIC, '98765' );
} );

test( 'Given German IBAN value, BIC-field becomes disabled', t => {
	t.plan( 2 );
	const wrapper = shallowMount( BankData, {
		propsData: newTestProperties()
	} );
	const bicInput = wrapper.find( '#bic' );

	t.notOk( bicInput.attributes().disabled, '"disabled" attribute should be false' );

	const ibanInput = wrapper.find( '#iban' );
	ibanInput.setValue( 'DE123' );
	ibanInput.trigger( 'input' );

	t.ok( bicInput.attributes().disabled, '"disabled" attribute should have a truthy value' );
} );

test( 'Given IBAN value changes in IBAN and BIC property, BankData.vue renders them to field values', t => {
	t.plan( 4 );
	const initialProperties = newTestProperties( {} );
	const wrapper = shallowMount( BankData, {
		propsData: initialProperties
	} );
	const bicInput = wrapper.find( '#bic' );
	const ibanInput = wrapper.find( '#iban' );

	t.equal( ibanInput.element.value, '' );
	t.equal( bicInput.element.value, '' );

	wrapper.setProps( {
		...initialProperties,
		bic: '98765',
		iban: 'DE123'
	} );

	t.equal( ibanInput.element.value, 'DE123' );
	t.equal( bicInput.element.value, '98765' );
} );

test( 'Given classic account value, BankData.vue does not render changes to IBAN and BIC field values', t => {
	t.plan( 4 );
	const initialProperties = newTestProperties( {} );
	const wrapper = shallowMount( BankData, {
		propsData: initialProperties
	} );
	const bicInput = wrapper.find( '#bic' );
	const ibanInput = wrapper.find( '#iban' );

	t.equal( ibanInput.element.value, '' );
	t.equal( bicInput.element.value, '' );

	wrapper.setProps( {
		...initialProperties,
		bic: '98765',
		iban: '123'
	} );

	t.equal( ibanInput.element.value, '' );
	t.equal( bicInput.element.value, '' );
} );
