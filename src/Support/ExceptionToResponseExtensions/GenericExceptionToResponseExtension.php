<?php

namespace Dedoc\Scramble\Support\ExceptionToResponseExtensions;

use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GenericExceptionToResponseExtension extends HttpExceptionToResponseExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->isInstanceOf(\Throwable::class);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toResponse(Type $type)
    {
        if (! $codeType = $type->templateTypes[0] ?? null) {
            return null;
        }

        if (! $codeType instanceof LiteralIntegerType) {
            return null;
        }

        $responseBodyType = (new OpenApiTypes\ObjectType())
            ->addProperty(
                'message',
                tap((new OpenApiTypes\StringType())->setDescription('Error overview.'), function (OpenApiTypes\StringType $t) use ($type) {
                    $messageType = $type->templateTypes[1] ?? null;
                    if (! $messageType instanceof LiteralStringType) {
                        return;
                    }
                    $t->example($messageType->value);
                })
            )
            ->setRequired(['message']);

        $codeValue = $codeType->value;
        return Response::make($codeValue)
            ->description(\Illuminate\Http\Response::$statusTexts[$codeValue] ?? 'An error occurred.')
            ->setContent(
                'application/json',
                Schema::fromType($responseBodyType)
            );
    }
}
