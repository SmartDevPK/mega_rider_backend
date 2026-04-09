<?php

namespace App\Exceptions;

use Exception;

class FraudException extends Exception
{
    public function __construct($message = "Fraud detected. Order cannot be processed.", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
