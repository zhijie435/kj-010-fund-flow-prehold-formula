<?php

namespace Shearerline\Exceptions;

use Exception;

class ShearerlineException extends Exception
{
    protected $code = 500;
    protected $errorCode = 'INTERNAL_ERROR';
    protected $details = [];

    public function __construct(string $message = '', int $code = 0, array $details = [])
    {
        parent::__construct($message, $code ?: $this->code);
        $this->details = $details;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
                'error_code' => $this->getErrorCode(),
                'details' => $this->getDetails(),
            ], $this->getCode());
        }

        return back()->withInput()->withErrors([
            'error' => $this->getMessage(),
        ]);
    }
}
