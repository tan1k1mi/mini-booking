<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function () {
    $index = public_path('app/index.html');

    abort_unless(is_file($index), 404);

    return response()->file($index);
})->where('path', '^(?!api(?:/|$)|up$).*$');
