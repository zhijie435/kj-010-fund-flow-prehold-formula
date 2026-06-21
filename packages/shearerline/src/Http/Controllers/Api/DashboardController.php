<?php

namespace Shearerline\Http\Controllers\Api;

use Shearerline\Http\Controllers\Controller;
use Shearerline\Shearerline;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $shearerline;

    public function __construct(Shearerline $shearerline)
    {
        $this->shearerline = $shearerline;
    }

    public function statistics()
    {
        $stats = $this->shearerline->getDashboardStatistics();

        return $this->success($stats, '获取统计数据成功');
    }
}
