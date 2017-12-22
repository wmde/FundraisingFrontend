'use strict';

var test = require( 'tape-catch' ),
	actions = require( '../lib/actions' );

test( 'newAddPageAction returns action object', function ( t ) {
	var expectedAction = {
		type: 'ADD_PAGE',
		payload: { name: 'firstPage' }
	};
	t.deepEqual( actions.newAddPageAction( 'firstPage' ), expectedAction );
	t.end();
} );

test( 'newNextPageAction returns action object', function ( t ) {
	var expectedAction = {
		type: 'NEXT_PAGE'
	};
	t.deepEqual( actions.newNextPageAction(), expectedAction );
	t.end();
} );

test( 'newPreviousPageAction returns action object', function ( t ) {
	var expectedAction = {
		type: 'PREVIOUS_PAGE'
	};
	t.deepEqual( actions.newPreviousPageAction(), expectedAction );
	t.end();
} );

test( 'newFinishPaymentDataValidationAction returns action object', function ( t ) {
	var expectedAction = {
		type: 'FINISH_PAYMENT_DATA_VALIDATION',
		payload: { status: 'OK' }
	};
	t.deepEqual( actions.newFinishPaymentDataValidationAction( { status: 'OK' } ), expectedAction );
	t.end();
} );

test( 'newSelectAmountAction returns action object', function ( t ) {
	var expectedAction = {
		type: 'SELECT_AMOUNT',
		payload: { amount: '1,99' }
	};
	t.deepEqual( actions.newSelectAmountAction( '1,99' ), expectedAction );
	t.end();
} );

test( 'newInputAmountAction returns action object', function ( t ) {
	var expectedAction = {
		type: 'INPUT_AMOUNT',
		payload: { amount: '1,99' }
	};
	t.deepEqual( actions.newInputAmountAction( '1,99' ), expectedAction );
	t.end();
} );

test( 'newChangeContentAction returns action object', function ( t ) {
	var expectedAction = {
		type: 'CHANGE_CONTENT',
		payload: { contentName: 'email', value: 'nyan@awesomecats.com' }
	};
	t.deepEqual( actions.newChangeContentAction( 'email', 'nyan@awesomecats.com' ), expectedAction );
	t.end();
} );

test( 'newInitializeContentAction returns action object', function ( t ) {
	var expectedAction = {
		type: 'INITIALIZE_CONTENT',
		payload: { paymentType: 'BEZ', amount: '50,00' }
	};
	t.deepEqual( actions.newInitializeContentAction( { paymentType: 'BEZ', amount: '50,00' } ), expectedAction );
	t.end();
} );

test( 'newInitializeValidationAction returns action object', function ( t ) {
	var expectedAction = {
		type: 'INITIALIZE_VALIDATION',
		payload: {
			violatedFields: { email: 'Not a valid email address'},
			initialValues: { email: 'foo' },
			initialValidationResult: { 'address': false }
		}
	};
	t.deepEqual( actions.newInitializeValidationStateAction(
		{ email: 'Not a valid email address'},
		{ email: 'foo' },
		{ 'address': false }
	), expectedAction );
	t.end();
} );

