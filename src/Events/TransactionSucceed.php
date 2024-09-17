<?php

namespace Lakasir\LakasirModule\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionSucceed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $selling;

    public array $data;

    public function __construct($selling, $data = [])
    {
        $this->selling = $selling;
        $this->data = $data;
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
