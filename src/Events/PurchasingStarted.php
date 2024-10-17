<?php

namespace Lakasir\LakasirModule\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchasingStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  \App\Models\Tenants\Purchasing  $purchasing
     * @return void
     */
    public function __construct(public $purchasing, public array $data)
    {

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
