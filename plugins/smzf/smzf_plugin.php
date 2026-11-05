<?php

class smzf_plugin
{
	static public $info = [
		'name'        => 'smzf', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '免签扫码支付（微信/支付宝）', //支付插件显示名称
		'author'      => 'Epay', //支付插件作者
		'link'        => '', //支付插件作者链接
		'types'       => ['alipay','wxpay'], //支付插件支持的支付方式
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称
			'appid' => [
				'name' => '商户ID（pid）',
				'type' => 'input',
				'note' => '由插件自动生成，留空即可，保存前请勿手动清空',
			],
			'appkey' => [
				'name' => '商户密钥（key）',
				'type' => 'input',
				'note' => '由插件自动生成，需与监听端保持一致',
			],
			'alipay_qr' => [
				'name' => '支付宝收款码图片',
				'type' => 'input',
				'note' => '填写图片地址，例如 https://你的域名/qr/alipay.png',
			],
			'wxpay_qr' => [
				'name' => '微信收款码图片',
				'type' => 'input',
				'note' => '填写图片地址，例如 https://你的域名/qr/wxpay.png',
			],
			'alipay_channel_code' => [
				'name' => '支付宝通道代码',
				'type' => 'input',
				'note' => '仅用于参数页示例签名，不参与订单校验，可留空',
			],
			'wxpay_channel_code' => [
				'name' => '微信通道代码',
				'type' => 'input',
				'note' => '仅用于参数页示例签名，不参与订单校验，可留空',
			],
			'appmchid' => [
				'name' => '渠道账号ID（可选）',
				'type' => 'input',
				'note' => '仅用于记录，不参与订单校验，可留空',
			],
			'report_token' => [
				'name' => '上报令牌（自动生成）',
				'type' => 'input',
				'note' => '自动生成并拼接在上报地址中，请勿随意修改，否则监听端需同步更新地址',
			],
		],
		'select' => null,
		'note' => '<p>商户ID（pid）、商户密钥（key）和上报地址令牌由插件自动生成，<a href="./plugin_page.php?channel=[channel]&func=config" target="_blank">点击查看上报地址与密钥</a>。</p>
<p>上报程序只需填三项：商户地址 <u>[siteurl]</u>（协议+域名，不带路径）、商户ID、商户密钥；上报路径由程序自动拼接为 /api/report/商户ID/pc，无需填写。</p>
<p>签名算法与本系统一致：除 sign 外的非空参数按参数名升序拼接成 a=b&amp;c=d，末尾直接拼接商户密钥后取 md5 小写。</p>
<p>amount 为整数，插件会自动按「分」和「元」两种方式换算并匹配订单，无需额外配置金额单位。支付类型 pay_type 支持 alipay、wxpay。</p>',
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	//支付提交
	static public function submit(){
		global $channel;
		self::ensureAuth($channel);
		return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
	}

	//移动端提交
	static public function mapi(){
		global $channel;
		self::ensureAuth($channel);
		return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
	}

	//展示收款码
	static public function qrcode(){
		global $order, $channel, $cdnpublic, $siteurl;
		self::ensureAuth($channel);

		$type = $order['typename'];
		$qr_image = $type == 'wxpay' ? (isset($channel['wxpay_qr']) ? $channel['wxpay_qr'] : '') : (isset($channel['alipay_qr']) ? $channel['alipay_qr'] : '');
		if(empty($qr_image)) sysmsg('该支付通道未配置收款码，请联系网站管理员');

		$paytime = strtotime($order['addtime']) + 300 - time();
		if($paytime < 0) $paytime = 0;

		include PAY_ROOT.'inc/qrcode.page.php';
		exit;
	}

	//后台查看/重新生成上报参数
	static public function config($channel){
		global $siteurl, $islogin;
		if(!$islogin) exit('Access Denied');

		if(isset($_POST['regenerate'])){
			$channel['appid'] = '';
			$channel['appkey'] = '';
			$channel['report_token'] = '';
			self::ensureAuth($channel);
			showmsg('已重新生成商户ID、密钥和上报令牌，请同步修改监听端配置！', 1);
		}

		$auth = self::ensureAuth($channel);
		//上报程序只保存「协议+域名」形式的商户地址，路径由程序自行拼接
		$merchant_url = rtrim($siteurl, '/');
		$report_path = '/api/report/'.$auth['appid'].'/pc';
		$report_url = $merchant_url.$report_path;
		$report_url_pay = $merchant_url.'/pay/report/'.$channel['id'].'/?token='.$auth['token'];
		$channel_code = $channel['type'] == 2 ? (isset($channel['wxpay_channel_code']) ? $channel['wxpay_channel_code'] : '') : (isset($channel['alipay_channel_code']) ? $channel['alipay_channel_code'] : '');
		$demo_type = $channel['type'] == 2 ? 'wxpay' : 'alipay';

		//按本系统MD5算法生成一条示例签名，便于监听端对照调试
		$demo = [
			'amount' => '100',
			'channel_code' => !empty($channel_code) ? $channel_code : ($demo_type == 'wxpay' ? 'wx_qr' : 'ali_qr'),
			'pay_type' => $demo_type,
			'timestamp' => strval(time()),
		];
		ksort($demo);
		$pairs = [];
		foreach($demo as $k => $v){
			$pairs[] = $k.'='.$v;
		}
		$demo_str = implode('&', $pairs);
		$demo_sign = md5($demo_str.$auth['appkey']);

		include PAY_ROOT.'conf.page.php';
		exit;
	}

	//确保商户ID、密钥和上报令牌存在，首次自动生成并写入通道配置
	static private function ensureAuth(&$channel){
		global $DB;

		$appid = isset($channel['appid']) ? trim(strval($channel['appid'])) : '';
		$appkey = isset($channel['appkey']) ? trim(strval($channel['appkey'])) : '';
		$token = isset($channel['report_token']) ? trim(strval($channel['report_token'])) : '';
		if($appid !== '' && $appkey !== '' && $token !== ''){
			return ['appid'=>$appid, 'appkey'=>$appkey, 'token'=>$token];
		}
		if(empty($channel['id'])) return ['appid'=>$appid, 'appkey'=>$appkey, 'token'=>$token];

		if($appid === '') $appid = strval(rand(100000, 999999));
		if($appkey === '') $appkey = md5(uniqid(mt_rand(), true));
		if($token === '') $token = substr(md5(uniqid(mt_rand(), true)), 0, 16);

		$row = $DB->getRow("SELECT config FROM pre_channel WHERE id=:id LIMIT 1", [':id'=>$channel['id']]);
		$config = $row && !empty($row['config']) ? json_decode($row['config'], true) : [];
		if(!is_array($config)) $config = [];
		$config['appid'] = $appid;
		$config['appkey'] = $appkey;
		$config['report_token'] = $token;
		$DB->update('channel', ['config'=>json_encode($config)], ['id'=>$channel['id']]);

		$channel['appid'] = $appid;
		$channel['appkey'] = $appkey;
		$channel['report_token'] = $token;
		return ['appid'=>$appid, 'appkey'=>$appkey, 'token'=>$token];
	}

	//上报支付信息（监听端/人工调用，路径：/pay/report/{通道ID}/?token=xxx）
	static public function report(){
		global $channel;

		if(empty($channel) || empty($channel['appkey'])){
			self::json(-1, '该通道未配置商户密钥');
		}

		//校验上报令牌（地址中的token，防止被随意猜测调用；token在URL中，不参与签名）
		$report_token = isset($channel['report_token']) ? trim(strval($channel['report_token'])) : '';
		if($report_token !== ''){
			$token = isset($_GET['token']) ? trim(strval($_GET['token'])) : '';
			if($token !== $report_token) self::json(-1, '上报令牌不正确，请使用后台上报参数页面中的完整地址');
		}

		self::doReport($channel);
	}

	//系统API上报入口：/api/report/{pid}/pc
	static public function apiReport($pid, $device = 'pc', $channel = null){
		if(!is_array($channel) || empty($channel['id'])){
			$channel = self::findChannelByPid($pid);
		}
		if(!$channel) self::json(-1, '商户ID不存在或未开通免签上报通道');
		self::doReport($channel, strval($pid));
	}

	//按插件自动生成的商户ID查找通道
	static private function findChannelByPid($pid){
		global $DB;
		$rows = $DB->getAll("SELECT id, config FROM pre_channel");
		foreach($rows as $row){
			$config = json_decode($row['config'], true);
			if(!is_array($config) || !isset($config['appid'], $config['appkey'], $config['report_token'])) continue;
			if(strval($config['appid']) === strval($pid)){
				return \lib\Channel::get($row['id']);
			}
		}
		return null;
	}

	//上报处理
	static private function doReport($channel, $expect_pid = ''){
		global $DB;
		//API上报时全局通道变量为空，需赋值给processNotify使用（计算成本费率等）
		$GLOBALS['channel'] = $channel;

		$data = self::getParams();

		$sign = isset($data['sign']) && !is_array($data['sign']) ? strval($data['sign']) : '';
		if($sign === '') self::json(-1, '缺少签名sign');
		if(!self::checkSign($data, $channel['appkey'])) self::json(-1, '签名校验失败');

		$amount = isset($data['amount']) ? $data['amount'] : '';
		$pay_type = isset($data['pay_type']) && !is_array($data['pay_type']) ? strval($data['pay_type']) : '';
		$timestamp = isset($data['timestamp']) && !is_array($data['timestamp']) ? strval($data['timestamp']) : '';
		if($amount === '' || !is_numeric($amount)) self::json(-1, '支付金额amount不能为空');
		if(!in_array($pay_type, ['alipay','wxpay'])) self::json(-1, '支付类型pay_type不正确');
		if($timestamp === '') self::json(-1, '时间戳timestamp不能为空');
		if(abs(time() - intval($timestamp)) > 600) self::json(-1, '时间戳不正确，请检查服务器时间');

		$pid = isset($data['pid']) && !is_array($data['pid']) ? strval($data['pid']) : '';
		$appid = isset($channel['appid']) ? strval($channel['appid']) : '';
		if($pid !== '' && $appid !== '' && $pid !== $appid){
			self::json(-1, '商户ID不匹配');
		}
		if($expect_pid !== '' && $appid !== '' && $expect_pid !== $appid){
			self::json(-1, '商户ID不匹配');
		}

		//channel_code、channel_account_id 由监听端按自己的识别规则生成，仅作参考，不参与订单校验
		//金额由监听端（PC挂机）按实际收款金额上报，插件同时按「分」「元」换算并匹配订单，无需配置单位
		$moneys = [self::formatMoney($amount, 'fen'), self::formatMoney($amount, 'yuan')];
		$moneys = array_values(array_unique(array_filter($moneys, function($v){ return $v !== null; })));
		if(empty($moneys)) self::json(-1, '支付金额格式不正确');

		$order = self::findOrder($moneys, $pay_type, $data, $channel);
		if(!$order){
			//放宽匹配：不限定具体通道，只要属于本免签插件的通道即可（兼容子通道/多通道场景）
			$order = self::findOrder($moneys, $pay_type, $data, $channel, true);
		}
		if(!$order){
			//兜底：仅按金额匹配未支付订单，用于识别「订单支付类型不一致」的情况
			$byMoney = self::findOrderByMoney($moneys);
			if($byMoney){
				$orderType = isset($byMoney['typename']) ? $byMoney['typename'] : '';
				self::json(-1, '金额已匹配到订单'.$byMoney['trade_no'].'，但订单支付方式为「'.$orderType.'」，与上报类型「'.$pay_type.'」不一致');
			}
		}
		if(!$order){
			self::json(-1, '未找到匹配的未支付订单（上报金额'.implode(' / ', $moneys).'，类型'.$pay_type.'；近期未支付订单金额：'.self::recentMoneyHint($pay_type).'）');
		}
		if($order['status'] >= 1){
			self::json(1, '订单已支付', [
				'trade_no'=>$order['trade_no'],
				'out_trade_no'=>$order['out_trade_no'],
				'order_addtime'=>$order['addtime'],
				'status'=>intval($order['status']),
			]);
		}
		if($order['typename'] !== $pay_type){
			self::json(-1, '订单支付类型不一致');
		}

		$api_trade_no = !empty($data['order_id']) && !is_array($data['order_id']) ? strval($data['order_id']) : 'SMZF'.date('YmdHis').rand(1000, 9999);
		$buyer = isset($data['pay_user']) && !is_array($data['pay_user']) ? strval($data['pay_user']) : null;
		processNotify($order, $api_trade_no, $buyer);

		//回读订单状态，便于确认匹配到的是哪一笔订单
		$check = $DB->getRow("SELECT status,out_trade_no,addtime FROM pre_order WHERE trade_no=:trade_no LIMIT 1", [':trade_no'=>$order['trade_no']]);
		if(!$check || intval($check['status']) < 1){
			self::json(-1, '订单状态更新失败：'.$order['trade_no']);
		}
		self::json(1, '上报成功', [
			'trade_no'=>$order['trade_no'],
			'out_trade_no'=>$order['out_trade_no'],
			'order_addtime'=>$order['addtime'],
			'status'=>$check ? intval($check['status']) : null,
		]);
	}

	//部分监听端会调用 notify 路径，兼容处理
	static public function notify(){
		self::report();
	}

	//获取上报参数（支持 GET、POST、JSON）
	static private function getParams(){
		$data = [];
		$raw = file_get_contents('php://input');
		if($raw){
			$json = json_decode($raw, true);
			if(is_array($json)) $data = $json;
		}
		if(empty($data)) $data = $_POST;
		if(empty($data)) $data = $_GET;
		unset($data['s'], $data['sitename']);
		return $data;
	}

	//签名校验（与本系统 MD5 签名算法一致）
	static private function checkSign($data, $key){
		if(!isset($data['sign']) || is_array($data['sign'])) return false;
		$sign = strtolower(trim(strval($data['sign'])));
		if($sign === '') return false;

		$data['sign_type'] = 'MD5';
		$mysign = \lib\Payment::makeSign($data, $key);
		return $mysign && strtolower($mysign) === $sign;
	}

	//金额单位转换
	static private function formatMoney($amount, $unit){
		if(!is_numeric($amount)) return null;
		$amount = floatval($amount);
		$money = $unit === 'yuan' ? $amount : $amount / 100;
		return number_format($money, 2, '.', '');
	}

	//查找匹配的未支付订单（$moneys 为兼容多种金额换算方式的候选金额；$loose 为 true 时不限定具体通道）
	static private function findOrder($moneys, $pay_type, $data, $channel, $loose = false){
		global $DB;

		$params = [':pay_type'=>$pay_type];
		$moneyKeys = [];
		foreach(array_values($moneys) as $i => $m){
			$key = ':money'.$i;
			$moneyKeys[] = $key;
			$params[$key] = $m;
		}

		$where = " WHERE LOWER(B.name)=LOWER(:pay_type) AND A.realmoney IN (".implode(',', $moneyKeys).")";
		if($loose){
			$where .= " AND A.channel IN (SELECT id FROM pre_channel WHERE plugin='smzf')";
			$minutes = 1440;
		}else{
			$cid = intval($channel['id']);
			$where .= " AND (A.channel=:cid OR A.subchannel IN (SELECT id FROM pre_subchannel WHERE channel=:cid2))";
			$params[':cid'] = $cid;
			$params[':cid2'] = $cid;
			$minutes = 120;
		}

		//优先使用平台订单号匹配（已支付也返回，便于重复上报时幂等处理）
		if(!empty($data['out_order_id']) && !is_array($data['out_order_id'])){
			$row = $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A LEFT JOIN pre_type B ON A.type=B.id".$where." AND A.trade_no=:trade_no LIMIT 1", array_merge($params, [':trade_no'=>strval($data['out_order_id'])]));
			if($row) return $row;
		}

		//使用商户订单号匹配
		if(!empty($data['order_id']) && !is_array($data['order_id'])){
			$row = $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A LEFT JOIN pre_type B ON A.type=B.id".$where." AND A.out_trade_no=:out_trade_no LIMIT 1", array_merge($params, [':out_trade_no'=>strval($data['order_id'])]));
			if($row) return $row;
		}

		//按金额+支付类型匹配最新创建的未支付订单（pre_order 无 id 字段，按 trade_no 排序）
		$row = $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A LEFT JOIN pre_type B ON A.type=B.id".$where." AND A.status=0 AND A.addtime >= DATE_SUB(NOW(), INTERVAL ".$minutes." MINUTE) ORDER BY A.trade_no DESC LIMIT 1", $params);
		if($row) return $row;

		//重复上报时，若2分钟内有同金额的已支付订单，按幂等返回成功
		return $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A LEFT JOIN pre_type B ON A.type=B.id".$where." AND A.status=1 AND A.endtime >= DATE_SUB(NOW(), INTERVAL 2 MINUTE) ORDER BY A.trade_no DESC LIMIT 1", $params);
	}

	//兜底查找：仅按金额匹配近24小时未支付订单（不限定支付类型与通道）
	static private function findOrderByMoney($moneys){
		global $DB;
		$moneyKeys = [];
		$params = [];
		foreach(array_values($moneys) as $i => $m){
			$key = ':money'.$i;
			$moneyKeys[] = $key;
			$params[$key] = $m;
		}
		return $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A LEFT JOIN pre_type B ON A.type=B.id WHERE A.realmoney IN (".implode(',', $moneyKeys).") AND A.status=0 AND A.addtime >= DATE_SUB(NOW(), INTERVAL 1440 MINUTE) ORDER BY A.trade_no DESC LIMIT 1", $params);
	}

	//近期未支付订单金额提示，便于上报失败时排查
	static private function recentMoneyHint($pay_type){
		global $DB;
		$all = intval($DB->getColumn("SELECT COUNT(*) FROM pre_order WHERE status=0 AND addtime >= DATE_SUB(NOW(), INTERVAL 1440 MINUTE)"));
		$rows = $DB->getAll("SELECT A.realmoney FROM pre_order A LEFT JOIN pre_type B ON A.type=B.id WHERE LOWER(B.name)=LOWER(:pay_type) AND A.status=0 AND A.addtime >= DATE_SUB(NOW(), INTERVAL 1440 MINUTE) ORDER BY A.trade_no DESC LIMIT 5", [':pay_type'=>$pay_type]);
		$list = [];
		if(is_array($rows)){
			foreach($rows as $row){
				$list[] = number_format($row['realmoney'], 2, '.', '');
			}
		}
		return (empty($list) ? '无' : implode(', ', array_unique($list))).'；近24小时全部未支付订单数：'.$all;
	}

	//输出JSON
	static private function json($code, $msg, $extra = []){
		@header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array_merge(['code'=>$code, 'msg'=>$msg], $extra));
		exit;
	}
}
