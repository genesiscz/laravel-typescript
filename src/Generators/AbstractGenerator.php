<?php

namespace Based\TypeScript\Generators;

use Based\TypeScript\Contracts\Generator;
use Based\TypeScript\Definitions\TypeScriptType;
use Illuminate\Support\Collection;
use ReflectionClass;
use Reflector;

abstract class AbstractGenerator implements Generator
{
    protected ReflectionClass $reflection;
    protected array $knownClasses;

    public function __construct(ReflectionClass $reflection, array $knownClasses = [])
    {
        $this->reflection = $reflection;
        $this->knownClasses = $knownClasses;
    }

    public abstract function getDefinition(): ?string;
    protected abstract function getDefinitionContent(): ?string;
    public abstract function getDependencies(): Collection;
    protected abstract function getDependenciesContent(): Collection;

    protected function boot(): void
    {

    }

    protected function typeScriptName(): string
    {
        return str_replace('\\', '.', $this->reflection->getShortName());
    }

    protected function getKnownClassOrType(string $typeName): string
    {
        $types = TypeScriptType::fromNativeType($typeName);

        // If the cast is a class, override the type
        if (class_exists($typeName)) {
            // Get cast class of this column
            $reflection = new ReflectionClass($typeName);

            // If class is going to be generated at some point, use its name, or fallback to return type
            if (in_array($reflection, $this->knownClasses)) {
                $types = str_replace('\\', '.', $reflection->getName());
            }
        }
        return $types;
    }
}
