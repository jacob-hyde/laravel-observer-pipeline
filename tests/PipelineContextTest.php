<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use Illuminate\Database\Eloquent\Model;
use JacobHyde\ObserverPipeline\Support\PipelineContext;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;

final class PipelineContextTest extends TestCase
{
    /** @test */
    public function it_provides_access_to_the_model(): void
    {
        $user = TestUser::query()->create(['name' => 'Jacob']);
        $ctx = new PipelineContext($user, 'created');

        $this->assertSame($user, $ctx->model());
        $this->assertInstanceOf(TestUser::class, $ctx->model());
    }

    /** @test */
    public function it_provides_access_to_the_event(): void
    {
        $user = TestUser::query()->create(['name' => 'Jacob']);
        $ctx = new PipelineContext($user, 'updated');

        $this->assertSame('updated', $ctx->event());
    }

    /** @test */
    public function it_stores_and_retrieves_original_attributes(): void
    {
        $user = TestUser::query()->create(['name' => 'Jacob']);
        $ctx = new PipelineContext($user, 'updated');

        $original = ['id' => 1, 'name' => 'Jacob', 'created_at' => now(), 'updated_at' => now()];
        $ctx->setOriginal($original);

        $this->assertSame($original, $ctx->original());
    }

    /** @test */
    public function it_stores_and_retrieves_changed_attributes(): void
    {
        $user = TestUser::query()->create(['name' => 'Jacob']);
        $ctx = new PipelineContext($user, 'updated');

        $changes = ['name' => 'John'];
        $ctx->setChanges($changes);

        $this->assertSame($changes, $ctx->changes());
    }

    /** @test */
    public function it_stores_and_retrieves_meta_data(): void
    {
        $user = TestUser::query()->create(['name' => 'Jacob']);
        $ctx = new PipelineContext($user, 'created');

        $ctx->set('key1', 'value1');
        $ctx->set('key2', 123);
        $ctx->set('key3', ['nested' => 'data']);

        $this->assertSame('value1', $ctx->get('key1'));
        $this->assertSame(123, $ctx->get('key2'));
        $this->assertSame(['nested' => 'data'], $ctx->get('key3'));
    }

    /** @test */
    public function it_returns_default_value_when_meta_key_does_not_exist(): void
    {
        $user = TestUser::query()->create(['name' => 'Jacob']);
        $ctx = new PipelineContext($user, 'created');

        $this->assertNull($ctx->get('nonexistent'));
        $this->assertSame('default', $ctx->get('nonexistent', 'default'));
    }

    /** @test */
    public function it_returns_all_meta_data(): void
    {
        $user = TestUser::query()->create(['name' => 'Jacob']);
        $ctx = new PipelineContext($user, 'created');

        $ctx->set('key1', 'value1');
        $ctx->set('key2', 'value2');

        $meta = $ctx->meta();

        $this->assertIsArray($meta);
        $this->assertSame('value1', $meta['key1']);
        $this->assertSame('value2', $meta['key2']);
    }

    /** @test */
    public function it_overwrites_existing_meta_values(): void
    {
        $user = TestUser::query()->create(['name' => 'Jacob']);
        $ctx = new PipelineContext($user, 'created');

        $ctx->set('key', 'value1');
        $this->assertSame('value1', $ctx->get('key'));

        $ctx->set('key', 'value2');
        $this->assertSame('value2', $ctx->get('key'));
    }
}

