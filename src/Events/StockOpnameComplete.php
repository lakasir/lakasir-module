<?php

namespace Lakasir\LakasirModule\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockOpnameComplete
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  \App\Models\Tenants\StockOpname  $stockOpname
     * @return void
     */
    public function __construct(public $stockOpname, public array $data)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
