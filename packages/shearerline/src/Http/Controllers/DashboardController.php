<?php

namespace Shearerline\Http\Controllers;

use Shearerline\Shearerline;

class DashboardController extends Controller
{
    protected $shearerline;

    public function __construct(Shearerline $shearerline)
    {
        $this->shearerline = $shearerline;
    }

    public function index()
    {
        $stats = $this->shearerline->getDashboardStatistics();
        return view('shearerline::dashboard', compact('stats'));
    }
}
