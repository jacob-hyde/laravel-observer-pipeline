<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\NoopStep;

final class PipelineBuilderValidationTest extends TestCase
{
    /** @test */
    public function it_throws_when_registering_without_event(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('event is required');

        ObserverPipeline::model(TestUser::class)
            ->pipe([NoopStep::class])
            ->register();
    }

    /** @test */
    public function it_throws_when_registering_without_steps(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('steps are required');

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->register();
    }

    /** @test */
    public function it_throws_when_registering_with_empty_steps(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('steps are required');

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([])
            ->register();
    }

    /** @test */
    public function it_allows_registering_with_valid_configuration(): void
    {
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([NoopStep::class])
            ->register();

        $this->assertTrue(true); // No exception thrown
    }
}

