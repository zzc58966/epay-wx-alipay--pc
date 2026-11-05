<?php
// 免签扫码支付页面
if (!defined('IN_PLUGIN')) exit();

$is_wxpay = $type == 'wxpay';
$theme = $is_wxpay ? '#07c160' : '#1677ff';
$title = $is_wxpay ? '微信扫码支付' : '支付宝扫码支付';
$tip = $is_wxpay ? '请使用微信扫一扫' : '请使用支付宝扫一扫';
$qr_image = htmlspecialchars($qr_image);
?>
<!DOCTYPE html>
<html>

<head>
    <meta name="viewport" content="initial-scale=1, maximum-scale=1, user-scalable=no, width=device-width">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="Content-Language" content="zh-cn">
    <meta name="renderer" content="webkit">
    <title><?php echo $title ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: #f2f3f5;
            font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
            color: #333;
        }
        .pay-wrap {
            max-width: 460px;
            margin: 0 auto;
            padding: 18px 14px 30px;
        }
        .pay-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, .06);
            padding: 24px 20px 20px;
            text-align: center;
        }
        .pay-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .pay-title .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: <?php echo $theme ?>;
        }
        .pay-amount {
            font-size: 34px;
            font-weight: 700;
            color: <?php echo $theme ?>;
            line-height: 1.2;
            margin-bottom: 6px;
        }
        .pay-amount small {
            font-size: 17px;
            font-weight: 500;
        }
        .pay-amount-tip {
            font-size: 13px;
            color: #fa5151;
            margin-bottom: 18px;
        }
        .qr-box {
            position: relative;
            display: inline-block;
            padding: 12px;
            border: 1px solid #ececec;
            border-radius: 12px;
            background: #fff;
        }
        .qr-box img {
            display: block;
            width: 230px;
            height: 230px;
            object-fit: contain;
        }
        .qr-expired {
            display: none;
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            border-radius: 12px;
            background: rgba(0, 0, 0, .72);
            color: #fff;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            line-height: 1.8;
        }
        .qr-expired .icon {
            font-size: 34px;
            color: #ff5722;
        }
        .pay-tip {
            font-size: 14px;
            color: #666;
            margin: 16px 0 4px;
        }
        .pay-tip strong { color: <?php echo $theme ?>; }
        .countdown {
            font-size: 14px;
            color: #999;
            margin-top: 10px;
        }
        .countdown b {
            color: #fa5151;
            font-weight: 600;
        }
        .pay-actions {
            margin-top: 20px;
        }
        .pay-btn {
            display: inline-block;
            width: 100%;
            padding: 12px 0;
            border: none;
            border-radius: 10px;
            background: <?php echo $theme ?>;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
        }
        .pay-btn:active { opacity: .85; }
        .pay-detail {
            margin-top: 22px;
            text-align: left;
            border-top: 1px dashed #e8e8e8;
            padding-top: 16px;
            font-size: 13px;
            color: #666;
        }
        .pay-detail div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 5px 0;
        }
        .pay-detail span:last-child {
            color: #333;
            word-break: break-all;
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="pay-wrap">
        <div class="pay-card">
            <div class="pay-title"><span class="dot"></span><?php echo $title ?></div>
            <div class="pay-amount"><small>￥</small><?php echo number_format($order['realmoney'], 2, '.', '') ?></div>
            <div class="pay-amount-tip">请务必按此金额付款，金额不一致无法自动到账</div>

            <div class="qr-box">
                <img src="<?php echo $qr_image ?>" alt="收款码" id="qrImage">
                <div class="qr-expired" id="qrExpired">
                    <div class="icon">×</div>
                    <div>二维码已失效</div>
                    <div>请返回重新发起支付</div>
                </div>
            </div>

            <div class="pay-tip"><?php echo $tip ?>，扫描二维码完成支付</div>
            <div class="pay-tip" style="font-size:13px;color:#999">手机端可长按二维码保存后识别</div>
            <div class="countdown">支付剩余时间 <b id="countdown">--:--</b></div>

            <div class="pay-actions">
                <button type="button" class="pay-btn" onclick="checkPay(true)">我已付款，刷新结果</button>
            </div>

            <div class="pay-detail">
                <div><span>商品名称</span><span><?php echo htmlspecialchars($order['name']) ?></span></div>
                <div><span>商户订单号</span><span><?php echo htmlspecialchars($order['trade_no']) ?></span></div>
                <div><span>创建时间</span><span><?php echo htmlspecialchars($order['addtime']) ?></span></div>
            </div>
        </div>
    </div>

    <script src="<?php echo $cdnpublic ?>jquery/1.12.4/jquery.min.js"></script>
    <script src="<?php echo $cdnpublic ?>layer/3.1.1/layer.js"></script>
    <script>
        var tradeNo = <?php echo json_encode($order['trade_no']); ?>;
        var paytime = <?php echo intval($paytime); ?>;
        var expired = false;

        function checkPay(manual) {
            $.ajax({
                type: "GET",
                dataType: "json",
                url: "/getshop.php",
                data: { trade_no: tradeNo },
                success: function(data) {
                    if (data.code == 1) {
                        layer.msg('支付成功，正在跳转中...', { icon: 16, shade: 0.1, time: 15000 });
                        setTimeout(function() {
                            window.location.href = data.backurl;
                        }, 800);
                    } else if (manual) {
                        layer.msg('暂未查询到付款记录，请确认金额后稍等几秒再试', { icon: 0, time: 2200 });
                    }
                },
                error: function() {
                    if (manual) layer.msg('服务器错误，请稍后重试', { icon: 2 });
                }
            });
        }

        function startCountdown(seconds) {
            var el = document.getElementById('countdown');
            var overlay = document.getElementById('qrExpired');
            if (seconds <= 0) {
                expired = true;
                el.textContent = '00:00';
                overlay.style.display = 'flex';
                return;
            }
            var timer = setInterval(function() {
                seconds--;
                if (seconds <= 0) {
                    clearInterval(timer);
                    expired = true;
                    el.textContent = '00:00';
                    overlay.style.display = 'flex';
                    return;
                }
                var m = Math.floor(seconds / 60);
                var s = seconds % 60;
                el.textContent = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
            }, 1000);
        }

        setInterval(function() {
            if (!expired) checkPay(false);
        }, 3000);

        startCountdown(paytime);
    </script>
</body>

</html>
