<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DollChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A line was added to a Doll chat. The only real-time event in the product
 * besides personal notifications (ADR-0005).
 *
 * The payload is deliberately thin — id and type only. Clients fetch the body
 * through `GET /doll-requests/{id}/messages`, which re-checks that the channel
 * is still open and that the caller is a participant. Nothing confidential
 * crosses the WebSocket.
 */
class DollChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly DollChatMessage $message) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('doll-request.'.$this->message->doll_request_id)];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'type' => $this->message->type->value,
            'sender_id' => $this->message->sender_id,
            'created_at' => $this->message->created_at->toIso8601ZuluString(),
        ];
    }
}
