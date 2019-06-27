import { Module } from 'vuex';
import { Payment } from '@/view_models/Payment';
import { Validity } from '@/view_models/Validity';
import { actions } from '@/store/payment/actions';
import { getters } from '@/store/payment/getters';
import { mutations } from '@/store/payment/mutations';

export default function (): Module<Payment, any> {
	const state: Payment = {
		validity: {
			amount: Validity.INCOMPLETE,
			type: Validity.INCOMPLETE,
			accountId: Validity.INCOMPLETE,
			bankId: Validity.INCOMPLETE
		},
		values: {
			amount: '', // amount in cents
			interval: '0',
			type: '',
			iban: '',
			bic: '',
			bankName: '',
		},
	};

	const namespaced = true;

	return {
		namespaced,
		state,
		getters,
		mutations,
		actions,
	};
}
