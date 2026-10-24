<?php

namespace App\Models;

use App\Enums\AnalysisConclusion;
use App\Enums\AnalysisStatus;
use App\Enums\ConfidenceLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Analysis extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'confidence' => ConfidenceLevel::class,
            'conclusion' => AnalysisConclusion::class,
            'parameters' => 'array',
            'summary' => 'array',
            'aggregate_score' => 'float',
            'progress' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ModelVersion, $this> */
    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class);
    }

    /** @return HasMany<SegmentPrediction, $this> */
    public function predictions(): HasMany
    {
        return $this->hasMany(SegmentPrediction::class);
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class)->latest();
    }
}
