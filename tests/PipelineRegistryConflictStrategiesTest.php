<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Registry\PipelineRegistry;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\NoopStep;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\StepA;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\StepB;

final class PipelineRegistryConflictStrategiesTest extends TestCase
{
    /** @test */
    public function prefer_fluent_keeps_existing_fluent_registration(): void
    {
        $this->app['config']->set('observer-pipeline.conflicts', 'prefer_fluent');

        // First registration (fluent)
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([StepA::class])
            ->register();

        // Second registration (fluent) - should be ignored
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([StepB::class])
            ->register();

        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);
        $definition = $registry->get(TestUser::class, 'created');

        $this->assertNotNull($definition);
        $this->assertSame([StepA::class], $definition->steps);
    }

    /** @test */
    public function prefer_fluent_overwrites_attributes_with_fluent(): void
    {
        $this->app['config']->set('observer-pipeline.conflicts', 'prefer_fluent');
        $this->app['config']->set('observer-pipeline.attributes.cache', false);
        $this->app['config']->set('observer-pipeline.attributes.paths', [
            __DIR__ . '/Fixtures/Pipelines',
        ]);

        // First registration (attributes)
        /** @var \JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer $discoverer */
        $discoverer = $this->app->make(\JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer::class);
        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);
        $discoverer->discoverAndRegister($registry, 'prefer_fluent');

        // Second registration (fluent) - should overwrite
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([StepB::class])
            ->register();

        $definition = $registry->get(TestUser::class, 'created');

        $this->assertNotNull($definition);
        $this->assertSame([StepB::class], $definition->steps);
        $this->assertSame('fluent', $registry->source(TestUser::class, 'created'));
    }

    /** @test */
    public function prefer_attributes_keeps_existing_attributes_registration(): void
    {
        $this->app['config']->set('observer-pipeline.conflicts', 'prefer_attributes');
        $this->app['config']->set('observer-pipeline.attributes.cache', false);
        $this->app['config']->set('observer-pipeline.attributes.paths', [
            __DIR__ . '/Fixtures/Pipelines',
        ]);

        // First registration (attributes)
        /** @var \JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer $discoverer */
        $discoverer = $this->app->make(\JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer::class);
        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);
        $discoverer->discoverAndRegister($registry, 'prefer_attributes');

        $firstDefinition = $registry->get(TestUser::class, 'created');
        $this->assertNotNull($firstDefinition);

        // Second registration (fluent) - should be ignored
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([StepB::class])
            ->register();

        $secondDefinition = $registry->get(TestUser::class, 'created');

        $this->assertNotNull($secondDefinition);
        $this->assertSame($firstDefinition->steps, $secondDefinition->steps);
        $this->assertSame('attributes', $registry->source(TestUser::class, 'created'));
    }

    /** @test */
    public function prefer_attributes_overwrites_fluent_with_attributes(): void
    {
        $this->app['config']->set('observer-pipeline.conflicts', 'prefer_attributes');

        // First registration (fluent)
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([StepA::class])
            ->register();

        // Second registration (attributes) - should overwrite
        $this->app['config']->set('observer-pipeline.attributes.cache', false);
        $this->app['config']->set('observer-pipeline.attributes.paths', [
            __DIR__ . '/Fixtures/Pipelines',
        ]);

        /** @var \JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer $discoverer */
        $discoverer = $this->app->make(\JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer::class);
        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);
        $discoverer->discoverAndRegister($registry, 'prefer_attributes');

        $definition = $registry->get(TestUser::class, 'created');

        $this->assertNotNull($definition);
        $this->assertSame('attributes', $registry->source(TestUser::class, 'created'));
    }

    /** @test */
    public function registry_has_returns_true_when_pipeline_exists(): void
    {
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([NoopStep::class])
            ->register();

        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);

        $this->assertTrue($registry->has(TestUser::class, 'created'));
        $this->assertFalse($registry->has(TestUser::class, 'updated'));
    }

    /** @test */
    public function registry_all_returns_all_registered_pipelines(): void
    {
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([NoopStep::class])
            ->register();

        ObserverPipeline::model(TestUser::class)
            ->on('updated')
            ->pipe([NoopStep::class])
            ->register();

        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);
        $all = $registry->all();

        $this->assertArrayHasKey(TestUser::class, $all);
        $this->assertArrayHasKey('created', $all[TestUser::class]);
        $this->assertArrayHasKey('updated', $all[TestUser::class]);
    }

    /** @test */
    public function registry_clear_removes_all_pipelines(): void
    {
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([NoopStep::class])
            ->register();

        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);

        $this->assertTrue($registry->has(TestUser::class, 'created'));

        $registry->clear();

        $this->assertFalse($registry->has(TestUser::class, 'created'));
        $this->assertEmpty($registry->all());
    }

    /** @test */
    public function registry_source_returns_registration_source(): void
    {
        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([NoopStep::class])
            ->register();

        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);

        $this->assertSame('fluent', $registry->source(TestUser::class, 'created'));
        $this->assertNull($registry->source(TestUser::class, 'updated'));
    }
}

