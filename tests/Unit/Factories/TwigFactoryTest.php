<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\Unit\Factories;

use PHPUnit\Framework\TestCase;
use Twig_Loader_Filesystem;
use WMDE\Fundraising\Frontend\Factories\TwigFactory;

/**
 * @covers \WMDE\Fundraising\Frontend\Factories\TwigFactory
 */
class TwigFactoryTest extends TestCase {

	public function testNewFilesystemLoaderCreatesInstance(): void {
		$factory = new TwigFactory(
			[
				'loaders' => [
					'filesystem' => [
						'template-dir' => __DIR__
					]
				]
			],
			'/tmp',
			'de_DE'
		);

		$loader = $factory->newFileSystemLoader();
		$this->assertInstanceOf( Twig_Loader_Filesystem::class, $loader );
		$this->assertSame( [ __DIR__ ], $loader->getPaths() );
	}

	public function testNewFilesystemLoaderUnconfigured_returnsNoInstance(): void {
		$factory = new TwigFactory(
			[
				'loaders' => [
					'filesystem' => [
					]
				]
			],
			'/tmp',
			'de_DE'
		);

		$this->assertNull( $factory->newFileSystemLoader() );
	}

	public function testFilesystemLoaderPrependsRelativePathsToArray(): void {
		$factory = new TwigFactory(
			[
				'loaders' => [
					'filesystem' => [
						'template-dir' => 'tests'
					]
				]
			],
			'/tmp',
			'de_DE'
		);
		$loader = $factory->newFileSystemLoader();
		$this->assertInstanceOf( Twig_Loader_Filesystem::class, $loader );
		$realPath = realpath( $loader->getPaths()[0] );
		$this->assertFalse( $realPath === false, 'path does not exist' );
		$this->assertSame( $realPath, realpath( __DIR__ . '/../../../tests' ) );
	}

}
