<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use JacobHyde\ObserverPipeline\Jobs\RunPipelineStepJob;
use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Support\PipelineContext;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\AsyncStep;

final class RunPipelineStepJobTest extends TestCase
{
    /** @test */
    public function it_executes_the_step_when_job_is_handled(): void
    {
        JobStep::$ran = false;
        JobStep::$context = null;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([AsyncStep::class])
            ->async([AsyncStep::class => []])
            ->register();

        $user = TestUser::query()->create(['name' => 'Jacob']);

        // Manually create and handle the job
        $job = new RunPipelineStepJob(
            modelClass: TestUser::class,
            modelId: $user->id,
            event: 'created',
            stepClass: JobStep::class,
            meta: ['test-key' => 'test-value']
        );

        $job->handle();

        $this->assertTrue(JobStep::$ran, 'Expected step to run when job is handled.');
        $this->assertNotNull(JobStep::$context);
        $this->assertInstanceOf(PipelineContext::class, JobStep::$context);
        $this->assertSame('test-value', JobStep::$context->get('test-key'));
    }

    /** @test */
    public function it_restores_model_from_database_in_job(): void
    {
        JobModelStep::$model = null;

        $user = TestUser::query()->create(['name' => 'Jacob']);

        $job = new RunPipelineStepJob(
            modelClass: TestUser::class,
            modelId: $user->id,
            event: 'created',
            stepClass: JobModelStep::class,
            meta: []
        );

        $job->handle();

        $this->assertNotNull(JobModelStep::$model);
        $this->assertInstanceOf(TestUser::class, JobModelStep::$model);
        $this->assertSame($user->id, JobModelStep::$model->id);
        $this->assertSame('Jacob', JobModelStep::$model->name);
    }

    /** @test */
    public function it_handles_missing_model_gracefully(): void
    {
        JobStep::$ran = false;

        $job = new RunPipelineStepJob(
            modelClass: TestUser::class,
            modelId: 99999, // Non-existent ID
            event: 'created',
            stepClass: JobStep::class,
            meta: []
        );

        $job->handle();

        // Should not throw, and step should not run
        $this->assertFalse(JobStep::$ran, 'Expected step NOT to run when model is missing.');
    }

    /** @test */
    public function it_restores_meta_data_in_context(): void
    {
        JobMetaStep::$meta = null;

        $user = TestUser::query()->create(['name' => 'Jacob']);

        $job = new RunPipelineStepJob(
            modelClass: TestUser::class,
            modelId: $user->id,
            event: 'created',
            stepClass: JobMetaStep::class,
            meta: [
                'key1' => 'value1',
                'key2' => 123,
                'key3' => ['nested' => 'data'],
            ]
        );

        $job->handle();

        $this->assertNotNull(JobMetaStep::$meta);
        $this->assertSame('value1', JobMetaStep::$meta['key1']);
        $this->assertSame(123, JobMetaStep::$meta['key2']);
        $this->assertSame(['nested' => 'data'], JobMetaStep::$meta['key3']);
    }

    /** @test */
    public function it_sets_original_and_changes_in_context(): void
    {
        JobContextDataStep::$original = null;
        JobContextDataStep::$changes = null;

        $user = TestUser::query()->create(['name' => 'Jacob']);
        $user->update(['name' => 'John']);

        $job = new RunPipelineStepJob(
            modelClass: TestUser::class,
            modelId: $user->id,
            event: 'updated',
            stepClass: JobContextDataStep::class,
            meta: []
        );

        $job->handle();

        $this->assertNotNull(JobContextDataStep::$original);
        $this->assertNotNull(JobContextDataStep::$changes);
    }
}

final class JobStep
{
    public static bool $ran = false;
    public static ?PipelineContext $context = null;

    public function __invoke(PipelineContext $ctx): void
    {
        self::$ran = true;
        self::$context = $ctx;
    }
}

final class JobModelStep
{
    public static ?TestUser $model = null;

    public function __invoke(PipelineContext $ctx): void
    {
        self::$model = $ctx->model();
    }
}

final class JobMetaStep
{
    public static ?array $meta = null;

    public function __invoke(PipelineContext $ctx): void
    {
        self::$meta = $ctx->meta();
    }
}

final class JobContextDataStep
{
    public static ?array $original = null;
    public static ?array $changes = null;

    public function __invoke(PipelineContext $ctx): void
    {
        self::$original = $ctx->original();
        self::$changes = $ctx->changes();
    }
}

