import Vue from 'vue';
import Vuex, { StoreOptions } from 'vuex';
import createPayment from '@/store/payment';
import createAddress from '@/store/address';
import createBankData from '@/store/bankdata';

import {
	NS_PAYMENT,
	NS_ADDRESS,
	NS_BANKDATA,
} from './namespaces';

Vue.use( Vuex );

export function createStore() {
	const storeBundle: StoreOptions<any> = {
		modules: {
			[ NS_PAYMENT ]: createPayment(),
			[ NS_ADDRESS ]: createAddress(),
			[ NS_BANKDATA ]: createBankData(),
		},
		strict: process.env.NODE_ENV !== 'production',
	};

	return new Vuex.Store<any>( storeBundle );
}
