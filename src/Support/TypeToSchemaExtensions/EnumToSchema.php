<?php

namespace Dedoc\Scramble\Support\TypeToSchemaExtensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;

class EnumToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type)
    {
        return function_exists('enum_exists')
            && $type instanceof ObjectType
            && enum_exists($type->name);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type)
    {
        $enumName = $type->name;

        $cases = collect($enumName::cases());
        if ($cases->isEmpty()) {
            return new UnknownType("$type->name enum doesnt have values");
        }

        $valueKey = method_exists($enumName, 'hasKeyAsEnumRule') && $cases->first()->hasKeyAsEnumRule() ? 'name' : 'value';
        $values = $cases->map(fn($case) => $case->$valueKey);

        $schemaType = is_string($values->first()) ? new StringType : new IntegerType;
        $schemaType->enum($values->toArray());

        return $schemaType;
    }

    public function reference(ObjectType $type)
    {
        return new Reference('schemas', $type->name, $this->components);
    }
}
