<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use Illuminate\Support\Facades\Queue;
use JacobHyde\ObserverPipeline\Jobs\RunPipelineStepJob;
use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\AsyncStep;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\SyncStep;

final class AsyncStepQueueTest extends TestCase
{
    /** @test */
    public function it_queues_async_steps_and_runs_sync_steps_immediately(): void
    {
        Queue::fake();
        SyncStep::reset();

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([
                SyncStep::class,
                AsyncStep::class,
            ])
            ->async([
                AsyncStep::class => ['queue' => 'emails', 'delay' => 5],
            ])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(SyncStep::$ran, 'Expected sync step to run immediately.');

        Queue::assertPushed(RunPipelineStepJob::class, function (RunPipelineStepJob $job): bool {
            return $job->stepClass === AsyncStep::class
                && $job->modelClass === TestUser::class
                && $job->event === 'created';
        });
    }
}
