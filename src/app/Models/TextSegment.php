<?php

namespace App\Models;

use App\Enums\SegmentKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TextSegment extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => SegmentKind::class,
            'is_excluded' => 'boolean',
            'char_start' => 'integer',
            'char_end' => 'integer',
            'word_count' => 'integer',
            'page_start' => 'integer',
            'page_end' => 'integer',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<DocumentSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(DocumentSection::class, 'section_id');
    }

    /** @return HasMany<SegmentPrediction, $this> */
    public function predictions(): HasMany
    {
        return $this->hasMany(SegmentPrediction::class, 'segment_id');
    }
}
