<?php

declare(strict_types=1);

namespace App\Pipelines;

use JacobHyde\ObserverPipeline\Attributes\OnModelEvent;
use JacobHyde\ObserverPipeline\Attributes\Pipeline;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Models\TestUser;
use JacobHyde\ObserverPipeline\Tests\Fixtures\Steps\AttributeRecordedStep;

#[OnModelEvent(model: TestUser::class, event: 'created')]
#[Pipeline(steps: [AttributeRecordedStep::class])]
final class TestUserCreatedPipeline
{
    // no body needed
}
