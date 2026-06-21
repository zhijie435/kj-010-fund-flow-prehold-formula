<?php

namespace Shearerline\Tests\Unit;

use Shearerline\Exceptions\CalculationException;
use Shearerline\Exceptions\InvalidProductException;
use Shearerline\Exceptions\SettlementNotFoundException;
use Shearerline\Exceptions\SettlementStateException;
use Shearerline\Exceptions\ShearerlineException;
use Shearerline\Exceptions\UnauthorizedActionException;
use Shearerline\Tests\TestCase;

class ExceptionClassesTest extends TestCase
{
    // ─── ShearerlineException 基类 ──────────────────────────────────────────

    public function test_shearerline_exception_base_structure()
    {
        $exception = new ShearerlineException('Test message', 500, ['key' => 'value']);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertEquals('INTERNAL_ERROR', $exception->getErrorCode());
        $this->assertEquals(['key' => 'value'], $exception->getDetails());
    }

    public function test_shearerline_exception_default_code_and_error_code()
    {
        $exception = new ShearerlineException('Default');

        $this->assertEquals(500, $exception->getCode());
        $this->assertEquals('INTERNAL_ERROR', $exception->getErrorCode());
        $this->assertEmpty($exception->getDetails());
    }

    public function test_shearerline_exception_uses_provided_code_over_default()
    {
        $exception = new ShearerlineException('Custom', 418);

        $this->assertEquals(418, $exception->getCode());
    }

    public function test_shearerline_exception_render_json_response()
    {
        $request = $this->app->make('request');
        $request->headers->set('Accept', 'application/json');

        $exception = new ShearerlineException('JSON Error', 422, ['field' => 'value']);

        $response = $exception->render($request);

        $this->assertEquals(422, $response->status());
        $data = $response->getData(true);
        $this->assertEquals('JSON Error', $data['message']);
        $this->assertEquals(422, $data['code']);
        $this->assertEquals('INTERNAL_ERROR', $data['error_code']);
        $this->assertEquals(['field' => 'value'], $data['details']);
    }

    // ─── SettlementStateException ───────────────────────────────────────────

    public function test_settlement_state_exception_defaults()
    {
        $exception = new SettlementStateException('Cannot transition');

        $this->assertEquals(422, $exception->getCode());
        $this->assertEquals('SETTLEMENT_INVALID_STATE', $exception->getErrorCode());
        $this->assertEquals('Cannot transition', $exception->getMessage());
    }

    public function test_settlement_state_exception_extends_base()
    {
        $exception = new SettlementStateException('Test');
        $this->assertInstanceOf(ShearerlineException::class, $exception);
    }

    // ─── CalculationException ──────────────────────────────────────────────

    public function test_calculation_exception_defaults()
    {
        $exception = new CalculationException('Math error');

        $this->assertEquals(422, $exception->getCode());
        $this->assertEquals('CALCULATION_ERROR', $exception->getErrorCode());
    }

    public function test_calculation_exception_with_details()
    {
        $exception = new CalculationException('Divide by zero', 422, ['divisor' => 0]);

        $this->assertEquals(422, $exception->getCode());
        $this->assertEquals('CALCULATION_ERROR', $exception->getErrorCode());
        $this->assertEquals(['divisor' => 0], $exception->getDetails());
    }

    // ─── SettlementNotFoundException ───────────────────────────────────────

    public function test_settlement_not_found_exception_defaults()
    {
        $exception = new SettlementNotFoundException('Settlement #123 not found');

        $this->assertEquals(404, $exception->getCode());
        $this->assertEquals('SETTLEMENT_NOT_FOUND', $exception->getErrorCode());
    }

    public function test_settlement_not_found_render_returns_404()
    {
        $request = $this->app->make('request');
        $request->headers->set('Accept', 'application/json');

        $exception = new SettlementNotFoundException('Not found');
        $response = $exception->render($request);

        $this->assertEquals(404, $response->status());
        $data = $response->getData(true);
        $this->assertEquals('SETTLEMENT_NOT_FOUND', $data['error_code']);
    }

    // ─── InvalidProductException ───────────────────────────────────────────

    public function test_invalid_product_exception_defaults()
    {
        $exception = new InvalidProductException('Product does not exist');

        $this->assertEquals(422, $exception->getCode());
        $this->assertEquals('INVALID_PRODUCT', $exception->getErrorCode());
    }

    // ─── UnauthorizedActionException ───────────────────────────────────────

    public function test_unauthorized_action_exception_defaults()
    {
        $exception = new UnauthorizedActionException('Forbidden');

        $this->assertEquals(403, $exception->getCode());
        $this->assertEquals('UNAUTHORIZED_ACTION', $exception->getErrorCode());
    }

    public function test_unauthorized_action_render_returns_403()
    {
        $request = $this->app->make('request');
        $request->headers->set('Accept', 'application/json');

        $exception = new UnauthorizedActionException('Not allowed');
        $response = $exception->render($request);

        $this->assertEquals(403, $response->status());
        $data = $response->getData(true);
        $this->assertEquals('UNAUTHORIZED_ACTION', $data['error_code']);
    }

    // ─── 异常层次结构验证 ──────────────────────────────────────────────────

    public function test_all_exceptions_extend_shearerline_exception()
    {
        $exceptions = [
            new SettlementStateException(''),
            new CalculationException(''),
            new SettlementNotFoundException(''),
            new InvalidProductException(''),
            new UnauthorizedActionException(''),
        ];

        foreach ($exceptions as $e) {
            $this->assertInstanceOf(ShearerlineException::class, $e);
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    public function test_exception_http_codes_are_distinct()
    {
        $codes = [
            (new SettlementStateException(''))->getCode(),
            (new CalculationException(''))->getCode(),
            (new SettlementNotFoundException(''))->getCode(),
            (new InvalidProductException(''))->getCode(),
            (new UnauthorizedActionException(''))->getCode(),
        ];

        $this->assertContains(403, $codes);
        $this->assertContains(404, $codes);
        $this->assertContains(422, $codes);
    }

    public function test_exception_error_codes_are_unique()
    {
        $exceptions = [
            new ShearerlineException(''),
            new SettlementStateException(''),
            new CalculationException(''),
            new SettlementNotFoundException(''),
            new InvalidProductException(''),
            new UnauthorizedActionException(''),
        ];

        $errorCodes = array_map(fn($e) => $e->getErrorCode(), $exceptions);
        $this->assertCount(count($exceptions), array_unique($errorCodes));
    }
}
