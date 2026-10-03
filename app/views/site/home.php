<section class="hero">
  <div class="hero-logo"><img src="<?= e(asset('img/logo.jpg')) ?>" alt="<?= e(setting('site_title')) ?>" width="98" height="54"></div>
  <h1><?= e(setting('site_title')) ?></h1>
  <p class="lead"><?= e(setting('site_tagline')) ?></p>
  <div class="meta">
    <?php if (setting('event_dates')): ?><span>📅 <?= e(setting('event_dates')) ?></span><?php endif ?>
    <?php if (setting('venue')): ?><span>📍 <?= e(setting('venue')) ?></span><?php endif ?>
  </div>
</section>

<h2>خرید بلیط</h2>
<?php if (!$sessions): ?>
  <div class="card empty">هنوز سانسی برای فروش تعریف نشده است.</div>
<?php else: ?>
<div class="grid g2">
  <?php foreach ($sessions as $s): $free = isset($avail[$s['id']]) ? $avail[$s['id']] : 0; ?>
  <div class="card session-card">
    <div class="date"><?= e(jdate('l j F Y – ساعت H:i', $s['starts_at'])) ?></div>
    <h3 class="mb0"><?= e($s['title']) ?></h3>
    <?php if ($s['subtitle']): ?><div class="muted"><?= e($s['subtitle']) ?></div><?php endif ?>
    <?php if ($s['description']): ?><p class="small"><?= nl2br(e($s['description'])) ?></p><?php endif ?>
    <div class="row between price">
      <span><?= isset($minPrice[$s['id']]) ? 'از ' . money($minPrice[$s['id']]) : '' ?></span>
      <span class="badge <?= $free > 20 ? 'ok' : ($free > 0 ? 'warn' : 'bad') ?>"><?= $free > 0 ? fa($free) . ' صندلی خالی' : 'تکمیل ظرفیت' ?></span>
    </div>
    <?php if (!$s['sale_open']): ?>
      <button class="btn ghost block" disabled>فروش این سانس فعال نیست</button>
    <?php elseif ($free < 1): ?>
      <button class="btn ghost block" disabled>ظرفیت تکمیل است</button>
    <?php else: ?>
      <a class="btn block" href="<?= e(url('/session/' . $s['id'])) ?>">انتخاب صندلی و خرید بلیط</a>
    <?php endif ?>
  </div>
  <?php endforeach ?>
</div>
<?php endif ?>

<?php if (setting('about_text')): ?>
<div class="card mt">
  <h2><?= e(setting('about_title', 'درباره رویداد')) ?></h2>
  <div><?= nl2br(e(setting('about_text'))) ?></div>
  <?php if (is_file(ROOT . '/assets/img/stages.jpg')): ?>
  <img class="mt" src="<?= e(asset('img/stages.jpg')) ?>" alt="بقا، بازیابی، بازگشت به رشد" loading="lazy" style="border-radius:12px" width="1400" height="462">
  <?php endif ?>
</div>
<?php endif ?>

<?php if ($speakers): ?>
<div class="card">
  <h2>سخنرانان و اساتید</h2>
  <div class="speakers">
    <?php foreach ($speakers as $sp): ?>
    <div class="speaker">
      <img src="<?= e($sp['photo'] ? base_path() . '/' . ltrim($sp['photo'], '/') : asset('img/logo.jpg')) ?>" alt="<?= e($sp['name']) ?>" loading="lazy" width="120" height="120">
      <b><?= e($sp['name']) ?></b>
      <small><?= e($sp['title']) ?></small>
    </div>
    <?php endforeach ?>
  </div>
</div>
<?php endif ?>

<?php if (setting('audience_text')): ?>
<div class="card">
  <h2>مخاطبان رویداد</h2>
  <div><?= nl2br(e(setting('audience_text'))) ?></div>
</div>
<?php endif ?>
