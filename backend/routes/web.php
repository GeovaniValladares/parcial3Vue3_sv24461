<?php

use Illuminate\Support\Facades\Route;
require __DIR__.'/academic.php';

Route::get('/', function () {
    return view('welcome');
});
