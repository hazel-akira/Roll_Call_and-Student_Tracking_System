<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'level',
        'is_junior',
        'active',
        'dynamics_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_junior' => 'boolean',
    ];

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'school_user')
            ->withTimestamps();
    }

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(Student::class, SchoolClass::class, 'school_id', 'class_id');
    }
}
