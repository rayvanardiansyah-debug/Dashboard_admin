<?php

namespace Tests\Unit;

use App\Events\OrderPlaced;
use PHPUnit\Framework\TestCase;

class OrderPlacedTest extends TestCase
{
    public function test_order_placed_event_properties(): void
    {
        $user = (object) [
            'id' => 5,
            'name' => 'Budi Santoso',
        ];

        $order = (object) [
            'id' => 101,
            'total' => 150000,
        ];

        $event = new OrderPlaced($order, $user);

        // Validasi private channel name
        $channels = $event->broadcastOn();
        $this->assertEquals('private-user.5', $channels->name);

        // Validasi broadcastAs
        $this->assertEquals('order.placed', $event->broadcastAs());

        // Validasi broadcast payload data
        $payload = $event->broadcastWith();
        $this->assertEquals(101, $payload['order_id']);
        $this->assertEquals(150000, $payload['total']);
        $this->assertEquals('Budi Santoso', $payload['user_name']);
    }
}
