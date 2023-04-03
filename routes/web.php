<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\IndexController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('users', function()
{
    return 'Users!';
});
Route::get('/user/{id}', [UserController::class, 'show']);
Route::get('/{path}', 'App\Http\Controllers\IndexController@index')->where('path', '.*');

