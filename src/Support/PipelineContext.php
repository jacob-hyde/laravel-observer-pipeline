<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Support;

use Illuminate\Database\Eloquent\Model;

final class PipelineContext
{
    private array $meta = [];
    private array $original = [];
    private array $changes = [];

    public function __construct(
        private readonly Model $model,
        private readonly string $event
    ) {}

    public function model(): Model
    {
        return $this->model;
    }

    public function event(): string
    {
        return $this->event;
    }

    public function original(): array
    {
        return $this->original;
    }

    public function changes(): array
    {
        return $this->changes;
    }

    public function setOriginal(array $original): void
    {
        $this->original = $original;
    }

    public function setChanges(array $changes): void
    {
        $this->changes = $changes;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }

    public function meta(): array
    {
        return $this->meta;
    }
}
