<p class="p-a-lg m-a-lg text-center">加载支付宝中...</p>

<script src="<?= $asset('plugins/alipay/js/ap.js') ?>"></script>
<script>
    _AP.pay(<?= json_encode($url) ?>);
</script>
