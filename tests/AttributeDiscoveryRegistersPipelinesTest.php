<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer;
use JacobHyde\ObserverPipeline\Registry\PipelineRegistry;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;

final class AttributeDiscoveryRegistersPipelinesTest extends TestCase
{
    /** @test */
    public function it_discovers_attribute_pipelines_and_registers_them_in_the_registry(): void
    {
        // configure discovery to load our fixtures
        $this->app['config']->set('observer-pipeline.attributes.cache', false);
        $this->app['config']->set('observer-pipeline.attributes.paths', [
            __DIR__ . '/Fixtures/Pipelines',
        ]);

        /** @var AttributeDiscoverer $discoverer */
        $discoverer = $this->app->make(AttributeDiscoverer::class);

        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);

        $discoverer->discoverAndRegister($registry, 'throw');

        $this->assertTrue($registry->has(TestUser::class, 'created'));
        $this->assertNotNull($registry->get(TestUser::class, 'created'));
    }
}
