<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'active_users' => User::query()->where('status', 'active')->count(),
                'documents' => Document::query()->count(),
                'analyses' => Analysis::query()->count(),
                'pending_analyses' => Analysis::query()->whereIn('status', [
                    AnalysisStatus::Queued,
                    AnalysisStatus::Running,
                ])->count(),
            ],
            'recentEvents' => AuditLog::query()
                ->with('user')
                ->latest('created_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
