<?php

namespace Shearerline\Exceptions;

class SettlementStateException extends ShearerlineException
{
    protected $code = 422;
    protected $errorCode = 'SETTLEMENT_INVALID_STATE';
}
