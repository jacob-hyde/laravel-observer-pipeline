<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Console;

use Illuminate\Console\Command;
use JacobHyde\ObserverPipeline\Registry\PipelineRegistry;

final class ListPipelinesCommand extends Command
{
    protected $signature = 'observer-pipeline:list';
    protected $description = 'List registered observer pipelines.';

    public function handle(PipelineRegistry $registry): int
    {
        $rows = [];

        foreach ($registry->all() as $model => $events) {
            foreach ($events as $event => $def) {
                $rows[] = [
                    'model' => $model,
                    'event' => $event,
                    'steps' => implode(', ', $def->steps),
                    'async' => implode(', ', array_keys($def->async)),
                    'stop_on_failure' => $def->stopOnFailure ? 'yes' : 'no',
                ];
            }
        }

        if ($rows === []) {
            $this->info('No pipelines registered.');
            return self::SUCCESS;
        }

        $this->table(['model', 'event', 'steps', 'async', 'stop_on_failure'], $rows);

        return self::SUCCESS;
    }
}
