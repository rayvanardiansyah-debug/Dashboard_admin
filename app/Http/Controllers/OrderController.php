<?php

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /**
     * Store a newly created order and trigger the OrderPlaced broadcast event.
     * Sesuai Prosedur Kerja BKPM Acara 32 Langkah 2.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user() ?? User::first();

        // Validasi atau gunakan data dummy jika dipanggil untuk pengujian
        $validated = $request->validate([
            'order_id' => 'nullable|integer',
            'total' => 'nullable|numeric',
        ]);

        $orderId = $validated['order_id'] ?? rand(1001, 9999);
        $total = $validated['total'] ?? rand(50000, 350000);

        // Objek order (bisa berupa model Transaction atau generic object)
        $order = (object) [
            'id' => $orderId,
            'total' => (int) $total,
        ];

        event(new OrderPlaced($order, $user));

        return response()->json([
            'status' => 'success',
            'message' => 'Event OrderPlaced berhasil dipicu (broadcasted)!',
            'data' => [
                'order_id' => $order->id,
                'total' => $order->total,
                'user_id' => $user->id,
                'user_name' => $user->name,
            ],
        ]);
    }
}
