<?php

require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use FileFetcher\SimpleFileFetcher;
use WMDE\Fundraising\Frontend\Infrastructure\EnvironmentBootstrapper;
use WMDE\Fundraising\Store\Factory;
use WMDE\Fundraising\Frontend\Infrastructure\ConfigReader;

/**
 * This is a CLI configuration file for Doctrine
 * https://www.doctrine-project.org/projects/doctrine-orm/en/2.6/reference/tools.html
 */

$dotenv = Dotenv\Dotenv::create( __DIR__ );
$dotenv->load();

$bootstrapper = new EnvironmentBootstrapper( getenv( 'APP_ENV' ) ?: 'dev' );
$configReader = new ConfigReader(
	new SimpleFileFetcher(),
	...$bootstrapper->getConfigurationPathsForEnvironment( __DIR__ . '/app/config' )
);

$config = $configReader->getConfig();
$factory = new Factory( DriverManager::getConnection( $config['db'] ), __DIR__ . '/var/doctrine_proxies' );

return ConsoleRunner::createHelperSet( $factory->getEntityManager() );
