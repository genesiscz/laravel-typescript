<?php

namespace Based\TypeScript;

use Based\TypeScript\Contracts\Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class TypeScriptGenerator
{
    /** @var Generator[] $generators */
    public array $generators;
    public string $output;
    public bool $autoloadDev;
    public array $paths = [];
    /** @var Collection<string,string> */
    protected array $classesToGenerate;

    public function __construct(
        $generators,
        $output,
        $autoloadDev,
        $paths = []
    ) {
        $this->paths = $paths;
        $this->autoloadDev = $autoloadDev;
        $this->output = $output;
        $this->generators = $generators;
    }

    public function execute()
    {
        $classes = $this->getPHPClasses();
        $this->classesToGenerate = $this->getDependencies($classes);


        $types = $classes
            ->groupBy(fn (ReflectionClass $reflection) => $reflection->getNamespaceName())
            ->map(fn (Collection $reflections, string $namespace) => $this->makeNamespace($namespace, $reflections))
            ->reject(fn (string $namespaceDefinition) => empty($namespaceDefinition))
            ->prepend(
                <<<END
                /**
                 * This file is auto generated using 'php artisan typescript:generate'
                 *
                 * Changes to this file will be lost when the command is run again
                 */

                END
            )
            ->join(PHP_EOL);

        file_put_contents($this->output, $types);
    }

    protected function getDependencies(Collection $classes): array
    {
        $dependencies = $classes
            ->map(fn(ReflectionClass $reflection) => $this->getDependentClasses($reflection))
            ->whereNotNull()
            ->collapse()
            ->unique()
            ->intersect($classes);
        return $dependencies->toArray();
    }

    protected function getGenerator(ReflectionClass $reflection): ?string
    {
        return collect($this->generators)
            ->filter(fn (string $generator, string $baseClass) => $reflection->isSubclassOf($baseClass))
            ->values()
            ->first();
    }

    protected function getDependentClasses(ReflectionClass $reflection): Collection
    {
        $generator = $this->getGenerator($reflection);

        if (!$generator) {
            return collect();
        }

        $dependencies = (new $generator($reflection))->getDependencies();

        return $dependencies
            ->map(fn (string $class) => new ReflectionClass($class))
            ->filter(fn (ReflectionClass $class) => $this->getGenerator($class));
    }

    protected function makeNamespace(string $namespace, Collection $reflections): string
    {
        return $reflections->map(fn (ReflectionClass $reflection) => $this->makeDefinition($reflection))
            ->whereNotNull()
            ->map(fn (string $definition) => indent($definition) . PHP_EOL)
            ->whenNotEmpty(function (Collection $definitions) use ($namespace) {
                $tsNamespace = str_replace('\\', '.', $namespace);

                return $definitions->prepend("declare namespace {$tsNamespace} {")->push('}' . PHP_EOL);
            })
            ->join(PHP_EOL);
    }

    protected function makeDefinition(ReflectionClass $reflection): ?string
    {
        /** @var Generator $generator */
        $generator = $this->getGenerator($reflection);

        if (!$generator) {
            return null;
        }

        return (new $generator($reflection, $this->classesToGenerate))->getDefinition();
    }

    protected function getPHPClasses(): Collection
    {
        $composer = json_decode(file_get_contents(realpath('composer.json')));

        return collect($composer->autoload->{'psr-4'})
            ->when($this->autoloadDev, function (Collection $paths) use ($composer) {
                return $paths->merge(
                    collect($composer->{'autoload-dev'}?->{'psr-4'})
                );
            })
            ->merge($this->paths)
            ->flatMap(function (string $path, string $namespace) {
                return collect((new Finder)->in($path)->name('*.php')->files())
                    ->map(function (SplFileInfo $file) use ($path, $namespace) {
                        return $namespace . str_replace(
                            ['/', '.php'],
                            ['\\', ''],
                            Str::after($file->getRealPath(), realpath($path) . DIRECTORY_SEPARATOR)
                        );
                    })
                    ->filter(function (string $className) {
                        try {
                            new ReflectionClass($className);

                            return true;
                        } catch (ReflectionException) {
                            return false;
                        }
                    })
                    ->map(fn (string $className) => new ReflectionClass($className))
                    ->reject(fn (ReflectionClass $reflection) => $reflection->isAbstract())
                    ->values();
            });
    }
}
