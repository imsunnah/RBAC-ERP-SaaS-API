<?php

namespace App\Services\Auth;

use App\Models\PasswordHistory;
use App\Models\User;
use App\Models\UserLoginAttempt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;

class AuthService
{
    private const MAX_PASSWORD_HISTORY = 5;

    /**
     * Log a login attempt (success or failure).
     */
    public function logLoginAttempt(string $email, string $status, ?string $reason = null, ?User $user = null): void
    {
        UserLoginAttempt::create([
            'user_id' => $user?->id,
            'email' => $email,
            'status' => $status,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'failure_reason' => $reason,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Record a new password to history.
     */
    public function recordPasswordHistory(User $user, string $hashedPassword): void
    {
        PasswordHistory::create([
            'user_id' => $user->id,
            'password' => $hashedPassword,
            'created_at' => now(),
        ]);

        // Purge old entries beyond max history
        $user->passwordHistories()
            ->orderByDesc('created_at')
            ->skip(self::MAX_PASSWORD_HISTORY)
            ->take(100)
            ->delete();
    }

    /**
     * Check if password was used recently.
     */
    public function wasPasswordUsed(User $user, string $plainPassword): bool
    {
        return $user->passwordHistories()
            ->orderByDesc('created_at')
            ->take(self::MAX_PASSWORD_HISTORY)
            ->get()
            ->contains(fn($h) => Hash::check($plainPassword, $h->password));
    }

    /**
     * Update user's last login metadata.
     */
    public function updateLastLogin(User $user): void
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => Request::ip(),
        ]);
    }
}
