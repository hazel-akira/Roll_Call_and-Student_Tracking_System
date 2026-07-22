<?php

namespace App\Models;

use App\Support\DutyRosterCategories;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolDutyRosterTemplateEntry extends Model
{
    protected $fillable = [
        'school_id',
        'category',
        'location',
        'time_slot',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function categoryLabel(): string
    {
        return DutyRosterCategories::label($this->category);
    }

    /**
     * @return array{category: string, location: string|null, time_slot: string|null, sort_order: int}
     */
    public function toTemplateRow(): array
    {
        return [
            'category' => $this->category,
            'location' => $this->location,
            'time_slot' => $this->time_slot,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
