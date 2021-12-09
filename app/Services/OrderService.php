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

    public function placeOrder(int $userId, int $planId, int $duration)
    {
        $plan = Plan::find($planId);
        if (!$plan) {
            throw new \Exception('Cannot find the plan');
        }

        $price = $this->calculateTotalPrice($plan->price, $duration);
        $user = $this->checkUserBalance($userId, $price);
        $order = $this->saveOrder($user, $planId, $duration, $price);
        $this->sendInvoice($user, $plan, $order);
        $this->sendNotificationsToAdmins($order);
    }

    public function calculateTotalPrice(int $planPrice, int $duration): int
    {
        return $planPrice * $duration;
    }

    public function checkUserBalance(int $userId, $price)
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('Cannot find the user');
        }
        if ($user->balance < $price) {
            throw new \Exception('User balance is lower than purchase price');
        }
        return $user;
    }

    public function saveOrder($user, int $planId, int $duration, $price)
    {
        return DB::transaction(function () use ($user, $planId, $duration, $price) {
            $order = Order::create([
                'user_id' => $user->id,
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
    }

    public function sendInvoice($user, $plan, $order): void
    {
        $customer = new Buyer([
            'name' => $user->name,
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
    }

    public function sendNotificationsToAdmins($order): void
    {
        $admins = User::where('is_admin', 1)->get();
        Notification::send($admins, new NewOrderNotification($order));
    }

}
