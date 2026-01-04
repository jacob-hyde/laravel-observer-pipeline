<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use Illuminate\Support\Facades\Queue;
use JacobHyde\ObserverPipeline\Jobs\RunPipelineStepJob;
use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\NoopStep;

final class EdgeCasesTest extends TestCase
{
    /** @test */
    public function it_handles_empty_async_configuration(): void
    {
        Queue::fake();

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([NoopStep::class])
            ->async([])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        // Should not queue anything
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_handles_pipeline_with_no_async_steps(): void
    {
        Queue::fake();
        EdgeCaseStep::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([EdgeCaseStep::class])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(EdgeCaseStep::$ran);
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_handles_multiple_pipelines_for_same_model_different_events(): void
    {
        EdgeCaseStep1::$ran = false;
        EdgeCaseStep2::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([EdgeCaseStep1::class])
            ->register();

        ObserverPipeline::model(TestUser::class)
            ->on('updated')
            ->pipe([EdgeCaseStep2::class])
            ->register();

        $user = TestUser::query()->create(['name' => 'Jacob']);
        $this->assertTrue(EdgeCaseStep1::$ran);
        $this->assertFalse(EdgeCaseStep2::$ran);

        $user->update(['name' => 'John']);
        $this->assertTrue(EdgeCaseStep2::$ran);
    }

    /** @test */
    public function it_handles_pipeline_with_single_step(): void
    {
        SingleStep::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([SingleStep::class])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(SingleStep::$ran);
    }

    /** @test */
    public function it_handles_continue_on_failure_with_no_failures(): void
    {
        SuccessfulStep1::$ran = false;
        SuccessfulStep2::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([
                SuccessfulStep1::class,
                SuccessfulStep2::class,
            ])
            ->continueOnFailure()
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(SuccessfulStep1::$ran);
        $this->assertTrue(SuccessfulStep2::$ran);
    }

    /** @test */
    public function it_handles_stop_on_failure_with_no_failures(): void
    {
        SuccessfulStep1::$ran = false;
        SuccessfulStep2::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([
                SuccessfulStep1::class,
                SuccessfulStep2::class,
            ])
            ->stopOnFailure()
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(SuccessfulStep1::$ran);
        $this->assertTrue(SuccessfulStep2::$ran);
    }

    /** @test */
    public function it_handles_job_with_deleted_model(): void
    {
        EdgeCaseStep::$ran = false;

        $user = TestUser::query()->create(['name' => 'Jacob']);
        $userId = $user->id;

        $user->delete();

        $job = new RunPipelineStepJob(
            modelClass: TestUser::class,
            modelId: $userId,
            event: 'created',
            stepClass: EdgeCaseStep::class,
            meta: []
        );

        // Should not throw
        $job->handle();

        $this->assertFalse(EdgeCaseStep::$ran);
    }

    /** @test */
    public function it_handles_context_with_empty_original_and_changes(): void
    {
        ContextEmptyStep::$original = null;
        ContextEmptyStep::$changes = null;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([ContextEmptyStep::class])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertIsArray(ContextEmptyStep::$original);
        $this->assertIsArray(ContextEmptyStep::$changes);
    }
}

final class EdgeCaseStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class EdgeCaseStep1
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class EdgeCaseStep2
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class SingleStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class SuccessfulStep1
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class SuccessfulStep2
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class ContextEmptyStep
{
    public static ?array $original = null;
    public static ?array $changes = null;

    public function __invoke($ctx): void
    {
        self::$original = $ctx->original();
        self::$changes = $ctx->changes();
    }
}

