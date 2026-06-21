<?php

namespace Shearerline\Exceptions;

use Exception;

class ShearerlineException extends Exception
{
    protected $code = 500;

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
            ], $this->getCode());
        }

        return back()->withInput()->withErrors([
            'error' => $this->getMessage(),
        ]);
    }
}
