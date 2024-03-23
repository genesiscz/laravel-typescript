<?php

namespace Based\TypeScript\Generators;

use Based\TypeScript\Definitions\TypeScriptProperty;
use Based\TypeScript\Definitions\TypeScriptType;
use Based\TypeScript\Generators\TypeScript\AbstractInterfaceGenerator;
use Doctrine\DBAL\Schema\Column;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

class ModelGenerator extends AbstractInterfaceGenerator
{
    protected Model $model;
    /** @var Collection<Column> */
    protected Collection $columns;
    /** @var Collection<string,ReflectionClass> */
    protected Collection $casts;

    protected function boot(): void
    {
        $this->model = $this->reflection->newInstance();

        $this->columns = collect(
            $this->model->getConnection()
                ->getDoctrineSchemaManager()
                ->listTableColumns($this->model->getConnection()->getTablePrefix() . $this->model->getTable())
        );
        $this->casts = $this->getCasts();
    }

    protected function getDefinitionContent(): ?string
    {
        return collect([
            $this->getPropertyDefinitions(),
            $this->getRelationDefinitions(),
            $this->getManyRelationDefinitions(),
            $this->getAccessorDefinitions(),
            $this->getAttributeAccessorDefinitions(),
        ])
            ->filter(fn (string $part) => !empty($part))
            ->join(PHP_EOL);
    }

    protected function getDependenciesContent(): Collection
    {
        return collect([
            $this->getPropertyClasses(),
            $this->getRelationClasses(),
            $this->getAccessorClasses(),
            $this->getAttributeAccessorClasses(),
        ])
            ->collapse()
            ->values()
            ->unique();
    }

    protected function getCasts(): Collection
    {
        return collect($this->model->getCasts());
    }

    protected function getPropertyClasses(): Collection
    {
        return $this->columns->map(function (Column $column) {
            if (!$this->casts->has($column->getName())) {
                return null;
            }

            $cast = $this->casts
                ->filter(fn (string $cast) => class_exists($cast))
                ->get($column->getName());
            if (!$cast) return null;

            return (new ReflectionClass($cast))->getName();
        })->filter();
    }

    protected function getPropertyDefinitions(): string
    {
        return $this->columns->map(function (Column $column) {
            // Types should be based on return type to start with
            $types = TypeScriptType::fromNativeType($column->getType()->getName());

            // Check if the model has a cast for this column
            if ($this->casts->has($column->getName())) {

                // Set the type to the eloquent cast
                $cast = $this->casts->get($column->getName());
                $types = TypeScriptType::fromEloquentType($cast);

                // If the cast is a class, override the type
                if (class_exists($cast)) {
                    // Get cast class of this column
                    $reflection = new ReflectionClass($cast);

                    // If class is going to be generated at some point, use its name, or fallback to return type
                    if (in_array($reflection, $this->knownClasses)) {
                        $types = str_replace('\\', '.', $reflection->getName());
                    }
                }
            }
            return (string) new TypeScriptProperty(
                name: $column->getName(),
                types: $types,
                nullable: !$column->getNotnull()
            );
        })->join(PHP_EOL);
    }

    protected function getRelationClasses(): Collection
    {
        return $this->getRelationMethods()
            ->map(function (ReflectionMethod $method) {
                return $this->getRelationRelatedClass($method);
            });
    }

    protected function getRelationDefinitions(): string
    {
        return $this->getRelationMethods()
            ->map(function (ReflectionMethod $method) {
                return (string) new TypeScriptProperty(
                    name: Str::snake($method->getName()),
                    types: $this->getRelationType($method),
                    optional: true,
                    nullable: true
                );
            })
            ->join(PHP_EOL);
    }

    protected function getManyRelationDefinitions(): string
    {
        return $this->getRelationMethods()
            ->filter(fn (ReflectionMethod $method) => $this->isManyRelation($method))
            ->map(function (ReflectionMethod $method) {
                return (string) new TypeScriptProperty(
                    name: Str::snake($method->getName()) . '_count',
                    types: TypeScriptType::NUMBER,
                    optional: true,
                    nullable: true
                );
            })
            ->join(PHP_EOL);
    }

    protected function getAccessorClasses(): Collection
    {
        return $this->getAccessors()
            ->map(function (ReflectionMethod $method) {
                $types = $method->getReturnType() instanceof ReflectionUnionType
                    ? collect($method->getReturnType()->getTypes())
                    : collect([$method->getReturnType()]);

                return $types
                    ->reject(fn (?ReflectionType $type) => is_null($type) || $type->isBuiltin())
                    ->filter(fn (?ReflectionType $type) => $type instanceof ReflectionNamedType)
                    ->reject(fn (ReflectionNamedType $type) => $type->getName() == "null")
                    ->map(fn (ReflectionNamedType $type) => $type->getName());
            })
            ->collapse();
    }

    protected function getAccessorDefinitions(): string
    {
        $relationsToSkip =  $this->getRelationMethods()
            ->map(function (ReflectionMethod $method) {
                return Str::snake($method->getName());
            });

        return $this->getAccessors()
            ->mapWithKeys(function (ReflectionMethod $method) {
                $property = (string) Str::of($method->getName())
                    ->between('get', 'Attribute')
                    ->snake();

                return [$property => $method];
            })
            ->reject(function (ReflectionMethod $method, string $property) {
                return $this->columns->contains(fn (Column $column) => $column->getName() == $property);
            })
            ->reject(function (ReflectionMethod $method, string $property) use ($relationsToSkip) {
                return $relationsToSkip->contains($property);
            })
            ->map(function (ReflectionMethod $method, string $property) {
                return (string) new TypeScriptProperty(
                    name: $property,
                    types: TypeScriptType::fromMethod($method),
                    optional: !$this->model->hasAppended($property),
                    readonly: true
                );
            })
            ->join(PHP_EOL);
    }

    protected function getAttributeAccessorClasses(): Collection
    {
        return $this->getAttributeAccessors()
            ->map(fn (ReflectionFunction $function, string $property) => $function->getReturnType()->getName());
    }

    protected function getAttributeAccessorDefinitions(): string
    {
        return $this->getAttributeAccessors()
            ->map(function (ReflectionFunction $function, string $property) {
                // Optional only if not in appends.
                return (string) new TypeScriptProperty(
                    name: $property,
                    types: $this->getKnownClassOrType($function->getReturnType()->getName()),
                    optional: !$this->model->hasAppended($property),
                    readonly: true,
                    nullable: true,
                );
            })
            ->join(PHP_EOL);
    }

    protected function getMethods(): Collection
    {
        return collect($this->reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn (ReflectionMethod $method) => $method->isStatic())
            ->reject(fn (ReflectionMethod $method) => $method->getNumberOfParameters());
    }


    protected function getRelationMethods(): Collection
    {
        return $this->getMethods()
            ->filter(function (ReflectionMethod $method) {
                try {
                    return $method->invoke($this->model) instanceof Relation;
                } catch (Throwable) {
                    return false;
                }
            });
    }

    protected function getAccessors(): Collection
    {
        return $this->getMethods()
            ->filter(fn (ReflectionMethod $method) => Str::startsWith($method->getName(), 'get'))
            ->filter(fn (ReflectionMethod $method) => Str::endsWith($method->getName(), 'Attribute'));
    }

    protected function getAttributeAccessors(): Collection
    {
        return collect($this->reflection->getMethods())
            ->filter(fn (ReflectionMethod $method) =>
                $method->hasReturnType()
                && $method->getReturnType() instanceof ReflectionNamedType
                && $method->getReturnType()->getName() === Attribute::class
            )
            ->mapWithKeys(function (ReflectionMethod $method) {
                $property = Str::snake($method->getName());
                $method->setAccessible(true);
                $returnAttribute = $method->invoke($this->model);
                $reflectFn = new ReflectionFunction($returnAttribute->get);
                return [$property => $reflectFn];
            })
            ->reject(function (ReflectionFunction $function, string $property) {
                return $this->columns->contains(fn (Column $column) => $column->getName() === $property) ||
                    !$function->hasReturnType();
            });
    }

    protected function getRelationRelatedClass(ReflectionMethod $method): string
    {
        $relationReturn = $method->invoke($this->model);
        return get_class($relationReturn->getRelated());
    }

    protected function getRelationType(ReflectionMethod $method): string
    {
        $relationClass = $this->getRelationRelatedClass($method);
        $related = $this->getKnownClassOrType($relationClass);
        $relatedRef = str_replace('\\', '.', $related);

        if ($this->isManyRelation($method)) {
            return TypeScriptType::array($relatedRef);
        }

        if ($this->isOneRelation($method)) {
            return $relatedRef;
        }

        return TypeScriptType::ANY;
    }

    protected function isManyRelation(ReflectionMethod $method): bool
    {
        $relationType = get_class($method->invoke($this->model));

        return in_array(
            $relationType,
            [
                HasMany::class,
                BelongsToMany::class,
                HasManyThrough::class,
                MorphMany::class,
                MorphToMany::class,
            ]
        );
    }

    protected function isOneRelation(ReflectionMethod $method): bool
    {
        $relationType = get_class($method->invoke($this->model));

        return in_array(
            $relationType,
            [
                HasOne::class,
                BelongsTo::class,
                MorphOne::class,
                HasOneThrough::class,
            ]
        );
    }
}
