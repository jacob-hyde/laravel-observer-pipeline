<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Testing;

use PHPUnit\Framework\Assert;

final class PipelineFake
{
    private bool $active = false;

    /**
     * @var array<int, array{model: string, event: string, step: string}>
     */
    private array $ran = [];

    /**
     * @var array<int, string>
     */
    private array $queuedSteps = [];

    public function activate(): void
    {
        $this->active = true;
        $this->ran = [];
        $this->queuedSteps = [];
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function recordRan(string $model, string $event, string $stepClass): void
    {
        $this->ran[] = [
            'model' => $model,
            'event' => $event,
            'step' => $stepClass,
        ];
    }

    public function recordQueued(string $stepClass): void
    {
        $this->queuedSteps[] = $stepClass;
    }

    /**
     * @param array<int, class-string> $expectedSteps
     */
    public function assertRan(string $model, string $event, array $expectedSteps): void
    {
        $actual = array_values(array_map(
            static fn (array $row) => $row['step'],
            array_filter($this->ran, static fn (array $row) => $row['model'] === $model && $row['event'] === $event)
        ));

        Assert::assertSame(
            $expectedSteps,
            $actual,
            "Expected pipeline steps to run for {$model}@{$event} in exact order."
        );
    }

    public function assertStepRan(string $stepClass): void
    {
        $found = false;

        foreach ($this->ran as $row) {
            if ($row['step'] === $stepClass) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "Expected step {$stepClass} to have run, but it did not.");
    }

    public function assertStepQueued(string $stepClass): void
    {
        Assert::assertContains(
            $stepClass,
            $this->queuedSteps,
            "Expected step {$stepClass} to have been queued, but it was not."
        );
    }
}
