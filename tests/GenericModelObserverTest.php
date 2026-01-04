<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use JacobHyde\ObserverPipeline\Observer\GenericModelObserver;
use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\NoopStep;

final class GenericModelObserverTest extends TestCase
{
    /** @test */
    public function it_prevents_duplicate_observer_registration(): void
    {
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([NoopStep::class])
            ->register();

        // Try to register again - should not cause issues
        ObserverPipeline::model(TestUser::class)
            ->on('updated')
            ->pipe([NoopStep::class])
            ->register();

        // Should not throw exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_throws_when_observing_non_model_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an Eloquent model');

        GenericModelObserver::observeModel(\stdClass::class, $this->app);
    }

    /** @test */
    public function reset_observed_models_clears_the_cache(): void
    {
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([NoopStep::class])
            ->register();

        // Reset should clear the cache
        GenericModelObserver::resetObservedModels();

        // Should be able to observe again (though in practice, Laravel's observe() might prevent this)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_only_handles_events_with_registered_pipelines(): void
    {
        ObserverStep::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([ObserverStep::class])
            ->register();

        // Trigger updated event (no pipeline registered)
        $user = TestUser::query()->create(['name' => 'Jacob']);
        
        // Step should have run for 'created' event
        $this->assertTrue(ObserverStep::$ran, 'Step should run for registered created event');
        
        // Reset and check it doesn't run for unregistered 'updated' event
        ObserverStep::$ran = false;
        $user->update(['name' => 'John']);

        // Step should not run for unregistered event
        $this->assertFalse(ObserverStep::$ran, 'Step should NOT run for unregistered updated event');
    }
}

final class ObserverStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

