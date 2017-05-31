<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Infrastructure;

use WMDE\Fundraising\Frontend\MembershipContext\Domain\Model\EmailAddress;
use WMDE\Fundraising\Frontend\Presentation\TwigTemplate;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class TemplateBasedMailer implements TemplateMailerInterface {

	private $messenger;
	private $template;
	private $subject;

	public function __construct( Messenger $messenger, TwigTemplate $template, string $mailSubject ) {
		$this->messenger = $messenger;
		$this->template = $template;
		$this->subject = $mailSubject;
	}

	/**
	 * @inheritdoc
	 * @throws \RuntimeException
	 */
	public function sendMail( EmailAddress $recipient, array $templateArguments = [] ): void {
		$this->messenger->sendMessageToUser(
			new Message(
				$this->subject,
				$this->template->render( $templateArguments )
			),
			$recipient
		);
	}

}
