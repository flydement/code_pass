<?php

namespace App\Http\Middleware;
use App\Code;
use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

use App\Http\Controllers\Code\CodeController;
use Illuminate\Support\Facades\Input;

class Codesign extends Middleware
{


    public function handle($request, \Closure $next)
    {

        $username = Input::post('username');
        $password = Input::post('password');

        if(empty($username) or empty($password)){
            return Code::wrong("缺少必要参数",401);
        }

        $res =  Code::login($username,$password);
        if($res === false){
            return Code::wrong("登录失败",405);
        }

        return $next($request);

    }
}
