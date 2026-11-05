<?php
if(!defined('IN_CRONLITE'))exit();
?>
<style>
.smzf-item{margin-bottom:12px}
.smzf-item label{display:block;font-weight:bold;margin-bottom:4px}
.smzf-item .input-group-addon{cursor:pointer}
.smzf-code{background:#f7f7f7;border:1px solid #e5e5e5;border-radius:4px;padding:10px;font-family:Consolas,Monaco,monospace;word-break:break-all}
</style>

<div class="panel panel-primary">
	<div class="panel-heading"><h3 class="panel-title">上报参数（插件自动生成）</h3></div>
	<div class="panel-body">
		<div class="smzf-item">
			<label>商户地址（对应上报程序的「商户地址」，只填协议+域名，不带路径）</label>
			<div class="input-group">
				<input type="text" class="form-control" id="smzf_merchant_url" value="<?php echo htmlspecialchars($merchant_url)?>" readonly>
				<span class="input-group-addon" onclick="smzfCopy('smzf_merchant_url')">复制</span>
			</div>
		</div>
		<div class="smzf-item">
			<label>商户ID（对应上报程序的「商户ID」）</label>
			<div class="input-group">
				<input type="text" class="form-control" id="smzf_pid" value="<?php echo htmlspecialchars($auth['appid'])?>" readonly>
				<span class="input-group-addon" onclick="smzfCopy('smzf_pid')">复制</span>
			</div>
		</div>
		<div class="smzf-item">
			<label>商户密钥（对应上报程序的「商户密钥」）</label>
			<div class="input-group">
				<input type="text" class="form-control" id="smzf_key" value="<?php echo htmlspecialchars($auth['appkey'])?>" readonly>
				<span class="input-group-addon" onclick="smzfCopy('smzf_key')">复制</span>
			</div>
		</div>
		<p class="help-block">上报程序只会保存商户地址，实际请求由程序自动拼接为 <b><?php echo htmlspecialchars($merchant_url.$report_path)?></b>，无需填写路径。带令牌的备用上报地址：<b><?php echo htmlspecialchars($report_url_pay)?></b>（token 不参与签名）。密钥与本站签名算法一一对应，请勿泄露。</p>
	</div>
</div>

<div class="panel panel-success">
	<div class="panel-heading"><h3 class="panel-title">签名示例（与本系统 MD5 算法一致）</h3></div>
	<div class="panel-body">
		<div class="smzf-item">
			<label>1. 待签名串（参数名升序，a=b&amp;c=d）</label>
			<div class="smzf-code"><?php echo htmlspecialchars($demo_str)?></div>
		</div>
		<div class="smzf-item">
			<label>2. md5(待签名串 + 密钥)</label>
			<div class="smzf-code"><?php echo htmlspecialchars($demo_sign)?></div>
		</div>
		<p class="help-block">sign_type 固定为 MD5；参数值为空的字段不参与签名；timestamp 为 10 位秒级时间戳，与服务器时间相差超过 10 分钟会被拒绝。</p>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h3 class="panel-title">说明</h3></div>
	<div class="panel-body">
		<p>1. 买家在收银台扫码付款后，监听端按上述参数上报金额与支付类型，插件自动匹配本站订单并完成回调。</p>
		<p>2. 上报参数：amount（整数，单位在通道配置的「上报金额单位」中设置）、pay_type（alipay/wxpay）、channel_code、timestamp、sign 必填；pid、order_id、out_order_id、remark、pay_time、coll_user、pay_user、uid、channel_account_id 可选。URL 中的 token 不参与签名。</p>
		<p>3. 当前通道代码：<b><?php echo htmlspecialchars(!empty($channel_code) ? $channel_code : '未设置（不校验）')?></b>，示例支付类型：<b><?php echo $demo_type?></b>。</p>
		<form action="./plugin_page.php?channel=<?php echo intval($channel['id'])?>&func=config" method="POST" onsubmit="return confirm('重新生成后旧密钥和旧上报地址立即失效，确定继续吗？');">
			<input type="hidden" name="regenerate" value="1">
			<button type="submit" class="btn btn-danger btn-sm">重新生成商户ID、密钥和上报令牌</button>
		</form>
	</div>
</div>

<script>
function smzfCopy(id){
	var el = document.getElementById(id);
	el.removeAttribute('readonly');
	el.select();
	try{ document.execCommand('copy'); }catch(e){}
	el.setAttribute('readonly','readonly');
	if(window.layer && layer.msg){ layer.msg('已复制'); }
}
</script>
