<div class="card" style="margin-top:20px">
  <div class="alert warn">این یک درگاه آزمایشی است و هیچ وجهی کسر نمی‌شود. برای فروش واقعی، درگاه زرین‌پال یا زیبال را در تنظیمات پنل مدیریت فعال کنید.</div>
  <h2>پرداخت سفارش <?= e(fa($o['id'])) ?></h2>
  <p>مبلغ: <b><?= money($o['total']) ?></b></p>
  <div class="grid g2">
    <a class="btn teal" href="<?= e(url('/payment/callback', ['oid' => $o['id'], 'a' => $o['authority'], 'status' => 'ok'])) ?>">پرداخت موفق</a>
    <a class="btn ghost" href="<?= e(url('/payment/callback', ['oid' => $o['id'], 'a' => $o['authority'], 'status' => 'cancel'])) ?>">انصراف</a>
  </div>
</div>
