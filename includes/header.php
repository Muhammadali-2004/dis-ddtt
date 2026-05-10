<?php
require_once __DIR__ . '/auth.php';
$cur = basename($_SERVER['PHP_SELF'], '.php');
$u = current_user();
$_notif_count = 0;
if (logged_in()) {
    try {
        $nc = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=:u AND is_read=FALSE");
        $nc->execute([':u'=>$_SESSION['uid']]);
        $_notif_count = (int)$nc->fetchColumn();
    } catch (Exception $e) {}
}
function nav_a($href, $label, $cur, $id) {
    $a = $cur === $id ? ' class="on"' : '';
    return '<a href="'.url($href).'"'.$a.'>'.$label.'</a>';
}
?>
<!DOCTYPE html>
<html lang="tg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($page_title) ? e($page_title).' — ' : '' ?><?= SITE_NAME ?> · ДИС ДДТТ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>

<header class="site-header">
  <div class="hdr-top">
    Донишкадаи Иқтисоди Сохтмон (ДИС) — Донишгоҳи Давлатии Технологии Тоҷикистон
  </div>
  <div class="hdr-row">
    <a href="<?= url('index.php') ?>" class="logo">
      <div class="logo-icon">А</div>
      <div class="logo-text">
        <b>Архиви Корҳои Илмӣ</b>
        <small>Системаи Иттилоотӣ · ДИС ДДТТ</small>
      </div>
    </a>

    <nav class="site-nav">
      <?= nav_a('index.php', 'Асосӣ', $cur, 'index') ?>
      <?= nav_a('works.php', 'Архив', $cur, 'works') ?>
      <?php if (logged_in()): ?>
        <?= nav_a('upload.php', 'Бор кардан', $cur, 'upload') ?>
        <?php if (can_review()): ?>
          <?= nav_a('admin.php', 'Идора', $cur, 'admin') ?>
        <?php endif; ?>
        <span class="user-chip">
          <?= e($u['name']) ?>
          <span class="badge b-<?= e($u['role']) ?>"><?= e($u['role']) ?></span>
        </span>
        <?= nav_a('profile.php', 'Профил'.($_notif_count>0?' <span class="notif-badge">'.$_notif_count.'</span>':''), $cur, 'profile') ?>
        <a href="<?= url('logout.php') ?>" class="btn btn-ghost btn-sm">Хуруҷ</a>
      <?php else: ?>
        <?= nav_a('register.php', 'Қайд', $cur, 'register') ?>
        <a href="<?= url('login.php') ?>" class="nav-cta">Дохил</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="fade-in">
