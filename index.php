<?php
require_once __DIR__ . '/includes/auth.php';
$page_title = t('nav.home');
$db = db();

$total    = (int)$db->query("SELECT COUNT(*) FROM works WHERE status='approved'")->fetchColumn();
$yr_stmt  = $db->prepare("SELECT COUNT(*) FROM works WHERE status='approved' AND year=:y");
$yr_stmt->execute([':y' => date('Y')]);
$this_yr  = (int)$yr_stmt->fetchColumn();
$students = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$dloads   = (int)$db->query("SELECT COALESCE(SUM(downloads),0) FROM works WHERE status='approved'")->fetchColumn();

$recent = $db->query("
  SELECT w.*, u.full_name AS student_name, t_user.full_name AS teacher_name
  FROM works w
  LEFT JOIN students s ON s.id = w.student_id
  LEFT JOIN users u ON u.id = s.user_id
  LEFT JOIN teachers t ON t.id = w.teacher_id
  LEFT JOIN users t_user ON t_user.id = t.user_id
  WHERE w.status='approved'
  ORDER BY w.created_at DESC LIMIT 5
")->fetchAll();

$by_type = $db->query("
  SELECT type, COUNT(*) cnt FROM works WHERE status='approved' AND type IS NOT NULL
  GROUP BY type ORDER BY cnt DESC
")->fetchAll();

include 'includes/header.php';
?>

<section class="hero">
  <div class="hero-inner">
    <h1><?= e(t('home.hero.title')) ?></h1>
    <p><?= e(t('home.hero.lead')) ?></p>
    <form action="<?= url('works.php') ?>" method="GET" class="hero-search">
      <input name="q" placeholder="<?= e(t('home.hero.search_placeholder')) ?>">
      <button type="submit"><?= e(t('btn.search')) ?></button>
    </form>
  </div>
</section>

<div class="wrap">

  <div class="stats">
    <div class="stat">
      <div class="ic">❖</div>
      <div><div class="val"><?= number_format($total) ?></div><div class="lbl"><?= e(t('home.stat.approved')) ?></div></div>
    </div>
    <div class="stat green">
      <div class="ic">✦</div>
      <div><div class="val"><?= number_format($this_yr) ?></div><div class="lbl"><?= e(t('home.stat.this_year')) ?> <?= date('Y') ?></div></div>
    </div>
    <div class="stat gold">
      <div class="ic">◉</div>
      <div><div class="val"><?= number_format($students) ?></div><div class="lbl"><?= e(t('home.stat.students')) ?></div></div>
    </div>
    <div class="stat">
      <div class="ic">⇣</div>
      <div><div class="val"><?= number_format($dloads) ?></div><div class="lbl"><?= e(t('home.stat.downloads')) ?></div></div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
    <div>
      <div class="page-title">
        <div>
          <h2><?= e(t('home.recent.title')) ?></h2>
          <p><?= e(t('home.recent.subtitle')) ?></p>
        </div>
        <a href="<?= url('works.php') ?>" class="btn btn-out btn-sm"><?= e(t('home.recent.all')) ?></a>
      </div>

      <?php if (empty($recent)): ?>
      <div class="empty">
        <div class="ic">❍</div>
        <h3><?= e(t('home.empty.title')) ?></h3>
        <p><?= e(t('home.empty.lead')) ?></p>
      </div>
      <?php else: ?>
      <div class="works-list">
        <?php foreach ($recent as $i => $w): ?>
        <div class="work-item">
          <div class="num"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></div>
          <div class="body">
            <h3><a href="<?= url('view.php?id='.$w['id']) ?>"><?= e($w['title']) ?></a></h3>
            <div class="meta">
              <span><?= e(t('works.item.author')) ?>: <?= e($w['student_name'] ?? '—') ?></span>
              <?php if ($w['teacher_name']): ?>
              <span><?= e(t('works.item.teacher')) ?>: <?= e($w['teacher_name']) ?></span>
              <?php endif; ?>
              <span><?= e(t('works.item.year')) ?> <?= $w['year'] ?></span>
              <span><?= work_type_label($w['type']) ?></span>
              <span><?= $w['views'] ?> <?= e(t('works.item.views')) ?></span>
            </div>
          </div>
          <div class="right">
            <span class="badge b-approved"><?= e(t('status.approved')) ?></span>
            <a href="<?= url('view.php?id='.$w['id']) ?>" class="btn btn-pri btn-sm"><?= e(t('btn.view')) ?></a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <div class="card">
        <div class="card-h"><?= e(t('home.by_type')) ?></div>
        <div class="card-b">
          <?php if (empty($by_type)): ?>
          <p style="color:var(--gray-500);font-size:14px;text-align:center"><?= e(t('home.no_data')) ?></p>
          <?php else: foreach ($by_type as $bt): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--gray-100)">
            <span style="font-size:14px"><?= work_type_label($bt['type']) ?></span>
            <strong style="color:var(--blue)"><?= $bt['cnt'] ?></strong>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <?php if (!logged_in()): ?>
      <div class="card">
        <div class="card-h"><?= e(t('home.join.title')) ?></div>
        <div class="card-b">
          <p style="font-size:14px;color:var(--gray-700);margin-bottom:16px">
            <?= e(t('home.join.lead')) ?>
          </p>
          <a href="<?= url('register.php') ?>" class="btn btn-pri btn-block" style="margin-bottom:8px"><?= e(t('nav.register')) ?></a>
          <a href="<?= url('login.php') ?>" class="btn btn-out btn-block"><?= e(t('nav.login')) ?></a>
        </div>
      </div>
      <?php else: ?>
      <div class="card">
        <div class="card-h"><?= e(t('home.quick.title')) ?></div>
        <div class="card-b">
          <a href="<?= url('upload.php') ?>" class="btn btn-pri btn-block" style="margin-bottom:10px"><?= e(t('btn.new_work')) ?></a>
          <a href="<?= url('works.php') ?>" class="btn btn-out btn-block" style="margin-bottom:10px"><?= e(t('home.quick.archive')) ?></a>
          <a href="<?= url('profile.php') ?>" class="btn btn-ghost btn-block"><?= e(t('home.quick.profile')) ?></a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
