<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VendorDashboardController extends Controller
{
    /**
     * Display the vendor dashboard.
     */
    public function index()
    {
        return view('vendor.dashboard');
    }
}
