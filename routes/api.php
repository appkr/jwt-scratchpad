<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('tokens/create', [
    'as' => 'tokens.created',
    'uses' => 'TokensController@store',
]);

Route::post('tokens/refresh', [
    'as' => 'tokens.refresh',
    'middleware' => 'jwt.refresh',
    'uses' => function () {}
]);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('jwt.auth');

DB::listen(function ($query) {
    Log::info('query', [$query->sql]);
});


