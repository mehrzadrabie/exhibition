<div class="card" style="margin-top:20px">
  <h1>مشخصات شما</h1>
  <p class="muted small">نام شما روی بلیط درج می‌شود.</p>
  <form method="post" action="<?= e(url('/profile')) ?>">
    <?= csrf_field() ?>
    <div class="grid g2">
      <div class="field"><label>نام *</label><input type="text" name="first_name" value="<?= e($u['first_name']) ?>" required maxlength="60"></div>
      <div class="field"><label>نام خانوادگی *</label><input type="text" name="last_name" value="<?= e($u['last_name']) ?>" required maxlength="80"></div>
    </div>
    <div class="field"><label>نام شرکت / سازمان</label><input type="text" name="company" value="<?= e($u['company']) ?>" maxlength="120"></div>
    <div class="field"><label>سمت</label><input type="text" name="job_title" value="<?= e($u['job_title']) ?>" maxlength="120"></div>
    <div class="field"><label>شماره موبایل</label><input type="text" value="<?= e($u['mobile']) ?>" class="input-ltr" disabled></div>
    <button class="btn block lg" type="submit">ذخیره و ادامه</button>
  </form>
</div>
