<?php

namespace Lakasir\LakasirModule\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionSucceed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public \App\Models\Tenants\Selling $sellings;

    public function __construct(\App\Models\Tenants\Selling $sellings)
    {
        $this->sellings = $sellings;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('transaction-succeed'),
        ];
    }
}
