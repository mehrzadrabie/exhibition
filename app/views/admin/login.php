<div class="card" style="margin-top:60px">
  <h1>ورود به پنل مدیریت</h1>
  <form method="post" action="<?= e(url('/admin/login')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label>نام کاربری</label><input type="text" name="username" class="input-ltr" required autofocus autocomplete="username"></div>
    <div class="field"><label>رمز عبور</label><input type="password" name="password" class="input-ltr" required autocomplete="current-password"></div>
    <button class="btn block lg" type="submit">ورود</button>
  </form>
</div>
