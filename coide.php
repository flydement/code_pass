<?php

/*
* 提交代码审核类
*/
class fileHandle{
	
	private $username;//账户
	private $password;//密码
	private $sid;//sessionid
	public $isAtAll = false;//是否@全部人推送
	public $atMobiles = [];//推送手机号
	public $version_id;
	public $url = 'http://newcode.3cuc.com.cn';
	
	/*
	* 构造
	*/
	public function __construct($username = '',$password = '',bool $isAtAll = false,$atMobiles = []){
		
		$this->username = $username;
		$this->password = $password;
		$this->isAtAll = $isAtAll;
		$this->atMobiles = $atMobiles;
		$this->sid = $this->login();
	}
	
	/*
	* 执行-上传流程
	*/
	public function run($name = 'app组版本' ,$project_id = 30 , $file_path = '',$reason = '修改新版本'){
		
		$k = 0;
		do{
			$first =  date("Ymd H:i:s")." 当前第".++$k."次 ";
			
			$param['id'] 			= '';
			$param['name'] 			= $name;
			$param['project_id'] 	= $project_id;//8支付正式
			$param['redis_desc'] 	= '';//redis描述
			$param['mysql_desc'] 	= '';//mysql描述
			$param['other_desc'] 	= '';//其他描述
			$res = $this->addVersion($param);
			
			if(!$res){
				die('解析失败');
				
			}elseif($res['result'] == true){
				
				$msg	=	sprintf("用户:%s , 版本: %s , 抢到了 , versionId:%d ",
									$this->username,$param['name'],$res['data']['id']);
				//dingding($msg);
				$this->version_id = $res['data']['id'];//上传id
				
				echo ($msg."\r\n");
				echo ("....................开始上传文件.............\r\n");
				
				$result = $this->uploadfile($this->version_id,$file_path);
				if($result['result']){
					
					$msg	=	sprintf("用户:%s , 版本: %s , versionId: %d , 上传文件成功",
										$this->username,$param['name'],$this->version_id);
					echo ($msg."\r\n");
					
					/*
					* 获取文件列表
					*/
                    $file_list  =   $this->getfilelist($this->version_id);
					foreach($file_list as $val){
						$reason_list[$val]	=	[[$reason,$reason,"0","0"]];
					}
					
					if(empty($file_list)){
						$msg	=	sprintf("用户:%s , 版本: %s , versionId: %d,获取文件列表失败",
											$this->username,$param['name'],$this->version_id);
						echo ($msg."\r\n");
						exit;
					}
					
					/*
					* 提交审核
					*/
					$audit_res = $this->versionaudit($this->version_id,$reason_list);
					if(!$audit_res or $audit_res['result'] == false){
						$msg	=	sprintf("用户:%s , 版本: %s , versionId: %d,提交审核失败",
											$this->username,$param['name'],$this->version_id);
						echo ($msg."\r\n");
						exit;
					}
					
					$msg	=	sprintf("用户:%s , 版本: %s , versionId: %d , 提交审核成功~",
										$this->username,$param['name'],$this->version_id);
					echo ($msg."\r\n");
					exit;
					
				}else{
					$msg	=	sprintf("用户:%s , 版本: %s , 上传文件失败 , 原因:%s",$this->username,$param['name'],$result['msg']?:'未知错误');
					echo ($msg."\r\n");
					exit;
				}
				
				sleep(10);
				die();
			}elseif($res['code'] == 40005){
				//重新登录
				$this->sid = $this->login();
			}elseif($res['code'] == 50003){
				//有人占版本
				echo $first,'返回信息:',$res['msg'],"\r\n";
			}else{
				echo("发生错误 错误代码:".$res['code'].' 错误信息:'.$res['msg']);
				sleep(10);
				die();
			}
			sleep(0.00001);
		}while($res['result'] == false);

	}
	
	/*
	* 创建版本
	*/
	public function addVersion($param = array()){
		
		$id 		= $param['id']??'';
		$name 		= $param['name']?:'APP组版本';//版本名称
		$project_id	= intval($param['project_id'])??8;// 8支付正式
		$redis_desc = $param['redis_desc']??"";//redis描述
		$mysql_desc = $param['mysql_desc']??'';//mysql描述
		$other_desc	= $param['other_desc']??'';//其他描述
		$info 		= json_encode([$redis_desc,$mysql_desc,$other_desc]);

		if($this->sid){
			$data = [
					'id'=>$id,
					'name'=>$name,
					'info'=>$info,
					'project_id'=>$project_id,
					'version'=>1,
					'sid'=>$this->sid,
				];
			$data['sign'] = $this->setSign($data);

			$url = $this->url.'/api/updateversion';
			$k = 0;
			$res = $this->post($url,$data);
			return json_decode($res,1);

		}else{
			die("账号密码有误");
		}
		
	}
	
	/*
	* 审核版本
	*version_id			版本id
	*check_reason		审核原因
	*check_result		1 通过 2 不过
	*/
	public function auditVersion($version_id,$check_reason = "审核不通过",$check_result = 1){
		
		if($check_result == 1){
			//通过
			$res = $this->versionauditpass($version_id);
			$rsg = '审核通过';
		}else{
			//不通过
			$res = $this->versionauditnonpass($version_id,$check_reason);
			$rsg = '审核不通过';
		}
		
		if($res && $res['result'] == true){
			$msg	=	sprintf("版本: %s , ".$rsg."处理成功 ",$version_id);
			echo ($msg."\r\n");
			exit;
		}
		
		$msg	=	sprintf("版本: %s , ".$rsg."处理失败 ",$version_id);
		echo ($msg."\r\n");
		exit;
		
	}	
	
	/*
	* 测试版本
	*version_id			版本id
	*check_reason		审核原因
	*check_result		1 通过 2 不过
	*/
	public function testVersion($version_id,$check_reason = "测试不通过",$check_result = 1){
		
		if($check_result == 1){
			//通过
			$res = $this->versiontestpass($version_id);
			$rsg = '测试通过';
		}else{
			//不通过
			$res = $this->versiontestnonpass($version_id,$check_reason);
			$rsg = '测试不通过';
		}
		
		if($res && $res['result'] == true){
			$msg	=	sprintf("版本: %s , ".$rsg."处理成功 ",$version_id);
			echo ($msg."\r\n");
			exit;
		}
		
		$msg	=	sprintf("版本: %s , ".$rsg."处理失败 ",$version_id);
		echo ($msg."\r\n");
		exit;
		
	}
	
	/*
	* 上线
	*/
	public function lineVersion($version_id){
		$res = $this->versiononline($version_id);
		if($res && $res['result'] == true){
			$msg	=	sprintf("版本: %s , 上线成功 ",$version_id);
			echo ($msg."\r\n");
			exit;
		}
		
		$msg	=	sprintf("版本: %s , 上线失败 ",$version_id);
		echo ($msg."\r\n");
		exit;
	}
	
	/*
	* 回滚
	*/
	public function rollVersion($version_id){
		$res = $this->versionrollback($version_id);
		if($res && $res['result'] == true){
			$msg	=	sprintf("版本: %s , 回滚成功 ",$version_id);
			echo ($msg."\r\n");
			exit;
		}
		
		$msg	=	sprintf("版本: %s , 回滚失败 ",$version_id);
		echo ($msg."\r\n");
		exit;
	}
	
	/*
	* 上线
	*/
	private function versiononline($version_id){
		$url = $this->url.'/api/versiononline';
		$param['sid']			=	$this->sid;
		$param['version']		=	1;
		$param['id']			=	$version_id;
		$param['online_type']	=	1;
		$param['sign']			= 	$this->setSign($param);
		$res = $this->post($url,$param);
		return json_decode($res,1);

	}

	/*
	* 回滚
	*/
	private function versionrollback($version_id){
		$url = $this->url.'/api/versionrollback';
		$param['sid']			=	$this->sid;
		$param['version']		=	1;
		$param['id']			=	$version_id;
		$param['server_id']		=	'';
		$param['sign']			= 	$this->setSign($param);
		$res = $this->post($url,$param);
		return json_decode($res,1);

	}

	/*
	* 登录
	*/
	private function login(){
		

		$loginurl = $this->url.'/api/login';
		$login['username']	= $this->username;//输入账号
		$login['password']	= md5($this->password);//输入密码
		$login['version']	= 1;
		$login['timestamp']	= time();
		$login['sign'] 	  	= $this->setSign($login);


		$res = $this->post($loginurl,$login);
		$data = json_decode($res,1);

		if($data['result'] == true && !empty($data['data']['sid']) ){
			$this->sid = $data['data']['sid'];
			return $this->sid;
		}
		
		if(!$this->sid){
			echo("账号密码有误");
			sleep(10);
		}

	}
	
	/*
	* 传参
	*/
	private function post($url,$post_data= [],$file = ''){
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
	* 获取加密
	*/
	private function setSign($data){
		$k = '50C7DE419914169C81FFEE232EFBB791';
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
	* 订单推送
	*/
	public function dingding( $message = '抢到版本了') {

		$remote_server = 'https://oapi.dingtalk.com/robot/send?access_token=dd844f2eafcd2dcf74dc01ec414b0d48c5da901ec5bd4df53400110a907dffe9';
		
		$data = array ('msgtype' => 'text','text' => array ('content' => $message));
		$data['at']	=	array('isAtAll'=>$this->isAtAll,'atMobiles'=>$this->atMobiles);//isAtAll 全部人推送 atMobiles 自己手机号
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
	private function versionaudit($version_id,$reason){
		
		$url = $this->url.'/api/versionaudit';
		$param['sid']			=	$this->sid;
		$param['version']		=	1;
		$param['id']			=	$version_id;
		$param['reason']		=	json_encode($reason);
		$param['sign']			= 	$this->setSign($param);
		$res = $this->post($url,$param);
		return json_decode($res,1);
		
	}
	
	/*
	* 上传文件
	*/
	private function uploadfile($version_id,$filepath){
		
		$upload_param['version']		=	1;
		$upload_param['version_id']		=	$version_id;
		$upload_param['sid']			=	$this->sid;
		$upload_param['sign'] = $this->setSign($upload_param);
		$upload_url = $this->url."/api/upfile";
		
		if(file_exists($filepath)){
			if (class_exists('\CURLFile')) {
				$file = new \CURLFile($filepath,'application/octet-stream');
			} else {
				$file = "@".realpath ($filepath );
			}

			$upload_param['files[]'] = $file;
			$upload_url = $this->url."/api/upfile";
			$result = $this->post($upload_url,$upload_param);
			$result = json_decode($result,1);
			return $result;
		}
		
		$arr = ['result'=>false,'code'=>-999,'msg'=>'文件不存在'];
		return $arr;
		
	}

	/*
	* 通过项目id获取版本
	*/
	private function getversionbyproject($project_id){
		
		$url = $this->url.'/api/getversionbyproject';
		$param['sid']			=	$this->sid;
		$param['version']		=	1;
		$param['project_id']	=	$project_id;
		$param['sign']			= $this->setSign($param);
		$res = $this->post($url,$param);
		return json_decode($res,1);

	}

	/*
	* 获取文件路径
	*/
	private function getresbyversion($version_id){
		
		$url = $this->url.'/api/getresbyversion';
		$param['sid']			=	$this->sid;
		$param['version']		=	1;
		$param['version_id']	=	$version_id;
		$param['sign']			= $this->setSign($param);
		$res = $this->post($url,$param);
		return json_decode($res,1);

	}

	/*
	* 获取上传文件信息
	*/
	private function getfilesbyres($res_id){
		
		$url = $this->url.'/api/getfilesbyres';
		$param['sid']			=	$this->sid;
		$param['version']		=	1;
		$param['res_id']		=	$res_id;
		$param['sign']			= $this->setSign($param);
		$res = $this->post($url,$param);
		return json_decode($res,1);

	}

	/*
	 * 获取文件列表
	 */
	private function getfilelist($version_id){

        $filepath = $this->getresbyversion($version_id);
        $filepath_id = $filepath['data']['list'][0]['id']??$filepath['data']['list']['id'];
        $file_res = $this->getfilesbyres($filepath_id);
        $file_list = $file_res['data']['list'];
        return $file_list;
    }

	/*
	* 获取文件差异
	*/
	private function getdiffcontent($res_id,$filename){
		
		$url = $this->url.'/api/getdiffcontent';
		$param['sid']			=	$this->sid;
		$param['version']		=	1;
		$param['res_id']		=	$res_id;
		$param['filename']		=	$filename;
		$param['sign']			= $this->setSign($param);
		$res = $this->post($url,$param);
		return json_decode($res,1);
		
	}
	
	/*
	* 审核-不通过
	*/
	private function versionauditnonpass($version_id,$check_reason = "审核不通过"){
		$url = $this->url.'/api/versionauditnonpass';
		$param['sid']			=	$this->sid;
		$param['version']		=	1;
		$param['id']			=	$version_id;
		$param['check_reason']	=	$check_reason;

		$file_list = $this->getfilelist($version_id);

		foreach ($file_list as $val){
            $check_data[$val]   =   ["0"];
		}
		$check_data['reason']	=	$check_reason;
		$param['check_data']	=	json_encode($check_data);
		$param['sign']			= 	$this->setSign($param);
		$res = $this->post($url,$param);
		
		return json_decode($res,1);
	}
	
	/*
	* 审核-通过
	*/
	private function versionauditpass($version_id,$check_reason = "审核通过"){
		$url = $this->url.'/api/versionauditpass';
		$param['sid']			=	$this->sid;
		$param['version']		=	1;
		$param['id']			=	$version_id;
		$param['check_reason']	=	$check_reason;

		$file_list = $this->getfilelist($version_id);

		foreach ($file_list as $val){
            $check_data[$val]   =   ["1"];
		}
		$param['check_data']	=	json_encode($check_data);
		$param['sign']			= 	$this->setSign($param);
		$res = $this->post($url,$param);
		
		return json_decode($res,1);
	}
	
	/*
	* 测试-不通过
	*/
	private function versiontestnonpass($version_id,$check_reason = "测试不通过"){
		$url = $this->url.'/api/versiontestnonpass';
		$param['sid']			=	$this->sid;
		$param['version']		=	1;
		$param['id']			=	$version_id;
		$param['ntest_reason']	=	$check_reason;

		$file_list = $this->getfilelist($version_id);

		foreach ($file_list as $val){
            $check_data[$val]   =   [["0"]];
		}
		
		$check_data['reason']	=	$check_reason;
		$param['reason']	=	json_encode($check_data,JSON_UNESCAPED_UNICODE);
		$param['sign']			= 	$this->setSign($param);
		$res = $this->post($url,$param);
		
		return json_decode($res,1);
	}
	
	/*
	* 测试-通过
	*/
	private function versiontestpass($version_id,$check_reason = "审核通过"){
		
		$url = $this->url.'/api/versiontestpass';
		$param['sid']			=	$this->sid;
		$param['version']		=	1;
		$param['id']			=	$version_id;

		$file_list = $this->getfilelist($version_id);

		foreach ($file_list as $val){
            $check_data[$val]   =   [["1"]];
		}
		$param['reason']	=	json_encode($check_data);
		$param['sign']			= 	$this->setSign($param);
		$res = $this->post($url,$param);
		
		return json_decode($res,1);
	}
	
	/*
	* 转码
	*/
	public function codeiconv($code = 'UTF-8',$code_m = 'GBK', $str = ''){
		return $str;
		//return iconv($code,$code_m,$str);
	}

}

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

$fileHandle = new fileHandle('xufei','Xf888888');
$version =	10555;//10304
if(isset($version) && $version){
	$fileHandle->auditVersion($version,"",1);exit;
	//$fileHandle->auditVersion($version,"审核不通过",0);exit;
	//$fileHandle->testVersion($version);exit;
	//$fileHandle->testVersion($version,"测试不通过",0);exit;
	$fileHandle->lineVersion($version);exit;
	//$fileHandle->rollVersion($version);exit; 
}

$ver_name 	= '限制传参长度及添加脚本';//版本名称
$project_id = '50';//项目id
$file_path 	= 'C:\Users\SuperBrother\Downloads\1.zip';//路径
$up_reason	= '限制传参长度及添加脚本';//上传原因
$fileHandle->run($ver_name,$project_id ,$file_path,$up_reason);

exit;
?>