<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;

final class ModelEventsTest extends TestCase
{
    /** @test */
    public function it_runs_pipeline_on_updated_event(): void
    {
        UpdatedStep::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('updated')
            ->pipe([UpdatedStep::class])
            ->register();

        $user = TestUser::query()->create(['name' => 'Jacob']);
        $user->update(['name' => 'John']);

        $this->assertTrue(UpdatedStep::$ran, 'Expected pipeline step to run on model updated event.');
    }

    /** @test */
    public function it_runs_pipeline_on_saved_event(): void
    {
        SavedStep::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('saved')
            ->pipe([SavedStep::class])
            ->register();

        $user = TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(SavedStep::$ran, 'Expected pipeline step to run on model saved event.');
    }

    /** @test */
    public function it_runs_pipeline_on_deleted_event(): void
    {
        DeletedStep::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('deleted')
            ->pipe([DeletedStep::class])
            ->register();

        $user = TestUser::query()->create(['name' => 'Jacob']);
        $user->delete();

        $this->assertTrue(DeletedStep::$ran, 'Expected pipeline step to run on model deleted event.');
    }

    /** @test */
    public function it_runs_pipeline_on_restored_event(): void
    {
        if (!trait_exists(SoftDeletes::class)) {
            $this->markTestSkipped('Soft deletes not available in this Laravel version');
        }

        RestoredStep::$ran = false;

        // Add soft deletes to TestUser for this test
        Schema::table('test_users', function (Blueprint $table): void {
            $table->softDeletes();
        });

        ObserverPipeline::model(TestUserWithSoftDeletes::class)
            ->on('restored')
            ->pipe([RestoredStep::class])
            ->register();

        $created = TestUserWithSoftDeletes::query()->create(['name' => 'Jacob']);
        $created->delete();
        $created->restore();

        $this->assertTrue(RestoredStep::$ran, 'Expected pipeline step to run on model restored event.');
    }

    /** @test */
    public function it_does_not_run_pipeline_for_unregistered_events(): void
    {
        UnregisteredEventStep::$ran = false;

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([UnregisteredEventStep::class])
            ->register();

        $user = TestUser::query()->create(['name' => 'Jacob']);
        
        // Step should have run for 'created' event
        $this->assertTrue(UnregisteredEventStep::$ran, 'Step should run for registered created event');
        
        // Reset and check it doesn't run for unregistered 'updated' event
        UnregisteredEventStep::$ran = false;
        $user->update(['name' => 'John']); // This triggers 'updated', not 'created'

        $this->assertFalse(UnregisteredEventStep::$ran, 'Expected pipeline step NOT to run for unregistered event.');
    }
}

final class UpdatedStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class SavedStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class DeletedStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class RestoredStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class UnregisteredEventStep
{
    public static bool $ran = false;

    public function __invoke($ctx): void
    {
        self::$ran = true;
    }
}

final class TestUserWithSoftDeletes extends Model
{
    use SoftDeletes;

    protected $table = 'test_users';
    protected $guarded = [];
}

