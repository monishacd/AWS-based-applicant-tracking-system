<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'company_name',
        'headline',
        'location',
        'bio',
        'skills_json',
        'preferred_job_titles_json',
        'preferred_location',
        'company_website',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'skills_json' => 'array',
            'preferred_job_titles_json' => 'array',
        ];
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function recruiterWorkspaceIds(): array
    {
        $sharedEmails = config('recruiter_sharing.shared_emails', []);
        if (! in_array(strtolower(trim($this->email)), $sharedEmails, true)) {
            return [$this->id];
        }

        return static::query()
            ->where('role', 'hr')
            ->whereIn(DB::raw('LOWER(email)'), $sharedEmails)
            ->pluck('id')
            ->all();
    }

    public function jobIdRange(): ?array
    {
        $range = config('job_listing.id_ranges.'.strtolower(trim($this->email)));
        if (! is_array($range) || count($range) !== 2) {
            return null;
        }

        return [(int) $range[0], (int) $range[1]];
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}
