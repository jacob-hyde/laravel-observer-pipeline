<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use Illuminate\Database\Eloquent\Model;
use JacobHyde\ObserverPipeline\ObserverPipeline;

final class FluentPipelineRunsOnCreatedTest extends TestCase
{
    /** @test */
    public function it_runs_the_pipeline_steps_when_a_model_is_created(): void
    {
        TestStep::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([TestStep::class])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(
            TestStep::$ran,
            'Expected pipeline step to run on model created event, but it did not.'
        );
    }
}

final class TestUser extends Model
{
    protected $table = 'test_users';

    protected $guarded = [];
}

final class TestStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        // In v1, $ctx will be a PipelineContext. For the first failing test, we don't care yet.
        self::$ran = true;
    }
}
