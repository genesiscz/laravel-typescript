<?php

namespace Based\TypeScript\Definitions;

use Doctrine\DBAL\Types\Types;
use ReflectionMethod;
use ReflectionUnionType;

class TypeScriptType
{
    public const STRING = 'string';
    public const NUMBER = 'number';
    public const BOOLEAN = 'boolean';
    public const ANY = 'any';
    public const NULL = 'null';

    public static function array(string $type = self::ANY): string
    {
        return "Array<{$type}>";
    }

    public static function fromMethod(ReflectionMethod $method): array
    {
        $types = $method->getReturnType() instanceof ReflectionUnionType
            ? $method->getReturnType()->getTypes()
            : (string) $method->getReturnType();

        if (is_string($types) && strpos($types, '?') !== false) {
            $types = [
                str_replace('?', '', $types),
                self::NULL
            ];
        }

        return collect($types)
            ->map(function (string $type) {
                return match ($type) {
                    'int' => self::NUMBER,
                    'float' => self::NUMBER,
                    'string' => self::STRING,
                    'array' => self::array(),
                    'object' => self::ANY,
                    'null' => self::NULL,
                    'bool' => self::BOOLEAN,
                    default => self::ANY,
                };
            })
            ->toArray();
    }

    public static function fromNativeType(string $type): string|array
    {
        return match ($type) {
            Types::ARRAY => [TypeScriptType::array(), TypeScriptType::ANY],
            Types::ASCII_STRING => TypeScriptType::STRING,
            Types::BIGINT => TypeScriptType::NUMBER,
            Types::BINARY => TypeScriptType::STRING,
            Types::BLOB => TypeScriptType::STRING,
            Types::BOOLEAN => TypeScriptType::BOOLEAN,
            Types::DATE_MUTABLE => TypeScriptType::STRING,
            Types::DATE_IMMUTABLE => TypeScriptType::STRING,
            Types::DATEINTERVAL => TypeScriptType::STRING,
            Types::DATETIME_MUTABLE => TypeScriptType::STRING,
            Types::DATETIME_IMMUTABLE => TypeScriptType::STRING,
            Types::DATETIMETZ_MUTABLE => TypeScriptType::STRING,
            Types::DATETIMETZ_IMMUTABLE => TypeScriptType::STRING,
            Types::DECIMAL => TypeScriptType::NUMBER,
            Types::FLOAT => TypeScriptType::NUMBER,
            Types::GUID => TypeScriptType::STRING,
            Types::INTEGER => TypeScriptType::NUMBER,
            Types::JSON => [TypeScriptType::array(), TypeScriptType::ANY],
            Types::OBJECT => TypeScriptType::ANY,
            Types::SIMPLE_ARRAY => [TypeScriptType::array(), TypeScriptType::ANY],
            Types::SMALLINT => TypeScriptType::NUMBER,
            Types::STRING => TypeScriptType::STRING,
            Types::TEXT => TypeScriptType::STRING,
            Types::TIME_MUTABLE => TypeScriptType::NUMBER,
            Types::TIME_IMMUTABLE => TypeScriptType::NUMBER,
            default => TypeScriptType::ANY,
        };
    }

    public static function fromEloquentType(string $type): string|array
    {
        return match ($type) {
            'array' => [self::array(), self::ANY],
            'bool' => self::BOOLEAN,
            'boolean' => self::BOOLEAN,
            'collection' => self::ANY,
            'custom_datetime' => self::STRING,
            'date' => self::STRING,
            'datetime' => self::STRING,
            'decimal' => self::NUMBER,
            'double' => self::NUMBER,
            'encrypted' => self::STRING,
            'encrypted:array' => [self::array(), self::ANY],
            'encrypted:collection' => self::ANY,
            'encrypted:json' => [self::array(), self::ANY],
            'encrypted:object' => self::ANY,
            'float' => self::NUMBER,
            'hashed' => self::STRING,
            'immutable_date' => self::STRING,
            'immutable_datetime' => self::STRING,
            'immutable_custom_datetime' => self::STRING,
            'int' => self::NUMBER,
            'integer' => self::NUMBER,
            'json' => [self::array(), self::ANY],
            'object' => self::ANY,
            'real' => self::NUMBER,
            'string' => self::STRING,
            'timestamp' => self::STRING,
            default => TypeScriptType::ANY,
        };
    }
}
