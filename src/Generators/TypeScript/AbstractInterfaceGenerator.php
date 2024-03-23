<?php

namespace Based\TypeScript\Generators\TypeScript;

use Based\TypeScript\Contracts\Generator;
use Based\TypeScript\Generators\AbstractGenerator;
use Illuminate\Support\Collection;

abstract class AbstractInterfaceGenerator extends AbstractGenerator implements Generator
{
    public function getDefinition(): ?string
    {
        $this->boot();

        $definition = indent($this->getDefinitionContent());
        if (empty(trim($definition))) {
            return "export interface {$this->typeScriptName()} {}" . PHP_EOL;
        }

        return <<<EOF
export interface {$this->typeScriptName()} {
{$definition}
}

EOF;
    }

    public function getDependencies(): Collection
    {
        $this->boot();

        return $this->getDependenciesContent();
    }
}
