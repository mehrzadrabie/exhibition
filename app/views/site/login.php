<div class="card" style="margin-top:20px">
  <h1>ورود / ثبت‌نام</h1>
  <p class="muted">شماره موبایل خود را وارد کنید تا کد تأیید برایتان پیامک شود.</p>
  <form method="post" action="<?= e(url('/login')) ?>">
    <?= csrf_field() ?>
    <div class="field">
      <label for="mobile">شماره موبایل</label>
      <input type="tel" id="mobile" name="mobile" inputmode="numeric" autocomplete="tel" placeholder="09123456789" class="input-ltr" required autofocus maxlength="14">
    </div>
    <button class="btn block lg" type="submit">دریافت کد تأیید</button>
  </form>
</div>
