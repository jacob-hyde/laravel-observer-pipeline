<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class OnModelEvent
{
    public function __construct(
        public readonly string $model,
        public readonly string $event
    ) {}
}
