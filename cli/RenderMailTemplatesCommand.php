<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Cli;

use FileFetcher\SimpleFileFetcher;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Twig_Environment;
use Twig_Error;
use WMDE\Fundraising\Frontend\App\Bootstrap;
use WMDE\Fundraising\Frontend\App\UrlGeneratorAdapter;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Infrastructure\ConfigReader;
use WMDE\Fundraising\Frontend\App\MailTemplates;
use WMDE\Fundraising\Frontend\Infrastructure\EnvironmentBootstrapper;

/**
 * A command to check and dump mail templates
 *
 * The most useful way to apply this is probably to...
 * - run this once before starting the work on a feature that touches emails, dumping the rendered templates
 * - run it again after the changes, dumping to another folder
 * - then diffing the resulting folders
 *
 * @license GNU GPL v2+
 */
class RenderMailTemplatesCommand extends Command {

	private const NAME = 'dump-mail-tpl';

	protected function configure(): void {
		$this->setName( self::NAME )
			->setDescription( 'Dump rendered Mail_* Twig templates' )
			->setDefinition(
				new InputDefinition( [
					new InputOption(
						'output-path',
						'o',
						InputOption::VALUE_REQUIRED,
						'Output path for rendered text'
					),
				] )
			);
	}

	protected function execute( InputInterface $input, OutputInterface $output ): void {
		$config = $this->getDefaultConfig();
		$config['twig']['strict-variables'] = true;

		$ffFactory = new FunFunFactory( $config );

		require __DIR__ . '/../app/Bootstrap.php';

		$app = Bootstrap::initializeApplication( $ffFactory );
		$app->flush();
		
		$ffFactory->setUrlGenerator( new UrlGeneratorAdapter( $app['url_generator'] ) );

		$mailTemplates = new MailTemplates( $ffFactory );
		$testData = $mailTemplates->get();

		$this->validateTemplateFixtures(
			$testData,
			iterator_to_array( $ffFactory->newMailTemplateFilenameTraversable() ),
			$output
		);

		$outputPath = $input->getOption( 'output-path' ) ?? '';
		if ( $outputPath && substr( $outputPath, -1 ) !== '/' ) {
			$outputPath .= '/';
		}

		$this->renderTemplates( $testData, $ffFactory->getMailerTwig(), $outputPath, $output );
	}

	private function getDefaultConfig(): array {
		$bootstrapper = new EnvironmentBootstrapper( getenv( 'APP_ENV' ) ?: 'dev' );
		$configReader = new ConfigReader(
			new SimpleFileFetcher(),
			...$bootstrapper->getConfigurationPathsForEnvironment( __DIR__ . '/../app/config' )
		);

		return $configReader->getConfig();
	}

	/**
	 * Check that there are templates for all fixtures and (even more important) vice-versa
	 *
	 * @param array $testData Template names and fixture information to render these templates
	 * @param array $mailTemplatePaths
	 * @param OutputInterface $output Command output
	 */
	private function validateTemplateFixtures( array $testData, array $mailTemplatePaths, OutputInterface $output ): void {
		$testTemplateNames = array_keys( $testData );

		$untestedTemplates = array_diff( $mailTemplatePaths, $testTemplateNames );

		if ( !empty( $untestedTemplates ) ) {
			$output->writeln(
				'<error>There are untested templates: ' . implode( ', ', $untestedTemplates ) . '</error>'
			);
		}

		$strayTemplates = array_diff( $testTemplateNames, $mailTemplatePaths );

		if ( !empty( $strayTemplates ) ) {
			$output->writeln(
				'<error>There are tests for non-existing templates: ' . implode( ', ', $strayTemplates ) . '</error>'
			);
		}
	}

	/**
	 * Render all templates and write them to disk to allow a comparison with an alternative data set
	 *
	 * @param array $testData Template names and fixture information to render these templates
	 * @param Twig_Environment $twig The templating engine to render the templates
	 * @param string $outputPath Path where rendered templates will be written to
	 * @param OutputInterface $output Command output
	 */
	private function renderTemplates( array $testData, Twig_Environment $twig, string $outputPath, OutputInterface $output ): void {
		foreach( $testData as $templateFileName => $templateSettings ) {

			if ( empty( $templateSettings['variants'] ) ) {
				$templateSettings['variants'] = [ '' => [] ];
			}

			foreach( $templateSettings['variants'] as $variantName => $additionalContext ) {
				$outputName =
					$outputPath .
					basename( $templateFileName, '.txt.twig' ) .
					( $variantName ? ".$variantName" : '' ) .
					'.txt';

				$output->write( "$outputName" );
				if ( file_exists( $outputName ) ) {
					$output->writeln( "$outputName already exists, skipping ..." );
					continue;
				}

				try {
					file_put_contents(
						$outputName,
						$twig->render(
							$templateFileName,
							array_merge_recursive(
								$templateSettings['context'],
								$additionalContext
							)
						)
					);
				} catch( Twig_Error $e ) {
					$output->writeln( '' );
					$output->writeln( '<error>' . $e->getMessage() . '</error>' );
					$output->writeln( var_export( $e->getSourceContext(), true ) );
				}
				$output->writeln( '' );
			}
		}
	}
}
