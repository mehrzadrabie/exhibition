<h1>کاربران پنل</h1>
<p class="small muted">نقش «کنترل ورود» فقط به صفحه اسکن QR دسترسی دارد — برای نیروهای پذیرش درب سالن. «اپراتور فروش» سفارش‌ها، بلیط‌ها و صدور دستی را می‌بیند.</p>
<div class="card tablewrap">
<table class="t">
  <tr><th>نام</th><th>نام کاربری</th><th>نقش</th><th>آخرین ورود</th><th>ویرایش (رمز خالی = بدون تغییر)</th><th></th></tr>
  <?php foreach ($staff as $s): ?>
  <tr>
    <td><?= e($s['name']) ?><?= $s['is_active'] ? '' : ' <span class="badge muted">غیرفعال</span>' ?></td>
    <td class="ltr"><?= e($s['username']) ?></td>
    <td><?= e(role_label($s['role'])) ?></td>
    <td class="small"><?= $s['last_login_at'] ? e(jdate('Y/m/d H:i', $s['last_login_at'])) : '—' ?></td>
    <td>
      <form method="post" action="<?= e(url('/admin/staff')) ?>" class="row" style="flex-wrap:nowrap">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <input type="text" name="name" value="<?= e($s['name']) ?>" style="width:130px">
        <select name="role" style="width:130px"><?php foreach (['admin', 'operator', 'checkin'] as $r): ?><option value="<?= $r ?>" <?= $s['role'] === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach ?></select>
        <input type="password" name="password" placeholder="رمز جدید" style="width:120px" autocomplete="new-password">
        <button class="btn sm ghost" type="submit">ذخیره</button>
      </form>
    </td>
    <td><form method="post" action="<?= e(url('/admin/staff')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn sm ghost" name="action" value="toggle"><?= $s['is_active'] ? 'غیرفعال' : 'فعال' ?></button></form></td>
  </tr>
  <?php endforeach ?>
</table>
</div>
<form method="post" action="<?= e(url('/admin/staff')) ?>" class="card">
  <?= csrf_field() ?>
  <h3>کاربر جدید</h3>
  <div class="grid g4">
    <div class="field"><label>نام</label><input type="text" name="name" required></div>
    <div class="field"><label>نام کاربری (لاتین)</label><input type="text" name="username" class="input-ltr" required></div>
    <div class="field"><label>رمز (حداقل ۸)</label><input type="password" name="password" class="input-ltr" required autocomplete="new-password"></div>
    <div class="field"><label>نقش</label><select name="role"><option value="checkin">کنترل ورود</option><option value="operator">اپراتور فروش</option><option value="admin">مدیر کل</option></select></div>
  </div>
  <button class="btn" type="submit">افزودن</button>
</form>
