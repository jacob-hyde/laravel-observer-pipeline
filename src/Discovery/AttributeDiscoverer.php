<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Discovery;

use Illuminate\Filesystem\Filesystem;
use JacobHyde\ObserverPipeline\Attributes\OnModelEvent;
use JacobHyde\ObserverPipeline\Attributes\Pipeline;
use JacobHyde\ObserverPipeline\Definitions\PipelineDefinition;
use JacobHyde\ObserverPipeline\Registry\PipelineRegistry;
use ReflectionClass;

final class AttributeDiscoverer
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly array $paths,
        private readonly bool $useCache,
        private readonly string $cachePath
    ) {}

    public function cachePath(): string
    {
        return $this->cachePath;
    }

    public function clearCache(): void
    {
        if ($this->files->exists($this->cachePath)) {
            $this->files->delete($this->cachePath);
        }
    }

    /**
     * Discover attribute-defined pipelines and register them into the registry.
     *
     * @param string $conflictStrategy 'throw' | 'prefer_fluent' | 'prefer_attributes'
     */
    public function discoverAndRegister(PipelineRegistry $registry, string $conflictStrategy = 'throw'): void
    {
        $definitions = $this->discover();

        foreach ($definitions as $definition) {
            $registry->register($definition, 'attributes', $conflictStrategy);
        }
    }

    /**
     * Build and write a cached manifest file.
     *
     * @return array<int, PipelineDefinition>
     */
    public function buildAndCache(): array
    {
        $definitions = $this->discoverFresh();

        $payload = PipelineManifest::serialize($definitions);

        $this->files->ensureDirectoryExists(dirname($this->cachePath));
        $this->files->put(
            $this->cachePath,
            '<?php return ' . var_export($payload, true) . ';'
        );

        return $definitions;
    }

    /**
     * @return array<int, PipelineDefinition>
     */
    public function discover(): array
    {
        if ($this->useCache && $this->files->exists($this->cachePath)) {
            /** @var array<int, array<string, mixed>> $data */
            $data = require $this->cachePath;

            return PipelineManifest::hydrate($data);
        }

        return $this->discoverFresh();
    }

    /**
     * @return array<int, PipelineDefinition>
     */
    private function discoverFresh(): array
    {
        $classes = $this->discoverClassesFromPaths($this->paths);

        $definitions = [];
        foreach ($classes as $class) {
            $def = $this->definitionFromClass($class);
            if ($def) {
                $definitions[] = $def;
            }
        }

        return $definitions;
    }

    /**
     * @return array<int, string>
     */
    private function discoverClassesFromPaths(array $paths): array
    {
        // v1: Keep discovery intentionally conservative:
        // - require_once all PHP files in configured paths
        // - then scan declared classes for those under App\Pipelines\
        foreach ($paths as $path) {
            if (!$this->files->isDirectory($path)) {
                continue;
            }

            foreach ($this->files->allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                require_once $file->getRealPath();
            }
        }

        $classes = [];
        foreach (get_declared_classes() as $declared) {
            if (str_starts_with($declared, 'App\\Pipelines\\')) {
                $classes[] = $declared;
            }
        }

        return $classes;
    }

    private function definitionFromClass(string $class): ?PipelineDefinition
    {
        $ref = new ReflectionClass($class);

        $eventAttrs = $ref->getAttributes(OnModelEvent::class);
        $pipeAttrs = $ref->getAttributes(Pipeline::class);

        if ($eventAttrs === [] || $pipeAttrs === []) {
            return null;
        }

        /** @var OnModelEvent $onModelEvent */
        $onModelEvent = $eventAttrs[0]->newInstance();

        /** @var Pipeline $pipeline */
        $pipeline = $pipeAttrs[0]->newInstance();

        return new PipelineDefinition(
            model: $onModelEvent->model,
            event: $onModelEvent->event,
            steps: $pipeline->steps,
            async: $pipeline->async,
            stopOnFailure: $pipeline->stopOnFailure,
            onFailureSteps: []
        );
    }
}
