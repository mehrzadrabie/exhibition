<?php if ($pg['pages'] > 1): $q = $_GET; unset($q['r']); ?>
<div class="pager">
  <?php for ($i = max(1, $pg['page'] - 4); $i <= min($pg['pages'], $pg['page'] + 4); $i++): $q['page'] = $i; ?>
    <?php if ($i === $pg['page']): ?><span><?= e(fa($i)) ?></span><?php else: ?><a href="<?= e(url(route_path(), $q)) ?>"><?= e(fa($i)) ?></a><?php endif ?>
  <?php endfor ?>
  <span style="background:none;color:var(--muted);border:0">مجموع: <?= e(fa($pg['total'])) ?></span>
</div>
<?php endif ?>
