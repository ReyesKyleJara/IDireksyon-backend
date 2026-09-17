<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GovernmentId;

class DashboardController extends Controller
{
    public function index()
    {
        $totalGovernmentIds = GovernmentId::count();

        $recentlyAdded = GovernmentId::where(
            'created_at',
            '>=',
            now()->subDays(30)
        )->count();

        $needsVerification = GovernmentId::where(
            'last_updated',
            '<',
            now()->subMonths(6)
        )->count();

        return view('admin.dashboard', compact(
            'totalGovernmentIds',
            'recentlyAdded',
            'needsVerification'
        ));
    }
}