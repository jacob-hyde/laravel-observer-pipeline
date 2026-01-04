<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Console;

use Illuminate\Console\Command;
use JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer;

final class ClearPipelinesCacheCommand extends Command
{
    protected $signature = 'observer-pipeline:clear';
    protected $description = 'Clear the cached observer pipeline manifest.';

    public function handle(AttributeDiscoverer $discoverer): int
    {
        $discoverer->clearCache();

        $this->info('Observer pipeline manifest cache cleared.');
        $this->line('Path: ' . $discoverer->cachePath());

        return self::SUCCESS;
    }
}
