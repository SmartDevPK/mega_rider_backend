<?php

namespace App\Exceptions;

use Exception;

class InsufficientInsuranceException extends Exception
{
    /**
     * Constructor to allow custom message or default message
     */
    public function __construct($message = "You do not have sufficient insurance to create this order.", $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
