<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'organization_id', 'annotation_instruments'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $attributes = ['role' => 'user'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'annotation_instruments' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Annotation rights are given by an organization: they do not travel to the next one.
        static::saving(function (User $user) {
            if ($user->isDirty('organization_id') && $user->exists) {
                $user->annotation_instruments = null;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Admins manage every library; organization admins only their own organization's. */
    public function canManageLibrary(?int $organizationId): bool
    {
        return $this->isAdmin()
            || ($this->role === Role::OrgAdmin && $organizationId !== null && $organizationId === $this->organization_id);
    }

    public function belongsToOrganization(?int $organizationId): bool
    {
        return $organizationId !== null && $organizationId === $this->organization_id;
    }

    /** May edit the organization's shared annotations on pieces for this instrument. */
    public function canAnnotateInstrument(string $instrument): bool
    {
        return in_array($instrument, $this->annotation_instruments ?? [], true);
    }

    public function canManageAnyLibrary(): bool
    {
        return $this->isAdmin() || ($this->role === Role::OrgAdmin && $this->organization_id !== null);
    }

    public function uploadedPieces(): HasMany
    {
        return $this->hasMany(Piece::class, 'owner_id');
    }

    public function annotations(): HasMany
    {
        return $this->hasMany(PieceAnnotation::class);
    }

    public function practiceSessions(): HasMany
    {
        return $this->hasMany(PracticeSession::class);
    }

    public function pitchStats(): HasMany
    {
        return $this->hasMany(UserPitchStat::class);
    }
}
