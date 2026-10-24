<?php

namespace App\Models;

use App\Enums\ConfidenceLevel;
use App\Enums\SegmentLabel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SegmentPrediction extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'label' => SegmentLabel::class,
            'confidence' => ConfidenceLevel::class,
            'score' => 'float',
            'indicators' => 'array',
            'requires_review' => 'boolean',
        ];
    }

    /** @return BelongsTo<Analysis, $this> */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    /** @return BelongsTo<TextSegment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(TextSegment::class, 'segment_id');
    }

    /** @return BelongsTo<ModelVersion, $this> */
    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class);
    }
}
