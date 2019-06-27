import { Validity } from './Validity';

export interface Payment {
    validity: {
        [key: string]: Validity
    },
    values: {
        [key: string]: string
    }
}

export interface AmountData {
    amountValue: string
    amountCustomValue: string
}

export interface IntervalData {
    selectedInterval: Number
}

export interface TypeData {
    selectedType: string
}

export interface InitialPaymentValues {
    amount: string,
    type: string,
    paymentIntervalInMonths: string,
    isCustomAmount: boolean,
}

export interface BankAccountData {
    accountId: string
    bankId: string
}

export interface BankAccountRequest {
    validationUrl: string
    requestParams: object
}

export interface BankAccountResponse {
    status: string
    bic: string
    iban: string
    account: string
    bankCode: string
    bankName: string
}