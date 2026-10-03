<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(setting('site_title')) ?> – خوش آمدید</title>
<link rel="preload" href="<?= e(base_path()) ?>/assets/fonts/Vazirmatn-Bold.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/display.css')) ?>">
</head>
<body>
<div class="glow"></div>
<header class="bar">
  <div class="brand"><span class="logo"><img src="<?= e(asset('img/logo.jpg')) ?>" alt=""></span><span><?= e(setting('site_title')) ?></span></div>
  <div class="meta"><span id="inside"></span><span id="clock"></span><i id="net" class="net"></i></div>
</header>

<main class="stage">
  <section id="idle" class="idle show">
    <h1><?= e(setting('site_title')) ?></h1>
    <p><?= e(setting('site_tagline')) ?></p>
    <div class="sub"><?= e(setting('venue')) ?></div>
  </section>
  <section id="welcome" class="welcome">
    <div class="heading" id="wHeading"></div>
    <div class="name" id="wName"></div>
    <div class="company" id="wCompany"></div>
    <div class="seat" id="wSeat"></div>
    <div class="msg" id="wMsg"></div>
    <div class="timer"><i id="wTimer"></i></div>
  </section>
</main>

<footer class="recent" id="recent"></footer>

<div class="start" id="start">
  <div>
    <b>برای شروع نمایش، روی صفحه کلیک کنید</b>
    <span>صفحه تمام‌صفحه می‌شود و صدای خوش‌آمد فعال می‌شود</span>
  </div>
</div>

<script type="application/json" id="cfg"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<script src="<?= e(asset('js/display.js')) ?>"></script>
</body>
</html>
