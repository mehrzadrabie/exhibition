<?php
$cancelled = $t['status'] !== 'valid';
$used = (bool)$t['checked_in_at'];
?>
<div class="ticket <?= $used ? 'used' : '' ?>" id="ticket">
  <div class="th">
    <div class="sub"><?= e(setting('site_title')) ?></div>
    <h2><?= e($t['title']) ?></h2>
    <div class="sub"><?= e(jdate('l j F Y – ساعت H:i', $t['starts_at'])) ?></div>
    <div class="sub"><?= e(setting('venue')) ?></div>
  </div>
  <div class="tb">
    <?php if ($cancelled): ?><div class="alert err">این بلیط باطل شده است.</div><?php endif ?>
    <div class="seatbig">
      <div><small>ردیف</small><b><?= e(fa($t['row_no'])) ?></b></div>
      <div><small>صندلی</small><b><?= e(fa($t['seat_no'])) ?></b></div>
      <div><small>بخش</small><b style="font-size:1.1rem;line-height:2.2"><?= e(section_name($t['section'])) ?></b></div>
    </div>
    <dl class="kv">
      <dt>به نام</dt><dd><?= e($holder) ?></dd>
      <?php if ($t['cat_name']): ?><dt>جایگاه</dt><dd><i class="swatch" style="background:<?= e($t['color']) ?>"></i> <?= e($t['cat_name']) ?></dd><?php endif ?>
      <?php if ($used): ?><dt>ورود</dt><dd><?= e(jdate('j F – H:i', $t['checked_in_at'])) ?></dd><?php endif ?>
    </dl>
  </div>
  <div class="qr" id="qr" data-text="<?= e($qrText) ?>"></div>
  <div class="code"><?= e($t['code']) ?></div>
</div>

<div class="row no-print" style="justify-content:center;margin-bottom:18px">
  <button class="btn dark" type="button" id="dlBtn">ذخیره تصویر بلیط</button>
  <button class="btn ghost" type="button" onclick="window.print()">چاپ</button>
</div>

<div class="card no-print" style="max-width:680px;margin:0 auto">
  <h3>موقعیت صندلی شما در سالن</h3>
  <div class="mapbox" id="miniMap"></div>
  <p class="small muted mt mb0">هنگام ورود، QR کد بالا را به مسئول پذیرش نشان دهید. لطفاً این لینک را فقط با صاحب بلیط به اشتراک بگذارید.</p>
</div>

<script type="application/json" id="ticketData"><?= json_encode([
    'layout' => $layout, 'seat' => (int)$t['seat_id'], 'site' => setting('site_title'), 'title' => $t['title'],
    'date' => jdate('l j F Y – ساعت H:i', $t['starts_at']), 'venue' => setting('venue'), 'holder' => $holder,
    'row' => fa($t['row_no']), 'no' => fa($t['seat_no']), 'section' => section_name($t['section']), 'code' => $t['code'],
    'cat' => $t['cat_name'], 'font' => base_path() . '/assets/fonts/Vazirmatn-Bold.woff2',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
