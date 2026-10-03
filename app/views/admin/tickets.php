<?php $q = $_GET; unset($q['r'], $q['page']); $q['export'] = '1'; ?>
<div class="row between"><h1 class="mb0">بلیط‌ها و لیست حضور</h1><a class="btn teal sm" href="<?= e(url('/admin/tickets', $q)) ?>">خروجی اکسل (CSV)</a></div>
<form class="filters mt" method="get" action="<?= e(url('/admin/tickets')) ?>">
  <?php if (!config('pretty_urls', true)): ?><input type="hidden" name="r" value="/admin/tickets"><?php endif ?>
  <div class="field"><label>جستجو</label><input type="search" name="q" value="<?= e(input('q')) ?>" placeholder="موبایل، نام، شرکت، کد بلیط"></div>
  <div class="field"><label>سانس</label><select name="session"><option value="">همه</option>
    <?php foreach ($sessions as $s): ?><option value="<?= (int)$s['id'] ?>" <?= input('session') == $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option><?php endforeach ?></select></div>
  <div class="field"><label>حضور</label><select name="checkin">
    <option value="">همه</option><option value="in" <?= input('checkin') === 'in' ? 'selected' : '' ?>>وارد شده</option><option value="out" <?= input('checkin') === 'out' ? 'selected' : '' ?>>هنوز نیامده</option></select></div>
  <button class="btn dark" type="submit">فیلتر</button>
</form>
<div class="card tablewrap">
<table class="t">
  <tr><th>کد</th><th>صندلی</th><th>دارنده</th><th>شرکت</th><th>سانس</th><th>حضور</th><th></th></tr>
  <?php foreach ($tickets as $t): ?>
  <tr>
    <td class="mono small"><a href="<?= e(url('/t/' . ticket_token($t['code']))) ?>" target="_blank"><?= e($t['code']) ?></a></td>
    <td><?= e('ر' . fa($t['row_no']) . ' / ص' . fa($t['seat_no'])) ?><div class="small muted"><?= e($t['cat']) ?></div></td>
    <td><?= e($t['guest_name'] ?: trim($t['first_name'] . ' ' . $t['last_name'])) ?><div class="small muted ltr"><?= e($t['guest_mobile'] ?: $t['mobile']) ?></div></td>
    <td class="small"><?= e($t['company']) ?></td>
    <td class="small"><?= e($t['title']) ?></td>
    <td><?= $t['checked_in_at'] ? '<span class="badge ok">' . e(jdate('H:i', $t['checked_in_at'])) . '</span>' : '<span class="badge muted">—</span>' ?></td>
    <td>
      <form method="post" action="<?= e(url('/admin/tickets/' . $t['id'])) ?>" class="inline">
        <?= csrf_field() ?>
        <?php if (!$t['checked_in_at']): ?><button class="btn sm ghost" name="action" value="checkin">ثبت ورود</button>
        <?php else: ?><button class="btn sm ghost" name="action" value="undo_checkin">لغو ورود</button><?php endif ?>
      </form>
      <?php if (admin_can('admin')): ?>
      <form method="post" action="<?= e(url('/admin/tickets/' . $t['id'])) ?>" class="inline" data-confirm="این بلیط باطل و صندلی آزاد شود؟">
        <?= csrf_field() ?><button class="btn sm ghost" name="action" value="cancel">ابطال</button>
      </form>
      <?php endif ?>
    </td>
  </tr>
  <?php endforeach ?>
  <?php if (!$tickets): ?><tr><td colspan="7" class="empty">موردی پیدا نشد.</td></tr><?php endif ?>
</table>
<?= render('admin/_pager', ['pg' => $pg]) ?>
</div>
