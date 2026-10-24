<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use App\Models\Document;
use App\Models\SegmentPrediction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $documents = Document::query()->where('user_id', $user->getKey());
        $analyses = Analysis::query()->where('user_id', $user->getKey());

        $stats = [
            'documents' => (clone $documents)->count(),
            'analyses' => (clone $analyses)->count(),
            'pending' => (clone $analyses)->whereIn('status', [
                AnalysisStatus::Queued,
                AnalysisStatus::Running,
            ])->count(),
            'review' => SegmentPrediction::query()
                ->where('requires_review', true)
                ->whereHas('analysis', fn ($query) => $query->where('user_id', $user->getKey()))
                ->count(),
        ];

        $recentDocuments = (clone $documents)
            ->withCount('segments')
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard', [
            'stats' => $stats,
            'recentDocuments' => $recentDocuments,
        ]);
    }
}
