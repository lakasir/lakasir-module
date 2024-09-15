<?php

use Illuminate\Support\Facades\Route;
use Modules\SampleModule\Http\Controllers\ModuleController;

Route::get('/', [ModuleController::class, 'index']);
