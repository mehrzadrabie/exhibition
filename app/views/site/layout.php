<?php $u = current_user(); $siteTitle = setting('site_title', 'همایش'); ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e(isset($title) ? $title . ' | ' . $siteTitle : $siteTitle) ?></title>
<meta name="description" content="<?= e(setting('site_tagline')) ?>">
<meta name="theme-color" content="#16181d">
<link rel="preload" href="<?= e(base_path()) ?>/assets/fonts/Vazirmatn-Regular.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="icon" href="<?= e(asset('img/logo.jpg')) ?>">
</head>
<body class="<?= e(isset($bodyClass) ? $bodyClass : '') ?>">
<header class="top">
  <div class="container">
    <a class="brand" href="<?= e(url('/')) ?>"><img src="<?= e(asset('img/logo-h.jpg')) ?>" alt="" width="137" height="32"><span><?= e($siteTitle) ?></span></a>
    <nav class="nav">
      <?php if ($u): ?>
        <a href="<?= e(url('/my')) ?>">بلیط‌های من</a>
        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit">خروج</button></form>
      <?php else: ?>
        <a href="<?= e(url('/login')) ?>">ورود / ثبت‌نام</a>
      <?php endif ?>
    </nav>
  </div>
</header>
<main>
  <div class="container <?= e(isset($wrap) ? $wrap : '') ?>">
    <?php foreach (flashes() as $f): ?>
      <div class="alert <?= e($f[0]) ?>"><?= e($f[1]) ?></div>
    <?php endforeach ?>
    <?= $content ?>
  </div>
</main>
<footer class="foot">
  <div class="container row between">
    <span><?= e(setting('footer_text', $siteTitle)) ?></span>
    <span><?= e(setting('support_phone')) ? 'پشتیبانی: ' . '<span class="ltr">' . e(fa(setting('support_phone'))) . '</span>' : '' ?></span>
  </div>
</footer>
<?php if (!empty($scripts)) foreach ($scripts as $s): ?>
<script src="<?= e(asset($s)) ?>" defer></script>
<?php endforeach ?>
</body>
</html>
