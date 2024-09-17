<?php

namespace Modules\SampleModule;

use Illuminate\Support\Facades\Event;
use Lakasir\LakasirModule\Events\TransactionSucceed;
use Lakasir\LakasirModule\Providers\ExtendModuleServiceProvider;

class SampleModuleServiceProvider extends ExtendModuleServiceProvider
{
    public function register()
    {
        parent::register();

        Event::listen(TransactionSucceed::class, function (TransactionSucceed $event) {
            // dd($event->sellings);
        });
    }
}
