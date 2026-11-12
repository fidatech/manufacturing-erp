<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('audit.view');

        $logs = AuditLog::with('user')
            ->when($request->query('user'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when(trim((string) $request->query('q')), fn ($q, $term) => $q->where(fn ($i) => $i->where('description', 'like', "%$term%")->orWhere('subject_type', 'like', "%$term%")))
            ->orderByDesc('id')
            ->paginate(per_page(50))
            ->withQueryString();

        return view('admin.audit', ['logs' => $logs, 'users' => User::orderBy('name')->pluck('name', 'id')]);
    }
}
