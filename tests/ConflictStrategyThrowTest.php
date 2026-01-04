<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\NoopStep;

final class ConflictStrategyThrowTest extends TestCase
{
    /** @test */
    public function it_throws_if_registering_the_same_model_and_event_twice_by_default(): void
    {
        // First registration
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([NoopStep::class])
            ->register();

        // Second registration should conflict (default config: conflicts => throw)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ObserverPipeline conflict');

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([NoopStep::class])
            ->register();
    }
}
