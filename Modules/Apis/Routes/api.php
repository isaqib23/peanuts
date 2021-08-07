<?php

use Illuminate\Support\Facades\Route;

Route::post('login', 'ApisController@login')->name('login');
Route::post('signup', 'ApisController@register')->name('register');
