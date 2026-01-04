<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests\Fixtures\Steps;

abstract class OrderRecorderStep
{
    /**
     * @var array<int, string>
     */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }
}
