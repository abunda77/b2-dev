<?php

namespace Database\Factories;

use App\Models\LoginOtpChallenge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<LoginOtpChallenge>
 */
class LoginOtpChallengeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => fake()->uuid(),
            'channel' => 'email',
            'destination' => fake()->safeEmail(),
            'code_hash' => bcrypt('123456'),
            'expires_at' => Carbon::now()->addMinutes(5),
            'verified_at' => null,
            'attempts' => 0,
            'max_attempts' => 5,
            'resend_count' => 0,
            'max_resends' => 3,
            'sent_status' => 'sent',
            'send_error' => null,
            'sent_at' => Carbon::now(),
            'last_sent_at' => Carbon::now(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'revoked_at' => null,
        ];
    }
}
