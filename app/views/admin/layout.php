<?php
$a = current_admin();
$p = route_path();
$nav = [
    ['/admin', 'داشبورد', ['admin', 'operator']],
    ['/admin/checkin', 'اسکن ورود (QR)', ['admin', 'operator', 'checkin']],
    ['/admin/display', 'نمایشگر خوش‌آمد', ['admin']],
    ['/admin/orders', 'سفارش‌ها', ['admin', 'operator']],
    ['/admin/tickets', 'بلیط‌ها و حضور', ['admin', 'operator']],
    ['/admin/issue', 'صدور بلیط دستی / VIP', ['admin', 'operator']],
    ['/admin/users', 'کاربران', ['admin', 'operator']],
    ['-'],
    ['/admin/sessions', 'سانس‌ها و قیمت‌ها', ['admin']],
    ['/admin/layout', 'نقشه سالن و جایگاه‌ها', ['admin']],
    ['/admin/coupons', 'کدهای تخفیف', ['admin']],
    ['/admin/speakers', 'سخنرانان', ['admin']],
    ['/admin/settings', 'تنظیمات', ['admin']],
    ['/admin/staff', 'کاربران پنل', ['admin']],
    ['/admin/sms', 'گزارش پیامک‌ها', ['admin']],
];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(isset($title) ? $title : 'پنل مدیریت') ?> | پنل مدیریت</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="admin-body">
<?php if ($a): ?>
<div class="adm">
  <aside class="side" id="side">
    <a class="brand" href="<?= e(url('/')) ?>" target="_blank"><?= e(setting('site_title', 'همایش')) ?></a>
    <?php foreach ($nav as $n): ?>
      <?php if ($n[0] === '-'): if ($a['role'] === 'admin'): ?><div class="sep"></div><?php endif; continue; endif ?>
      <?php if (!in_array($a['role'], $n[2], true)) continue; ?>
      <a href="<?= e(url($n[0])) ?>" class="<?= ($p === $n[0] || ($n[0] !== '/admin' && strpos($p, $n[0]) === 0)) ? 'on' : '' ?>"><?= e($n[1]) ?></a>
    <?php endforeach ?>
    <div class="sep"></div>
    <div class="small" style="padding:4px 12px;color:#8b919b"><?= e($a['name']) ?> · <?= e(role_label($a['role'])) ?></div>
    <form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button type="submit">خروج</button></form>
  </aside>
  <div class="amain">
    <button class="btn ghost sm adm-toggle" type="button" onclick="document.getElementById('side').classList.toggle('open')">☰ منو</button>
    <?php foreach (flashes() as $f): ?>
      <div class="alert <?= e($f[0]) ?>"><?= e($f[1]) ?></div>
    <?php endforeach ?>
    <?= $content ?>
  </div>
</div>
<?php else: ?>
  <main><div class="container narrow">
    <?php foreach (flashes() as $f): ?><div class="alert <?= e($f[0]) ?>"><?= e($f[1]) ?></div><?php endforeach ?>
    <?= $content ?>
  </div></main>
<?php endif ?>
<?php if (!empty($scripts)) foreach ($scripts as $s): ?>
<script src="<?= e(asset($s)) ?>" defer></script>
<?php endforeach ?>
<script>
document.addEventListener('submit', function (e) {
  var m = e.target.getAttribute('data-confirm');
  if (m && !confirm(m)) e.preventDefault();
});
</script>
</body>
</html>
