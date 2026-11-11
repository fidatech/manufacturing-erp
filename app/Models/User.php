<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Audited, HasFactory, HasRoles, Notifiable;

    /** The role that may do everything. It cannot be edited or removed. */
    public const ADMIN_ROLE = 'Administrator';

    protected $fillable = ['name', 'email', 'password', 'is_active', 'calendar'];

    protected $hidden = ['password', 'remember_token'];

    /** One or two capital letters for the avatar, e.g. "Ahmad Karimi" gives "AK". */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: '?';
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ADMIN_ROLE);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
