<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::post('/order_confirmation', 'ApisController@order_confirmation');
Route::get('email_confirmation', 'ApisController@email_confirmation')->name('email_confirmation');
