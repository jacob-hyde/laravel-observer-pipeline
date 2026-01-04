<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Pipeline
{
    /**
     * @param array<int, class-string> $steps
     * @param array<class-string, array<string, mixed>> $async
     */
    public function __construct(
        public readonly array $steps,
        public readonly array $async = [],
        public readonly bool $stopOnFailure = true
    ) {}
}
