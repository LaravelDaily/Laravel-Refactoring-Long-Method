<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Notifications\InvoiceNotification;
use App\Notifications\NewOrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_saved_successfully()
    {
        $planPrice = 11;
        $duration = 3;
        $userBalance = 100;

        Notification::fake();

        $plan = Plan::create([
            'name' => 'Silver plan',
            'price' => $planPrice,
        ]);

        $user = User::factory()->create(['balance' => $userBalance]);
        $admin = User::factory()->create(['is_admin' => 1]);

        $response = $this->actingAs($user)->post('/orders', [
            'plan_id' => $plan->id,
            'duration' => $duration, // in months
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_id' => $plan->id,
            'plan_until' => now()->addMonths($duration)->toDateString(),
            'balance' => $userBalance - $planPrice * $duration
        ]);

        Notification::assertSentTo(
            [$user], InvoiceNotification::class
        );

        Notification::assertSentTo(
            [$admin], NewOrderNotification::class
        );
    }
}
