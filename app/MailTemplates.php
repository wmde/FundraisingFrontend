<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\App;

use WMDE\Fundraising\Frontend\Factories\FunFunFactory;

/**
 * This file contains a list of all Mail templates and the variables rendered in them.
 *
 * Some templates contain if statements, leading to different permutations of output, which are rendered individually.
 * These outputs are covered by the "variants", which are automatically recursively merged into the main "context".
 */
class MailTemplates {

	/**
	 * @var FunFunFactory
	 */
	private $factory;

	public function __construct( FunFunFactory $factory ) {
		$this->factory = $factory;
	}

	public function get(): array {
		return [

			'Contact_Confirm_to_User.txt.twig' => [
				'context' => []
			],

			'Contact_Forward_to_Operator.txt.twig' => [
				'context' => [
					'firstName' => 'John',
					'lastName' => 'Doe',
					'emailAddress' => 'j.doe808@example.com',
					'donationNumber' => '123456',
					'subject' => 'Missing Link',
					'category' => 'Other',
					'message' => 'Please advise',
				],
			],

			'Donation_Cancellation_Confirmation.txt.twig' => [
				'context' => [
					'greeting_generator' => $this->factory->getGreetingGenerator(),
					'recipient' => [
						'firstName' => 'Timothy',
						'lastName' => "O'Reilly",
						'salutation' => 'Herr',
						'title' => 'Dr.'
					],
					'donationId' => 42
				]
			],

			'Donation_Confirmation.txt.twig' => [
				'context' => [
					'greeting_generator' => $this->factory->getGreetingGenerator(),
					'donation' => [
						'id' => 42,
						'amount' => 12.34,
						'needsModeration' => false,
					],
					'recipient' => [
						'lastName' => '姜',
						'salutation' => 'Frau',
						'title' => ''
					],
				],
				'variants' => [
					'deposit_unmoderated_non_recurring' => [
						'donation' => [
							'paymentType' => 'UEB',
							'interval' => 0,
							'bankTransferCode' => 'WZF3984Y',
							'receiptOptIn' => true
						]
					],
					'deposit_unmoderated_recurring' => [
						'donation' => [
							'paymentType' => 'UEB',
							'interval' => 6,
							'bankTransferCode' => 'WZF3984Y',
						]
					],
					'direct_debit_unmoderated_non_recurring' => [
						'donation' => [
							'paymentType' => 'BEZ',
							'interval' => 0,
						]
					],
					'direct_debit_unmoderated_recurring' => [
						'donation' => [
							'paymentType' => 'BEZ',
							'interval' => 3,
							'receiptOptIn' => true
						]
					],
					'paypal_unmoderated_non_recurring' => [
						'donation' => [
							'paymentType' => 'PPL',
							'interval' => 0,
						]
					],
					'sofort_unmoderated_non_recurring' => [
						'donation' => [
							'paymentType' => 'SUB',
							'interval' => 0,
							'status' => 'Z'
						]
					],
					'credit_card_unmoderated_recurring' => [
						'donation' => [
							'paymentType' => 'MCP',
							'interval' => 1
						]
					],
					'paypal_unmoderated_recurring' => [
						'donation' => [
							'paymentType' => 'PPL',
							'interval' => 6,
						]
					],
					// moderated all generate the same message, no need to test different payment types
					'micropayment_moderated_recurring' => [
						'donation' => [
							'needsModeration' => true,
							'paymentType' => 'MCP',
							'interval' => 6
						],
					]
				],
			],

			'Membership_Application_Cancellation_Confirmation.txt.twig' => [
				'context' => [
					'greeting_generator' => $this->factory->getGreetingGenerator(),
					'membershipApplicant' => [
						'firstName' => 'Timothy',
						'lastName' => "O'Reilly",
						'salutation' => 'Herr',
						'title' => 'Dr.'
					],
					'applicationId' => 23
				]
			],

			'Membership_Application_Confirmation.txt.twig' => [
				'context' => [
					'greeting_generator' => $this->factory->getGreetingGenerator(),
					'firstName' => 'Timothy',
					'lastName' => "O'Reilly",
					'salutation' => 'Herr',
					'title' => 'Dr.',
					'membershipFee' => 15.23,
				],
				'variants' => [
					'direct_debit_active_yearly' => [
						'membershipType' => 'active',
						'paymentIntervalInMonths' => 12,
						'paymentType' => 'BEZ',
						'hasReceiptEnabled' => true
					],
					'direct_debit_active_yearly_receipt_optout' => [
						'membershipType' => 'active',
						'paymentIntervalInMonths' => 12,
						'paymentType' => 'BEZ',
						'hasReceiptEnabled' => false
					],
					'direct_debit_sustaining_quarterly' => [
						'membershipType' => 'sustaining',
						'paymentIntervalInMonths' => 3,
						'paymentType' => 'BEZ',
						'hasReceiptEnabled' => true
					],
					'paypal_sustaining_monthly' => [
						'membershipType' => 'sustaining',
						'paymentIntervalInMonths' => 1,
						'paymentType' => 'PPL',
						'hasReceiptEnabled' => true
					]
				]
			],

			'Subscription_Confirmation.txt.twig' => [
				'context' => [
					'greeting_generator' => $this->factory->getGreetingGenerator(),
					'subscription' => [
						'email' => 'test@example.com',
						'address' => [
							'lastName' => "O'Reilly",
							'salutation' => 'Herr',
							'title' => 'Dr.'
						]
					]
				]
			],

			'Subscription_Request.txt.twig' => [
				'context' => [
					'greeting_generator' => $this->factory->getGreetingGenerator(),
					'subscription' => [
						'email' => 'test@example.com',
						'confirmationCode' => '00deadbeef',
						'address' => [
							'lastName' => "O'Reilly",
							'salutation' => 'Herr',
							'title' => 'Dr.'
						]
					]
				]
			],
		];
	}
}
