<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\AsyncStep;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\NoopStep;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\StepA;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\StepB;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\StepC;

final class PipelineFakeTest extends TestCase
{
    /** @test */
    public function it_records_pipeline_execution_when_faked(): void
    {
        $fake = ObserverPipeline::fake();

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([
                StepA::class,
                StepB::class,
                StepC::class,
            ])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $fake->assertRan(TestUser::class, 'created', [
            StepA::class,
            StepB::class,
            StepC::class,
        ]);
    }

    /** @test */
    public function it_records_individual_step_execution(): void
    {
        $fake = ObserverPipeline::fake();

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([StepA::class])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $fake->assertStepRan(StepA::class);
    }

    /** @test */
    public function it_records_queued_steps_when_faked(): void
    {
        $fake = ObserverPipeline::fake();

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([AsyncStep::class])
            ->async([AsyncStep::class => ['queue' => 'emails']])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $fake->assertStepQueued(AsyncStep::class);
    }

    /** @test */
    public function it_does_not_execute_steps_when_faked(): void
    {
        FakeStep::$ran = false;

        ObserverPipeline::fake();

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([FakeStep::class])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertFalse(FakeStep::$ran, 'Expected step NOT to run when faked.');
    }

    /** @test */
    public function it_does_not_dispatch_jobs_when_faked(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        ObserverPipeline::fake();

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([AsyncStep::class])
            ->async([AsyncStep::class => []])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        \Illuminate\Support\Facades\Queue::assertNothingPushed();
    }

    /** @test */
    public function it_tracks_multiple_pipeline_executions(): void
    {
        $fake = ObserverPipeline::fake();

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([StepA::class])
            ->register();

        ObserverPipeline::model(TestUser::class)
            ->on('updated')
            ->pipe([StepB::class])
            ->register();

        $user = TestUser::query()->create(['name' => 'Jacob']);
        $user->update(['name' => 'John']);

        $fake->assertRan(TestUser::class, 'created', [StepA::class]);
        $fake->assertRan(TestUser::class, 'updated', [StepB::class]);
    }

    /** @test */
    public function it_asserts_step_not_ran_when_it_did_not_run(): void
    {
        $fake = ObserverPipeline::fake();

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([StepA::class])
            ->register();

        // Don't create a user, so pipeline doesn't run

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $fake->assertStepRan(StepA::class);
    }

    /** @test */
    public function it_asserts_step_not_queued_when_it_was_not_queued(): void
    {
        $fake = ObserverPipeline::fake();

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([StepA::class]) // Not async
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $fake->assertStepQueued(StepA::class);
    }
}

final class FakeStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

