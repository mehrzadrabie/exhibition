<div class="row between" style="margin-bottom:10px">
  <div>
    <h1 class="mb0"><?= e($session['title']) ?></h1>
    <div class="muted"><?= e(jdate('l j F Y – ساعت H:i', $session['starts_at'])) ?> · <?= e(setting('venue')) ?></div>
  </div>
</div>

<?php if ($pending): ?>
  <div class="alert warn">شما یک سفارش در انتظار پرداخت برای این سانس دارید. <a href="<?= e(url('/order/' . $pending['id'])) ?>"><b>ادامه پرداخت</b></a> — با ثبت انتخاب جدید، سفارش قبلی لغو می‌شود.</div>
<?php endif ?>
<?php if (!$session['sale_open']): ?>
  <div class="alert info">فروش این سانس در حال حاضر فعال نیست.</div>
<?php endif ?>

<div class="seat-layout">
  <div>
    <div class="mapbox" id="map"></div>
    <div class="legend" id="legend"></div>
    <p class="small muted mt">روی صندلی دلخواه بزنید. برای بزرگ‌نمایی از دکمه‌های + و − یا دو انگشت استفاده کنید.</p>
  </div>
  <aside class="card cart">
    <h3>صندلی‌های انتخابی</h3>
    <ul id="cartList"><li class="muted">هنوز صندلی‌ای انتخاب نکرده‌اید.</li></ul>
    <div class="total"><span>جمع</span><span id="cartTotal">۰ تومان</span></div>
    <form method="post" action="<?= e(url('/book')) ?>" id="bookForm">
      <?= csrf_field() ?>
      <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">
      <input type="hidden" name="seats" id="seatsInput">
      <button class="btn block lg" type="submit" id="bookBtn" disabled>ادامه و پرداخت</button>
    </form>
    <p class="small muted mt mb0">حداکثر <?= e(fa($session['max_per_order'])) ?> صندلی در هر سفارش. صندلی‌ها پس از ثبت، به مدت <?= e(fa(setting('hold_minutes', 12))) ?> دقیقه برای شما نگه داشته می‌شوند.</p>
  </aside>
</div>

<div class="mobile-bar">
  <div class="sum"><b id="mbCount">۰ صندلی</b><br><span id="mbTotal" class="muted">۰ تومان</span></div>
  <button class="btn" type="button" id="mbBtn" disabled>ادامه و پرداخت</button>
</div>

<script type="application/json" id="pageData"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<script>window.LOGIN_URL = <?= json_encode(url('/login')) ?>;</script>
