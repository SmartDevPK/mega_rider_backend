<?php

namespace App\Exceptions;

use Exception;

class OrderException extends Exception
{
    /**
     * @var array
     */
    protected array $errors = [];

    /**
     * OrderException constructor.
     *
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     * @param array $errors
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Exception $previous = null,
        array $errors = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}