<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'metadata' => 'array',
            'size_bytes' => 'integer',
            'page_count' => 'integer',
            'word_count' => 'integer',
            'paragraph_count' => 'integer',
            'uploaded_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<DocumentSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(DocumentSection::class)->orderBy('position');
    }

    /** @return HasMany<TextSegment, $this> */
    public function segments(): HasMany
    {
        return $this->hasMany(TextSegment::class)->orderBy('position');
    }

    /** @return HasMany<Analysis, $this> */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class)->latest();
    }

    public function latestAnalysis(): ?Analysis
    {
        return $this->analyses()->first();
    }
}
