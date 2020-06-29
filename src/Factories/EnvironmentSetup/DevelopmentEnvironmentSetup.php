<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Factories\EnvironmentSetup;

use Doctrine\ORM\Tools\Setup;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Factories\LoggerFactory;
use WMDE\Fundraising\Frontend\Presentation\Presenters\DevelopmentInternalErrorHtmlPresenter;

class DevelopmentEnvironmentSetup implements EnvironmentSetup {

	private ErrorLogHandler $logHandler;

	public function setEnvironmentDependentInstances( FunFunFactory $factory, array $configuration ) {
		$this->logHandler = new ErrorLogHandler();
		$this->setApplicationLogger( $factory, $configuration['logging'] );
		$this->setPaypalLogger( $factory );
		$this->setSofortLogger( $factory );
		$this->setDoctrineConfiguration( $factory );
		$this->setErrorPageHtmlPresenter( $factory );
	}

	private function setApplicationLogger( FunFunFactory $factory, array $loggingConfig ) {
		$factory->setLogger(
			( new LoggerFactory( $loggingConfig ) )
				->getLogger()
		);
	}

	private function setErrorPageHtmlPresenter( FunFunFactory $factory ) {
		$factory->setInternalErrorHtmlPresenter(
			new DevelopmentInternalErrorHtmlPresenter()
		);
	}

	private function setPaypalLogger( FunFunFactory $factory ) {
		$logger = new Logger( 'paypal', [ $this->logHandler ] );
		$factory->setPaypalLogger( $logger );
	}

	private function setSofortLogger( FunFunFactory $factory ) {
		$logger = new Logger( 'sofort', [ $this->logHandler ] );
		$factory->setSofortLogger( $logger );
	}

	private function setDoctrineConfiguration( FunFunFactory $factory ) {
		// Setup will use /tmp for proxies and ArrayCache for caching
		$factory->setDoctrineConfiguration( Setup::createConfiguration( true ) );
	}

}
