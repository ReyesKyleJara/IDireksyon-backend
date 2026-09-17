<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentChangeLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $logs = ContentChangeLog::with('user')->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query
            ->where('entity_name', 'like', '%'.$q.'%')->orWhere('entity_type', 'like', '%'.$q.'%')
            ->orWhereHas('user', fn ($query) => $query->where('username', 'like', '%'.$q.'%')->orWhere('name', 'like', '%'.$q.'%'))))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.audit-logs.index', compact('logs'));
    }
}
