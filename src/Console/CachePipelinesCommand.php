<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Console;

use Illuminate\Console\Command;
use JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer;

final class CachePipelinesCommand extends Command
{
    protected $signature = 'observer-pipeline:cache';
    protected $description = 'Discover attribute pipelines and cache them for fast loading.';

    public function handle(AttributeDiscoverer $discoverer): int
    {
        $definitions = $discoverer->buildAndCache();

        $this->info('Observer pipeline manifest cached.');
        $this->line('Path: ' . $discoverer->cachePath());
        $this->line('Pipelines: ' . count($definitions));

        return self::SUCCESS;
    }
}
