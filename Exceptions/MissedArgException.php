<?php
declare(strict_types=1);

namespace FpDbTest\Exceptions;

use Exception;

class MissedArgException extends Exception
{
    public function __construct(int $index)
    {
        $message = "An argument at index [$index] was not found";
        parent::__construct($message);
    }
}