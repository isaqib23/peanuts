<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/votes')->group(function() {

    Route::get('/', [
        'as' => 'admin.votes.index',
        'uses' => 'Admin\VotesController@index'
    ]);

    Route::get('/create', [
        'as' => 'admin.votes.create',
        'uses' => 'Admin\VotesController@create',
    ]);

    Route::post('/', [
        'as' => 'admin.votes.store',
        'uses' => 'Admin\VotesController@store',
    ]);

    Route::get('/{id}/edit', [
        'as' => 'admin.votes.edit',
        'uses' => 'Admin\VotesController@edit',
    ]);

    Route::put('/{id}', [
        'as' => 'admin.votes.update',
        'uses' => 'Admin\VotesController@update',
    ]);

    Route::delete('/{ids?}', [
        'as' => 'admin.votes.destroy',
        'uses' => 'Admin\VotesController@destroy',
    ]);
});
