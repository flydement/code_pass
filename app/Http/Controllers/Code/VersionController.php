<?php

namespace App\Http\Controllers\Code;

use App\Code;
use App\Http\Controllers\Code\CodeController;
use http\Env\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Storage;

class VersionController extends CodeController
{
    /*
     * 添加版本
     */
    public function add(){

        $action = \Illuminate\Support\Facades\Route::currentRouteAction();
        $project_id     = Input::post("project_id","-1");
        $version_name   = Input::post("version_name","");
        $redis_desc     = Input::post("redis_desc","");
        $mysql_desc     = Input::post("mysql_desc","");
        $other_desc     = Input::post("other_desc","");
        $file           = Input::file("file");
        $auto_audit     = Input::post("auto_audit",0);
        $reason         = Input::post("reason","修改版本");


        if(!in_array($project_id,$this->allowlsit)){
            return self::format('project_id不允许',401);
        }

        if(empty($version_name)){
            return self::format('版本名称不能为空',401);
        }

        if(empty($other_desc)){
            $other_desc = $version_name;
        }

        if(!session("login")){
            return self::format('登录状态有误');
        }

        $param = [
            'id'            =>'',
            'project_id'    =>$project_id,
            'name'          =>$version_name,
            'redis_desc'    =>$redis_desc,
            'mysql_desc'    =>$mysql_desc,
            'other_desc'    =>$other_desc,
            'sid'           =>session("login"),
        ];


        $res = Code::addVersion($param);
        Code::addLog($param,$res,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"添加版本");


        if($res && $res['result'] === true){

            if($file && $file->isValid()){
                //有文件
                $version_id = $res['data']['id'];
                $ext = $file->getClientOriginalExtension();
                $ext2 = $file->getClientMimeType();
                if($ext != 'zip'){
                    return self::format("文件类型有误，请上传",401,['version_id'=>$version_id]);
                }

                if($ext2 != "application/zip"){
                    return self::format("文件类型有误，请上传",401,['version_id'=>$version_id]);
                }

                $path       = $file->getRealPath();
                $filename   = $project_id."/".date("Ymd")."/".$this->username."/".date('Y_m_d-h_i_s')."_{$version_id}.".$ext;
                $res = Storage::disk("public")->put($filename,file_get_contents($path));
                if(!$res){
                    return self::format("提交版本成功,文件上传失败",0,['version_id'=>$version_id]);
                }

                $return = Code::uploadfile($version_id,storage_path()."/app/public/".$filename,session("login"));
                Code::addLog(['version_id'=>$version_id,'storage_path'=>storage_path()."/app/public/".$filename],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,2,"自动上传文件");

                if($return && $return['result'] === true){

                    if($auto_audit == 1){

                        $file_list = Code::getfilelist($version_id,session("login"));

                        if(!$file_list){
                            return self::format('上传文件有误');
                        }

                        foreach($file_list as $val){
                            $reason_list[$val]	=	[[$reason,$reason,"0","0"]];
                        }

                        $return = Code::versionaudit($version_id,$reason_list,session("login"));
                        Code::addLog($param,$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,3,"自动提交审核");

                        if($return && $return['result'] === true){

                            if(Input::post("username") == self::$admin_username){
                                $return = Code::versionauditpass($version_id,"审核通过",session("login"));
                                Code::addLog(['version_id'=>$version_id],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,4,"自动审核通过");
                                if($return && $return['result'] === true){
                                    return self::format("自动审核成功",0,['version_id'=>$version_id]);
                                }
                            }
                            return self::format("自动提交审核成功",0,['version_id'=>$version_id]);

                        }
                        return self::format("上传成功,自动提交审核失败",0,['version_id'=>$version_id]);
                    }

                    return self::format("上传成功",0,['version_id'=>$version_id]);

                }

                 return self::format("提交版本成功,文件上传失败",0,['version_id'=>$version_id]);



            }
            return self::format($res['msg'],0,['version_id'=>$res['data']['id']]);

        }elseif($res && $res['result'] === false){

            return self::format($res['msg'],405);

        }else{

            return self::format("提交失败",405);

        }
    }

    /*
     * 修改脚本
     */
    public function edit(){

        $version_id     = Input::post("version_id","");
        $version_name   = Input::post("version_name","");
        $redis_desc     = Input::post("redis_desc","");
        $mysql_desc     = Input::post("mysql_desc","");
        $other_desc     = Input::post("other_desc","");

        if(empty($version_name)){
            return self::format('版本名称不能为空',401);
        }

        if(empty($version_id)){
            return self::format('version_id不能为空',401);
        }

        if(!session("login")){
            return self::format('登录状态有误');
        }


        $version = Code::getoneversion($version_id,session("login"));
        if(!$version or !$version['result']){
            return self::format('无法获取该版本',404);
        }

        $param = [
            'id'            =>$version_id,
            'project_id'    =>$version['data']['project_id'],
            'name'          =>$version_name,
            'redis_desc'    =>$redis_desc,
            'mysql_desc'    =>$mysql_desc,
            'other_desc'    =>$other_desc,
            'sid'           =>session("login"),
        ];

        $res = Code::addVersion($param);
        Code::addLog($param,$res,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"修改版本信息");


        if($res && $res['result'] === true){

            return self::format($res['msg'],0,['version_id'=>$version_id]);

        }elseif($res && $res['result'] === false){

            return self::format($res['msg'],405,['version_id'=>$version_id]);

        }else{

            return self::format("提交失败",405,['version_id'=>$version_id]);

        }
    }

    /*
     * 上传文件
     */
    public function upload(){

        $version_id     = Input::post("version_id","");
        $file           = Input::file("file");

        if(empty($version_id)){
            return self::format('version_id不能为空',401);
        }

        if(!session("login")){
            return self::format('登录状态有误');
        }

        $version = Code::getoneversion($version_id,session("login"));
        if(!$version or !$version['result']){
            return self::format('无法获取该版本',404);
        }

        if($file && $file->isValid()){
            //有文件
            $ext = $file->getClientOriginalExtension();
            $ext2 = $file->getClientMimeType();
            if($ext != 'zip'){
                return self::format("文件类型有误，请上传",401,['version_id'=>$version_id]);
            }

            if($ext2 != "application/zip"){
                return self::format("文件类型有误，请上传",401,['version_id'=>$version_id]);
            }

            $path       = $file->getRealPath();
            $filename   = $version['data']['project_id']."/".date("Ymd")."/".$this->username."/".date('Y_m_d-h_i_s')."_{$version_id}.".$ext;
            $res = Storage::disk("public")->put($filename,file_get_contents($path));
            if(!$res){
                return self::format("文件上传失败",401,['version_id'=>$version_id]);
            }

            $return = Code::uploadfile($version_id,storage_path()."/app/public/".$filename,session("login"));
            Code::addLog(['version_id'=>$version_id,'storage_path'=>storage_path()."/app/public/".$filename],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"上传文件");


            if($return && $return['result'] === true){

                return self::format("上传成功",0,['version_id'=>$version_id]);

            }elseif($return && $return['result'] === false){

                return self::format($return['msg'],405,['version_id'=>$version_id]);

            }

        }else{
            return self::format("文件有误，请上传",401);
        }

        return self::format("提交文件失败",405,['version_id'=>$version_id]);

    }

    /*
     * 删除文件
     */
    public function remove(){

        $version_id     = Input::post("version_id","");

        if(empty($version_id)){
            return self::format('version_id不能为空',401);
        }

        if(!session("login")){
            return self::format('登录状态有误');
        }

        $file = Code::getresbyversion($version_id,session("login"));

        if(!$file or !isset($file['data']['list'][0]['id'])){
            return self::format('文件不存在',404);
        }

        $return = Code::delres($file['data']['list'][0]['id'],session("login"));
        Code::addLog(['version_id'=>$file['data']['list'][0]['id']],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"删除文件");

        if($return && $return['result'] === true){

            return self::format($return['msg'],0,['version_id'=>$version_id]);

        }elseif($return && $return['result'] === false){

            return self::format($return['msg'],405,['version_id'=>$version_id]);

        }

        return self::format("删除失败",405,['version_id'=>$version_id]);
    }

    /*
     * 提交审核
     */
    public function audit(){
        $version_id     = Input::post("version_id","");
        $reason         = Input::post("reason ","修改版本");

        if(empty($version_id)){
            return self::format('version_id不能为空',401);
        }

        if(!session("login")){
            return self::format('登录状态有误');
        }

        $file_list = Code::getfilelist($version_id,session("login"));

        if(!$file_list){
            return self::format('上传文件有误');
        }

        foreach($file_list as $val){
            $reason_list[$val]	=	[[$reason,$reason,"0","0"]];
        }

        $return = Code::versionaudit($version_id,$reason_list,session("login"));
        Code::addLog(['version_id'=>$version_id],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"提交审核");

        if($return && $return['result'] === true){

            return self::format("提交审核成功",0,['version_id'=>$version_id]);

        }elseif($return && $return['result'] === false){

            return self::format($return['msg'],405,['version_id'=>$version_id]);

        }

        return self::format("提交审核失败",405,['version_id'=>$version_id]);

    }

    /*
     * 审核通过
     */
    public function pass(){

        $version_id     = Input::post("version_id","");
        $project_id     = Input::post("project_id","");

        if(!session("login")){
            return self::format('登录状态有误');
        }

        if(empty($version_id)){
            return self::format('version_id不能为空',401);
        }

        if(empty($project_id)){
            return self::format('project_id不能为空',401);
        }

        $admin = self::admin_login();
        if(!$admin){
            return self::format("审核失败",406,['version_id'=>$version_id]);
        }
        $list = Code::getversionlist($project_id,$version_id,session("login"));

        if(Input::post("username") != self::$admin_username){

            if(empty($list) or !isset($list['data']['list']) or  empty($list['data']['list']) ){
                return self::format('未找到对应版本',401);
            }

            $is_self = false;
            foreach ($list['data']['list'] as $datum) {
                if($datum['id'] == $version_id){
                    if($datum['create_name'] == Input::post('username')){
                        $is_self = true;
                    }
                }
            }

            if(!$is_self){
                return self::format('版本非自己提交',401);
            }
        }

        $return = Code::versionauditpass($version_id,"审核通过",session("admin_login"));
        Code::addLog(['version_id'=>$version_id],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"审核通过");

        if($return && $return['result'] === true){

            return self::format("审核成功",0,['version_id'=>$version_id]);

        }elseif($return && $return['result'] === false){

            return self::format($return['msg'],405,['version_id'=>$version_id]);

        }

        return self::format("审核失败",405,['version_id'=>$version_id]);
    }

    /*
     * 审核不通过
     */
    public function nopass(){

        $version_id     = Input::post("version_id","");
        $project_id     = Input::post("project_id","");

        if(!session("login")){
            return self::format('登录状态有误');
        }

        if(empty($version_id)){
            return self::format('version_id不能为空',401);
        }

        if(empty($project_id)){
            return self::format('project_id不能为空',401);
        }

        $list = Code::getversionlist($project_id,$version_id,session("login"));

        if(Input::post("username") != self::$admin_username){
            if(empty($list) or !isset($list['data']['list']) or  empty($list['data']['list']) ){
                return self::format('未找到对应版本',401);
            }

            $is_self = false;
            foreach ($list['data']['list'] as $datum) {
                if($datum['id'] == $version_id){
                    if($datum['create_name'] == Input::post('username')){
                        $is_self = true;
                    }
                }
            }

            if(!$is_self){
                return self::format('版本非自己提交',401);
            }
        }
        $admin = self::admin_login();

        if(!$admin){
            return self::format("审核不通过失败",406,['version_id'=>$version_id]);
        }

        $return = Code::versionauditnonpass($version_id,"审核不通过",session("admin_login"));
        Code::addLog(['version_id'=>$version_id],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"审核不通过");

        if($return && $return['result'] === true){

            return self::format("审核不通过成功",0,['version_id'=>$version_id]);

        }elseif($return && $return['result'] === false){

            return self::format($return['msg'],405,['version_id'=>$version_id]);

        }

        return self::format("审核不通过失败",405,['version_id'=>$version_id]);
    }

    /*
     * 测试通过
     */
    public function test(){

        $version_id     = Input::post("version_id","");
        $project_id     = Input::post("project_id","");

        if(!session("login")){
            return self::format('登录状态有误');
        }

        if(empty($version_id)){
            return self::format('version_id不能为空',401);
        }

        if(empty($project_id)){
            return self::format('project_id不能为空',401);
        }

        $list = Code::getversionlist($project_id,$version_id,session("login"));

        if(Input::post("username") != self::$admin_username){
            if(empty($list) or !isset($list['data']['list']) or  empty($list['data']['list']) ){
                return self::format('未找到对应版本',401);
            }

            $is_self = false;
            foreach ($list['data']['list'] as $datum) {
                if($datum['id'] == $version_id){
                    if($datum['create_name'] == Input::post('username')){
                        $is_self = true;
                    }
                }
            }

            if(!$is_self){
                return self::format('版本非自己提交',401);
            }
        }
        $admin = self::admin_login();

        if(!$admin){
            return self::format("测试通过失败",406,['version_id'=>$version_id]);
        }

        $return = Code::versiontestpass($version_id,"测试通过",session("admin_login"));
        Code::addLog(['version_id'=>$version_id],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"测试通过");

        if($return && $return['result'] === true){

            return self::format("测试通过成功",0,['version_id'=>$version_id]);

        }elseif($return && $return['result'] === false){

            return self::format($return['msg'],405,['version_id'=>$version_id]);

        }

        return self::format("测试通过失败",405,['version_id'=>$version_id]);
    }

    /*
     * 测试不通过
     */
    public function notest(){

        $version_id     = Input::post("version_id","");
        $project_id     = Input::post("project_id","");

        if(!session("login")){
            return self::format('登录状态有误');
        }


        if(empty($version_id) ){
            return self::format('version_id不能为空',401);
        }

        if(empty($project_id)){
            return self::format('project_id不能为空',401);
        }

        $list = Code::getversionlist($project_id,$version_id,session("login"));

        if(Input::post("username") != self::$admin_username){
            if(empty($list) or !isset($list['data']['list']) or  empty($list['data']['list']) ){
                return self::format('未找到对应版本',401);
            }

            $is_self = false;
            foreach ($list['data']['list'] as $datum) {
                if($datum['id'] == $version_id){
                    if($datum['create_name'] == Input::post('username')){
                        $is_self = true;
                    }
                }
            }

            if(!$is_self){
                return self::format('版本非自己提交',401);
            }
        }
        $admin = self::admin_login();

        if(!$admin){
            return self::format("测试不通过失败",406,['version_id'=>$version_id]);
        }

        $return = Code::versiontestnonpass($version_id,"测试不通过",session("admin_login"));
        Code::addLog(['version_id'=>$version_id],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"测试不通过");

        if($return && $return['result'] === true){

            return self::format("测试不通过成功",0,['version_id'=>$version_id]);

        }elseif($return && $return['result'] === false){

            return self::format($return['msg'],405,['version_id'=>$version_id]);

        }

        return self::format("测试不通过失败",405,['version_id'=>$version_id]);
    }

    /*
     * 删除版本
     */
    public function delversion(){

        $version_id     = Input::post("version_id","");
        $project_id     = Input::post("project_id","");

        if(!session("login")){
            return self::format('登录状态有误');
        }

        if(empty($version_id) ){
            return self::format('version_id不能为空',401);
        }

        if(empty($project_id)){
            return self::format('project_id不能为空',401);
        }
        $list = Code::getversionlist($project_id,$version_id,session("login"));

        if(Input::post("username") != self::$admin_username){
            if(empty($list) or !isset($list['data']['list']) or  empty($list['data']['list']) ){
                return self::format('未找到对应版本',401);
            }
            $is_self = false;
            foreach ($list['data']['list'] as $datum) {
                if($datum['id'] == $version_id){
                    if($datum['create_name'] == Input::post('username')){
                        $is_self = true;
                    }
                }
            }

            if(!$is_self){
                return self::format('版本非自己提交',401);
            }
        }

        $admin = self::admin_login();
        if(!$admin){
            return self::format("删除失败",406,['version_id'=>$version_id]);
        }
        $return = Code::delversion($version_id,session("admin_login"));
        Code::addLog(['version_id'=>$version_id],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"删除版本");

        if($return && $return['result'] === true){

            return self::format($return['msg'],0,['version_id'=>$version_id]);

        }elseif($return && $return['result'] === false){

            return self::format($return['msg'],405,['version_id'=>$version_id]);

        }

        return self::format("删除版本失败",405,['version_id'=>$version_id]);
    }


    /*
     * 上线
     */
    public function line(){

        $version_id     = Input::post("version_id","");
        $project_id     = Input::post("project_id","");

        if(!session("login")){
            return self::format('登录状态有误');
        }

        if(empty($version_id)){
            return self::format('version_id不能为空',401);
        }

        if(empty($project_id)){
            return self::format('project_id不能为空',401);
        }
        $list = Code::getversionlist($project_id,$version_id,session("login"));

        if(Input::post("username") != self::$admin_username){
            if(empty($list) or !isset($list['data']['list']) or  empty($list['data']['list']) ){
                return self::format('未找到对应版本',401);
            }
            $is_self = false;
            foreach ($list['data']['list'] as $datum) {
                if($datum['id'] == $version_id){
                    if($datum['create_name'] == Input::post('username')){
                        $is_self = true;
                    }
                }
            }

            if(!$is_self){
                return self::format('版本非自己提交',401);
            }
        }

        $admin = self::admin_login();

        if(!$admin){
            return self::format("上线失败",406,['version_id'=>$version_id]);
        }

        $return = Code::versiononline($version_id,session("admin_login"));
        Code::addLog(['version_id'=>$version_id],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"版本上线");

        if($return && $return['result'] === true){

            return self::format("上线成功",0,['version_id'=>$version_id]);

        }elseif($return && $return['result'] === false){

            return self::format($return['msg'],405,['version_id'=>$version_id]);

        }

        return self::format("上线失败",405,['version_id'=>$version_id]);
    }


    /*
     * 回滚
     */
    public function rollback(){

        $version_id     = Input::post("version_id","");
        $project_id     = Input::post("project_id","");

        if(!session("login")){
            return self::format('登录状态有误');
        }

        if(empty($version_id)){
            return self::format('version_id不能为空',401);
        }

        if(empty($project_id)){
            return self::format('project_id不能为空',401);
        }

        $list = Code::getversionlist($project_id,$version_id,session("login"));

        if(Input::post("username") != self::$admin_username){
            if(empty($list) or !isset($list['data']['list']) or  empty($list['data']['list']) ){
                return self::format('未找到对应版本',401);
            }
            $is_self = false;
            foreach ($list['data']['list'] as $datum) {
                if($datum['id'] == $version_id){
                    if($datum['create_name'] == Input::post('username')){
                        $is_self = true;
                    }
                }
            }

            if(!$is_self){
                return self::format('版本非自己提交',401);
            }
        }

        $admin = self::admin_login();
        if(!$admin){
            return self::format("回滚失败",406,['version_id'=>$version_id]);
        }

        $return = Code::versionrollback($version_id,session("admin_login"));
        Code::addLog(['version_id'=>$version_id],$return,\Illuminate\Support\Facades\Route::currentRouteAction(),$this->username,1,"版本回滚");

        if($return && $return['result'] === true){

            return self::format("回滚成功",0,['version_id'=>$version_id]);

        }elseif($return && $return['result'] === false){

            return self::format($return['msg'],405,['version_id'=>$version_id]);

        }

        return self::format("回滚失败",405,['version_id'=>$version_id]);
    }

    /*
     * 获取差异
     */
    public function getdiff(){
        
    }

    /*
     * 获取最近的版本
     */
    public function getCurrentVersion(){

        $project_id     = Input::post("project_id","");

        if(!session("login")){
            return self::format('登录状态有误');
        }

        /*if(empty($project_id)){
            return self::format('project_id不能为空',401);
        }*/

        $list = Code::getversionlist($project_id,0,session("login"));
        $data = [];
        if($list && isset($list['data']['list'][0])){
            $tem = $list['data']['list'][0];
            $data['version_id']     =   $tem['id']??"";
            $data['version_name']   =   $tem['name']??"";
            $data['version_status'] =   strip_tags($tem['status']);
            $data['project_name']   =   $tem['project_name']??"";
            $data['create_time']    =   date("Y-m-d H:i:s",$tem['create_time']);
            $data['check_time']     =   !empty($tem['check_time'])?date("Y-m-d H:i:s",$tem['check_time']):'';
            $data['test_time']      =   !empty($tem['test_time'])?date("Y-m-d H:i:s",$tem['test_time']):'';
            $data['pub_time']       =   !empty($tem['pub_time'])?date("Y-m-d H:i:s",$tem['pub_time']):'';
            $data['create_name']    =   $tem['create_name']??"";
            $data['check_name']     =   $tem['check_name']??"";
            $data['test_name']      =   $tem['test_name']??"";
            $data['pub_name']       =   $tem['pub_name']??"";
        }

        return self::format('获取成功',0,$data);



    }
}
