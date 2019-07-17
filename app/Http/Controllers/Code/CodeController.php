<?php

namespace App\Http\Controllers\Code;

use App\Code;
use App\Http\Controllers\Controller;
use http\Env\Request;
use Illuminate\Support\Facades\Input;

class CodeController extends Controller
{
    /*
     8:支付主项目(后台)
     12:微信开发平台
     20:支付订单系统（旧）
     30:公共api
     32:超盟商家公共接口
     48:支付主项目（微信端）
     50:支付主项目(app)
     72:超盟零售(微信端)
     74:超盟零售(订单系统)
     76:超盟零售(app)
     78:超盟零售(后台)
     80:超盟零售api
     */
    public $code;
    protected $allowlsit = [8,12,20,30,32,48,50,72,74,76,78,80];
    protected $username;
    protected $password;
    protected static $admin_username = 'xufei';
    protected static $admin_password = 'Xf888888';

    /*
     * 构造
     */
    public function __construct()
    {
        $this->username = Input::post("username");
        $this->password = Input::post("password");
    }

    /*
     * 格式化
     */
    public static function format($msg='',$code =0,$data= []){
        return response()->json(['msg'=>$msg,'code'=>$code,'data'=>$data],200);
    }

    /*
     * 错误方法
     */
    public function wrong($msg='wrong',$code = 405,$data =[]){
        return response()->json(['msg'=>$msg,'code'=>$code,'data'=>$data]);
    }

    /*
     * 登录
     */
    public function login(){

        $res = Code::login($this->username,$this->password);
        if($res){
            return self::format('登录失败',405);
        }
        return self::format('登录成功',0);
    }

    /*
     * 登录
     */
    protected static  function admin_login(){

        $res = Code::login(self::$admin_username,self::$admin_password,"admin_login");
        return $res;
    }





	
}
