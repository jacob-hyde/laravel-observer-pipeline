<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer;
use JacobHyde\ObserverPipeline\Observer\GenericModelObserver;
use JacobHyde\ObserverPipeline\Registry\PipelineRegistry;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\AttributeRecordedStep;

final class AttributePipelineRunsOnCreatedTest extends TestCase
{
    /** @test */
    public function it_runs_attribute_pipeline_steps_when_a_model_is_created(): void
    {
        AttributeRecordedStep::reset();

        $this->app['config']->set('observer-pipeline.attributes.cache', false);
        $this->app['config']->set('observer-pipeline.attributes.paths', [
            __DIR__ . '/Fixtures/Pipelines',
        ]);

        /** @var AttributeDiscoverer $discoverer */
        $discoverer = $this->app->make(AttributeDiscoverer::class);

        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);

        // register discovered pipelines
        $discoverer->discoverAndRegister($registry, 'throw');

        // IMPORTANT: attribute registration only registers into the registry.
        // We must ensure the model observer is attached for the event to fire.
        GenericModelObserver::observeModel(TestUser::class, $this->app);

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertTrue(
            AttributeRecordedStep::$ran,
            'Expected attribute pipeline step to run on model created event, but it did not.'
        );
    }
}
