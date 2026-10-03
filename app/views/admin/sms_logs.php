<h1>گزارش پیامک‌ها</h1>
<div class="card tablewrap">
<table class="t">
  <tr><th>زمان</th><th>موبایل</th><th>نوع</th><th>نتیجه</th><th>پاسخ سرویس</th></tr>
  <?php foreach ($logs as $l): ?>
  <tr>
    <td class="small"><?= e(jdate('m/d H:i:s', $l['created_at'])) ?></td>
    <td class="ltr"><?= e($l['mobile']) ?></td>
    <td><?= $l['kind'] === 'otp' ? 'کد ورود' : 'بلیط' ?></td>
    <td><?= $l['ok'] ? '<span class="badge ok">موفق</span>' : '<span class="badge bad">ناموفق</span>' ?></td>
    <td class="small ltr" style="max-width:420px;word-break:break-all"><?= e(preg_replace('/\b\d{4,6}\b/', '****', (string)$l['response'])) ?></td>
  </tr>
  <?php endforeach ?>
</table>
</div>
