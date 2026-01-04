<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use Illuminate\Database\Eloquent\Model;
use JacobHyde\ObserverPipeline\ObserverPipeline;

final class ContinueOnFailureTest extends TestCase
{
    /** @test */
    public function it_continues_running_steps_but_still_fails_loudly_at_the_end(): void
    {
        AfterFailureStep2::$ran = false;

        ObserverPipeline::model(TestUserContinue::class)
            ->on('created')
            ->pipe([
                ThrowingStep2::class,
                AfterFailureStep2::class,
            ])
            ->continueOnFailure()
            ->register();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom2');

        TestUserContinue::query()->create(['name' => 'Jacob']);

        $this->assertTrue(AfterFailureStep2::$ran, 'Expected later steps to run when continueOnFailure is enabled.');
    }
}

final class TestUserContinue extends Model
{
    protected $table = 'test_users';
    protected $guarded = [];
}

final class ThrowingStep2
{
    public function __invoke($ctx): void
    {
        throw new \RuntimeException('boom2');
    }
}

final class AfterFailureStep2
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}
