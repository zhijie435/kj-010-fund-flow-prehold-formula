<?php

namespace Shearerline\Exceptions;

class SettlementNotFoundException extends ShearerlineException
{
    protected $code = 404;
    protected $errorCode = 'SETTLEMENT_NOT_FOUND';
}
