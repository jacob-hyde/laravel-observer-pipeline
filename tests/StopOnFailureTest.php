<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use Illuminate\Database\Eloquent\Model;
use JacobHyde\ObserverPipeline\ObserverPipeline;

final class StopOnFailureTest extends TestCase
{
    /** @test */
    public function it_throws_on_first_failure_and_does_not_run_remaining_steps(): void
    {
        AfterFailureStep::$ran = false;

        ObserverPipeline::model(TestUserFailFast::class)
            ->on('created')
            ->pipe([
                ThrowingStep::class,
                AfterFailureStep::class,
            ])
            ->stopOnFailure()
            ->register();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        TestUserFailFast::query()->create(['name' => 'Jacob']);

        $this->assertFalse(AfterFailureStep::$ran, 'Expected steps after failure to NOT run.');
    }
}

final class TestUserFailFast extends Model
{
    protected $table = 'test_users';
    protected $guarded = [];
}

final class ThrowingStep
{
    public function __invoke($ctx): void
    {
        throw new \RuntimeException('boom');
    }
}

final class AfterFailureStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}
