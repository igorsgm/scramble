<?php

namespace Dedoc\Scramble\Support\ExceptionToResponseExtensions;

use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;

class GenericExceptionToResponseExtension extends HttpExceptionToResponseExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof Generic
            && $type->isInstanceOf(\Throwable::class);
    }
}
