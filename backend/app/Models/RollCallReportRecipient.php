<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RollCallReportRecipient extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'email',
        'label',
        'grade_level',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedEmail(): ?string
    {
        if ($this->user?->email) {
            return $this->user->email;
        }

        $email = trim((string) $this->email);

        return $email !== '' ? $email : null;
    }
}
