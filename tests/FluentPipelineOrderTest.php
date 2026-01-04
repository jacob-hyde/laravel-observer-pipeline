<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\OrderRecorderStep;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\StepA;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\StepB;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\StepC;

final class FluentPipelineOrderTest extends TestCase
{
    /** @test */
    public function it_runs_steps_in_the_exact_order_registered(): void
    {
        OrderRecorderStep::reset();

        ObserverPipeline::model(TestUser::class)
            ->on('created')
            ->pipe([
                StepA::class,
                StepB::class,
                StepC::class,
            ])
            ->register();

        TestUser::query()->create(['name' => 'Jacob']);

        $this->assertSame(['A', 'B', 'C'], OrderRecorderStep::$calls);
    }
}
