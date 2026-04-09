<?php

namespace App\Exceptions;

use Exception;

class OrderingDisabledException extends Exception
{
    // Optional: default message
    public function __construct($message = "Ordering is currently disabled.", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
