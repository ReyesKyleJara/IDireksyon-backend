<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GovernmentId;

class DashboardController extends Controller
{
    public function index()
    {
        $governmentIdCount = GovernmentId::count();

        return view('admin.dashboard', compact('governmentIdCount'));
    }
}