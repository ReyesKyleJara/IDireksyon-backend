<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\GovernmentId;
use App\Models\Office;

class DashboardController extends Controller
{
    public function index()
    {
        $governmentIdCount = GovernmentId::count();
        $documentCount = Document::count();
        $officeCount = Office::count();
        $recentGovernmentIds = GovernmentId::with('agency')
            ->latest('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'governmentIdCount',
            'documentCount',
            'officeCount',
            'recentGovernmentIds',
        ));
    }
}
