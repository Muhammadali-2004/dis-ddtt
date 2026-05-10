<?php
require_once __DIR__ . '/includes/auth.php';
$page_title = 'Саҳифаи асосӣ';
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
    <h1>Архиви Корҳои Илмии Донишҷӯён</h1>
    <p>Системаи иттилоотии расмии нигоҳдорӣ ва дастрасии корҳои илмии донишҷӯён — рисолаҳо, корҳои курсӣ ва мақолаҳо.</p>
    <form action="<?= url('works.php') ?>" method="GET" class="hero-search">
      <input name="q" placeholder="Ҷустуҷӯ: унвон, муаллиф, мавзӯъ, калидвожа...">
      <button type="submit">Ҷустуҷӯ</button>
    </form>
  </div>
</section>

<div class="wrap">

  <div class="stats">
    <div class="stat">
      <div class="ic">❖</div>
      <div><div class="val"><?= number_format($total) ?></div><div class="lbl">Корҳои тасдиқшуда</div></div>
    </div>
    <div class="stat green">
      <div class="ic">✦</div>
      <div><div class="val"><?= number_format($this_yr) ?></div><div class="lbl">Соли <?= date('Y') ?></div></div>
    </div>
    <div class="stat gold">
      <div class="ic">◉</div>
      <div><div class="val"><?= number_format($students) ?></div><div class="lbl">Донишҷӯён</div></div>
    </div>
    <div class="stat">
      <div class="ic">⇣</div>
      <div><div class="val"><?= number_format($dloads) ?></div><div class="lbl">Зеркашиҳо</div></div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
    <div>
      <div class="page-title">
        <div>
          <h2>Охирин корҳои тасдиқшуда</h2>
          <p>5 кори охирини иловашуда</p>
        </div>
        <a href="<?= url('works.php') ?>" class="btn btn-out btn-sm">Ҳамаи корҳо →</a>
      </div>

      <?php if (empty($recent)): ?>
      <div class="empty">
        <div class="ic">❍</div>
        <h3>Ҳанӯз кор вуҷуд надорад</h3>
        <p>Аввалин кор бор кунед!</p>
      </div>
      <?php else: ?>
      <div class="works-list">
        <?php foreach ($recent as $i => $w): ?>
        <div class="work-item">
          <div class="num"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></div>
          <div class="body">
            <h3><a href="<?= url('view.php?id='.$w['id']) ?>"><?= e($w['title']) ?></a></h3>
            <div class="meta">
              <span>Муаллиф: <?= e($w['student_name'] ?? '—') ?></span>
              <?php if ($w['teacher_name']): ?>
              <span>Роҳбар: <?= e($w['teacher_name']) ?></span>
              <?php endif; ?>
              <span>Соли <?= $w['year'] ?></span>
              <span><?= work_type_label($w['type']) ?></span>
              <span><?= $w['views'] ?> дидан</span>
            </div>
          </div>
          <div class="right">
            <span class="badge b-approved">Тасдиқшуда</span>
            <a href="<?= url('view.php?id='.$w['id']) ?>" class="btn btn-pri btn-sm">Дидан</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <div class="card">
        <div class="card-h">Корҳо аз рӯи намуд</div>
        <div class="card-b">
          <?php if (empty($by_type)): ?>
          <p style="color:var(--gray-500);font-size:14px;text-align:center">Маълумот нест</p>
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
        <div class="card-h">Иштирок кунед</div>
        <div class="card-b">
          <p style="font-size:14px;color:var(--gray-700);margin-bottom:16px">
            Барои бор кардани кор ва дастрасии пурра ба архив, дар система қайд шавед.
          </p>
          <a href="<?= url('register.php') ?>" class="btn btn-pri btn-block" style="margin-bottom:8px">Қайд шудан</a>
          <a href="<?= url('login.php') ?>" class="btn btn-out btn-block">Дохил шудан</a>
        </div>
      </div>
      <?php else: ?>
      <div class="card">
        <div class="card-h">Амалиётҳои зуд</div>
        <div class="card-b">
          <a href="<?= url('upload.php') ?>" class="btn btn-pri btn-block" style="margin-bottom:10px">Кори нав илова</a>
          <a href="<?= url('works.php') ?>" class="btn btn-out btn-block" style="margin-bottom:10px">Архиви пурра</a>
          <a href="<?= url('profile.php') ?>" class="btn btn-ghost btn-block">Профили ман</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
