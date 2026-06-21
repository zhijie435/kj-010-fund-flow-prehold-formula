<?php

namespace Shearerline\Exceptions;

class UnauthorizedActionException extends ShearerlineException
{
    protected $code = 403;
    protected $errorCode = 'UNAUTHORIZED_ACTION';
}
