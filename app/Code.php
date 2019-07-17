<?php

namespace App;


use Illuminate\Database\Eloquent\Model;

class Code extends Model
{

    public static 	$url = 'http://newcode.3cuc.com.cn';
    private static $keys = '50C7DE419914169C81FFEE232EFBB791';

    public function __construct()
    {

    }

    /*
     * 错误方法
     */
    public static function wrong($msg='wrong',$code = 405,$data =[]){
        return response()->json(['msg'=>$msg,'code'=>$code,'data'=>$data]);
    }

    /*
	* 登录
	*/
    public static function login($username ,$password,$login_name='login'){

        if(session($login_name))
            return true;

        $loginurl = self::$url.'/api/login';
        $login['username']	= $username;//输入账号
        $login['password']	= md5($password);//输入密码
        $login['version']	= 1;
        $login['timestamp']	= time();
        $login['sign'] 	  	= self::setSign($login);


        $res = self::post($loginurl,$login);
        $data = json_decode($res,1);
        if($data['result'] == true && !empty($data['data']['sid']) ){
            //$sid = $data['data']['sid'];
            session(["{$login_name}"=>$data['data']['sid']]);
            return true;
        }

        return false;

    }

    /*
    * 获取加密
    */
    private static function setSign($data){
        $k = self::$keys;
        $arr = [];
        foreach($data as $key=>$val){
            $arr[]	=	$key;
        }
        sort($arr);
        $s = '';
        foreach($arr as $i=>$v){
            $s .= 0==$i?$arr[$i].'='.$data[$arr[$i]]:'&'.$arr[$i].'='.$data[$arr[$i]];
        }
        return md5($s.$k);
    }

    /*
	* 创建版本
	*/
    public static function addVersion($param = array()){

        $id 		= $param['id']??'';
        $name 		= $param['name']?:'APP组版本';//版本名称
        $project_id	= intval($param['project_id'])??8;// 8支付正式
        $redis_desc = $param['redis_desc']??"";//redis描述
        $mysql_desc = $param['mysql_desc']??'';//mysql描述
        $other_desc	= $param['other_desc']??'';//其他描述
        $info 		= json_encode([$redis_desc,$mysql_desc,$other_desc]);


        $data = [
            'id'=>$id,
            'name'=>$name,
            'info'=>$info,
            'project_id'=>$project_id,
            'version'=>1,
            'sid'=>$param['sid'],
        ];
        $data['sign'] = self::setSign($data);

        $url = self::$url.'/api/updateversion';
        $res = self::post($url,$data);
        return json_decode($res,1);

    }

    /*
    * 上线
    */
    public static function versiononline($version_id,$sid){
        $url = self::$url.'/api/versiononline';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['id']			=	$version_id;
        $param['online_type']	=	1;
        $param['sign']			= 	self::setSign($param);
        $res = self::post($url,$param);
        return json_decode($res,1);

    }

    /*
    * 回滚
    */
    public static function versionrollback($version_id,$sid){
        $url = self::$url.'/api/versionrollback';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['id']			=	$version_id;
        $param['server_id']		=	'';
        $param['sign']			= 	self::setSign($param);
        $res = self::post($url,$param);
        return json_decode($res,1);

    }

    /*
    * 传参
    */
    public static function post($url,$post_data= [],$file = ''){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, ($post_data));
        if($file){
            curl_setopt($curl, CURLOPT_POSTFIELDS, $file);
        }
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;
    }

    /*
    * 订单推送
    */
    public static function dingding( $message = '抢到版本了',$atMobiles='',$isAtAll=true) {

        $remote_server = 'https://oapi.dingtalk.com/robot/send?access_token=dd844f2eafcd2dcf74dc01ec414b0d48c5da901ec5bd4df53400110a907dffe9';

        $data = array ('msgtype' => 'text','text' => array ('content' => $message));
        $data['at']	=	array('isAtAll'=>$isAtAll,'atMobiles'=>$atMobiles);//isAtAll 全部人推送 atMobiles 自己手机号
        $post_string = json_encode($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $remote_server);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array ('Content-Type: application/json;charset=utf-8'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /*
    * 提交审核
    */
    public static function versionaudit($version_id,array $reason,$sid){

        $url = self::$url.'/api/versionaudit';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['id']			=	$version_id;
        $param['reason']		=	json_encode($reason);
        $param['sign']			= 	self::setSign($param);
        $res = self::post($url,$param);
        return json_decode($res,1);

    }

    /*
    * 上传文件
    */
    public static function uploadfile($version_id,$filepath,$sid){

        $upload_param['version']		=	1;
        $upload_param['version_id']		=	$version_id;
        $upload_param['sid']			=	$sid;
        $upload_param['sign']           =   self::setSign($upload_param);

        if(file_exists($filepath)){
            if (class_exists('\CURLFile')) {
                $file = new \CURLFile($filepath,'application/octet-stream');
            } else {
                $file = "@".realpath ($filepath );
            }

            $upload_param['files[]'] = $file;
            $upload_url = self::$url."/api/upfile";
            $result = self::post($upload_url,$upload_param);
            $result = json_decode($result,1);
            return $result;
        }

        $arr = ['result'=>false,'code'=>-999,'msg'=>'文件不存在'];
        return $arr;

    }

    /*
    * 通过项目id获取版本
    */
    public static function getversionbyproject($project_id,$sid){

        $url = self::$url.'/api/getversionbyproject';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['project_id']	=	$project_id;
        $param['sign']			= self::setSign($param);
        $res = self::post($url,$param);
        return json_decode($res,1);

    }

    /*
    * 获取文件路径
    */
    public static function getresbyversion($version_id,$sid){

        $url = self::$url.'/api/getresbyversion';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['version_id']	=	$version_id;
        $param['sign']			=   self::setSign($param);
        $res = self::post($url,$param);
        return json_decode($res,1);

    }

    /*
    * 获取上传文件信息
    */
    public static function getfilesbyres($res_id,$sid){

        $url = self::$url.'/api/getfilesbyres';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['res_id']		=	$res_id;
        $param['sign']			=   self::setSign($param);
        $res = self::post($url,$param);
        return json_decode($res,1);

    }

    /*
     * 获取文件列表
     */
    public static function getfilelist($version_id,$sid){

        $filepath = self::getresbyversion($version_id,$sid);
        $filepath_id = $filepath['data']['list'][0]['id']??$filepath['data']['list']['id'];
        $file_res = self::getfilesbyres($filepath_id,$sid);
        $file_list = $file_res['data']['list']??[];
        return $file_list;
    }

    /*
    * 获取文件差异
    */
    public static function getdiffcontent($res_id,$filename,$sid){

        $url = self::$url.'/api/getdiffcontent';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['res_id']		=	$res_id;
        $param['filename']		=	$filename;
        $param['sign']			=   self::setSign($param);
        $res = self::post($url,$param);
        return json_decode($res,1);

    }

    /*
     * 删除文件
     */
    public static function  delres($file_id,$sid){

        $url = self::$url.'/api/delres';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['id']		    =	$file_id;
        $param['sign']			=   self::setSign($param);
        $res = self::post($url,$param);
        return json_decode($res,1);

    }

    /*
     * 获取一个版本
     */
    public static function getoneversion($version_id,$sid){

        $url = self::$url.'/api/getoneversion';
        $param['id']			=	$version_id;
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['sign']			= self::setSign($param);
        $res = self::post($url,$param);
        return json_decode($res,1);
    }

    /*
    * 审核-不通过
    */
    public static function versionauditnonpass($version_id,$check_reason = "审核不通过",$sid){
        $url = self::$url.'/api/versionauditnonpass';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['id']			=	$version_id;
        $param['check_reason']	=	$check_reason;

        $file_list = self::getfilelist($version_id,$sid);

        foreach ($file_list as $val){
            $check_data[$val]   =   ["0"];
        }
        $check_data['reason']	=	$check_reason;
        $param['check_data']	=	json_encode($check_data);
        $param['sign']			= 	self::setSign($param);
        $res = self::post($url,$param);

        return json_decode($res,1);
    }

    /*
    * 审核-通过
    */
    public static function versionauditpass($version_id,$check_reason = "审核通过",$sid){
        $url = self::$url.'/api/versionauditpass';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['id']			=	$version_id;
        $param['check_reason']	=	$check_reason;

        $file_list = self::getfilelist($version_id,$sid);

        foreach ($file_list as $val){
            $check_data[$val]   =   ["1"];
        }
        $param['check_data']	=	json_encode($check_data);
        $param['sign']			= 	self::setSign($param);
        $res = self::post($url,$param);

        return json_decode($res,1);
    }

    /*
    * 测试-不通过
    */
    public static function versiontestnonpass($version_id,$check_reason = "测试不通过",$sid){
        $url = self::$url.'/api/versiontestnonpass';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['id']			=	$version_id;
        $param['ntest_reason']	=	$check_reason;

        $file_list = self::getfilelist($version_id,$sid);

        foreach ($file_list as $val){
            $check_data[$val]   =   [["0"]];
        }

        $check_data['reason']	=	$check_reason;
        $param['reason']	    =	json_encode($check_data,JSON_UNESCAPED_UNICODE);
        $param['sign']			= 	self::setSign($param);
        $res = self::post($url,$param);

        return json_decode($res,1);
    }

    /*
    * 测试-通过
    */
    public static function versiontestpass($version_id,$check_reason = "审核通过",$sid){

        $url = self::$url.'/api/versiontestpass';
        $param['sid']			=	$sid;
        $param['version']		=	1;
        $param['id']			=	$version_id;

        $file_list = self::getfilelist($version_id,$sid);

        foreach ($file_list as $val){
            $check_data[$val]   =   [["1"]];
        }
        $param['reason']	=	json_encode($check_data);
        $param['sign']		= 	self::setSign($param);
        $res = self::post($url,$param);

        return json_decode($res,1);
    }

    /*
     * 获取项目列表
     */
    public static function getversionlist($project_id,$version_id,$sid){

        $url = self::$url.'/api/getversionlist';
        $param['sid']			=	$sid;
        $param['starttime']		=	date("Y-m-d",strtotime("-3 days"));
        $param['endtime']		=	date("Y-m-d");
        $param['project_id']	=	$project_id;
        $param['name']	        =	'';
        $param['role']	        =	'is_coder';
        $param['status']	    =	0;
        $param['currentPage']	=	1;
        $param['pagesize']	    =	10;
        $param['version']		=	1;
        $param['sign']		= 	self::setSign($param);
        $res = self::post($url,$param);

        return json_decode($res,1);
    }

    /*
     * 删除版本
     */
    public static function delversion($version_id,$sid){

        $url = self::$url.'/api/delversion';
        $param['sid']			=	$sid;
        $param['id']	        =	$version_id;
        $param['version']		=	1;
        $param['sign']		= 	self::setSign($param);
        $res = self::post($url,$param);

        return json_decode($res,1);
    }

    /*
    * 转码
    */
    public static function codeiconv($code = 'UTF-8',$code_m = 'GBK', $str = ''){
        return iconv($code,$code_m,$str);
    }

}
