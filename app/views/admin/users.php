<h1>کاربران</h1>
<form class="filters" method="get" action="<?= e(url('/admin/users')) ?>">
  <?php if (!config('pretty_urls', true)): ?><input type="hidden" name="r" value="/admin/users"><?php endif ?>
  <div class="field"><label>جستجو</label><input type="search" name="q" value="<?= e(input('q')) ?>" placeholder="موبایل، نام، شرکت"></div>
  <button class="btn dark" type="submit">جستجو</button>
</form>
<div class="card tablewrap">
<table class="t">
  <tr><th>#</th><th>نام</th><th>موبایل</th><th>شرکت / سمت</th><th>بلیط</th><th>عضویت</th><th></th></tr>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><?= e(fa($u['id'])) ?></td>
    <td><?= e(trim($u['first_name'] . ' ' . $u['last_name']) ?: '—') ?><?= $u['is_blocked'] ? ' <span class="badge bad">مسدود</span>' : '' ?></td>
    <td class="ltr"><a href="<?= e(url('/admin/orders', ['q' => $u['mobile'], 'status' => 'all'])) ?>"><?= e($u['mobile']) ?></a></td>
    <td class="small"><?= e($u['company']) ?> <?= $u['job_title'] ? '/ ' . e($u['job_title']) : '' ?></td>
    <td><?= e(fa($u['tickets'])) ?></td>
    <td class="small"><?= e(jdate('Y/m/d', $u['created_at'])) ?></td>
    <td><?php if (admin_can('admin')): ?>
      <form method="post" action="<?= e(url('/admin/users/' . $u['id'])) ?>"><?= csrf_field() ?>
        <button class="btn sm ghost" name="action" value="<?= $u['is_blocked'] ? 'unblock' : 'block' ?>"><?= $u['is_blocked'] ? 'رفع مسدودی' : 'مسدود' ?></button></form>
    <?php endif ?></td>
  </tr>
  <?php endforeach ?>
</table>
<?= render('admin/_pager', ['pg' => $pg]) ?>
</div>
