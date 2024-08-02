<?php
declare(strict_types=1);

namespace FpDbTest\Exceptions;

use Exception;

class ResolveArgException extends Exception
{
    public function __construct(int $index, string $type)
    {
        $message = "Is not possible to convert an argument at index [$index] to $type";
        parent::__construct($message);
    }
}