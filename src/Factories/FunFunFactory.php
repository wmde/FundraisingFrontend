<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Factories;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use FileFetcher\ErrorLoggingFileFetcher;
use FileFetcher\SimpleFileFetcher;
use GuzzleHttp\Client;
use NumberFormatter;
use Pimple\Container;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Swift_MailTransport;
use Swift_NullTransport;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Translation\TranslatorInterface;
use TNvpServiceDispatcher;
use Twig_Environment;
use Twig_Extensions_Extension_Intl;
use Twig_SimpleFunction;
use WMDE\Fundraising\Frontend\DonationContext\DonationAcceptedEventHandler;
use WMDE\Fundraising\Frontend\Infrastructure\Cache\AllOfTheCachePurger;
use WMDE\Fundraising\Frontend\Infrastructure\WordListFileReader;
use WMDE\Fundraising\Frontend\Infrastructure\PageViewTracker;
use WMDE\Fundraising\Frontend\Infrastructure\PiwikServerSideTracker;
use WMDE\Fundraising\Frontend\Infrastructure\ServerSideTracker;
use WMDE\Fundraising\Frontend\MembershipContext\UseCases\ApplyForMembership\ApplyForMembershipPolicyValidator;
use WMDE\Fundraising\Frontend\MembershipContext\UseCases\HandleSubscriptionPaymentNotification\HandleSubscriptionPaymentNotificationUseCase;
use WMDE\Fundraising\Frontend\MembershipContext\UseCases\HandleSubscriptionSignupNotification\HandleSubscriptionSignupNotificationUseCase;
use WMDE\Fundraising\Frontend\Presentation\ContentPage\PageSelector;
use WMDE\Fundraising\Frontend\Presentation\Honorifics;
use WMDE\Fundraising\Frontend\Presentation\Presenters\PageNotFoundPresenter;
use WMDE\Fundraising\Frontend\UseCases\GetInTouch\GetInTouchUseCase;
use WMDE\Fundraising\Frontend\Infrastructure\Cache\AuthorizedCachePurger;
use WMDE\Fundraising\Frontend\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\Frontend\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineCommentFinder;
use WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationAuthorizer;
use WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationEventLogger;
use WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationPrePersistSubscriber;
use WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationTokenFetcher;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Repositories\CommentFinder;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\Frontend\DonationContext\Infrastructure\BestEffortDonationEventLogger;
use WMDE\Fundraising\Frontend\DonationContext\Infrastructure\DonationConfirmationMailer;
use WMDE\Fundraising\Frontend\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\Frontend\DonationContext\Infrastructure\LoggingCommentFinder;
use WMDE\Fundraising\Frontend\DonationContext\Infrastructure\LoggingDonationRepository;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddComment\AddCommentUseCase;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddComment\AddCommentValidator;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddDonation\AddDonationPolicyValidator;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddDonation\AddDonationUseCase;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddDonation\AddDonationValidator;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddDonation\ReferrerGeneralizer;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\CancelDonation\CancelDonationUseCase;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationUseCase;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\HandlePayPalPaymentNotification\HandlePayPalPaymentNotificationUseCase;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\ListComments\ListCommentsUseCase;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\ShowDonationConfirmation\ShowDonationConfirmationUseCase;
use WMDE\Fundraising\Frontend\DonationContext\Validation\DonorAddressValidator;
use WMDE\Fundraising\Frontend\DonationContext\Validation\DonorNameValidator;
use WMDE\Fundraising\Frontend\DonationContext\Validation\DonorValidator;
use WMDE\Fundraising\Frontend\Infrastructure\InternetDomainNameValidator;
use WMDE\Fundraising\Frontend\Infrastructure\LoggingMailer;
use WMDE\Fundraising\Frontend\Infrastructure\LoggingPaymentNotificationVerifier;
use WMDE\Fundraising\Frontend\Infrastructure\Messenger;
use WMDE\Fundraising\Frontend\Infrastructure\OperatorMailer;
use WMDE\Fundraising\Frontend\Infrastructure\PaymentNotificationVerifier;
use WMDE\Fundraising\Frontend\Infrastructure\PayPalPaymentNotificationVerifier;
use WMDE\Fundraising\Frontend\Infrastructure\ProfilerDataCollector;
use WMDE\Fundraising\Frontend\Infrastructure\ProfilingDecoratorBuilder;
use WMDE\Fundraising\Frontend\Infrastructure\RandomTokenGenerator;
use WMDE\Fundraising\Frontend\Infrastructure\TemplateBasedMailer;
use WMDE\Fundraising\Frontend\Infrastructure\TokenGenerator;
use WMDE\Fundraising\Frontend\MembershipContext\Authorization\ApplicationAuthorizer;
use WMDE\Fundraising\Frontend\MembershipContext\Authorization\ApplicationTokenFetcher;
use WMDE\Fundraising\Frontend\MembershipContext\DataAccess\DoctrineApplicationAuthorizer;
use WMDE\Fundraising\Frontend\MembershipContext\DataAccess\DoctrineApplicationPiwikTracker;
use WMDE\Fundraising\Frontend\MembershipContext\DataAccess\DoctrineApplicationRepository;
use WMDE\Fundraising\Frontend\MembershipContext\DataAccess\DoctrineApplicationTokenFetcher;
use WMDE\Fundraising\Frontend\MembershipContext\DataAccess\DoctrineApplicationTracker;
use WMDE\Fundraising\Frontend\MembershipContext\DataAccess\DoctrineMembershipApplicationPrePersistSubscriber;
use WMDE\Fundraising\Frontend\MembershipContext\Domain\Model\EmailAddress;
use WMDE\Fundraising\Frontend\MembershipContext\Domain\Repositories\ApplicationRepository;
use WMDE\Fundraising\Frontend\MembershipContext\Infrastructure\LoggingApplicationRepository;
use WMDE\Fundraising\Frontend\MembershipContext\Tracking\ApplicationPiwikTracker;
use WMDE\Fundraising\Frontend\MembershipContext\Tracking\ApplicationTracker;
use WMDE\Fundraising\Frontend\MembershipContext\UseCases\ApplyForMembership\ApplyForMembershipUseCase;
use WMDE\Fundraising\Frontend\MembershipContext\UseCases\ApplyForMembership\MembershipApplicationValidator;
use WMDE\Fundraising\Frontend\MembershipContext\UseCases\CancelMembershipApplication\CancelMembershipApplicationUseCase;
use WMDE\Fundraising\Frontend\MembershipContext\UseCases\ShowMembershipApplicationConfirmation\ShowMembershipApplicationConfirmationUseCase;
use WMDE\Fundraising\Frontend\PaymentContext\DataAccess\McpCreditCardService;
use WMDE\Fundraising\Frontend\PaymentContext\DataAccess\UniqueTransferCodeGenerator;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\BankDataConverter;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\SimpleTransferCodeGenerator;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\TransferCodeGenerator;
use WMDE\Fundraising\Frontend\PaymentContext\Infrastructure\CreditCardService;
use WMDE\Fundraising\Frontend\PaymentContext\UseCases\CheckIban\CheckIbanUseCase;
use WMDE\Fundraising\Frontend\PaymentContext\UseCases\GenerateIban\GenerateIbanUseCase;
use WMDE\Fundraising\Frontend\Presentation\AmountFormatter;
use WMDE\Fundraising\Frontend\Presentation\CreditCardUrlConfig;
use WMDE\Fundraising\Frontend\Presentation\CreditCardUrlGenerator;
use WMDE\Fundraising\Frontend\Presentation\DonationConfirmationPageSelector;
use WMDE\Fundraising\Frontend\Presentation\FilePrefixer;
use WMDE\Fundraising\Frontend\Presentation\GreetingGenerator;
use WMDE\Fundraising\Frontend\Presentation\PayPalUrlConfig;
use WMDE\Fundraising\Frontend\Presentation\PayPalUrlGenerator;
use WMDE\Fundraising\Frontend\Presentation\Presenters\AddSubscriptionHtmlPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\AddSubscriptionJsonPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\CancelDonationHtmlPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\CancelMembershipApplicationHtmlPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\CommentListHtmlPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\CommentListJsonPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\CommentListRssPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\ConfirmSubscriptionHtmlPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\CreditCardNotificationPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\CreditCardPaymentHtmlPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\DonationConfirmationHtmlPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\DonationFormPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\DonationFormViolationPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\GetInTouchHtmlPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\IbanPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\InternalErrorHtmlPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\MembershipApplicationConfirmationHtmlPresenter;
use WMDE\Fundraising\Frontend\Presentation\Presenters\MembershipFormViolationPresenter;
use WMDE\Fundraising\Frontend\Presentation\TwigTemplate;
use WMDE\Fundraising\Frontend\SubscriptionContext\DataAccess\DoctrineSubscriptionRepository;
use WMDE\Fundraising\Frontend\SubscriptionContext\Domain\Repositories\SubscriptionRepository;
use WMDE\Fundraising\Frontend\SubscriptionContext\Infrastructure\LoggingSubscriptionRepository;
use WMDE\Fundraising\Frontend\SubscriptionContext\UseCases\AddSubscription\AddSubscriptionUseCase;
use WMDE\Fundraising\Frontend\SubscriptionContext\UseCases\ConfirmSubscription\ConfirmSubscriptionUseCase;
use WMDE\Fundraising\Frontend\SubscriptionContext\Validation\SubscriptionDuplicateValidator;
use WMDE\Fundraising\Frontend\SubscriptionContext\Validation\SubscriptionValidator;
use WMDE\Fundraising\Frontend\Validation\AllowedValuesValidator;
use WMDE\Fundraising\Frontend\Validation\AmountPolicyValidator;
use WMDE\Fundraising\Frontend\Validation\PaymentDataValidator;
use WMDE\Fundraising\Frontend\Validation\BankDataValidator;
use WMDE\Fundraising\Frontend\Validation\EmailValidator;
use WMDE\Fundraising\Frontend\Validation\GetInTouchValidator;
use WMDE\Fundraising\Frontend\Validation\IbanValidator;
use WMDE\Fundraising\Frontend\Validation\MembershipFeeValidator;
use WMDE\Fundraising\Frontend\Validation\TemplateNameValidator;
use WMDE\Fundraising\Frontend\Validation\TextPolicyValidator;
use WMDE\Fundraising\ContentProvider\ContentProvider;
use WMDE\Fundraising\Store\Factory as StoreFactory;
use WMDE\Fundraising\Store\Installer;

/**
 * @licence GNU GPL v2+
 */
class FunFunFactory {

	private $config;

	/**
	 * @var Container
	 */
	private $pimple;

	private $addDoctrineSubscribers = true;

	/**
	 * @var Stopwatch|null
	 */
	private $profiler = null;

	public function __construct( array $config ) {
		$this->config = $config;
		$this->pimple = $this->newPimple();
	}

	private function newPimple(): Container {
		$pimple = new Container();

		$pimple['logger'] = function() {
			return new NullLogger();
		};

		$pimple['paypal_logger'] = function() {
			return new NullLogger();
		};

		$pimple['profiler_data_collector'] = function() {
			return new ProfilerDataCollector();
		};

		$pimple['dbal_connection'] = function() {
			return DriverManager::getConnection( $this->config['db'] );
		};

		$pimple['entity_manager'] = function() {
			$entityManager = ( new StoreFactory( $this->getConnection(), $this->getVarPath() . '/doctrine_proxies' ) )
				->getEntityManager();
			if ( $this->addDoctrineSubscribers ) {
				$entityManager->getEventManager()->addEventSubscriber( $this->newDoctrineDonationPrePersistSubscriber() );
				$entityManager->getEventManager()->addEventSubscriber( $this->newDoctrineMembershipApplicationPrePersistSubscriber() );
			}

			return $entityManager;
		};

		$pimple['subscription_repository'] = function() {
			return new LoggingSubscriptionRepository(
				new DoctrineSubscriptionRepository( $this->getEntityManager() ),
				$this->getLogger()
			);
		};

		$pimple['donation_repository'] = function() {
			return new LoggingDonationRepository(
				new DoctrineDonationRepository( $this->getEntityManager() ),
				$this->getLogger()
			);
		};

		$pimple['membership_application_repository'] = function() {
			return new LoggingApplicationRepository(
				new DoctrineApplicationRepository( $this->getEntityManager() ),
				$this->getLogger()
			);
		};

		$pimple['comment_repository'] = function() {
			$finder = new LoggingCommentFinder(
				new DoctrineCommentFinder( $this->getEntityManager() ),
				$this->getLogger()
			);

			return $this->addProfilingDecorator( $finder, 'CommentFinder' );
		};

		$pimple['mail_validator'] = function() {
			return new EmailValidator( new InternetDomainNameValidator() );
		};

		$pimple['subscription_validator'] = function() {
			return new SubscriptionValidator(
				$this->getEmailValidator(),
				$this->newTextPolicyValidator( 'fields' ),
				$this->newSubscriptionDuplicateValidator(),
				$this->newHonorificValidator()
			);
		};

		$pimple['template_name_validator'] = function() {
			return new TemplateNameValidator( $this->getTwig() );
		};

		$pimple['contact_validator'] = function() {
			return new GetInTouchValidator( $this->getEmailValidator() );
		};

		$pimple['greeting_generator'] = function() {
			return new GreetingGenerator();
		};

		$pimple['translator'] = function() {
			$translationFactory = new TranslationFactory();
			$loaders = [
				'json' => $translationFactory->newJsonLoader()
			];
			$locale = $this->config['locale'];
			$messagesPath = $this->getI18nDirectory() . $this->config['translation']['message-dir'];
			$translator = $translationFactory->create( $loaders, $locale );
			foreach ($this->config['translation']['files'] as $domain => $file) {
				$translator->addResource( 'json', $messagesPath . '/' . $file, $locale, $domain );
			}

			return $translator;
		};

		// In the future, this could be locale-specific or filled from a DB table
		$pimple['honorifics'] = function() {
			return new Honorifics( [
				'' => 'Kein Titel',
				'Dr.' => 'Dr.',
				'Prof.' => 'Prof.',
				'Prof. Dr.' => 'Prof. Dr.'
			] );
		};

		$pimple['twig_factory'] = function () {
			return new TwigFactory(
				array_merge_recursive(
					$this->config['twig'],
					['web-basepath' => $this->config['web-basepath']]
				),
				$this->getCachePath() . '/twig',
				$this->config['locale']
			);
		};

		$pimple['twig'] = function() {
			$twigFactory = $this->getTwigFactory();
			$configurator = $twigFactory->newTwigEnvironmentConfigurator();

			$loaders = array_filter( [
				$twigFactory->newFileSystemLoader(),
				$twigFactory->newArrayLoader(), // This is just a fallback for testing
			] );
			$extensions = [
				$twigFactory->newTranslationExtension( $this->getTranslator() ),
				new Twig_Extensions_Extension_Intl()
			];
			$filters = [
				$twigFactory->newFilePrefixFilter(
					$this->getFilePrefixer()
				)
			];
			$functions = [
				new Twig_SimpleFunction(
					'web_content',
					[$this->getContentProvider(), 'getWeb'],
					['is_safe' => ['html']]
				),
				new Twig_SimpleFunction(
					'mail_content',
					[$this->getContentProvider(), 'getMail'],
					['is_safe' => ['all']]
				)
			];

			return $configurator->getEnvironment( $this->pimple['twig_environment'], $loaders, $extensions, $filters, $functions );
		};

		$pimple['messenger_suborganization'] = function() {
			return new Messenger(
				new Swift_MailTransport(),
				$this->getSubOrganizationEmailAddress(),
				$this->config['contact-info']['suborganization']['name']
			);
		};

		$pimple['messenger_organization'] = function() {
			return new Messenger(
				new Swift_MailTransport(),
				$this->getOrganizationEmailAddress(),
				$this->config['contact-info']['organization']['name']
			);
		};

		$pimple['confirmation-page-selector'] = function() {
			return new DonationConfirmationPageSelector( $this->config['confirmation-pages'] );
		};

		$pimple['paypal-payment-notification-verifier'] = function() {
			return new LoggingPaymentNotificationVerifier(
				new PayPalPaymentNotificationVerifier(
					new Client(),
					$this->config['paypal-donation']['base-url'],
					$this->config['paypal-donation']['account-address']
				),
				$this->getLogger()
			);
		};

		$pimple['paypal-membership-fee-notification-verifier'] = function() {
			return new LoggingPaymentNotificationVerifier(
				new PayPalPaymentNotificationVerifier(
					new Client(),
					$this->config['paypal-membership']['base-url'],
					$this->config['paypal-membership']['account-address']
				),
				$this->getLogger()
			);
		};

		$pimple['credit-card-api-service'] = function() {
			return new McpCreditCardService(
				new TNvpServiceDispatcher(
					'IMcpCreditcardService_v1_5',
					'https://sipg.micropayment.de/public/creditcard/v1.5/nvp/'
				),
				$this->config['creditcard']['access-key'],
				$this->config['creditcard']['testmode']
			);
		};

		$pimple['token_generator'] = function() {
			return new RandomTokenGenerator(
				$this->config['token-length'],
				new \DateInterval( $this->config['token-validity-timestamp'] )
			);
		};

		$pimple['page_cache'] = function() {
			return new VoidCache();
		};

		$pimple['rendered_page_cache'] = function() {
			return new VoidCache();
		};

		$pimple['page_view_tracker'] = function () {
			return new PageViewTracker( $this->newServerSideTracker(), $this->config['piwik']['siteUrlBase'] );
		};

		$pimple['cachebusting_fileprefixer'] = function () {
			return new FilePrefixer( $this->getFilePrefix() );
		};

		$pimple['content_page_selector'] = function () {
			$json = (new SimpleFileFetcher())->fetchFile( $this->getI18nDirectory() . '/data/pages.json' );
			$config = json_decode( $json, true ) ?? [];

			return new PageSelector( $config );
		};

		$pimple['content_provider'] = function () {
			return new ContentProvider( [
				'content_path' => $this->getI18nDirectory(),
				'cache' => $this->config['twig']['enable-cache'] ? $this->getCachePath() . '/content' : false,
				'globals' => [
					'basepath' => $this->config['web-basepath']
				]
			] );
		};

		return $pimple;
	}

	public function getConnection(): Connection {
		return $this->pimple['dbal_connection'];
	}

	public function getEntityManager(): EntityManager {
		return $this->pimple['entity_manager'];
	}

	private function newDonationEventLogger(): DonationEventLogger {
		return new BestEffortDonationEventLogger(
			new DoctrineDonationEventLogger( $this->getEntityManager() ),
			$this->getLogger()
		);
	}

	public function newInstaller(): Installer {
		return ( new StoreFactory( $this->getConnection() ) )->newInstaller();
	}

	public function newListCommentsUseCase(): ListCommentsUseCase {
		return new ListCommentsUseCase( $this->getCommentFinder() );
	}

	public function newCommentListJsonPresenter(): CommentListJsonPresenter {
		return new CommentListJsonPresenter();
	}

	public function newCommentListRssPresenter(): CommentListRssPresenter {
		return new CommentListRssPresenter( new TwigTemplate(
			$this->getTwig(),
			'Comment_List.rss.twig'
		) );
	}

	public function newCommentListHtmlPresenter(): CommentListHtmlPresenter {
		return new CommentListHtmlPresenter( $this->getLayoutTemplate( 'Comment_List.html.twig', [ 'piwikGoals' => [ 1 ] ] ) );
	}

	private function getCommentFinder(): CommentFinder {
		return $this->pimple['comment_repository'];
	}

	public function getSubscriptionRepository(): SubscriptionRepository {
		return $this->pimple['subscription_repository'];
	}

	public function setSubscriptionRepository( SubscriptionRepository $subscriptionRepository ) {
		$this->pimple['subscription_repository'] = $subscriptionRepository;
	}

	private function getSubscriptionValidator(): SubscriptionValidator {
		return $this->pimple['subscription_validator'];
	}

	public function getEmailValidator(): EmailValidator {
		return $this->pimple['mail_validator'];
	}

	public function getTemplateNameValidator(): TemplateNameValidator {
		return $this->pimple['template_name_validator'];
	}

	public function newAddSubscriptionHtmlPresenter(): AddSubscriptionHtmlPresenter {
		return new AddSubscriptionHtmlPresenter( $this->getLayoutTemplate( 'Subscription_Form.html.twig' ), $this->getTranslator() );
	}

	public function newConfirmSubscriptionHtmlPresenter(): ConfirmSubscriptionHtmlPresenter {
		return new ConfirmSubscriptionHtmlPresenter(
			$this->getLayoutTemplate( 'Confirm_Subscription.twig' ),
			$this->getTranslator()
		);
	}

	public function newAddSubscriptionJsonPresenter(): AddSubscriptionJsonPresenter {
		return new AddSubscriptionJsonPresenter( $this->getTranslator() );
	}

	public function newGetInTouchHtmlPresenter(): GetInTouchHtmlPresenter {
		return new GetInTouchHtmlPresenter( $this->getLayoutTemplate( 'contact_form.html.twig' ), $this->getTranslator() );
	}

	public function setTwigEnvironment( Twig_Environment $twig ) {
		$this->pimple['twig_environment'] = $twig;
	}

	public function getTwig(): Twig_Environment {
		return $this->pimple['twig'];
	}

	/**
	 * Get a template, with the content for the layout areas filled in.
	 *
	 * @param string $templateName
	 * @param array $context Additional variables for the template
	 * @return TwigTemplate
	 */
	public function getLayoutTemplate( string $templateName, array $context = [] ): TwigTemplate {
		 return new TwigTemplate(
			$this->getTwig(),
			$templateName,
			array_merge( $this->getDefaultTwigVariables(), $context )
		);
	}

	/**
	 * Get a layouted template that includes another template
	 *
	 * @deprecated Change the template to use extend and block and call getLayoutTemplate instead.
	 *
	 * @param string $templateName Template to include
	 * @return TwigTemplate
	 */
	private function getIncludeTemplate( string $templateName, array $context = [] ): TwigTemplate {
		return new TwigTemplate(
			$this->getTwig(),
			'Include_in_Layout.twig',
			array_merge(
				$this->getDefaultTwigVariables(),
				[ 'main_template' => $templateName ],
				$context
			)
		);
	}

	private function getDefaultTwigVariables() {
		return [
			'honorifics' => $this->getHonorifics()->getList(),
			'header_template' => $this->config['default-layout-templates']['header'],
			'footer_template' => $this->config['default-layout-templates']['footer'],
			'no_js_notice_template' => $this->config['default-layout-templates']['no-js-notice'],
			'piwik' => $this->config['piwik'],
			'locale' => $this->config['locale'],
		];
	}

	private function newReferrerGeneralizer() {
		return new ReferrerGeneralizer(
			$this->config['referrer-generalization']['default'],
			$this->config['referrer-generalization']['domain-map']
		);
	}

	public function getLogger(): LoggerInterface {
		return $this->pimple['logger'];
	}

	public function getPaypalLogger(): LoggerInterface {
		return $this->pimple['paypal_logger'];
	}

	private function getVarPath(): string {
		return __DIR__ . '/../../var';
	}

	public function getCachePath(): string {
		return $this->getVarPath() . '/cache';
	}

	public function getLoggingPath(): string {
		return $this->getVarPath() . '/log';
	}

	public function getTemplatePath(): string {
		return __DIR__ . '/../../app/fundraising-frontend-content/templates';
	}

	public function newAddSubscriptionUseCase(): AddSubscriptionUseCase {
		return new AddSubscriptionUseCase(
			$this->getSubscriptionRepository(),
			$this->getSubscriptionValidator(),
			$this->newAddSubscriptionMailer()
		);
	}

	public function newConfirmSubscriptionUseCase(): ConfirmSubscriptionUseCase {
		return new ConfirmSubscriptionUseCase(
			$this->getSubscriptionRepository(),
			$this->newConfirmSubscriptionMailer()
		);
	}

	private function newAddSubscriptionMailer(): TemplateBasedMailer {
		return $this->newTemplateMailer(
			$this->getSuborganizationMessenger(),
			new TwigTemplate(
				$this->getTwig(),
				'Mail_Subscription_Request.txt.twig',
				[
					'greeting_generator' => $this->getGreetingGenerator()
				]
			),
			'mail_subject_subscription'
		);
	}

	private function newConfirmSubscriptionMailer(): TemplateBasedMailer {
		return $this->newTemplateMailer(
			$this->getSuborganizationMessenger(),
			new TwigTemplate(
					$this->getTwig(),
					'Mail_Subscription_Confirmation.txt.twig',
					[ 'greeting_generator' => $this->getGreetingGenerator() ]
			),
			'mail_subject_subscription_confirmed'
		);
	}

	private function newTemplateMailer( Messenger $messenger, TwigTemplate $template, string $messageKey ): TemplateBasedMailer {
		$mailer = new TemplateBasedMailer(
			$messenger,
			$template,
			$this->getTranslator()->trans( $messageKey )
		);

		$mailer = new LoggingMailer( $mailer, $this->getLogger() );

		return $this->addProfilingDecorator( $mailer, 'Mailer' );
	}

	public function getGreetingGenerator() {
		return $this->pimple['greeting_generator'];
	}

	public function newCheckIbanUseCase(): CheckIbanUseCase {
		return new CheckIbanUseCase( $this->newBankDataConverter(), $this->newIbanValidator() );
	}

	public function newGenerateIbanUseCase(): GenerateIbanUseCase {
		return new GenerateIbanUseCase( $this->newBankDataConverter(), $this->newIbanValidator() );
	}

	public function newIbanPresenter(): IbanPresenter {
		return new IbanPresenter();
	}

	public function newBankDataConverter() {
		return new BankDataConverter( $this->config['bank-data-file'] );
	}

	public function setSubscriptionValidator( SubscriptionValidator $subscriptionValidator ) {
		$this->pimple['subscription_validator'] = $subscriptionValidator;
	}

	public function newGetInTouchUseCase() {
		return new GetInTouchUseCase(
			$this->getContactValidator(),
			$this->newContactOperatorMailer(),
			$this->newContactUserMailer()
		);
	}

	private function newContactUserMailer(): TemplateBasedMailer {
		return $this->newTemplateMailer(
			$this->getSuborganizationMessenger(),
			new TwigTemplate( $this->getTwig(), 'Mail_Contact_Confirm_to_User.txt.twig' ),
			'mail_subject_getintouch'
		);
	}

	private function newContactOperatorMailer(): OperatorMailer {
		return new OperatorMailer(
			$this->getSuborganizationMessenger(),
			new TwigTemplate( $this->getTwig(), 'Mail_Contact_Forward_to_Operator.txt.twig' ),
			$this->getTranslator()->trans( 'mail_subject_getintouch_forward' )
		);
	}

	private function getContactValidator(): GetInTouchValidator {
		return $this->pimple['contact_validator'];
	}

	private function newSubscriptionDuplicateValidator(): SubscriptionDuplicateValidator {
		return new SubscriptionDuplicateValidator(
				$this->getSubscriptionRepository(),
				$this->newSubscriptionDuplicateCutoffDate()
		);
	}

	private function newSubscriptionDuplicateCutoffDate(): \DateTime {
		$cutoffDateTime = new \DateTime();
		$cutoffDateTime->sub( new \DateInterval( $this->config['subscription-interval'] ) );
		return $cutoffDateTime;
	}

	private function newHonorificValidator(): AllowedValuesValidator {
		return new AllowedValuesValidator( $this->getHonorifics()->getKeys() );
	}

	private function getHonorifics(): Honorifics {
		return $this->pimple['honorifics'];
	}

	public function newAuthorizedCachePurger(): AuthorizedCachePurger {
		return new AuthorizedCachePurger(
			new AllOfTheCachePurger( $this->getTwig(), $this->getPageCache(), $this->getRenderedPageCache() ),
			$this->config['purging-secret']
		);
	}

	private function newBankDataValidator(): BankDataValidator {
		return new BankDataValidator( $this->newIbanValidator() );
	}

	private function getSuborganizationMessenger(): Messenger {
		return $this->pimple['messenger_suborganization'];
	}

	public function setSuborganizationMessenger( Messenger $messenger ) {
		$this->pimple['messenger_suborganization'] = $messenger;
	}

	private function getOrganizationMessenger(): Messenger {
		return $this->pimple['messenger_organization'];
	}

	public function setOrganizationMessenger( Messenger $messenger ) {
		$this->pimple['messenger_organization'] = $messenger;
	}

	public function setNullMessenger() {
		$this->setSuborganizationMessenger( new Messenger(
			Swift_NullTransport::newInstance(),
			$this->getSubOrganizationEmailAddress()
		) );
		$this->setOrganizationMessenger( new Messenger(
			Swift_NullTransport::newInstance(),
			$this->getOrganizationEmailAddress()
		) );
	}

	public function getSubOrganizationEmailAddress(): EmailAddress {
		return new EmailAddress( $this->config['contact-info']['suborganization']['email'] );
	}

	public function getOrganizationEmailAddress() {
		return new EmailAddress( $this->config['contact-info']['organization']['email'] );
	}

	public function newInternalErrorHtmlPresenter(): InternalErrorHtmlPresenter {
		return new InternalErrorHtmlPresenter( $this->getIncludeTemplate( 'Error_Page.html.twig' ) );
	}

	public function newAccessDeniedHtmlPresenter(): InternalErrorHtmlPresenter {
		return new InternalErrorHtmlPresenter( $this->getLayoutTemplate( 'Access_Denied.twig' ) );
	}

	public function getTranslator(): TranslatorInterface {
		return $this->pimple['translator'];
	}

	public function setTranslator( TranslatorInterface $translator ) {
		$this->pimple['translator'] = $translator;
	}

	public function getTwigFactory(): TwigFactory {
		return $this->pimple['twig_factory'];
	}

	private function newTextPolicyValidator( string $policyName ): TextPolicyValidator {
		$fetcher = new ErrorLoggingFileFetcher(
			new SimpleFileFetcher(),
			$this->getLogger()
		);
		$textPolicyConfig = $this->config['text-policies'][$policyName];
		return new TextPolicyValidator(
			new WordListFileReader(
				$fetcher,
				$textPolicyConfig['badwords'] ? $this->getAbsolutePath( $textPolicyConfig['badwords'] ) : ''
			),
			new WordListFileReader(
				$fetcher,
				$textPolicyConfig['whitewords'] ? $this->getAbsolutePath( $textPolicyConfig['whitewords'] ) : ''
			)
		);
	}

	private function newCommentPolicyValidator(): TextPolicyValidator {
		return $this->newTextPolicyValidator( 'comment' );
	}

	public function newCancelDonationUseCase( string $updateToken ): CancelDonationUseCase {
		return new CancelDonationUseCase(
			$this->getDonationRepository(),
			$this->newCancelDonationMailer(),
			$this->newDonationAuthorizer( $updateToken ),
			$this->newDonationEventLogger()
		);
	}

	private function newCancelDonationMailer(): TemplateBasedMailer {
		return $this->newTemplateMailer(
			$this->getSuborganizationMessenger(),
			new TwigTemplate(
				$this->getTwig(),
				'Mail_Donation_Cancellation_Confirmation.txt.twig',
				[ 'greeting_generator' => $this->getGreetingGenerator() ]
			),
			'mail_subject_confirm_cancellation'
		);
	}

	public function newAddDonationUseCase(): AddDonationUseCase {
		return new AddDonationUseCase(
			$this->getDonationRepository(),
			$this->newDonationValidator(),
			$this->newDonationPolicyValidator(),
			$this->newReferrerGeneralizer(),
			$this->newDonationConfirmationMailer(),
			$this->newBankTransferCodeGenerator(),
			$this->newDonationTokenFetcher()
		);
	}

	private function newBankTransferCodeGenerator(): TransferCodeGenerator {
		return new UniqueTransferCodeGenerator(
			new SimpleTransferCodeGenerator(),
			$this->getEntityManager()
		);
	}

	private function newDonationValidator(): AddDonationValidator {
		return new AddDonationValidator(
			$this->newPaymentDataValidator(),
			$this->newBankDataValidator(),
			$this->getEmailValidator()
		);
	}

	public function newPersonalInfoValidator(): DonorValidator {
		return new DonorValidator(
			new DonorNameValidator(),
			new DonorAddressValidator(),
			$this->getEmailValidator()
		);
	}

	private function newDonationConfirmationMailer(): DonationConfirmationMailer {
		return new DonationConfirmationMailer(
			$this->newTemplateMailer(
				$this->getSuborganizationMessenger(),
				new TwigTemplate(
					$this->getTwig(),
					'Mail_Donation_Confirmation.txt.twig',
					[
						'greeting_generator' => $this->getGreetingGenerator()
					]
				),
				'mail_subject_confirm_donation'
			)
		);
	}

	public function newPayPalUrlGeneratorForDonations() {
		return new PayPalUrlGenerator( $this->getPayPalUrlConfigForDonations() );
	}

	public function newPayPalUrlGeneratorForMembershipApplications() {
		return new PayPalUrlGenerator( $this->getPayPalUrlConfigForMembershipApplications() );
	}

	private function getPayPalUrlConfigForDonations() {
		return PayPalUrlConfig::newFromConfig( $this->config['paypal-donation'] );
	}

	private function getPayPalUrlConfigForMembershipApplications() {
		return PayPalUrlConfig::newFromConfig( $this->config['paypal-membership'] );
	}

	private function newCreditCardUrlGenerator() {
		return new CreditCardUrlGenerator( $this->newCreditCardUrlConfig() );
	}

	private function newCreditCardUrlConfig() {
		return CreditCardUrlConfig::newFromConfig( $this->config['creditcard'] );
	}

	public function getDonationRepository(): DonationRepository {
		return $this->pimple['donation_repository'];
	}

	public function newPaymentDataValidator(): PaymentDataValidator {
		return new PaymentDataValidator( $this->config['donation-minimum-amount'], $this->config['donation-maximum-amount'] );
	}

	private function newAmountFormatter(): AmountFormatter {
		return new AmountFormatter( $this->config['locale'] );
	}

	public function newDecimalNumberFormatter(): NumberFormatter {
		return new NumberFormatter( $this->config['locale'], NumberFormatter::DECIMAL );
	}

	public function newAddCommentUseCase( string $updateToken ): AddCommentUseCase {
		return new AddCommentUseCase(
			$this->getDonationRepository(),
			$this->newDonationAuthorizer( $updateToken ),
			$this->newCommentPolicyValidator(),
			$this->newAddCommentValidator()
		);
	}

	private function newDonationAuthorizer( string $updateToken = null, string $accessToken = null ): DonationAuthorizer {
		return new DoctrineDonationAuthorizer(
			$this->getEntityManager(),
			$updateToken,
			$accessToken
		);
	}

	public function getTokenGenerator(): TokenGenerator {
		return $this->pimple['token_generator'];
	}

	public function newDonationConfirmationPresenter() {
		return new DonationConfirmationHtmlPresenter(
			$this->getIncludeTemplate( 'Donation_Confirmation.html.twig', [ 'piwikGoals' => [ 3 ] ] )
		);
	}

	public function newCreditCardPaymentHtmlPresenter() {
		return new CreditCardPaymentHtmlPresenter(
			$this->getIncludeTemplate( 'Credit_Card_Payment_Iframe.html.twig' ),
			$this->getTranslator(),
			$this->newCreditCardUrlGenerator()
		);
	}

	public function newCancelDonationHtmlPresenter() {
		return new CancelDonationHtmlPresenter(
			$this->getIncludeTemplate( 'Donation_Cancellation_Confirmation.html.twig' )
		);
	}

	public function newApplyForMembershipUseCase(): ApplyForMembershipUseCase {
		return new ApplyForMembershipUseCase(
			$this->getMembershipApplicationRepository(),
			$this->newMembershipApplicationTokenFetcher(),
			$this->newApplyForMembershipMailer(),
			$this->newMembershipApplicationValidator(),
			$this->newApplyForMembershipPolicyValidator(),
			$this->newMembershipApplicationTracker(),
			$this->newMembershipApplicationPiwikTracker()
		);
	}

	private function newApplyForMembershipMailer(): TemplateBasedMailer {
		return $this->newTemplateMailer(
			$this->getOrganizationMessenger(),
			new TwigTemplate(
				$this->getTwig(),
				'Mail_Membership_Application_Confirmation.txt.twig',
				[ 'greeting_generator' => $this->getGreetingGenerator() ]
			),
			'mail_subject_confirm_membership_application'
		);
	}

	private function newMembershipApplicationValidator(): MembershipApplicationValidator {
		return new MembershipApplicationValidator(
			new MembershipFeeValidator(),
			$this->newBankDataValidator(),
			$this->getEmailValidator()
		);
	}

	private function newMembershipApplicationTracker(): ApplicationTracker {
		return new DoctrineApplicationTracker( $this->getEntityManager() );
	}

	private function newMembershipApplicationPiwikTracker(): ApplicationPiwikTracker {
		return new DoctrineApplicationPiwikTracker( $this->getEntityManager() );
	}

	private function newApplyForMembershipPolicyValidator(): ApplyForMembershipPolicyValidator {
		return new ApplyForMembershipPolicyValidator(
			$this->newTextPolicyValidator( 'fields' ),
			$this->config['email-address-blacklist']
		);
	}

	public function newCancelMembershipApplicationUseCase( string $updateToken ): CancelMembershipApplicationUseCase {
		return new CancelMembershipApplicationUseCase(
			$this->newMembershipApplicationAuthorizer( $updateToken ),
			$this->getMembershipApplicationRepository(),
			$this->newCancelMembershipApplicationMailer()
		);
	}

	private function newMembershipApplicationAuthorizer(
		string $updateToken = null, string $accessToken = null ): ApplicationAuthorizer {

		return new DoctrineApplicationAuthorizer(
			$this->getEntityManager(),
			$updateToken,
			$accessToken
		);
	}

	public function getMembershipApplicationRepository(): ApplicationRepository {
		return $this->pimple['membership_application_repository'];
	}

	private function newCancelMembershipApplicationMailer(): TemplateBasedMailer {
		return $this->newTemplateMailer(
			$this->getOrganizationMessenger(),
			new TwigTemplate(
				$this->getTwig(),
				'Mail_Membership_Application_Cancellation_Confirmation.txt.twig',
				[ 'greeting_generator' => $this->getGreetingGenerator() ]
			),
			'mail_subject_confirm_membership_application_cancellation'
		);
	}

	public function newMembershipApplicationConfirmationUseCase( string $accessToken ) {
		return new ShowMembershipApplicationConfirmationUseCase(
			$this->newMembershipApplicationAuthorizer( null, $accessToken ), $this->getMembershipApplicationRepository(),
			$this->newMembershipApplicationTokenFetcher()
		);
	}

	public function newShowDonationConfirmationUseCase( string $accessToken ): ShowDonationConfirmationUseCase {
		return new ShowDonationConfirmationUseCase(
			$this->newDonationAuthorizer( null, $accessToken ),
			$this->newDonationTokenFetcher(),
			$this->getDonationRepository()
		);
	}

	public function setDonationConfirmationPageSelector( DonationConfirmationPageSelector $selector ) {
		$this->pimple['confirmation-page-selector'] = $selector;
	}

	public function getDonationConfirmationPageSelector(): DonationConfirmationPageSelector {
		return $this->pimple['confirmation-page-selector'];
	}

	public function newDonationFormViolationPresenter() {
		// TODO make the template name dependent on the 'form' value from the HTTP POST request
		// (we need different form pages for A/B testing)
		$template = $this->getLayoutTemplate( 'Donation_Form.html.twig' );
		return new DonationFormViolationPresenter( $template, $this->newAmountFormatter() );
	}

	public function newDonationFormPresenter() {
		// TODO make the template name dependent on the 'form' value from the HTTP POST request
		// (we need different form pages for A/B testing)
		$template = $this->getLayoutTemplate( 'Donation_Form.html.twig' );
		return new DonationFormPresenter( $template, $this->newAmountFormatter() );
	}

	public function newHandlePayPalPaymentNotificationUseCase( string $updateToken ) {
		return new HandlePayPalPaymentNotificationUseCase(
			$this->getDonationRepository(),
			$this->newDonationAuthorizer( $updateToken ),
			$this->newDonationConfirmationMailer(),
			$this->newDonationEventLogger()
		);
	}

	public function newMembershipApplicationSubscriptionSignupNotificationUseCase( string $updateToken ) {
		return new HandleSubscriptionSignupNotificationUseCase(
			$this->getMembershipApplicationRepository(),
			$this->newMembershipApplicationAuthorizer( $updateToken ),
			$this->newApplyForMembershipMailer(),
			$this->getLogger()
		);
	}

	public function newMembershipApplicationSubscriptionPaymentNotificationUseCase( string $updateToken ) {
		return new HandleSubscriptionPaymentNotificationUseCase(
			$this->getMembershipApplicationRepository(),
			$this->newMembershipApplicationAuthorizer( $updateToken ),
			$this->newApplyForMembershipMailer(),
			$this->getLogger()
		);
	}

	public function getPayPalPaymentNotificationVerifier(): PaymentNotificationVerifier {
		return $this->pimple['paypal-payment-notification-verifier'];
	}

	public function setPayPalPaymentNotificationVerifier( PaymentNotificationVerifier $verifier ) {
		$this->pimple['paypal-payment-notification-verifier'] = $verifier;
	}

	public function getPayPalMembershipFeeNotificationVerifier(): PaymentNotificationVerifier {
		return $this->pimple['paypal-membership-fee-notification-verifier'];
	}

	public function setPayPalMembershipFeeNotificationVerifier( PaymentNotificationVerifier $verifier ) {
		$this->pimple['paypal-membership-fee-notification-verifier'] = $verifier;
	}

	public function newCreditCardNotificationUseCase( string $updateToken ) {
		return new CreditCardNotificationUseCase(
			$this->getDonationRepository(),
			$this->newDonationAuthorizer( $updateToken ),
			$this->getCreditCardService(),
			$this->newDonationConfirmationMailer(),
			$this->getLogger(),
			$this->newDonationEventLogger()
		);
	}

	public function newCancelMembershipApplicationHtmlPresenter() {
		return new CancelMembershipApplicationHtmlPresenter(
			$this->getIncludeTemplate( 'Membership_Application_Cancellation_Confirmation.html.twig' )
		);
	}

	public function newMembershipApplicationConfirmationHtmlPresenter() {
		return new MembershipApplicationConfirmationHtmlPresenter(
			$this->getIncludeTemplate( 'Membership_Application_Confirmation.html.twig' )
		);
	}

	public function newMembershipFormViolationPresenter() {
		return new MembershipFormViolationPresenter(
			$this->getLayoutTemplate( 'Membership_Application.html.twig' )
		);
	}

	public function setCreditCardService( CreditCardService $ccService ) {
		$this->pimple['credit-card-api-service'] = $ccService;
	}

	public function getCreditCardService(): CreditCardService {
		return $this->pimple['credit-card-api-service'];
	}

	public function newCreditCardNotificationPresenter(): CreditCardNotificationPresenter {
		return new CreditCardNotificationPresenter(
			new TwigTemplate(
				$this->getTwig(),
				'Credit_Card_Payment_Notification.txt.twig',
				[ 'returnUrl' => $this->config['creditcard']['return-url'] ]
			)
		);
	}

	private function newDoctrineDonationPrePersistSubscriber(): DoctrineDonationPrePersistSubscriber {
		$tokenGenerator = $this->getTokenGenerator();
		return new DoctrineDonationPrePersistSubscriber(
			$tokenGenerator,
			$tokenGenerator
		);
	}

	private function newDoctrineMembershipApplicationPrePersistSubscriber(): DoctrineMembershipApplicationPrePersistSubscriber {
		$tokenGenerator = $this->getTokenGenerator();
		return new DoctrineMembershipApplicationPrePersistSubscriber(
			$tokenGenerator,
			$tokenGenerator
		);
	}

	public function setTokenGenerator( TokenGenerator $tokenGenerator ) {
		$this->pimple['token_generator'] = $tokenGenerator;
	}

	public function disableDoctrineSubscribers() {
		$this->addDoctrineSubscribers = false;
	}

	private function newDonationTokenFetcher(): DonationTokenFetcher {
		return new DoctrineDonationTokenFetcher(
			$this->getEntityManager()
		);
	}

	private function newMembershipApplicationTokenFetcher(): ApplicationTokenFetcher {
		return new DoctrineApplicationTokenFetcher(
			$this->getEntityManager()
		);
	}

	private function newDonationPolicyValidator(): AddDonationPolicyValidator {
		return new AddDonationPolicyValidator(
			$this->newDonationAmountPolicyValidator(),
			$this->newTextPolicyValidator( 'fields' ),
			$this->config['email-address-blacklist']
		);
	}

	private function newDonationAmountPolicyValidator(): AmountPolicyValidator {
		// in the future, this might come from the configuration
		return new AmountPolicyValidator( 1000, 1000 );
	}

	public function getDonationTimeframeLimit() {
		return $this->config['donation-timeframe-limit'];
	}

	public function newSystemMessageResponse( string $message ) {
		$test = $this->getIncludeTemplate( 'System_Message.html.twig' );
		return $test->render( [ 'message' => $message ] );
	}

	public function getMembershipApplicationTimeframeLimit() {
		return $this->config['membership-application-timeframe-limit'];
	}

	private function newAddCommentValidator(): AddCommentValidator {
		return new AddCommentValidator();
	}

	private function getPageCache(): Cache {
		return $this->pimple['page_cache'];
	}

	private function getRenderedPageCache(): Cache {
		return $this->pimple['rendered_page_cache'];
	}

	public function enablePageCache() {
		$this->pimple['page_cache'] = function() {
			return new FilesystemCache( $this->getCachePath() . '/pages/raw' );
		};

		$this->pimple['rendered_page_cache'] = function() {
			return new FilesystemCache( $this->getCachePath() . '/pages/rendered' );
		};
	}

	private function addProfilingDecorator( $objectToDecorate, string $profilingLabel ) {
		if ( $this->profiler === null ) {
			return $objectToDecorate;
		}

		$builder = new ProfilingDecoratorBuilder( $this->profiler, $this->getProfilerDataCollector() );

		return $builder->decorate( $objectToDecorate, $profilingLabel );
	}

	public function setProfiler( Stopwatch $profiler ) {
		$this->profiler = $profiler;
	}

	public function setEmailValidator( EmailValidator $validator ) {
		$this->pimple['mail_validator'] = $validator;
	}

	public function setLogger( LoggerInterface $logger ) {
		$this->pimple['logger'] = $logger;
	}

	public function setPaypalLogger( LoggerInterface $logger ) {
		$this->pimple['paypal_logger'] = $logger;
	}

	public function getProfilerDataCollector(): ProfilerDataCollector {
		return $this->pimple['profiler_data_collector'];
	}

	private function newIbanValidator(): IbanValidator {
		return new IbanValidator( $this->newBankDataConverter(), $this->config['banned-ibans'] );
	}

	public function setFilePrefixer( FilePrefixer $prefixer ): void {
		$this->pimple['cachebusting_fileprefixer'] = $prefixer;
	}

	private function getFilePrefixer(): FilePrefixer {
		return $this->pimple['cachebusting_fileprefixer'];
	}

	private function getFilePrefix(): string {
		$prefixContentFile = $this->getVarPath() . '/file_prefix.txt';
		if ( !file_exists( $prefixContentFile ) ) {
			return '';
		}
		return $prefix = preg_replace( '/[^0-9a-f]/', '', file_get_contents( $prefixContentFile ) );
	}

	public function newDonationAcceptedEventHandler( string $updateToken ): DonationAcceptedEventHandler {
		return new DonationAcceptedEventHandler(
			$this->newDonationAuthorizer( $updateToken ),
			$this->getDonationRepository(),
			$this->newDonationConfirmationMailer()
		);
	}

	public function newPageNotFoundHtmlPresenter(): PageNotFoundPresenter {
		return new PageNotFoundPresenter( $this->getLayoutTemplate( 'Page_not_found.html.twig' ) );
	}

	public function setPageViewTracker( PageViewTracker $tracker ) {
		$this->pimple['page_view_tracker'] = function () use ( $tracker )  {
			return $tracker;
		};
	}

	public function getPageViewTracker(): PageViewTracker {
		return $this->pimple['page_view_tracker'];
	}

	public function newServerSideTracker(): ServerSideTracker {
		// the "https:" prefix does NOT get any slashes because baseURL is stored in a protocol-agnostic way
		// (e.g. "//tracking.wikimedia.de" )
		return new PiwikServerSideTracker(
			new \PiwikTracker( $this->config['piwik']['siteId'], 'https:' . $this->config['piwik']['baseUrl'] )
		);
	}

	public function getI18nDirectory(): string {
		return $this->getAbsolutePath( $this->config['i18n-base-path'] ) . '/' . $this->config['locale'];
	}

	/**
	 * If the pathname does not start with a slash, make the path absolute to root dir of application
	 */
	private function getAbsolutePath( string $path ): string {
		if ( $path[0] === '/' ) {
			return $path;
		}
		return __DIR__ . '/../../' . $path;
	}

	public function setContentPagePageSelector( PageSelector $pageSelector ): void {
		$this->pimple['content_page_selector'] = $pageSelector;
	}

	public function getContentPagePageSelector(): PageSelector {
		return $this->pimple['content_page_selector'];
	}

	public function setContentProvider( ContentProvider $contentProvider ): void {
		$this->pimple['content_provider'] = $contentProvider;
	}

	private function getContentProvider(): ContentProvider {
		return $this->pimple['content_provider'];
	}
}
