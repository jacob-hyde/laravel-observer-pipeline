<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JacobHyde\ObserverPipeline\Observer\GenericModelObserver;
use JacobHyde\ObserverPipeline\ObserverPipeline;
use JacobHyde\ObserverPipeline\ObserverPipelineServiceProvider;
use JacobHyde\ObserverPipeline\Registry\PipelineRegistry;
use JacobHyde\ObserverPipeline\Runner\PipelineRunner;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ObserverPipelineServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        ObserverPipeline::setApplication($this->app);
        GenericModelObserver::resetObservedModels();

        $this->app->forgetInstance(PipelineRegistry::class);
        $this->app->forgetInstance(PipelineRunner::class);


        Schema::dropAllTables();

        Schema::create('test_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

}
