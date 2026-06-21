<?php

namespace Shearerline\Exceptions;

class InvalidProductException extends ShearerlineException
{
    protected $code = 422;
    protected $errorCode = 'INVALID_PRODUCT';
}
