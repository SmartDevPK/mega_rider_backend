<?php

namespace App\Exceptions;

use Exception;

class PromoException extends Exception
{
    /**
     * @var int|null HTTP status code
     */
    protected $statusCode;

    /**
     * Create a new promo exception instance.
     *
     * @param string $message
     * @param int|null $statusCode
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, ?int $statusCode = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $statusCode;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Render the exception as an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|null
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'code' => $this->getMessage(),
            ], $this->statusCode ?? 400);
        }

        return null;
    }
}