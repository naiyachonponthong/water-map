<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $province = $this->province();

        $logs = AuditLog::with(['user:id,name,avatar_url', 'province:id,name_th'])
            ->when(! $me->isSuperAdmin() || ! $request->boolean('all'), fn ($q) => $q->where('province_id', $province->id))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('subject'), fn ($q) => $q->where('subject_type', $request->query('subject')))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->query('date')))
            ->latest('created_at')
            ->paginate(40)
            ->withQueryString();

        $users = User::query()
            ->when(! $me->isSuperAdmin(), fn ($q) => $q->where('province_id', $province->id))
            ->orderBy('name')->get(['id', 'name']);

        return view('admin.audit.index', [
            'logs' => $logs,
            'users' => $users,
            'actions' => AuditLog::ACTIONS,
            'subjects' => AuditLog::SUBJECTS,
        ]);
    }

    public function show(Request $request, AuditLog $log)
    {
        if (! $request->user()->isSuperAdmin()) {
            $this->authorizeProvince($log->province_id);
        }

        return view('admin.audit.show', ['log' => $log->load('user', 'province')]);
    }
}
