<?php
function rainyunrcs_MetaData()
{
	return ["DisplayName" => "RainyunRcs", "APIVersion" => "1.1", "HelpDoc" => "https://forum.rainyun.com/t/topic/5552"];
}
function rainyunrcs_ConfigOptions()
{
	return [
		["type" => "text", "name" => "type", "description" => "开通类型(必填)", "default" => "rcs", "key" => "type"], 
		["type" => "text", "name" => "os_id", "description" => "*系统镜像ID(必填)", "key" => "os_id"], 
		["type" => "text", "name" => "plan_id", "description" => "套餐ID(必填)", "key" => "plan_id"], 
		["type" => "text", "name" => "try", "description" => "是否试用(选填)", "default" => "false", "key" => "try"],
		["type" => "text", "name" => "with_coupon_id", "description" => "优惠券id(选填)", "default" => "0", "key" => "with_coupon_id"], 
		["type" => "text", "name" => "with_eip_num", "description" => "附加独立ip(选填)", "default" => "0", "key" => "with_eip_num"]
	];
}

// 图表信息
function rainyunrcs_Chart(){
	return [
		'cpu'=>[
			'title'=>'CPU 占用',
		],
		'ram'=>[
			'title'=>'剩余内存',
		],
		'disk'=>[
			'title'=>'磁盘读写',
			'select'=>[
				[
					'name'=>'系统盘',
					'value'=>'vda'
				],
				// [
				// 	'name'=>'数据盘',
				// 	'value'=>'vdb'
				// ],
				// 暂时注释了 因为好像雨云就给一个 - 
			]
		],
		'flow'=>[
			'title'=>'网络流量'
		],
	];
}

// 图表数据
function rainyunrcs_ChartData($params){
	$vserverid = rainyunrcs_GetServerid($params);
	if(empty($vserverid)){ return ['status'=>'error', 'msg'=>'数据获取失败']; }
	
	// 请求数据
	$start = $_GET["start"]/1000 ? $_GET["start"]/1000 : strtotime('-10 days');
	$end = $_GET["end"]/1000 ? $_GET["end"]/1000 : time();
	$url = $params["server_host"] . "/product/rcs/" . $vserverid . "/monitor/?start_date=".$start."&end_date=".$end;
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$res = rainyunrcs_Curl($url, null, 30, "GET", $header);

	// var_dump($res);
	if($res['code'] == 200){

		$result['status'] = 'success';
		$result['data'] = [];

		$data = $res["data"]["Values"];
		$timeIndex = array_search("time", $res["data"]["Columns"]);
		usort($data, function($a, $b) use ($timeIndex) { return $a[$timeIndex] - $b[$timeIndex]; });

		// 获取cpu数据 - 我cpu烧了
		if($params['chart']['type'] == 'cpu') {
			// 给前端传数据单位
			$result['data']['unit'] = '%';
			$result['data']['chart_type'] = 'line';
			$result['data']['list'] = [];
			$result['data']['label'] = ['CPU使用率(%)'];
			// 获取数据
			$cpuIndex = array_search("cpu", $res["data"]["Columns"]);
			foreach($data as $dataInfo) {
				// 取得这坨的时间戳
				$timestamp = $dataInfo[$timeIndex];
				// 添加数据
				$result['data']['list'][0][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round($dataInfo[$cpuIndex]*1000)/10
				]; 
			}
		}

		// 获取硬盘io数据
		if($params['chart']['type'] == 'disk') {
			// 给前端传数据单位
			$result['data']['unit'] = 'kb/s';
			$result['data']['chart_type'] = 'line';
			$result['data']['list'] = [];
			$result['data']['label'] = ['读取 (kb/s)','写入 (kb/s)'];
			// 获取数据
			$writeIndex = array_search("diskwrite", $res["data"]["Columns"]);
			$readIndex = array_search("diskread", $res["data"]["Columns"]);
			foreach($data as $dataInfo) {
				// 取得这坨的时间戳
				$timestamp = $dataInfo[$timeIndex];
				// 添加数据
				$result['data']['list'][0][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round($dataInfo[$readIndex]*100/1024)/100
				]; 
				$result['data']['list'][1][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round($dataInfo[$writeIndex]*100/1024)/100
				]; 
			}
		}

		// 获取流量数据
		if($params['chart']['type'] == 'flow') {
			// 给前端传数据单位
			$result['data']['unit'] = 'KB/s';
			$result['data']['chart_type'] = 'line';
			$result['data']['list'] = [];
			$result['data']['label'] = ['进(KB/s)','出(KB/s)'];
			// 获取数据
			$inIndex = array_search("netin", $res["data"]["Columns"]);
			$outIndex = array_search("netout", $res["data"]["Columns"]);
			foreach($data as $dataInfo) {
				// 取得这坨的时间戳
				$timestamp = $dataInfo[$timeIndex];
				// 添加数据
				$result['data']['list'][0][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round(($dataInfo[$inIndex]*100-1)/1024)/100
				]; 
				$result['data']['list'][1][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round(($dataInfo[$outIndex]*100-1)/1024)/100
				]; 
			}
		}

		// 获取内存数据
		if($params['chart']['type'] == 'ram') {
			// 给前端传数据单位
			$result['data']['unit'] = 'GB';
			$result['data']['chart_type'] = 'line';
			$result['data']['list'] = [];
			$result['data']['label'] = ['剩余内存 (GB)'];
			// 获取数据
			$memIndex = array_search("freemem", $res["data"]["Columns"]);
			foreach($data as $dataInfo) {
				// 取得这坨的时间戳
				$timestamp = $dataInfo[$timeIndex];
				// 添加数据
				if($dataInfo[$memIndex]) $result['data']['list'][0][] = [
					'time'=>date('Y-m-d H:i:s', $timestamp),
					'value'=>round($dataInfo[$memIndex]*100/1024/1024/1024)/100
				];
			}
		}

		return $result;
	}else{
		return ['status'=>'error', 'msg'=>'数据获取失败'];
	}
}


function rainyunrcs_TestLink($params)
{	
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/user/";
	$res = rainyunrcs_Curl($url, null, 10, "GET", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		$result["status"] = 200;
		$result["data"]["server_status"] = 1;
	} else {
		$result["status"] = 200;
		$result["data"]["server_status"] = 0;
		$result["data"]["msg"] = "未知错误";
	}
	return $result;
}
function rainyunrcs_ClientArea($params)
{
    $vserverid = rainyunrcs_GetServerid($params);
    $url = $params["server_host"] . "/product/rcs/" . $vserverid;
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$res = rainyunrcs_Curl($url, null, 30, "GET", $header);
	if ($res["data"]["Data"]["MainIPv4"] == "-") {
		$panel = ["NAT" => ["name" => "NAT转发"]];
	}
	return $panel;
}
function rainyunrcs_ClientAreaOutput($params, $key)
{
	$vserverid = rainyunrcs_GetServerid($params);
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	if ($key == "NAT") {
		$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
		$detail_url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/" . $vserverid;
		$res = rainyunrcs_Curl($detail_url, [], 10, "GET", $header);
		return ["template" => "templates/NAT.html", "vars" => ["list" => $res["data"]["NatList"], "ip" => $res["data"]["Data"]["NatPublicIP"]]];
	}
}
function rainyunrcs_AllowFunction()
{
	return ["client" => ["CreateSnap", "DeleteSnap", "RestoreSnap", "CreateBackup", "DeleteBackup", "RestoreBackup", "CreateSecurityGroup", "DeleteSecurityGroup", "ApplySecurityGroup", "ShowSecurityGroupAcl", "CreateSecurityGroupAcl", "DeleteSecurityGroupAcl", "MountCdRom", "UnmountCdRom", "addNatAcl", "delNatAcl", "addNatWeb", "delNatWeb", "addNat", "delNat", "ssh", "xtermjs"]];
}
function rainyunrcs_CrackPassword($params, $new_pass)
{
	$vserverid = rainyunrcs_GetServerid($params);
	if (empty($vserverid)) {
		return "服务器不存在";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/rcs/" . $vserverid . "/reset-password";
	$post_data = "\n{\n    \"password\": \"" . $new_pass . "\"\n}\n";
	$res = rainyunrcs_Curl($url, $post_data, 30, "POST", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "重置密码成功"];
	} else {
		return ["status" => "error", "msg" => $res["message"] ?: "重置密码失败"];
	}
}
function rainyunrcs_addNat($params)
{
	$post = input("post.");
	$vserverid = rainyunrcs_GetServerid($params);
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/" . $vserverid . "/nat";
	$post_data = "\n\n{\n    \"port_in\": " . trim($post["port_in"]) . ",\n    \"port_out\": " . trim($post["port_out"]) . ",\n    \"port_type\": \"" . trim($post["port_type"]) . "\"\n}\n\n";
	$res = rainyunrcs_Curl($url, $post_data, 30, "POST", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		$description = sprintf("NAT转发添加成功");
		$result = ["status" => "success", "msg" => $res["data"]];
	} else {
		$description = sprintf("NAT转发添加失败 - Host ID:%d", $params["hostid"]);
		$result = ["status" => "error", "msg" => $res["message"] ?: "NAT转发添加失败"];
	}
	active_logs($description, $params["uid"], 2);
	active_logs($description, $params["uid"], 2, 2);
	return $result;
}
function rainyunrcs_delNat($params)
{
	$post = input("post.");
	$vserverid = rainyunrcs_GetServerid($params);
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/" . $vserverid . "/nat/?nat_id=" . trim($post["nat_id"]);
	$res = rainyunrcs_Curl($url, [], 30, "DELETE", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		$description = sprintf("NAT转发删除成功");
		$result = ["status" => "success", "msg" => $res["data"]];
	} else {
		$description = sprintf("NAT转发删除失败 - Host ID:%d", $params["hostid"]);
		$result = ["status" => "error", "msg" => $res["message"] ?: "NAT转发删除失败"];
	}
	active_logs($description, $params["uid"], 2);
	active_logs($description, $params["uid"], 2, 2);
	return $result;
}


function rainyunrcs_Renew($params)
{
    $vserverid = rainyunrcs_GetServerid($params);
    if ($params["billingcycle"] == "monthly") {
        $duration = "1";
    } elseif ($params["billingcycle"] == "annually") {
        $duration = "12";
    } elseif ($params["billingcycle"] == "quarterly") {
        $duration = "3";
    } elseif ($params["billingcycle"] == "semiannually") {
        $duration = "6";
    } else {
        $duration = "1";
    }
    $header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
    $url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/" . $vserverid . "/renew";
    $post_data = "\n\n{\n    \"duration\": " . $duration . ",\n    \"with_coupon_id\": 0\n}\n\n";
    $res = rainyunrcs_Curl($url, $post_data, 30, "POST", $header);
    if (isset($res["code"]) && $res["code"] == 200) {
        $detail_url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/" . $vserverid;
        $res1 = rainyunrcs_Curl($detail_url, [], 10, "GET", $header);
        $str = $res1["Data"]["ExpDate"];
        $str1 = $res1["data"]["Data"]["MonthPrice"];
        $str2 = date("Y-m-d H:i:s", $res1["data"]["Data"]["ExpDate"]);
        $log = [
            "status" => "success",
            "message" => $str2,
            "timestamp" => time(),
            "vserver_id" => $vserverid,
            "billing_cycle" => $params["billingcycle"],
            "renew_duration" => $duration
        ];
        writeLog($log);
        return $log;
    } else {
        $log = [
            "status" => "error",
            "message" => $res["message"],
            "timestamp" => date("Y-m-d H:i:s", time()),
            "vserver_id" => $vserverid,
            "billing_cycle" => $params["billingcycle"],
            "renew_duration" => $duration
        ];
        writeLog($log);
        return $log;
    }
}

function writeLog($log)
{
    // 获取当前运行目录
    $currentDir = getcwd();
    // 创建日志文件路径
    $logFile = $currentDir . "/rcs-log.json";
    // 读取现有日志内容
    $existingLogs = [];
    if (file_exists($logFile)) {
        $existingLogs = json_decode(file_get_contents($logFile), true);
    }
    // 添加新日志
    $existingLogs[] = $log;
    // 写入日志文件
    file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
}

function rainyunrcs_Reinstall($params)
{
    $vserverid = rainyunrcs_GetServerid($params);
    if (empty($vserverid)) {
        return "产品参数错误";
    }
    if (empty($params["reinstall_os"])) {
        return "操作系统错误";
    }
    $header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
    $url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/" . $vserverid . "/changeos";
    $post_data = "\n\n{\n    \"os_id\": " . $params["reinstall_os"] . "\n}\n\n";
    $res = rainyunrcs_Curl($url, $post_data, 30, "POST", $header);
    if ($res["code"] == 200) {
        if (stripos($params["reinstall_os_name"], "win") !== false) {
            $username = "administrator";
        } else {
            $username = "root";
        }
        \think\Db::name("host")->where("id", $params["hostid"])->update(["username" => $username]);
        // 密码重置成功后，发送 GET 请求获取密码
        $password_url = $params["server_host"] . "/product/rcs/" . $vserverid . "/";
        $password_res = rainyunrcs_Curl($password_url, null, 30, "GET", $header);

        if (isset($password_res["code"]) && $password_res["code"] == 200) {
            $sys_pwd = $password_res['data']['Data']['DefaultPass']; // 获取DefaultPass项内容
            $update["password"] = cmf_encrypt($sys_pwd);
            \think\Db::name("host")->where("id", $params["hostid"])->update($update);
            return ["status" => "success", "msg" => "重装系统执行成功 请刷新界面查看新的默认密码"];
        } else {
            return ["status" => "error", "msg" => $password_res["message"] ?: "获取密码失败"];
        }
    } else {
        return ["status" => "error", "msg" => $res["message"] ?: "重装失败"];
    }
}





function rainyunrcs_CreateAccount($params)
{
    $vserverid = rainyunrcs_GetServerid($params);
    if (!empty($vserverid)) {
        return "已开通,不能重复开通";
    }
    if ($params["billingcycle"] == "monthly") {
        $duration = "1";
    } elseif ($params["billingcycle"] == "annually") {
        $duration = "12";
    } elseif ($params["billingcycle"] == "quarterly") {
        $duration = "3";
    } elseif ($params["billingcycle"] == "semiannually") {
        $duration = "6";
    } elseif ($params["billingcycle"] == "ontrial") {
        $duration = "1";
        $try = "true";
    } else {
        $duration = "1";
    }
    $header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
    $url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/";
    if ($params["configoptions"]["with_eip_num"] == null) {
        $eip = "0";
    } else {
        $eip = $params["configoptions"]["with_eip_num"];
    }
    if($params["configoptions"]["os_id"]==="true"){
        $try = $params["configoptions"]["os_id"];
    }
    if(empty($try)){
        $try = "false";
    }
    $post_data = "\n{\n    \"duration\": " . $duration . ",\n    \"plan_id\": " . $params["configoptions"]["plan_id"] . ",\n    \"os_id\": " . $params["configoptions"]["os_id"] . ",\n    \"try\": " . $try . ",\n    \"with_eip_flags\": \"\",\n    \"with_eip_num\": " . $eip . "\n}\n";
    $res = rainyunrcs_Curl($url, $post_data, 10, "POST", $header);
    if (isset($res["code"]) && $res["code"] == 200) {
        $server_id = $res["data"]["ID"];
        $sys_pwd = $res["data"]["DefaultPass"];
        $detail_url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/" . $server_id;
        $res1 = rainyunrcs_Curl($detail_url, [], 10, "GET", $header);
        $natip = $res1["data"]["Data"]["NatPublicIP"];
        $ipv4 = $res1["data"]["Data"]["MainIPv4"];
        $customid = \think\Db::name("customfields")->where("type", "product")->where("relid", $params["productid"])->where("fieldname", "vserverid")->value("id");
        if (empty($customid)) {
            $customfields = ["type" => "product", "relid" => $params["productid"], "fieldname" => "vserverid", "fieldtype" => "text", "adminonly" => 1, "create_time" => time()];
            $customid = \think\Db::name("customfields")->insertGetId($customfields);
        }
        $exist = \think\Db::name("customfieldsvalues")->where("fieldid", $customid)->where("relid", $params["hostid"])->find();
        if (empty($exist)) {
            $data = ["fieldid" => $customid, "relid" => $params["hostid"], "value" => $server_id, "create_time" => time()];
            \think\Db::name("customfieldsvalues")->insert($data);
        } else {
            \think\Db::name("customfieldsvalues")->where("id", $exist["id"])->update(["value" => $server_id]);
        }
        $os_info = \think\Db::name("host_config_options")->alias("a")->field("c.option_name")->leftJoin("product_config_options b", "a.configid=b.id")->leftJoin("product_config_options_sub c", "a.optionid=c.id")->where("a.relid", $params["hostid"])->where("b.option_type", 5)->find();
        if (stripos($os_info["option_name"], "win") !== false) {
            $username = "administrator";
        } else {
            $username = "root";
        }
		// 存入IP
		$ip = [];
		if($res1["data"]["Data"]["MainIPv4"] == "-"){
		    $update["dedicatedip"] = $res1["data"]["Data"]["NatPublicIP"];
		    foreach(array_reverse($res1['data']['NatList']) as $h){
		        if($h['PortIn']==22){
		            $update['port'] = $h['PortOut'];
		        }
		    }
		}else{
		    foreach($res1['data']['EIPList'] as $v){
		        if($res1["data"]["Data"]["MainIPv4"] === $v['IP']){
		            $update["dedicatedip"] = $res1["data"]["Data"]["MainIPv4"];
		        }else{
		            $ip[] = $v['IP'];
		        }
		    }
		   $update['port'] = 0;
		}
		$update['assignedips'] = implode(',', $ip);
        $update["domainstatus"] = "Active";
        $update["username"] = $username;
        $update["domain"] = $params["domain"];
        $update["password"] = cmf_encrypt($sys_pwd);
        if (empty($os_info)) {
            $update["os"] = $params["configoptions"]["os_id"];
        }
        \think\Db::name("host")->where("id", $params["hostid"])->update($update);
        return "ok";
    } else {
        return ["status" => "error", "msg" => "开通失败，原因：" . $res["message"]];
    }
}

function rainyunrcs_Status($params)
{
	$vserverid = rainyunrcs_GetServerid($params);
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$detail_url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/" . $vserverid;
	$res = rainyunrcs_Curl($detail_url, [], 10, "GET", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		if ($res["data"]["Data"]["Status"] == "running") {
			$result["status"] = "success";
			$result["data"]["status"] = "on";
			$result["data"]["des"] = "运行中";
			return $result;
		} elseif ($res["data"]["Data"]["Status"] == "stopped") {
			$result["status"] = "success";
			$result["data"]["status"] = "off";
			$result["data"]["des"] = "已停止";
			return $result;
		} elseif ($res["data"]["Data"]["Status"] == "creating") {
			$result["status"] = "success";
			$result["data"]["status"] = "process";
			$result["data"]["des"] = "创建中";
			return $result;
		} elseif ($res["data"]["Data"]["Status"] == "stopping") {
			$result["status"] = "success";
			$result["data"]["status"] = "process";
			$result["data"]["des"] = "正在停止";
			return $result;
		} elseif ($res["data"]["Data"]["Status"] == "booting") {
			$result["status"] = "success";
			$result["data"]["status"] = "process";
			$result["data"]["des"] = "正在操作";
			return $result;
		} elseif ($res["data"]["Data"]["Status"] == "banned") {
			$result["status"] = "success";
			$result["data"]["status"] = "off";
			$result["data"]["des"] = "因违规已禁封";
			return $result;
		}
	}
}

function rainyunrcs_Sync($params)
{
    $vserverid = rainyunrcs_GetServerid($params);
	if(empty($vserverid)){
		return '产品参数错误';
	}
    $url = $params["server_host"] . "/product/rcs/" . $vserverid;
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$res = rainyunrcs_Curl($url, null, 30, "GET", $header);
	if(isset($res['code']) && $res['code'] == 200){
		// 存入IP
		$ip = [];
		if($res["data"]["Data"]["MainIPv4"] == "-"){
		    $update["dedicatedip"] = $res["data"]["Data"]["NatPublicIP"];
		    foreach(array_reverse($res['data']['NatList']) as $h){
		        if($h['PortIn']==22){
		            $update['port'] = $h['PortOut'];
		        }
		    }
		}else{
		    foreach($res['data']['EIPList'] as $v){
		        if($res["data"]["Data"]["MainIPv4"] === $v['IP']){
		            $update["dedicatedip"] = $res["data"]["Data"]["MainIPv4"];
		        }else{
		            $ip[] = $v['IP'];
		        }
		    }
		   $update['port'] = 0;
		}
		$update['assignedips'] = implode(',', $ip);
		$update['password'] = cmf_encrypt($res['data']['Data']['DefaultPass']);
		$update['domain'] = $params["domain"];
  		$os_info = \think\Db::name("host_config_options")->alias("a")->field("c.option_name")->leftJoin("product_config_options b", "a.configid=b.id")->leftJoin("product_config_options_sub c", "a.optionid=c.id")->where("a.relid", $params["hostid"])->where("b.option_type", 5)->find();
        if (stripos($os_info["option_name"], "win") !== false) {
            $update['username'] = "administrator";
        } else {
            $update['username'] = "root";
        }
		Db::name('host')->where('id', $params['hostid'])->update($update);
		return ['status'=>'success', 'msg'=>$res['message']];
	}else{
		return ['status'=>'error', 'msg'=>$res['message'] ?: '同步失败'];
	}
}

function rainyunrcs_On($params)
{
    $vserverid = rainyunrcs_GetServerid($params);
    if (empty($vserverid)) {
        return "产品参数错误";
    }

    // 获取服务器当前状态
    $status = rainyunrcs_Status($params, $vserverid);
    if ($status["data"]["status"] == "on") {
        return "开机失败，当前已经是开机状态";
    }

    $header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
    $url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/" . $vserverid . "/start";
    $post_data = [];
    $post_data["id"] = $vserverid;
    $res = rainyunrcs_Curl($url, $post_data, 10, "POST", $header);

    if (isset($res["code"]) && $res["code"] == 200) {
        return ["status" => "success", "msg" => "开机成功"];
    } else {
        $errorMessage = isset($res["message"]) ? $res["message"] : "";
        if (strpos($errorMessage, "此产品已过期") !== false) {
            return ["status" => "error", "msg" => "开机失败，请联系工单处理"];
        } else {
            return ["status" => "error", "msg" => "开机失败，原因：" . $errorMessage];
        }
    }
}

function rainyunrcs_Off($params)
{
	$vserverid = rainyunrcs_GetServerid($params);
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/" . $vserverid . "/stop";
	$post_data = [];
	$post_data["id"] = $vserverid;
	$res = rainyunrcs_Curl($url, $post_data, 10, "POST", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "关机成功"];
	} else {
		return ["status" => "error", "msg" => "关机失败，原因：" . $res["message"]];
	}
}
function rainyunrcs_Reboot($params)
{
	$vserverid = rainyunrcs_GetServerid($params);
	if(empty($vserverid)){
        $vserverid = intval($params['old_configoptions']['customfields']['vserverid']);
        if (empty($vserverid)){
            return '产品参数错误';
        }
	}
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$url = $params["server_host"] . "/product/" . $params["configoptions"]["type"] . "/" . $vserverid . "/reboot";
	$post_data = [];
	$post_data["id"] = $vserverid;
	$res = rainyunrcs_Curl($url, $post_data, 10, "POST", $header);
	if (isset($res["code"]) && $res["code"] == 200) {
		return ["status" => "success", "msg" => "重启成功"];
	} else {
		return ["status" => "error", "msg" => "重启失败，原因：" . $res["message"]];
	}
}

function rainyunrcs_ChangePackage($params)
{
	$vserverid = rainyunrcs_GetServerid($params);
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	if (empty($vserverid)) {
		return "产品参数错误";
	}
	if(isset($params['configoptions_upgrade']['with_eip_num'])){
		$ip_num = $params['configoptions']['with_eip_num'];
		$old_ip_num = $params['old_configoptions']['with_eip_num'];
		if($ip_num > $old_ip_num){
		    $url = $params["server_host"] . "/product/rcs/" . $vserverid . "/eip/";
		    $post_data = "\n\n{\n    \"with_ip_num\": " . intval($ip_num - $old_ip_num) . "\n}\n\n";
		    $res = rainyunrcs_Curl($url, $post_data, 10, "POST", $header);
		}
	}
	rainyunrcs_Sync($params);
	$result['status'] = 'success';
	$result['msg'] = $res['message'] ?: '升级成功';
	return $result;
}

// VNC部分
function rainyunrcs_Vnc($params){
    $vserverid = rainyunrcs_GetServerid($params);
	// 请求数据
	$url = $params["server_host"] . "/product/rcs/" . $vserverid . "/vnc/?console_type=" . ( $params['rainyunrcs_console'] == "xtermjs" ? "xtermjs": "novnc" );
	$header = ["Content-Type: application/json; charset=utf-8", "x-api-key: " . $params["server_password"]];
	$res = rainyunrcs_Curl($url, null, 30, "GET", $header);
	if ($res["code"] != 200){
	    return ["status" => "error", "msg" => "连接 VNC 请求失败，请稍后再试"];
	}
	$data = $res["data"];
	$urlcs =  isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
	$urlcs .= "://" . $_SERVER['HTTP_HOST'];
	if(empty($data['VNCProxyURL'])){
	    return ["status" => "success", "url" => "$urlcs/plugins/servers/rainyunrcs/handlers/vncRedirect.php?RequestURL=".rawurlencode($data["RequestURL"])."&RedirectURL=".rawurlencode($data["RedirectURL"])."&PVEAuth=".rawurlencode($data["PVEAuth"]), "pass" => "YanJi-1116"];
	}else{
	    return ["status" => "success", "url" => $data["VNCProxyURL"], "pass" => "YanJi-1116"];
	}
}

function rainyunrcs_xtermjs($params){
    $post = input('post.');
    $params['rainyunrcs_console'] = $post['func'];
    $vnc = rainyunrcs_Vnc($params);
    if($vnc['status']==="success"){
        return ["status" => "success", "msg" => "VNC启动成功<script type='text/javascript'>window.open('$vnc[url]', '_blank');</script>"];
    }
}

function rainyunrcs_ClientButton($params){
    $os_info = \think\Db::name("host_config_options")->alias("a")->field("c.option_name")->leftJoin("product_config_options b", "a.configid=b.id")->leftJoin("product_config_options_sub c", "a.optionid=c.id")->where("a.relid", $params["hostid"])->where("b.option_type", 5)->find();
    if (stripos($os_info["option_name"], "win") === false) {
         $button = [
                   'xtermjs'=>[
                            'place'=>'console',   // 支持control和console 分别输出在控制和控制台
                            'name'=>'Xtermjs'     // 按钮名称
                   ],
                   'ssh'=>[
                            'place'=>'console',   // 支持control和console 分别输出在控制和控制台
                            'name'=>'SSH'     // 按钮名称
                   ],
         ];
         return $button;
    }
}

function rainyunrcs_ssh($params){
    $url="https://ssh.mhjz1.cn/?hostname=".$params['dedicatedip']."&username=".$params['username']."&password=".base64_encode($params['password']);
    return ["status" => "success", "msg" => "SSH启动成功<script type='text/javascript'>window.open('$url', '_blank');</script>"];
}

function rainyunrcs_GetServerid($params)
{
	return $params["customfields"]["vserverid"];
}
function rainyunrcs_Curl($url = "", $data = [], $timeout = 30, $request = "POST", $header = [])
{
	$curl = curl_init();
	if ($request == "GET") {
		$s = "";
		if (!empty($data)) {
			foreach ($data as $k => $v) {
				$s .= $k . "=" . urlencode($v) . "&";
			}
		}
		if ($s) {
			$s = "?" . trim($s, "&");
		}
		curl_setopt($curl, CURLOPT_URL, $url . $s);
	} else {
		curl_setopt($curl, CURLOPT_URL, $url);
	}
	curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($curl, CURLOPT_USERAGENT, "Mofang");
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($curl, CURLOPT_HEADER, 0);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	if (strtoupper($request) == "GET") {
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPGET, 1);
	}
	if (strtoupper($request) == "POST") {
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POST, 1);
		if (is_array($data)) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		} else {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
	}
	if (strtoupper($request) == "PUT" || strtoupper($request) == "DELETE") {
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($request));
		if (is_array($data)) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		} else {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
	}
	if (!empty($header)) {
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	}
	$res = curl_exec($curl);
	$error = curl_error($curl);
	if (!empty($error)) {
		return ["status" => 500, "message" => "CURL ERROR:" . $error];
	}
	$info = curl_getinfo($curl);
	curl_close($curl);
	return json_decode($res, true);
}