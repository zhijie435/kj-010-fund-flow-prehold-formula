<?php

namespace Shearerline\Exceptions;

class CalculationException extends ShearerlineException
{
    protected $code = 422;
    protected $errorCode = 'CALCULATION_ERROR';
}
