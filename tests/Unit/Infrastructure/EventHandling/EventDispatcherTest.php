<?php

declare(strict_types = 1);

namespace WMDE\Fundraising\Frontend\Tests\Unit\Infrastructure\EventHandling;

use WMDE\Fundraising\Frontend\Infrastructure\EventHandling\EventDispatcher;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\Frontend\Tests\Fixtures\FakeDonationEvent;

/**
 * @covers \WMDE\Fundraising\Frontend\Infrastructure\EventHandling\EventDispatcher
 */
class EventDispatcherTest extends TestCase {

	public function testDispatcherCallsAllListenersForEvent(): void {
		$handler = new class() {
			private bool $listenerOneCalled = false;
			private bool $listenerTwoCalled = false;

			public function firstListener( FakeDonationEvent $evt ): void {
				$this->listenerOneCalled = true;
			}
			public function secondListener( FakeDonationEvent $evt ): void {
				$this->listenerTwoCalled = true;
			}
			public function allListenersCalled(): bool {
				return $this->listenerOneCalled && $this->listenerTwoCalled;
			}
		};

		$dispatcher = new EventDispatcher();
		$dispatcher-> addEventListener( FakeDonationEvent::class, [$handler, 'firstListener'] )
			->addEventListener( FakeDonationEvent::class, [$handler, 'secondListener'] );

		$dispatcher->dispatch( new FakeDonationEvent() );

		$this->assertTrue( $handler->allListenersCalled() );
	}
}