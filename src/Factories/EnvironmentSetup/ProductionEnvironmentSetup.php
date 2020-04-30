<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Factories\EnvironmentSetup;

use Doctrine\ORM\Tools\Setup;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Factories\LoggerFactory;

class ProductionEnvironmentSetup implements EnvironmentSetup {

	public function setEnvironmentDependentInstances( FunFunFactory $factory, array $configuration ) {
		$this->initializeLoggers( $factory, $configuration['logging'] );

		$factory->enableCaching();
	}

	private function initializeLoggers( FunFunFactory $factory, array $loggingConfig ) {
		$this->setApplicationLogger( $factory, $loggingConfig );
		$this->setPaypalLogger( $factory );
		$this->setSofortLogger( $factory );
		$this->setDoctrineConfiguration( $factory );
	}

	private function setApplicationLogger( FunFunFactory $factory, array $loggingConfig ) {
		$factory->setLogger(
			( new LoggerFactory( $loggingConfig ) )
				->getLogger()
		);
	}

	private function setPaypalLogger( FunFunFactory $factory ) {
		$logger = new Logger( 'paypal' );

		$streamHandler = new StreamHandler(
			$factory->getLoggingPath() . '/paypal.log'
		);
		$streamHandler->setFormatter( new JsonFormatter() );
		$logger->pushHandler( $streamHandler );

		$factory->setPaypalLogger( $logger );
	}

	private function setSofortLogger( FunFunFactory $factory ) {
		$logger = new Logger( 'sofort' );

		$streamHandler = new StreamHandler( $factory->getLoggingPath() . '/sofort.log' );
		$streamHandler->setFormatter( new JsonFormatter() );
		$logger->pushHandler( $streamHandler );

		$factory->setSofortLogger( $logger );
	}

	private function setDoctrineConfiguration( FunFunFactory $factory ) {
		// Setup will choose its own caching (APCu, Redis, Memcached, Array) based on the PHP environment and its extensions.
		// See https://phabricator.wikimedia.org/T249338
		$factory->setDoctrineConfiguration( Setup::createConfiguration( false, $factory->getWritableApplicationDataPath() . '/doctrine_proxies' ) );
	}
}
