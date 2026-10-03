<h1>نقشه سالن و جایگاه‌ها</h1>
<p class="small muted">صندلی‌ها را انتخاب کنید (کلیک روی شماره ردیف = انتخاب کل ردیف) و جایگاه/قیمت‌گروه آن‌ها را تغییر دهید یا غیرفعالشان کنید (مثلاً صندلی خراب یا جای دوربین). این تغییرات روی همه سانس‌ها اعمال می‌شود.</p>
<div class="seat-layout">
  <div>
    <div class="mapbox" id="map"></div>
    <div class="legend">
      <?php foreach ($cats as $c): ?><span><i style="background:<?= e($c['color']) ?>"></i><?= e($c['name']) ?> (<?= e(fa($c['seats'])) ?>)</span><?php endforeach ?>
      <span><i style="background:#fff;border:1px dashed #999"></i>غیرفعال (<?= e(fa($inactive)) ?>)</span>
    </div>
  </div>
  <aside>
    <div class="card">
      <h3>انتخاب: <span id="selCount">۰</span> صندلی</h3>
      <div class="row"><button class="btn ghost sm" type="button" id="selAll">انتخاب همه</button><button class="btn ghost sm" type="button" id="selNone">لغو انتخاب</button></div>
      <form method="post" action="<?= e(url('/admin/layout')) ?>" class="mt">
        <?= csrf_field() ?>
        <input type="hidden" name="seats" id="seatsInput">
        <div class="field"><label>تغییر جایگاه به</label>
          <select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach ?></select></div>
        <button class="btn sm" name="action" value="category" type="submit">اعمال جایگاه</button>
        <div class="row mt">
          <button class="btn ghost sm" name="action" value="deactivate" type="submit">غیرفعال کردن</button>
          <button class="btn ghost sm" name="action" value="activate" type="submit">فعال کردن</button>
        </div>
      </form>
    </div>
    <div class="card">
      <h3>جایگاه‌ها (گروه قیمت)</h3>
      <?php foreach ($cats as $c): ?>
      <form method="post" action="<?= e(url('/admin/categories')) ?>" class="row" style="flex-wrap:nowrap;margin-bottom:8px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <input type="color" name="color" value="<?= e($c['color']) ?>" style="width:42px;height:38px;padding:2px;border-radius:8px;border:1px solid #ddd">
        <input type="text" name="name" value="<?= e($c['name']) ?>">
        <input type="hidden" name="sort" value="<?= (int)$c['sort'] ?>">
        <button class="btn sm ghost" type="submit">ذخیره</button>
      </form>
      <?php endforeach ?>
      <form method="post" action="<?= e(url('/admin/categories')) ?>" class="row mt" style="flex-wrap:nowrap">
        <?= csrf_field() ?>
        <input type="color" name="color" value="#0ea5e9" style="width:42px;height:38px;padding:2px;border-radius:8px;border:1px solid #ddd">
        <input type="text" name="name" placeholder="جایگاه جدید">
        <button class="btn sm" type="submit">افزودن</button>
      </form>
    </div>
  </aside>
</div>
<script type="application/json" id="layoutData"><?= json_encode($layoutJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
