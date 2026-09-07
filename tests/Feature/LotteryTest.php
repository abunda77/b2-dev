<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotteryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('lottery.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_lottery_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withSession(['auth.pending_otp_passed' => true]);

        $response = $this->get(route('lottery.index'));
        $response->assertOk();
    }

    public function test_lottery_page_has_spin_button(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withSession(['auth.pending_otp_passed' => true]);

        $response = $this->get(route('lottery.index'));
        $response->assertOk();
        $response->assertSee('Putar Sekarang');
    }

    public function test_lottery_page_has_probability_table(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withSession(['auth.pending_otp_passed' => true]);

        $response = $this->get(route('lottery.index'));
        $response->assertOk();
        $response->assertSee('Tabel Peluang');
    }
}
