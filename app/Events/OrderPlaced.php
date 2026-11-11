<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $user;

    public function __construct($order, $user)
    {
        $this->order = $order;
        $this->user = $user;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->user->id);
    }

    public function broadcastAs()
    {
        return 'order.placed';
    }

    public function broadcastWith()
    {
        return [
            'order_id' => is_object($this->order) ? ($this->order->id ?? null) : ($this->order['id'] ?? null),
            'total' => is_object($this->order) ? ($this->order->total ?? ($this->order->grand_total ?? 0)) : ($this->order['total'] ?? ($this->order['grand_total'] ?? 0)),
            'user_name' => is_object($this->user) ? ($this->user->name ?? '') : ($this->user['name'] ?? ''),
        ];
    }
}
