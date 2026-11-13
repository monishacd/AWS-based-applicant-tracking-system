<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'department',
        'employment_type',
        'required_skills_json',
        'experience_requirements',
        'status',
    ];

    protected $casts = [
        'required_skills_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(Matches::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function scopeAccessibleToRecruiter(Builder $query, User $recruiter): Builder
    {
        return $query->whereIn('user_id', $recruiter->recruiterWorkspaceIds());
    }
}
