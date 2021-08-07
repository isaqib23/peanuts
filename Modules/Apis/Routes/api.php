<?php

use Illuminate\Support\Facades\Route;

Route::post('login', 'ApisController@login')->name('login');
Route::post('signup', 'ApisController@register')->name('register');
Route::post('products', 'ApisController@products')->name('products');
Route::post('product', 'ApisController@product')->name('product');
