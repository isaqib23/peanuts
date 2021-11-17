<?php

use Illuminate\Support\Facades\Route;

Route::match(array('GET','POST'),'reports', [
    'as' => 'admin.reports.index',
    'uses' => 'ReportController@index',
    'middleware' => 'can:admin.reports.index',
]);
