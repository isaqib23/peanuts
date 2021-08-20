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

Route::prefix('admin/suppliers')->group(function() {

    Route::get('/', [
        'as' => 'admin.suppliers.index',
        'uses' => 'Admin\SuppliersController@index'
    ]);

    Route::get('/create', [
        'as' => 'admin.suppliers.create',
        'uses' => 'Admin\SuppliersController@create',
    ]);

    Route::post('/', [
        'as' => 'admin.suppliers.store',
        'uses' => 'Admin\SuppliersController@store',
    ]);

    Route::get('/{id}/edit', [
        'as' => 'admin.suppliers.edit',
        'uses' => 'Admin\SuppliersController@edit',
    ]);

    Route::put('/{id}', [
        'as' => 'admin.suppliers.update',
        'uses' => 'Admin\SuppliersController@update',
    ]);

    Route::delete('/{ids?}', [
        'as' => 'admin.suppliers.destroy',
        'uses' => 'Admin\SuppliersController@destroy',
    ]);

});
