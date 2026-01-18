<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AuthSession extends Model
{
    protected $table = 'auth_sessions';

    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'refresh_token_hash',
        'ip_address',
        'user_agent',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    /* -------------------------
     | Relationships
     |--------------------------*/
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* -------------------------
     | Session state helpers
     |--------------------------*/
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function revoke(): void
    {
        $this->update([
            'revoked_at' => now(),
        ]);
    }

    public function markUsed(): void
    {
        $this->update([
            'last_used_at' => now(),
        ]);
    }
}
