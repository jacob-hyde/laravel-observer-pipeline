<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Support\PipelineContext;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;

final class PipelineContextDataTest extends TestCase
{
    /** @test */
    public function it_provides_original_attributes_in_context(): void
    {
        ContextOriginalStep::$original = null;

        ObserverPipeline::model(TestUser::class)
            ->on('updated')
            ->pipe([ContextOriginalStep::class])
            ->register();

        $user = TestUser::query()->create(['name' => 'Jacob']);
        $user->update(['name' => 'John']);

        $this->assertNotNull(ContextOriginalStep::$original);
        $this->assertArrayHasKey('name', ContextOriginalStep::$original);
        $this->assertSame('Jacob', ContextOriginalStep::$original['name']);
    }

    /** @test */
    public function it_provides_changed_attributes_in_context(): void
    {
        ContextChangesStep::$changes = null;

        ObserverPipeline::model(TestUser::class)
            ->on('updated')
            ->pipe([ContextChangesStep::class])
            ->register();

        $user = TestUser::query()->create(['name' => 'Jacob']);
        $user->update(['name' => 'John']);

        $this->assertNotNull(ContextChangesStep::$changes);
        $this->assertArrayHasKey('name', ContextChangesStep::$changes);
        $this->assertSame('John', ContextChangesStep::$changes['name']);
    }

    /** @test */
    public function it_provides_model_in_context(): void
    {
        ContextModelStep::$model = null;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([ContextModelStep::class])
            ->register();

        $user = TestUser::query()->create(['name' => 'Jacob']);

        $this->assertNotNull(ContextModelStep::$model);
        $this->assertInstanceOf(TestUser::class, ContextModelStep::$model);
        $this->assertSame($user->id, ContextModelStep::$model->id);
    }

    /** @test */
    public function it_provides_event_in_context(): void
    {
        ContextEventStep::$event = null;

        ObserverPipeline::model(TestUser::class)
            ->on('updated')
            ->pipe([ContextEventStep::class])
            ->register();

        $user = TestUser::query()->create(['name' => 'Jacob']);
        $user->update(['name' => 'John']);

        $this->assertSame('updated', ContextEventStep::$event);
    }

    /** @test */
    public function it_allows_steps_to_share_data_via_context(): void
    {
        ContextSharingStep1::$ran = false;
        ContextSharingStep2::$receivedValue = null;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([
                ContextSharingStep1::class,
                ContextSharingStep2::class,
            ])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(ContextSharingStep1::$ran);
        $this->assertSame('shared-value', ContextSharingStep2::$receivedValue);
    }
}

final class ContextOriginalStep
{
    public static ?array $original = null;

    public function __invoke(PipelineContext $ctx): void
    {
        self::$original = $ctx->original();
    }
}

final class ContextChangesStep
{
    public static ?array $changes = null;

    public function __invoke(PipelineContext $ctx): void
    {
        self::$changes = $ctx->changes();
    }
}

final class ContextModelStep
{
    public static ?TestUser $model = null;

    public function __invoke(PipelineContext $ctx): void
    {
        self::$model = $ctx->model();
    }
}

final class ContextEventStep
{
    public static ?string $event = null;

    public function __invoke(PipelineContext $ctx): void
    {
        self::$event = $ctx->event();
    }
}

final class ContextSharingStep1
{
    public static bool $ran = false;

    public function __invoke(PipelineContext $ctx): void
    {
        self::$ran = true;
        $ctx->set('shared-key', 'shared-value');
    }
}

final class ContextSharingStep2
{
    public static ?string $receivedValue = null;

    public function __invoke(PipelineContext $ctx): void
    {
        self::$receivedValue = $ctx->get('shared-key');
    }
}

