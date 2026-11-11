<?php

namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderPlacedBroadcastTest extends TestCase
{
    public function test_order_placed_event_has_correct_broadcast_channel_and_payload(): void
    {
        $user = new User(['name' => 'Admin User']);
        $user->id = 1;
        $order = (object) [
            'id' => 99,
            'total' => 150000,
        ];

        $event = new OrderPlaced($order, $user);

        // Periksa broadcast channel (PrivateChannel 'user.{id}')
        $channels = $event->broadcastOn();
        $this->assertEquals('private-user.' . $user->id, $channels->name);

        // Periksa broadcast alias
        $this->assertEquals('order.placed', $event->broadcastAs());

        // Periksa broadcast payload
        $payload = $event->broadcastWith();
        $this->assertEquals(99, $payload['order_id']);
        $this->assertEquals(150000, $payload['total']);
        $this->assertEquals($user->name, $payload['user_name']);
    }

    public function test_triggering_order_broadcast_dispatches_event(): void
    {
        Event::fake([OrderPlaced::class]);

        $user = new User(['name' => 'Admin User']);
        $user->id = 1;

        $response = $this->actingAs($user)->postJson(route('admin.order.trigger'), [
            'order_id' => 123,
            'total' => 250000,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'order_id' => 123,
                'total' => 250000,
            ],
        ]);

        Event::assertDispatched(OrderPlaced::class, function ($event) use ($user) {
            return $event->order->id === 123 && $event->user->id === $user->id;
        });
    }
}
