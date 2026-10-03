<div class="card" style="margin-top:20px">
  <h1>کد تأیید</h1>
  <p class="muted">کد ارسال‌شده به شماره <b class="ltr"><?= e(fa($mobile)) ?></b> را وارد کنید. <a href="<?= e(url('/login')) ?>">تغییر شماره</a></p>
  <?php if ($debugCode): ?>
    <div class="alert warn small">حالت آزمایشی پیامک فعال است. کد: <b class="mono"><?= e($debugCode) ?></b></div>
  <?php endif ?>
  <form method="post" action="<?= e(url('/login/verify')) ?>" id="otpForm">
    <?= csrf_field() ?>
    <div class="field">
      <input type="text" name="code" id="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9۰-۹]*" maxlength="6" data-len="<?= (int)setting('otp_length', 5) ?>" class="input-otp" required autofocus>
    </div>
    <button class="btn block lg" type="submit">ورود</button>
  </form>
  <form method="post" action="<?= e(url('/login/resend')) ?>" class="center mt">
    <?= csrf_field() ?>
    <button class="linkbtn" type="submit" id="resend" data-wait="<?= (int)$wait ?>" <?= $wait > 0 ? 'disabled' : '' ?>>ارسال مجدد کد</button>
    <span class="muted small" id="resendWait"></span>
  </form>
</div>
