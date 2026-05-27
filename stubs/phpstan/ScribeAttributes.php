<?php

declare(strict_types=1);

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Response
{
    public function __construct(mixed ...$parameters)
    {
    }
}

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ResponseFromApiResource
{
    public function __construct(mixed ...$parameters)
    {
    }
}
