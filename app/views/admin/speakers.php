<h1>سخنرانان</h1>
<?php foreach (array_merge($speakers, [null]) as $sp): ?>
<form method="post" action="<?= e(url('/admin/speakers')) ?>" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $sp ? (int)$sp['id'] : 0 ?>">
  <div class="row" style="align-items:flex-start">
    <?php if ($sp && $sp['photo']): ?><img src="<?= e(base_path() . '/' . $sp['photo']) ?>" alt="" style="width:72px;height:72px;border-radius:50%;object-fit:cover"><?php endif ?>
    <div style="flex:1;min-width:240px">
      <?php if (!$sp): ?><h3>افزودن سخنران</h3><?php endif ?>
      <div class="grid g3">
        <div class="field"><label>نام</label><input type="text" name="name" value="<?= e($sp ? $sp['name'] : '') ?>" <?= $sp ? '' : '' ?>></div>
        <div class="field"><label>عنوان / سمت</label><input type="text" name="title" value="<?= e($sp ? $sp['title'] : '') ?>"></div>
        <div class="field"><label>ترتیب</label><input type="number" name="sort" value="<?= e($sp ? $sp['sort'] : 0) ?>"></div>
      </div>
      <div class="field"><label>بیوگرافی</label><textarea name="bio" style="min-height:60px"><?= e($sp ? $sp['bio'] : '') ?></textarea></div>
      <div class="row">
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
        <label class="check"><input type="checkbox" name="is_active" value="1" <?= !$sp || $sp['is_active'] ? 'checked' : '' ?>> نمایش</label>
        <button class="btn sm" type="submit">ذخیره</button>
        <?php if ($sp): ?><button class="btn sm ghost" name="action" value="delete" type="submit" onclick="return confirm('حذف شود؟')">حذف</button><?php endif ?>
      </div>
    </div>
  </div>
</form>
<?php endforeach ?>
