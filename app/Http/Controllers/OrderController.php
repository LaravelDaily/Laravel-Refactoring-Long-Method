<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request, OrderService $orderService)
    {
        try {
            $orderService->placeOrder(auth()->id(), $request->plan_id, $request->duration);
        } catch (\Exception $exception) {
            abort(400, $exception->getMessage());
        }

        return 'Success';
    }
}
