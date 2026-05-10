<?php
require_once __DIR__ . '/includes/auth.php';
$db = db();
$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: '.url('works.php')); exit; }

$st = $db->prepare("
    SELECT w.*, u.full_name AS student_name, u.id AS author_user_id,
           t_user.full_name AS teacher_name,
           f.name AS faculty_name, s.group_name
    FROM works w
    LEFT JOIN students s ON s.id=w.student_id
    LEFT JOIN users u ON u.id=s.user_id
    LEFT JOIN teachers t ON t.id=w.teacher_id
    LEFT JOIN users t_user ON t_user.id=t.user_id
    LEFT JOIN faculties f ON f.id=s.faculty_id
    WHERE w.id=:id
");
$st->execute([':id'=>$id]);
$w = $st->fetch();
if (!$w || ($w['status']!=='approved' && !can_review() && (current_user()['id'] ?? 0) !== ($w['author_user_id'] ?? -1))) {
    header('Location: '.url('works.php')); exit;
}

if ($w['status']==='approved' && (!logged_in() || $_SESSION['uid']!=($w['author_user_id']??-1))) {
    $db->prepare("UPDATE works SET views=views+1 WHERE id=:id")->execute([':id'=>$id]);
    $w['views']++;
}

// Comment submission
if ($_SERVER['REQUEST_METHOD']==='POST' && logged_in() && !empty($_POST['comment'])) {
    $db->prepare("INSERT INTO comments(work_id,user_id,content) VALUES(:w,:u,:c)")
       ->execute([':w'=>$id, ':u'=>$_SESSION['uid'], ':c'=>trim($_POST['comment'])]);
    log_activity($_SESSION['uid'], 'comment', "Тавзеҳ ба кор #$id");
    header('Location: '.url('view.php?id='.$id.'#comments')); exit;
}

// Rating
if (!empty($_POST['rating']) && logged_in()) {
    $stars = max(1, min(5, intval($_POST['rating'])));
    $db->prepare("INSERT INTO ratings(user_id,work_id,stars) VALUES(:u,:w,:s) ON CONFLICT(user_id,work_id) DO UPDATE SET stars=:s2")
       ->execute([':u'=>$_SESSION['uid'], ':w'=>$id, ':s'=>$stars, ':s2'=>$stars]);
    header('Location: '.url('view.php?id='.$id)); exit;
}

// Favorite toggle
if (isset($_GET['fav']) && logged_in()) {
    $exists = $db->prepare("SELECT id FROM favorites WHERE user_id=:u AND work_id=:w");
    $exists->execute([':u'=>$_SESSION['uid'], ':w'=>$id]);
    if ($exists->fetchColumn()) {
        $db->prepare("DELETE FROM favorites WHERE user_id=:u AND work_id=:w")->execute([':u'=>$_SESSION['uid'], ':w'=>$id]);
    } else {
        $db->prepare("INSERT INTO favorites(user_id,work_id) VALUES(:u,:w)")->execute([':u'=>$_SESSION['uid'], ':w'=>$id]);
    }
    header('Location: '.url('view.php?id='.$id)); exit;
}

$comments = $db->prepare("SELECT c.*, u.full_name, u.role FROM comments c JOIN users u ON u.id=c.user_id WHERE c.work_id=:w ORDER BY c.created_at DESC");
$comments->execute([':w'=>$id]);
$comments = $comments->fetchAll();

$avg_st = $db->prepare("SELECT AVG(stars) avg, COUNT(*) cnt FROM ratings WHERE work_id=:w");
$avg_st->execute([':w'=>$id]);
$rating = $avg_st->fetch();

$is_fav = false;
if (logged_in()) {
    $f = $db->prepare("SELECT 1 FROM favorites WHERE user_id=:u AND work_id=:w");
    $f->execute([':u'=>$_SESSION['uid'], ':w'=>$id]);
    $is_fav = (bool)$f->fetchColumn();
}

$page_title = $w['title'];
include 'includes/header.php';
?>

<div class="wrap" style="max-width:980px">
  <div class="breadcrumb">
    <a href="<?= url('index.php') ?>">Асосӣ</a><span>›</span>
    <a href="<?= url('works.php') ?>">Архив</a><span>›</span>
    <?= e(mb_strimwidth($w['title'],0,60,'…')) ?>
  </div>

  <?php if($w['status']==='pending'): ?><div class="alert alert-warn">Кор дар интизори тасдиқ аст</div><?php endif; ?>
  <?php if($w['status']==='rejected'): ?>
  <div class="alert alert-err">Кор рад карда шудааст<?= $w['rejection_reason']?': '.e($w['rejection_reason']):'' ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-h">
      <span><?= work_type_label($w['type']) ?></span>
      <span style="margin-left:auto"><span class="badge b-<?= $w['status'] ?>"><?= status_label($w['status']) ?></span></span>
    </div>
    <div class="card-b">
      <h1 style="font-family:var(--font-head);font-size:24px;color:var(--blue-dark);margin-bottom:14px;line-height:1.3"><?= e($w['title']) ?></h1>
      
      <div class="info-grid">
        <div class="it"><div class="lbl">Муаллиф</div><div class="val"><?= e($w['student_name'] ?? '—') ?></div></div>
        <?php if($w['teacher_name']): ?>
        <div class="it"><div class="lbl">Роҳбари илмӣ</div><div class="val"><?= e($w['teacher_name']) ?></div></div>
        <?php endif; ?>
        <?php if($w['faculty_name']): ?>
        <div class="it"><div class="lbl">Факулта</div><div class="val"><?= e($w['faculty_name']) ?></div></div>
        <?php endif; ?>
        <?php if($w['group_name']): ?>
        <div class="it"><div class="lbl">Гурӯҳ</div><div class="val"><?= e($w['group_name']) ?></div></div>
        <?php endif; ?>
        <div class="it"><div class="lbl">Сол</div><div class="val"><?= $w['year'] ?></div></div>
        <?php if($w['subject']): ?>
        <div class="it"><div class="lbl">Мавзӯъ</div><div class="val"><?= e($w['subject']) ?></div></div>
        <?php endif; ?>
        <?php if($w['grade']): ?>
        <div class="it"><div class="lbl">Баҳо</div><div class="val"><?= e($w['grade']) ?></div></div>
        <?php endif; ?>
        <div class="it"><div class="lbl">Дидан</div><div class="val"><?= $w['views'] ?></div></div>
        <div class="it"><div class="lbl">Зеркашӣ</div><div class="val"><?= $w['downloads'] ?></div></div>
      </div>

      <?php if($w['keywords']): ?>
      <div style="margin-top:14px">
        <div style="font-size:12px;text-transform:uppercase;color:var(--gray-500);margin-bottom:8px;letter-spacing:.5px">Калидвожаҳо</div>
        <?php foreach(explode(',',$w['keywords']) as $kw): ?>
        <a href="<?= url('works.php?q='.urlencode(trim($kw))) ?>" style="display:inline-block;padding:4px 12px;background:var(--blue-bg);border:1px solid var(--blue-light);font-size:12px;color:var(--blue-dark);margin:2px;text-decoration:none"><?= e(trim($kw)) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if($w['description']): ?>
      <div style="margin-top:20px;padding:18px;background:var(--gray-50);border-left:4px solid var(--blue)">
        <div style="font-size:12px;text-transform:uppercase;color:var(--gray-500);margin-bottom:8px;font-weight:600">Аннотатсия</div>
        <p style="line-height:1.7"><?= nl2br(e($w['description'])) ?></p>
      </div>
      <?php endif; ?>

      <?php if($w['status']==='approved' && logged_in()): ?>
      <div style="margin-top:20px;padding:14px;border:1px solid var(--gray-200)">
        <div style="font-size:12px;text-transform:uppercase;color:var(--gray-500);margin-bottom:8px;font-weight:600">Реитинги шумо</div>
        <form method="POST" style="display:inline-flex;gap:6px">
          <?php for($s=1;$s<=5;$s++): ?>
          <button type="submit" name="rating" value="<?= $s ?>" style="background:none;border:none;font-size:24px;color:var(--gold);cursor:pointer;padding:0">★</button>
          <?php endfor; ?>
        </form>
        <span style="margin-left:14px;color:var(--gray-500);font-size:13px">
          Реитинги миёна: <?= $rating['avg']?number_format($rating['avg'],1):'—' ?> (<?= $rating['cnt'] ?> овоз)
        </span>
      </div>
      <?php endif; ?>

      <div style="display:flex;gap:10px;margin-top:24px;flex-wrap:wrap;padding-top:20px;border-top:2px solid var(--gray-200)">
        <?php if($w['file_path'] && $w['status']==='approved'): ?>
        <a href="<?= url('download.php?id='.$id) ?>" class="btn btn-pri btn-lg">Зеркашии файл (<?= filesize_human($w['file_size']) ?>)</a>
        <?php endif; ?>
        <?php if(logged_in()): ?>
        <a href="<?= url('view.php?id='.$id.'&fav=1') ?>" class="btn <?= $is_fav?'btn-dark':'btn-out' ?>"><?= $is_fav?'★ Дилхоҳ':'☆ Илова ба дилхоҳ' ?></a>
        <?php endif; ?>
        <?php if(can_review() && $w['status']==='pending'): ?>
        <a href="<?= url('admin.php?do=approve&id='.$id) ?>" class="btn btn-success" onclick="return confirm('Тасдиқ?')">Тасдиқ</a>
        <a href="<?= url('admin.php?do=reject&id='.$id) ?>" class="btn btn-danger" onclick="return confirm('Рад?')">Рад</a>
        <?php endif; ?>
        <a href="<?= url('works.php') ?>" class="btn btn-ghost">← Бозгашт</a>
      </div>
    </div>
  </div>

  <div class="card" id="comments">
    <div class="card-h">Тавзеҳот (<?= count($comments) ?>)</div>
    <div class="card-b">
      <?php if(logged_in()): ?>
      <form method="POST" style="margin-bottom:20px">
        <textarea name="comment" class="fc" rows="3" placeholder="Тавзеҳи худро нависед..." required></textarea>
        <button class="btn btn-pri" style="margin-top:10px">Илова кардан</button>
      </form>
      <?php else: ?>
      <div class="alert alert-info">Барои гузоштани тавзеҳ <a href="<?= url('login.php') ?>">дохил шавед</a></div>
      <?php endif; ?>

      <?php if(empty($comments)): ?>
      <p style="color:var(--gray-500);text-align:center;padding:20px">Ҳанӯз тавзеҳ нест</p>
      <?php else: foreach($comments as $c): ?>
      <div class="comment">
        <div class="h">
          <b><?= e($c['full_name']) ?> <span class="badge b-<?= e($c['role']) ?>" style="margin-left:6px"><?= e($c['role']) ?></span></b>
          <time><?= time_ago($c['created_at']) ?></time>
        </div>
        <p><?= nl2br(e($c['content'])) ?></p>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
