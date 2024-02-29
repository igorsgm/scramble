<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use App\Exceptions\ApiException;
use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\TypeHelper;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;

class GenericExceptionInfer implements ExpressionExceptionExtension
{
    public function getException(Expr $node, Scope $scope): array
    {
        if (
            $node instanceof Expr\New_
            && ($node->class instanceof Name && is_a($node->class->toString(), \Throwable::class, true))
        ) {
            $codeType = TypeHelper::getArgType($scope, $node->args, ['code', 1]);
            $messageType = TypeHelper::getArgType($scope, $node->args, ['message', 0], new LiteralStringType(''));
            $headersType = TypeHelper::getArgType($scope, $node->args, ['headers', 2], new ArrayType());

            return [
                new Generic($node->class->toString(), [
                    $codeType,
                    $messageType,
                    $headersType,
                ]),
            ];
        }

        return [];
    }
}
