<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['web', 'auth', 'locale'],
    'prefix' => '/tools/mineralhauling',
    'namespace'=>'RecursiveTree\Seat\MineralHauling\Http\Controllers'
], function () {
    Route::get('/')
        ->name('mineralhauling::calculator')
        ->uses('MineralHaulingController@calculator');
        //->middleware('can:pricescore.settings');

    Route::post('/calculate')
        ->name('mineralhauling::calculate')
        ->uses('MineralHaulingController@calculate');

    Route::get('/type/{id}')
        ->name('mineralhauling::type.info')
        ->uses('MineralHaulingController@typeInfo');
});