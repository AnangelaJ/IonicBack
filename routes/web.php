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
Route::post('signup', 'AuthController@register'); //
Route::get('/', function () {
    return view('welcome');
});

Route::group([
    'prefix' => 'auth',
], function ($router) {
    Route::post('Login', 'AuthController@login'); //
});

Route::group([
    'middleware' => 'jwt.auth'
], function($router){
    Route::post('logout', 'AuthController@logout'); //
    Route::post('/editProfile', 'AuthController@editProfile'); //
    Route::get('/follow/{idSeguidor}', 'AuthController@follow'); //
    Route::post('/create/post', 'PostController@store'); //
    Route::post('/create/coment', 'PostController@storeComent'); //
    Route::delete('/delete/coment/{idComent}', 'PostController@deleteComent'); //
    Route::get('/show/posts/user/{idUser}', 'PostController@showByUser'); //
    Route::get('/show/posts/all', 'PostController@index'); //
    Route::post('/like/post/{idPost}', 'PostController@like'); //
    Route::post('/dislike/post/{idPost}', 'PostController@dislike'); //
    Route::get('/show/users/{email}', 'AuthController@showUsers'); //
    Route::get('/show/user/{idUser}', 'AuthController@show'); //
});