<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\OrganizationScope;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_active', 'firebase_uid', 'onboarded_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function unitAssignments(): HasMany
    {
        return $this->hasMany(UnitAssignment::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && $this->unitAssignments()->where('is_active', true)->exists();
    }

    /**
     * Determine whether the user has completed onboarding.
     */
    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null && filled($this->name);
    }

    /**
     * @return list<int>
     */
    public function visibleUnitIds(): array
    {
        return app(OrganizationScope::class)->visibleUnitIds($this);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
