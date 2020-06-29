<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Factories;

use RuntimeException;
use Twig_Loader_Array;
use Twig_Loader_Filesystem;
use Twig_SimpleFilter;
use WMDE\Fundraising\Frontend\Presentation\FilePrefixer;
use WMDE\Fundraising\Frontend\Presentation\TwigEnvironmentConfigurator;

class TwigFactory {

	private $config;
	private $cachePath;
	private $locale;

	public function __construct( array $config, string $cachePath, string $locale ) {
		$this->config = $config;
		$this->cachePath = $cachePath;
		$this->locale = $locale;
	}

	public function newFileSystemLoader(): ?Twig_Loader_Filesystem {
		if ( empty( $this->config['loaders']['filesystem'] ) ) {
			return null;
		}
		$templateDir = $this->getTemplateDir( $this->config['loaders']['filesystem'] );
		return new Twig_Loader_Filesystem( $templateDir );
	}

	/**
	 * Create an array of absolute template directories from the loader
	 *
	 * @param array $config Configuration for the filesystem loader. The key 'template-dir' can be a string or an array.
	 * @return array
	 */
	private function getTemplateDir( array $config ): array {
		$appRoot = realpath( __DIR__ . '/../..' ) . '/';
		if ( is_string( $config['template-dir'] ) ) {
			return $this->convertToAbsolute( $appRoot, [ $config['template-dir'] ] );
		} elseif ( is_array( $config['template-dir'] ) ) {
			return $this->convertToAbsolute( $appRoot, $config['template-dir'] );
		}

		throw new RuntimeException( 'wrong template directory type' );
	}

	private function convertToAbsolute( string $root, array $dirs ): array {
		return array_map(
				function ( $dir ) use ( $root ) {
					if ( strlen( $dir ) === 0 || $dir[0] !== '/' ) {
						$dir = $root . $dir;
					}
					return $dir;
				},
				$dirs
		);
	}

	public function newArrayLoader(): Twig_Loader_Array {
		$templates = $this->config['loaders']['array'] ?? [];
		return new Twig_Loader_Array( $templates );
	}

	public function newFilePrefixFilter( FilePrefixer $filePrefixer ): Twig_SimpleFilter {
		return new Twig_SimpleFilter( 'prefix_file', [ $filePrefixer, 'prefixFile' ] );
	}

	public function newTwigEnvironmentConfigurator(): TwigEnvironmentConfigurator {
		return new TwigEnvironmentConfigurator( $this->config, $this->cachePath );
	}
}
