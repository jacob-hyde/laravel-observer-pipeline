
# Laravel Observer Pipeline

**Explicit, ordered, and testable pipelines for Laravel Eloquent model events.**

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://www.php.net/) [![Laravel](https://img.shields.io/badge/laravel-%5E10.0%7C%5E11.0%7C%5E12.0-red.svg)](https://laravel.com/) [![License](https://img.shields.io/badge/license-MIT-green.svg)](https://claude.ai/chat/LICENSE.md)

Laravel observers are powerful, but they quickly become hard to reason about when multiple side effects happen on a single model event, execution order matters, some steps should be async, logic becomes scattered across observers and listeners, and testing behavior becomes painful.

**Laravel Observer Pipeline** solves this by introducing **first-class pipelines** for model events.

## Table of Contents

-   [Features](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#features)
-   [Installation](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#installation)
-   [Quick Start](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#quick-start)
-   [Core Concepts](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#core-concepts)
-   [Fluent Builder API](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#fluent-builder-api)
-   [Attribute-Based Pipelines](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#attribute-based-pipelines)
-   [Pipeline Context](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#pipeline-context)
-   [Failure Handling](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#failure-handling)
-   [Async Steps](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#async-steps)
-   [Configuration](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#configuration)
-   [Testing](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#testing)
-   [Artisan Commands](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#artisan-commands)
-   [Advanced Usage](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#advanced-usage)
-   [Examples](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#examples)
-   [Troubleshooting](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#troubleshooting)
-   [Design Philosophy](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#design-philosophy)

## Features

-   ✅ **Explicit, ordered pipelines** for Eloquent model events (created, updated, saved, deleted, restored)
-   ✅ **Two registration methods**: Fluent builder API or PHP 8 attributes
-   ✅ **Step-level async execution** with configurable queue, connection, and delay
-   ✅ **Shared pipeline context** across all steps for data passing
-   ✅ **Flexible failure handling**: Fail-loud (default) or continue-on-failure semantics
-   ✅ **Failure handler steps** that run when exceptions occur
-   ✅ **Built-in testing utilities** with `ObserverPipeline::fake()` for easy test isolation
-   ✅ **Zero magic**: Uses Laravel's native observer system and queue jobs
-   ✅ **Attribute discovery** with optional caching for production performance
-   ✅ **Conflict resolution** strategies for handling duplicate pipeline registrations

## Installation

Require the package via Composer:

```bash
composer require jacobhyde/laravel-observer-pipeline
```

### Requirements

-   PHP 8.1 or higher
-   Laravel 10.0, 11.0, or 12.0

The package uses Laravel's package auto-discovery, so the service provider will be automatically registered.

### Publish Configuration (Optional)

Publish the configuration file to customize settings:

```bash
php artisan vendor:publish --tag=observer-pipeline-config
```

This will create `config/observer-pipeline.php` in your application.

## Quick Start

Here's a simple example to get you started:

**Fluent Builder Approach:**

```php
use JacobHyde\ObserverPipeline\ObserverPipeline;

ObserverPipeline::model(User::class)
    ->on('created')
    ->pipe([
        SendWelcomeEmail::class,
    ])
    ->register();
```

**Attribute-Based Approach:**

```php
use JacobHyde\ObserverPipeline\Attributes\OnModelEvent;
use JacobHyde\ObserverPipeline\Attributes\Pipeline;

#[OnModelEvent(model: User::class, event: 'created')]
#[Pipeline(steps: [SendWelcomeEmail::class])]
final class UserCreatedPipeline {}
```

Both approaches achieve the same result. Choose the one that fits your project's style.

## Core Concepts

### Pipeline Steps

Each step in a pipeline is a simple **invokable class** that receives a `PipelineContext`:

```php
use JacobHyde\ObserverPipeline\Support\PipelineContext;

final class SendWelcomeEmail
{
    public function __invoke(PipelineContext $ctx): void
    {
        $user = $ctx->model();
        Mail::to($user->email)->send(new WelcomeMail($user));
    }
}
```

Steps are executed **in the exact order** they're defined in the pipeline.

### Supported Model Events

The package supports all standard Eloquent model events:

-   `created` - Fired when a new model is saved for the first time
-   `updated` - Fired when an existing model is updated
-   `saved` - Fired when a model is created or updated
-   `deleted` - Fired when a model is deleted
-   `restored` - Fired when a soft-deleted model is restored (requires `SoftDeletes` trait)

## Fluent Builder API

The fluent builder provides a chainable interface for defining pipelines.

### Basic Usage

```php
use JacobHyde\ObserverPipeline\ObserverPipeline;

ObserverPipeline::model(User::class)
    ->on('created')
    ->pipe([
        SyncToCrm::class,
        AssignDefaultRole::class,
        SendWelcomeEmail::class,
    ])
    ->register();
```

### Method Reference

#### `ObserverPipeline::model(string $model)`

Start building a pipeline for a specific model class.

```php
ObserverPipeline::model(User::class)
```

#### `->on(string $event)`

Specify which model event should trigger the pipeline.

```php
->on('created')  // or 'updated', 'saved', 'deleted', 'restored'
```

#### `->pipe(array $steps)`

Define the ordered list of step classes to execute.

```php
->pipe([
    StepOne::class,
    StepTwo::class,
    StepThree::class,
])
```

Steps are executed in the exact order provided.

#### `->async(array $stepOptions)`

Mark specific steps to be executed asynchronously via Laravel's queue system.

```php
->async([
    SendWelcomeEmail::class => ['queue' => 'emails'],
    ProcessAnalytics::class => [
        'queue' => 'analytics',
        'connection' => 'redis',
        'delay' => 60,  // seconds
    ],
])
```

**Available async options:**

-   `queue` (string|null) - Queue name (default: null, uses default queue)
-   `connection` (string|null) - Queue connection name (default: null)
-   `delay` (int|null) - Delay in seconds before processing (default: null)

These options are merged with defaults from your configuration file. See [Async Steps](https://claude.ai/chat/bcad2c00-bc9d-465e-97d8-0adb48c9364c#async-steps) for more details.

#### `->stopOnFailure()`

Stop executing remaining steps when a step throws an exception (default behavior).

```php
->stopOnFailure()
```

When a step fails, the exception is immediately re-thrown, and remaining steps are skipped.

#### `->continueOnFailure()`

Continue executing remaining steps even if one step fails.

```php
->continueOnFailure()
```

All steps will execute, but the first exception encountered will be re-thrown at the end, ensuring the pipeline still "fails loudly" for error reporting.

#### `->onFailure(array $steps)`

Define handler steps that run when any step in the pipeline throws an exception.

```php
->onFailure([
    LogFailure::class,
    NotifyAdmin::class,
])
```

Failure handlers receive the same `PipelineContext` as regular steps, with the exception available via `$ctx->get('_exception')`. Failure handlers run in "best-effort" mode - if they throw exceptions, those are ignored to prevent masking the original failure.

**Note:** `onFailure()` is currently only available via the fluent builder, not in attribute-based pipelines.

#### `->register()`

Register the pipeline definition. This must be called last to complete the registration.

```php
->register()
```

### Complete Example

```php
ObserverPipeline::model(Order::class)
    ->on('created')
    ->pipe([
        ValidateOrder::class,
        ChargePayment::class,
        CreateShippingLabel::class,
        SendConfirmationEmail::class,
    ])
    ->async([
        CreateShippingLabel::class => ['queue' => 'shipping'],
        SendConfirmationEmail::class => [
            'queue' => 'emails',
            'delay' => 30,
        ],
    ])
    ->onFailure([
        LogOrderFailure::class,
        RefundPayment::class,
    ])
    ->continueOnFailure()
    ->register();
```

## Attribute-Based Pipelines

Define pipelines using PHP 8 attributes for a declarative approach.

### Basic Example

```php
use JacobHyde\ObserverPipeline\Attributes\OnModelEvent;
use JacobHyde\ObserverPipeline\Attributes\Pipeline;

#[OnModelEvent(model: User::class, event: 'created')]
#[Pipeline(
    steps: [
        SyncToCrm::class,
        AssignDefaultRole::class,
        SendWelcomeEmail::class,
    ],
    async: [
        SendWelcomeEmail::class => ['queue' => 'emails'],
    ],
    stopOnFailure: true
)]
final class UserCreatedPipeline {}
```

### Attribute Reference

#### `#[OnModelEvent]`

Specifies which model and event the pipeline handles.

```php
#[OnModelEvent(model: User::class, event: 'created')]
```

**Parameters:**

-   `model` (string) - Fully qualified class name of the Eloquent model
-   `event` (string) - Model event name ('created', 'updated', 'saved', 'deleted', 'restored')

#### `#[Pipeline]`

Defines the pipeline configuration.

```php
#[Pipeline(
    steps: array,
    async?: array,
    stopOnFailure?: bool
)]
```

**Parameters:**

-   `steps` (array) - Ordered array of step class names
-   `async` (array, optional) - Async step configuration (same format as fluent builder)
-   `stopOnFailure` (bool, optional) - Whether to stop on failure (default: true)

**Note:** `onFailure` handlers are not yet supported in attribute-based pipelines. Use the fluent builder if you need failure handlers.

### Discovery and Caching

Attribute-based pipelines are automatically discovered when the application boots. The discovery process:

1.  Scans configured paths (default: `app_path('Pipelines')`)
2.  Loads classes with both `#[OnModelEvent]` and `#[Pipeline]` attributes
3.  Registers them into the pipeline registry

For production performance, enable caching in your configuration:

```php
'attributes' => [
    'cache' => true,  // Cache discovered pipelines
],
```

Then run the cache command:

```bash
php artisan observer-pipeline:cache
```

Clear the cache when you add or modify attribute pipelines:

```bash
php artisan observer-pipeline:clear
```

## Pipeline Context

Every step receives a `PipelineContext` instance that provides access to the model, event, and shared data.

### Context API

#### `model(): Model`

Get the Eloquent model instance that triggered the pipeline.

```php
$user = $ctx->model();
```

#### `event(): string`

Get the name of the event that triggered the pipeline.

```php
$event = $ctx->event();  // 'created', 'updated', etc.
```

#### `original(): array`

Get the original model attributes (before changes).

```php
$original = $ctx->original();
// ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']
```

This is particularly useful in `updated` events to compare old vs new values.

#### `changes(): array`

Get only the attributes that changed.

```php
$changes = $ctx->changes();
// ['name' => 'Jane'] // Only changed attributes
```

#### `get(string $key, mixed $default = null): mixed`

Retrieve a value from the shared context meta data.

```php
$value = $ctx->get('custom-key');
$value = $ctx->get('custom-key', 'default-value');
```

#### `set(string $key, mixed $value): void`

Store a value in the shared context meta data for use by subsequent steps.

```php
$ctx->set('processed-by', 'step-one');
$ctx->set('metadata', ['key' => 'value']);
```

#### `meta(): array`

Get all stored meta data.

```php
$allMeta = $ctx->meta();
// ['key1' => 'value1', 'key2' => 'value2']
```

### Data Sharing Between Steps

Steps can share data via the context:

```php
final class StepOne
{
    public function __invoke(PipelineContext $ctx): void
    {
        $result = expensive_operation();
        $ctx->set('operation-result', $result);
    }
}

final class StepTwo
{
    public function __invoke(PipelineContext $ctx): void
    {
        $result = $ctx->get('operation-result');
        // Use the result from StepOne
    }
}
```

### Original and Changes Example

```php
final class LogUserUpdate
{
    public function __invoke(PipelineContext $ctx): void
    {
        $user = $ctx->model();
        $original = $ctx->original();
        $changes = $ctx->changes();

        Log::info('User updated', [
            'user_id' => $user->id,
            'old_name' => $original['name'] ?? null,
            'new_name' => $changes['name'] ?? null,
        ]);
    }
}
```

## Failure Handling

### Default Behavior: Fail Loudly

By default, pipelines **fail loudly**:

-   If a step throws an exception, execution stops immediately
-   Remaining steps are skipped
-   The exception is re-thrown so Laravel can handle it (logging, reporting, etc.)

```php
ObserverPipeline::model(User::class)
    ->on('created')
    ->pipe([
        StepOne::class,   // Runs
        StepTwo::class,   // Throws exception - execution stops
        StepThree::class, // Never runs
    ])
    ->register();
```

### Continue on Failure

Use `continueOnFailure()` to execute all steps even if some fail:

```php
ObserverPipeline::model(User::class)
    ->on('created')
    ->pipe([
        StepOne::class,   // Runs
        StepTwo::class,   // Throws exception - but execution continues
        StepThree::class, // Still runs
    ])
    ->continueOnFailure()
    ->register();
```

**Important:** Even with `continueOnFailure()`, the pipeline still fails loudly. The first exception encountered will be re-thrown after all steps complete, ensuring errors are still reported.

### Failure Handler Steps

Define steps that run when exceptions occur:

```php
ObserverPipeline::model(Order::class)
    ->on('created')
    ->pipe([
        ProcessPayment::class,
        CreateShipping::class,
    ])
    ->onFailure([
        LogFailure::class,
        RefundPayment::class,
        NotifyAdmin::class,
    ])
    ->register();
```

Failure handlers:

-   Run when any step in the pipeline throws an exception
-   Receive the same `PipelineContext` as regular steps
-   Can access the exception via `$ctx->get('_exception')`
-   Run in "best-effort" mode - their exceptions are ignored to prevent masking the original failure

**Example Failure Handler:**

```php
final class LogFailure
{
    public function __invoke(PipelineContext $ctx): void
    {
        $exception = $ctx->get('_exception');
        $model = $ctx->model();

        Log::error('Pipeline step failed', [
            'model' => $model::class,
            'model_id' => $model->id,
            'event' => $ctx->event(),
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

## Async Steps

Mark steps to run asynchronously via Laravel's queue system.

### Basic Async Configuration

```php
ObserverPipeline::model(User::class)
    ->on('created')
    ->pipe([
        SyncToCrm::class,         // Runs synchronously
        SendWelcomeEmail::class,  // Runs asynchronously
    ])
    ->async([
        SendWelcomeEmail::class => ['queue' => 'emails'],
    ])
    ->register();
```

### Async Options

#### Queue Name

Specify which queue to use:

```php
->async([
    SendEmail::class => ['queue' => 'emails'],
])
```

#### Queue Connection

Specify which queue connection to use:

```php
->async([
    ProcessData::class => [
        'connection' => 'redis',
        'queue' => 'processing',
    ],
])
```

#### Delay

Delay execution by a number of seconds:

```php
->async([
    SendReminder::class => [
        'queue' => 'emails',
        'delay' => 3600,  // 1 hour
    ],
])
```

#### Combined Options

```php
->async([
    SendEmail::class => [
        'queue' => 'emails',
        'connection' => 'redis',
        'delay' => 60,
    ],
])
```

### Config Defaults

Async options are merged with defaults from your configuration:

```php
// config/observer-pipeline.php
'async' => [
    'queue' => 'default',
    'connection' => 'database',
    'delay' => null,
],
```

Step-specific options override config defaults:

```php
// Config: queue => 'default', connection => 'database'
->async([
    SendEmail::class => ['queue' => 'emails'],  // Uses 'emails' queue, 'database' connection
])
```

### Job Execution

Async steps are dispatched as `RunPipelineStepJob` queue jobs. The job:

1.  Retrieves the model from the database using the stored model ID
2.  Recreates the `PipelineContext` with the model and event
3.  Restores any meta data that was set in previous steps
4.  Executes the step

If the model is deleted before the job runs, the job silently skips execution.

### Testing Async Steps

When using `ObserverPipeline::fake()`, async steps are recorded but not actually queued:

```php
$fake = ObserverPipeline::fake();

// ... trigger pipeline ...

$fake->assertStepQueued(SendEmail::class);
```

## Configuration

Publish the configuration file to customize behavior:

```bash
php artisan vendor:publish --tag=observer-pipeline-config
```

### Configuration Reference

#### `attributes.enabled`

Enable or disable automatic discovery of attribute-based pipelines.

```php
'attributes' => [
    'enabled' => true,  // Set to false to disable attribute discovery
],
```

#### `attributes.paths`

Array of directory paths to scan for pipeline classes with attributes.

```php
'attributes' => [
    'paths' => [
        app_path('Pipelines'),
        app_path('Domain/Orders/Pipelines'),
    ],
],
```

**Note:** Currently, only classes in the `App\Pipelines\` namespace are discovered. This is a limitation of the current discovery implementation.

#### `attributes.cache`

Cache discovered pipelines for performance. Should be `true` in production.

```php
'attributes' => [
    'cache' => true,  // Cache in production
],
```

When enabled, run `php artisan observer-pipeline:cache` after adding or modifying attribute pipelines.

#### `conflicts`

Strategy for handling duplicate pipeline registrations (same model + event).

**Options:**

-   `'throw'` (default) - Throw an exception when duplicates are detected
-   `'prefer_fluent'` - Keep fluent builder registrations, ignore attribute registrations
-   `'prefer_attributes'` - Keep attribute registrations, ignore fluent builder registrations

```php
'conflicts' => 'throw',
```

**Example with `prefer_fluent`:**

```php
// First: Attribute pipeline
#[OnModelEvent(model: User::class, event: 'created')]
#[Pipeline(steps: [StepA::class])]
class UserCreatedPipeline {}

// Second: Fluent pipeline (takes precedence)
ObserverPipeline::model(User::class)
    ->on('created')
    ->pipe([StepB::class])
    ->register();
// Result: StepB runs, StepA is ignored
```

#### `defaults.stop_on_failure`

Default failure behavior for pipelines that don't explicitly specify.

```php
'defaults' => [
    'stop_on_failure' => true,  // Default: stop on first failure
],
```

This can be overridden per pipeline using `->stopOnFailure()` or `->continueOnFailure()`.

#### `defaults.on_failure`

Default failure handler steps applied to all pipelines.

```php
'defaults' => [
    'on_failure' => [
        GlobalFailureLogger::class,
    ],
],
```

Pipeline-specific failure handlers (via `->onFailure()`) are executed in addition to these defaults.

#### `async.queue`

Default queue name for async steps.

```php
'async' => [
    'queue' => 'default',  // or null to use Laravel's default
],
```

#### `async.connection`

Default queue connection for async steps.

```php
'async' => [
    'connection' => 'database',  // or 'redis', 'sqs', etc.
],
```

#### `async.delay`

Default delay (in seconds) for async steps.

```php
'async' => [
    'delay' => null,  // No delay by default
],
```

#### `reentry.enabled`

Enable re-entry protection to prevent pipelines from triggering themselves.

```php
'reentry' => [
    'enabled' => true,
],
```

**Note:** This feature may not be fully implemented yet. Check the source code for current status.

#### `reentry.ttl`

Time-to-live for re-entry protection locks (in seconds).

```php
'reentry' => [
    'ttl' => 10,  // Lock expires after 10 seconds
],
```

### Complete Configuration Example

```php
return [
    'attributes' => [
        'enabled' => true,
        'paths' => [
            app_path('Pipelines'),
        ],
        'cache' => env('APP_ENV') === 'production',
    ],

    'conflicts' => 'throw',

    'defaults' => [
        'stop_on_failure' => true,
        'on_failure' => [],
    ],

    'async' => [
        'queue' => null,
        'connection' => null,
        'delay' => null,
    ],

    'reentry' => [
        'enabled' => true,
        'ttl' => 10,
    ],
];
```

## Testing

Observer Pipeline includes built-in testing utilities to make testing pipelines easy and isolated.

### Basic Usage

```php
use JacobHyde\ObserverPipeline\ObserverPipeline;

$fake = ObserverPipeline::fake();

// Register and trigger pipelines
ObserverPipeline::model(User::class)
    ->on('created')
    ->pipe([SendEmail::class])
    ->register();

User::factory()->create();

// Assertions
$fake->assertRan(User::class, 'created', [SendEmail::class]);
```

### Testing API

#### `ObserverPipeline::fake(): PipelineFake`

Activate fake mode and return the fake instance for assertions.

```php
$fake = ObserverPipeline::fake();
```

When faked:

-   Steps do **not** execute
-   Jobs are **not** dispatched to the queue
-   Pipeline execution is **recorded** for assertions

#### `->assertRan(string $model, string $event, array $steps)`

Assert that a pipeline ran with specific steps in exact order.

```php
$fake->assertRan(User::class, 'created', [
    SyncToCrm::class,
    SendEmail::class,
]);
```

#### `->assertStepRan(string $stepClass)`

Assert that a specific step ran (regardless of which pipeline).

```php
$fake->assertStepRan(SendEmail::class);
```

#### `->assertStepQueued(string $stepClass)`

Assert that a specific step was queued for async execution.

```php
$fake->assertStepQueued(SendEmail::class);
```

### Complete Testing Example

```php
use Tests\TestCase;
use JacobHyde\ObserverPipeline\ObserverPipeline;
use App\Models\User;

class UserPipelineTest extends TestCase
{
    public function test_user_created_pipeline_runs(): void
    {
        $fake = ObserverPipeline::fake();

        ObserverPipeline::model(User::class)
            ->on('created')
            ->pipe([
                SyncToCrm::class,
                AssignRole::class,
                SendWelcomeEmail::class,
            ])
            ->async([
                SendWelcomeEmail::class => ['queue' => 'emails'],
            ])
            ->register();

        User::factory()->create();

        $fake->assertRan(User::class, 'created', [
            SyncToCrm::class,
            AssignRole::class,
            SendWelcomeEmail::class,
        ]);

        $fake->assertStepQueued(SendWelcomeEmail::class);
    }

    public function test_pipeline_continues_on_failure(): void
    {
        $fake = ObserverPipeline::fake();

        ObserverPipeline::model(User::class)
            ->on('created')
            ->pipe([
                StepOne::class,
                ThrowingStep::class,
                StepThree::class,
            ])
            ->continueOnFailure()
            ->register();

        try {
            User::factory()->create();
        } catch (\Exception $e) {
            // Expected
        }

        // All steps should have run
        $fake->assertStepRan(StepOne::class);
        $fake->assertStepRan(ThrowingStep::class);
        $fake->assertStepRan(StepThree::class);
    }
}
```

## Artisan Commands

### `observer-pipeline:list`

List all registered pipelines.

```bash
php artisan observer-pipeline:list
```

**Example Output:**

```
+------------------+---------+----------------------------+------+-----------------+
| model            | event   | steps                      | async| stop_on_failure |
+------------------+---------+----------------------------+------+-----------------+
| App\Models\User  | created | SyncToCrm, SendEmail       |      | yes             |
| App\Models\Order | created | ProcessPayment, ShipOrder  | ShipOrder | yes       |
+------------------+---------+----------------------------+------+-----------------+

```

### `observer-pipeline:cache`

Discover and cache attribute-based pipelines for fast loading.

```bash
php artisan observer-pipeline:cache
```

**When to use:**

-   After adding new attribute-based pipelines
-   In your deployment process
-   When `attributes.cache` is enabled in config

**Output:**

```
Observer pipeline manifest cached.
Path: /path/to/bootstrap/cache/observer-pipeline.php
Pipelines: 5

```

### `observer-pipeline:clear`

Clear the cached pipeline manifest.

```bash
php artisan observer-pipeline:clear
```

**When to use:**

-   During development when modifying attribute pipelines
-   When pipelines aren't being discovered
-   To force fresh discovery on next request

**Output:**

```
Observer pipeline manifest cache cleared.
Path: /path/to/bootstrap/cache/observer-pipeline.php

```

## Advanced Usage

### Multiple Pipelines for Same Model/Event

You can register multiple pipelines for the same model and event, but you must configure conflict resolution:

```php
// config/observer-pipeline.php
'conflicts' => 'prefer_fluent',  // or 'prefer_attributes'
```

With `prefer_fluent`, the last fluent registration wins. With `prefer_attributes`, attribute pipelines take precedence.

### Custom Step Classes with Dependencies

Steps are resolved from the service container, so you can inject dependencies:

```php
final class SyncToCrm
{
    public function __construct(
        private CrmClient $crm,
        private LoggerInterface $logger
    ) {}

    public function __invoke(PipelineContext $ctx): void
    {
        $user = $ctx->model();
        $this->crm->syncUser($user);
        $this->logger->info('User synced to CRM', ['user_id' => $user->id]);
    }
}
```

### Context Data Sharing Patterns

**Pattern 1: Accumulate Data**

```php
final class CollectUserData
{
    public function __invoke(PipelineContext $ctx): void
    {
        $data = $ctx->get('collected-data', []);
        $data[] = 'step-one-result';
        $ctx->set('collected-data', $data);
    }
}

final class ProcessCollectedData
{
    public function __invoke(PipelineContext $ctx): void
    {
        $data = $ctx->get('collected-data', []);
        // Process all collected data
    }
}
```

**Pattern 2: Conditional Execution**

```php
final class CheckCondition
{
    public function __invoke(PipelineContext $ctx): void
    {
        if ($someCondition) {
            $ctx->set('should-process', true);
        }
    }
}

final class ConditionalStep
{
    public function __invoke(PipelineContext $ctx): void
    {
        if (!$ctx->get('should-process', false)) {
            return;  // Skip this step
        }
        // Process...
    }
}
```

### Manual Discovery

Trigger attribute discovery manually:

```php
use JacobHyde\ObserverPipeline\ObserverPipeline;

ObserverPipeline::discover();
```

This uses the configured paths from your config file.

## Examples

### User Registration Pipeline

```php
ObserverPipeline::model(User::class)
    ->on('created')
    ->pipe([
        ValidateUserData::class,
        CreateUserProfile::class,
        AssignDefaultRole::class,
        SendWelcomeEmail::class,
        TrackRegistration::class,
    ])
    ->async([
        SendWelcomeEmail::class => ['queue' => 'emails'],
        TrackRegistration::class => ['queue' => 'analytics'],
    ])
    ->onFailure([
        LogRegistrationFailure::class,
        CleanupPartialUser::class,
    ])
    ->register();
```

### Order Processing Pipeline

```php
ObserverPipeline::model(Order::class)
    ->on('created')
    ->pipe([
        ValidateInventory::class,
        ReserveInventory::class,
        ProcessPayment::class,
        CreateShippingLabel::class,
        SendOrderConfirmation::class,
        UpdateInventory::class,
    ])
    ->async([
        CreateShippingLabel::class => [
            'queue' => 'shipping',
            'delay' => 300,  // 5 minutes
        ],
        SendOrderConfirmation::class => ['queue' => 'emails'],
    ])
    ->onFailure([
        ReleaseInventory::class,
        RefundPayment::class,
        NotifyOrderTeam::class,
    ])
    ->continueOnFailure()
    ->register();
```

### Attribute-Based Email Pipeline

```php
#[OnModelEvent(model: Newsletter::class, event: 'created')]
#[Pipeline(
    steps: [
        ValidateContent::class,
        RenderTemplate::class,
        QueueEmails::class,
    ],
    async: [
        QueueEmails::class => ['queue' => 'emails'],
    ],
    stopOnFailure: true
)]
final class NewsletterCreatedPipeline {}
```

### Multi-Step Data Synchronization

```php
ObserverPipeline::model(Product::class)
    ->on('updated')
    ->pipe([
        SyncToSearchIndex::class,      // Uses context to get changes
        UpdateInventorySystem::class,  // Uses context to get changes
        InvalidateCache::class,        // Uses context to get model
        NotifySubscribers::class,      // Uses context to get changes
    ])
    ->async([
        SyncToSearchIndex::class => ['queue' => 'search'],
        UpdateInventorySystem::class => ['queue' => 'inventory'],
        NotifySubscribers::class => ['queue' => 'notifications'],
    ])
    ->register();

// Step implementation
final class SyncToSearchIndex
{
    public function __invoke(PipelineContext $ctx): void
    {
        $product = $ctx->model();
        $changes = $ctx->changes();

        // Only sync if relevant fields changed
        if (isset($changes['name']) || isset($changes['description'])) {
            SearchIndex::update($product);
        }
    }
}
```

## Troubleshooting

### Pipelines Not Running

**Check 1:** Ensure the pipeline is registered before the model event fires.

```php
// In a service provider's boot() method
ObserverPipeline::model(User::class)
    ->on('created')
    ->pipe([...])
    ->register();
```

**Check 2:** Verify the model observer is registered (happens automatically, but check logs).

**Check 3:** For attribute pipelines, ensure discovery is enabled and paths are correct:

```php
'attributes' => [
    'enabled' => true,
    'paths' => [app_path('Pipelines')],
],

```

**Check 4:** Clear and rebuild the cache:

```bash
php artisan observer-pipeline:clear
php artisan observer-pipeline:cache
```

### Steps Not Executing in Order

Steps execute in the exact order defined in `->pipe()`. If order seems wrong:

1.  Check that steps are listed in the correct order
2.  Verify no steps are throwing exceptions (which would stop execution)
3.  For async steps, remember they execute later, not in sequence

### Async Steps Not Queuing

**Check 1:** Verify queue configuration:

```php
->async([
    Step::class => ['queue' => 'emails'],  // Must specify queue
])
```

**Check 2:** Ensure queue worker is running:

```bash
php artisan queue:work
```

**Check 3:** Check queue connection settings in `config/queue.php`

### Attribute Pipelines Not Discovered

**Check 1:** Verify class namespace is `App\Pipelines\*` (current limitation)

**Check 2:** Ensure both attributes are present:

```php
#[OnModelEvent(...)]  // Required
#[Pipeline(...)]      // Required
```

**Check 3:** Check that the file is in a configured path:

```php
'attributes' => [
    'paths' => [app_path('Pipelines')],  // Must match your file location
],
```

### Testing Issues

**Problem:** Assertions fail even though pipeline should run

**Solution:** Store the fake instance and reuse it:

```php
$fake = ObserverPipeline::fake();  // Store this
// ... register and trigger ...
$fake->assertRan(...);  // Use the same instance
```

**Problem:** Steps execute during tests

**Solution:** Ensure `ObserverPipeline::fake()` is called before registering pipelines.

## Design Philosophy

### Explicit Over Implicit

Pipelines are explicitly defined - no magic discovery of "listeners" or convention-based registration. You see exactly what runs and when.

### No Workflow Engines

This is not a workflow engine. It's a simple, ordered list of steps. No DAGs, no complex state machines, no UI builders.

### Laravel-Native

Uses Laravel's built-in observer system and queue jobs. No custom event dispatchers or job systems.

### Testable

Built-in faking makes it easy to test pipeline behavior without executing side effects.

### Fail Loudly

By default, exceptions are re-thrown immediately. This ensures errors are visible and can be handled by Laravel's error handling system.

### Simple Steps

Steps are just invokable classes. No interfaces to implement, no base classes to extend. Keep it simple.

----------

**Just clean, predictable pipelines for model events.**