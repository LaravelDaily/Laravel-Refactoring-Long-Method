<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\InvoiceNotification;
use App\Notifications\NewOrderNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Invoice;

class OrderService
{

    public function place_order(int $userId, int $planId, int $duration)
    {
        // Calculate total price
        $plan = Plan::find($planId);
        if (!$plan) {
            throw new \Exception('Cannot find the plan');
        }
        $price = $plan->price * $duration;

        // Check user's balance
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('Cannot find the user');
        }
        if ($user->balance < $price) {
            throw new \Exception('User balance is lower than purchase price');
        }

        // Create order and update user
        $order = DB::transaction(function () use ($user, $userId, $planId, $duration, $price) {
            $order = Order::create([
                'user_id' => $userId,
                'plan_id' => $planId,
                'duration' => $duration,
                'price' => $price
            ]);
            $planUntil = now()->addMonths($duration)->toDateString();
            $newBalance = $user->balance - $price;
            $user->update([
                'plan_id' => $planId,
                'plan_until' => $planUntil,
                'balance' => $newBalance
            ]);

            return $order;
        });

        // Save invoice and send it
        $customer = new Buyer([
            'name'          => $user->name,
            'custom_fields' => [
                'email' => $user->email,
            ],
        ]);

        $item = (new InvoiceItem())->title($plan->name)->pricePerUnit($plan->price);

        Invoice::make()
            ->buyer($customer)
            ->addItem($item)
            ->filename($order->id)
            ->save('public');

        Notification::send($user, new InvoiceNotification($order));

        // Send notifications to admins
        $admins = User::where('is_admin', 1)->get();
        Notification::send($admins, new NewOrderNotification($order));
    }

}
