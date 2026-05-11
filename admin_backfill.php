<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
$page_title = 'Пур кардани хеши файлҳо';
$db = db();

$pending_st = $db->query(
    "SELECT COUNT(*) FROM works
     WHERE file_hash IS NULL AND file_path IS NOT NULL AND file_path <> ''"
);
$pending = (int)$pending_st->fetchColumn();

$processed = 0; $hashed = 0; $missing = 0; $dups = []; $started = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $started = true;
    $rows = $db->query(
        "SELECT id, file_path, title, year FROM works
         WHERE file_hash IS NULL AND file_path IS NOT NULL AND file_path <> ''
         ORDER BY id ASC"
    )->fetchAll();

    $upd = $db->prepare("UPDATE works SET file_hash = :h WHERE id = :i");
    $check_dup = $db->prepare(
        "SELECT w.id, w.title, w.year, u.full_name
         FROM works w
         LEFT JOIN students s ON s.id = w.student_id
         LEFT JOIN users    u ON u.id = s.user_id
         WHERE w.file_hash = :h AND w.id <> :self
         LIMIT 1"
    );

    foreach ($rows as $r) {
        $processed++;
        $path = UPLOAD_DIR . $r['file_path'];
        if (!is_file($path)) { $missing++; continue; }
        $h = hash_file('sha256', $path);
        if (!$h) { $missing++; continue; }

        $check_dup->execute([':h'=>$h, ':self'=>$r['id']]);
        $other = $check_dup->fetch();
        if ($other) {
            $dups[] = [
                'a' => $r,
                'b' => $other,
            ];
        }
        $upd->execute([':h'=>$h, ':i'=>$r['id']]);
        $hashed++;
    }
    log_activity($_SESSION['uid'], 'backfill_hashes', "Коркард: $processed, хешшуда: $hashed, дубликатҳо: ".count($dups));
    $pending = max(0, $pending - $hashed);
}

include 'includes/header.php';
?>
<div class="wrap" style="max-width:840px">
  <div class="page-title">
    <div>
      <h2>Пур кардани хеши файлҳо</h2>
      <p>Барои корҳои қаблан боршуда хеши SHA-256 ҳисоб карда мешавад — то системаи муайянкунии такрорӣ ба онҳо низ татбиқ гардад.</p>
    </div>
  </div>

  <?php if ($started): ?>
    <div class="alert alert-ok" style="line-height:1.6">
      <b>✓ Иҷро шуд</b><br>
      Коркардшуда: <b><?= $processed ?></b> · Хешҳои нав: <b><?= $hashed ?></b> · Файлҳои гумшуда: <b><?= $missing ?></b> · Дубликатҳои дарёфтшуда: <b><?= count($dups) ?></b>
    </div>

    <?php if ($dups): ?>
      <div class="card">
        <div class="card-h">⚠️ Дубликатҳои дарёфтшуда дар архив</div>
        <div class="card-b">
          <p style="margin-bottom:14px">Ин корҳо файлҳои якхела доранд — лозим аст ҳаллу фасл карда шавад (нигоҳ доштани як, ҳазфи дигар, ё ҳарду).</p>
          <table class="t">
            <thead><tr><th>Кори №1</th><th>Кори №2</th></tr></thead>
            <tbody>
              <?php foreach ($dups as $d): ?>
                <tr>
                  <td>
                    #<?= (int)$d['a']['id'] ?> — <b><?= e($d['a']['title']) ?></b><br>
                    <small>соли <?= (int)$d['a']['year'] ?></small>
                  </td>
                  <td>
                    #<?= (int)$d['b']['id'] ?> — <b><?= e($d['b']['title']) ?></b><br>
                    <small>соли <?= (int)$d['b']['year'] ?> · <?= e($d['b']['full_name'] ?: 'номаълум') ?></small>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="card">
    <div class="card-h">Корҳои бе хеш</div>
    <div class="card-b">
      <?php if ($pending === 0): ?>
        <p>✓ Ҳамаи корҳо хеш доранд. Чизе кардан лозим нест.</p>
        <a href="<?= url('admin.php') ?>" class="btn btn-ghost">← Бозгашт ба идора</a>
      <?php else: ?>
        <p style="margin-bottom:14px">Дар архив <b><?= $pending ?></b> кор бе хеш мавҷуд аст.</p>
        <form method="POST">
          <button type="submit" class="btn btn-pri btn-lg">Ҳисоб кардани хешҳо</button>
          <a href="<?= url('admin.php') ?>" class="btn btn-ghost btn-lg" style="margin-left:8px">Бекор</a>
        </form>
        <p style="margin-top:12px;color:#666;font-size:13px">
          Эзоҳ: вобаста ба миқдор ва андозаи файлҳо метавонад якчанд сония гирад. Ҳангоми ҳисоб ягон чизе таҳрир намешавад — танҳо хешҳо илова мегарданд.
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
