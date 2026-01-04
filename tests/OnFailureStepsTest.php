<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;

final class OnFailureStepsTest extends TestCase
{
    /** @test */
    public function it_runs_on_failure_steps_when_a_step_throws(): void
    {
        OnFailureHandlerStep::$ran = false;
        OnFailureHandlerStep::$exception = null;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([ThrowingStepForFailure::class])
            ->onFailure([OnFailureHandlerStep::class])
            ->register();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('step failed');

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(OnFailureHandlerStep::$ran, 'Expected failure handler to run.');
        $this->assertNotNull(OnFailureHandlerStep::$exception);
        $this->assertInstanceOf(\RuntimeException::class, OnFailureHandlerStep::$exception);
    }

    /** @test */
    public function it_runs_on_failure_steps_even_when_continuing_on_failure(): void
    {
        OnFailureHandlerStep::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([
                ThrowingStepForFailure::class,
                OnFailureAfterFailureStep::class,
            ])
            ->onFailure([OnFailureHandlerStep::class])
            ->continueOnFailure()
            ->register();

        $this->expectException(\RuntimeException::class);

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(OnFailureHandlerStep::$ran, 'Expected failure handler to run.');
        $this->assertTrue(OnFailureAfterFailureStep::$ran, 'Expected later steps to run when continueOnFailure is enabled.');
    }

    /** @test */
    public function it_does_not_run_on_failure_steps_when_no_exception_occurs(): void
    {
        OnFailureHandlerStep::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([OnFailureSuccessfulStep::class])
            ->onFailure([OnFailureHandlerStep::class])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertFalse(OnFailureHandlerStep::$ran, 'Expected failure handler NOT to run when no exception occurs.');
    }

    /** @test */
    public function it_runs_multiple_on_failure_steps(): void
    {
        OnFailureHandlerStep1::$ran = false;
        OnFailureHandlerStep2::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([ThrowingStepForFailure::class])
            ->onFailure([
                OnFailureHandlerStep1::class,
                OnFailureHandlerStep2::class,
            ])
            ->register();

        $this->expectException(\RuntimeException::class);

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(OnFailureHandlerStep1::$ran);
        $this->assertTrue(OnFailureHandlerStep2::$ran);
    }

    /** @test */
    public function it_does_not_let_failure_handler_exceptions_mask_original_exception(): void
    {
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([ThrowingStepForFailure::class])
            ->onFailure([ThrowingFailureHandlerStep::class])
            ->register();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('step failed'); // Original exception, not handler exception

        TestUser::query()->create(['name' => 'Jacob']);
    }

    /** @test */
    public function it_provides_exception_in_context_for_failure_handlers(): void
    {
        ExceptionContextStep::$exception = null;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([ThrowingStepForFailure::class])
            ->onFailure([ExceptionContextStep::class])
            ->register();

        $this->expectException(\RuntimeException::class);

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertNotNull(ExceptionContextStep::$exception);
        $this->assertInstanceOf(\RuntimeException::class, ExceptionContextStep::$exception);
        $this->assertSame('step failed', ExceptionContextStep::$exception->getMessage());
    }
}

final class ThrowingStepForFailure
{
    public function __invoke($ctx): void
    {
        throw new \RuntimeException('step failed');
    }
}

final class OnFailureHandlerStep
{
    public static bool $ran = false;
    public static ?\Throwable $exception = null;

    public function __invoke($ctx): void
    {
        self::$ran = true;
        self::$exception = $ctx->get('_exception');
    }
}

final class OnFailureAfterFailureStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class OnFailureSuccessfulStep
{
    public function __invoke($ctx): void
    {
        // Success
    }
}

final class OnFailureHandlerStep1
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class OnFailureHandlerStep2
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class ThrowingFailureHandlerStep
{
    public function __invoke($ctx): void
    {
        throw new \RuntimeException('handler failed');
    }
}

final class ExceptionContextStep
{
    public static ?\Throwable $exception = null;

    public function __invoke($ctx): void
    {
        self::$exception = $ctx->get('_exception');
    }
}

