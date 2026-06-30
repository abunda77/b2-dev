<?php

namespace App\Models;

use Database\Factories\LoginOtpChallengeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $session_id
 * @property string $channel
 * @property string $destination
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property Carbon|null $verified_at
 * @property int $attempts
 * @property int $max_attempts
 * @property int $resend_count
 * @property int $max_resends
 * @property string $sent_status
 * @property string|null $send_error
 * @property Carbon $sent_at
 * @property Carbon|null $last_sent_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $revoked_at
 * @property User $user
 */
class LoginOtpChallenge extends Model
{
    /** @use HasFactory<LoginOtpChallengeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'channel',
        'destination',
        'code_hash',
        'expires_at',
        'verified_at',
        'attempts',
        'max_attempts',
        'resend_count',
        'max_resends',
        'sent_at',
        'last_sent_at',
        'ip_address',
        'user_agent',
        'revoked_at',
        'sent_status',
        'send_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->sent_status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->sent_status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->sent_status === 'failed';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
