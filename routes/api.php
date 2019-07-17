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

Route::middleware(['codesign'])->group(function () {
    Route::any('add','\App\Http\Controllers\Code\VersionController@add');//添加版本
    Route::any('edit','\App\Http\Controllers\Code\VersionController@edit');//修改版本
    Route::any('upload','\App\Http\Controllers\Code\VersionController@upload');//上传文件
    Route::any('remove','\App\Http\Controllers\Code\VersionController@remove');//删除文件
    Route::any('audit','\App\Http\Controllers\Code\VersionController@audit');//提交审核
    Route::any('pass','\App\Http\Controllers\Code\VersionController@pass');//审核通过
    Route::any('nopass','\App\Http\Controllers\Code\VersionController@nopass');//审核不通过
    Route::any('test','\App\Http\Controllers\Code\VersionController@test');//测试通过
    Route::any('notest','\App\Http\Controllers\Code\VersionController@notest');//测试不通过
    Route::any('line','\App\Http\Controllers\Code\VersionController@line');//上线
    Route::any('rollback','\App\Http\Controllers\Code\VersionController@rollback');//回滚
    Route::any('del','\App\Http\Controllers\Code\VersionController@delversion');//删除版本
});

Route::post('create', '\App\Http\Controllers\Code\CodeController@create');
Route::any('wrong', '\App\Http\Controllers\Code\CodeController@wrong');

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
