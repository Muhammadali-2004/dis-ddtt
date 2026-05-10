<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title = 'Профил';
$db = db();
$uid = $_SESSION['uid'];
$err=''; $ok='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name = trim($_POST['name']??'');
    $phone = trim($_POST['phone']??'');
    $pass = $_POST['pass']??'';
    
    if (!$name) $err='Ном ҳатмист';
    else {
        if ($pass && strlen($pass)<6) $err='Парол аз 6 ҳарф кам';
        else {
            if ($pass) {
                $db->prepare("UPDATE users SET full_name=:n,phone=:p,password_hash=:h WHERE id=:i")
                   ->execute([':n'=>$name, ':p'=>$phone, ':h'=>password_hash($pass,PASSWORD_DEFAULT), ':i'=>$uid]);
            } else {
                $db->prepare("UPDATE users SET full_name=:n,phone=:p WHERE id=:i")->execute([':n'=>$name, ':p'=>$phone, ':i'=>$uid]);
            }
            $_SESSION['uname']=$name;
            log_activity($uid, 'profile_update', '');
            $ok='Профил нав шуд';
        }
    }
}

$st = $db->prepare("SELECT * FROM users WHERE id=:i");
$st->execute([':i'=>$uid]); $user = $st->fetch();

// Корҳо аз рӯи нақш
if (is_student()) {
    $sid = $db->prepare("SELECT id FROM students WHERE user_id=:u");
    $sid->execute([':u'=>$uid]); $sid = $sid->fetchColumn();
    $works = $db->prepare("SELECT * FROM works WHERE student_id=:s ORDER BY created_at DESC");
    $works->execute([':s'=>$sid]); $my_works = $works->fetchAll();
} elseif (is_teacher()) {
    $tid = $db->prepare("SELECT id FROM teachers WHERE user_id=:u");
    $tid->execute([':u'=>$uid]); $tid = $tid->fetchColumn();
    $works = $db->prepare("SELECT w.*, u.full_name student_name FROM works w LEFT JOIN students s ON s.id=w.student_id LEFT JOIN users u ON u.id=s.user_id WHERE w.teacher_id=:t ORDER BY w.created_at DESC");
    $works->execute([':t'=>$tid]); $my_works = $works->fetchAll();
} else { $my_works = []; }

// Дилхоҳ
$favs = $db->prepare("SELECT w.* FROM favorites f JOIN works w ON w.id=f.work_id WHERE f.user_id=:u ORDER BY f.created_at DESC LIMIT 10");
$favs->execute([':u'=>$uid]);
$favorites = $favs->fetchAll();

// Огоҳиномаҳо
$notifs_stmt = $db->prepare("SELECT * FROM notifications WHERE user_id=:u ORDER BY created_at DESC LIMIT 20");
$notifs_stmt->execute([':u'=>$uid]);
$notifs = $notifs_stmt->fetchAll();
try {
    $db->prepare("UPDATE notifications SET is_read=TRUE WHERE user_id=:u AND is_read=FALSE")->execute([':u'=>$uid]);
} catch (Exception $e) {}

include 'includes/header.php';
?>

<div class="wrap">
  <div class="page-title">
    <div>
      <h2>Профили ман</h2>
      <p><span class="badge b-<?= e($user['role']) ?>"><?= e($user['role']) ?></span> · <?= e($user['email']) ?></p>
    </div>
    <?php if(is_student()): ?>
    <a href="<?= url('upload.php') ?>" class="btn btn-pri">+ Кори нав</a>
    <?php endif; ?>
  </div>

  <?php if($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-ok"><?= e($ok) ?></div><?php endif; ?>

  <?php if(!empty($notifs)): ?>
  <div class="card">
    <div class="card-h">Огоҳиномаҳо (<?= count($notifs) ?>)</div>
    <div class="card-b" style="padding:0">
      <?php foreach($notifs as $n): ?>
      <div class="notif-item<?= !$n['is_read'] ? ' unread' : '' ?>">
        <span class="badge <?= $n['type']==='approved'?'b-approved':'b-rejected' ?>">
          <?= $n['type']==='approved' ? '✓ Тасдиқ' : '× Рад' ?>
        </span>
        <span style="flex:1;font-size:14px"><?= e($n['message']) ?></span>
        <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
          <?php if($n['work_id']): ?>
          <a href="<?= url('view.php?id='.$n['work_id']) ?>" class="btn btn-out btn-sm">Дидан</a>
          <?php endif; ?>
          <time style="color:var(--gray-500);font-size:12px;white-space:nowrap"><?= time_ago($n['created_at']) ?></time>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px">
    <div>
      <div class="card">
        <div class="card-h">Маълумоти шахсӣ</div>
        <div class="card-b">
          <form method="POST">
            <div class="fg">
              <label>Ном ва насаб</label>
              <input name="name" class="fc" value="<?= e($user['full_name']) ?>" required>
            </div>
            <div class="fg">
              <label>Email</label>
              <input class="fc" value="<?= e($user['email']) ?>" disabled>
            </div>
            <div class="fg">
              <label>Телефон</label>
              <input name="phone" class="fc" value="<?= e($user['phone']??'') ?>">
            </div>
            <div class="fg">
              <label>Парол нав (ихтиёрӣ)</label>
              <input type="password" name="pass" class="fc" placeholder="Холӣ — танҳо нав не">
            </div>
            <button class="btn btn-pri btn-block">Нигоҳ доштан</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-h">★ Дилхоҳ (<?= count($favorites) ?>)</div>
        <div class="card-b">
          <?php if(empty($favorites)): ?>
          <p style="color:var(--gray-500);text-align:center">Дилхоҳ нест</p>
          <?php else: foreach($favorites as $f): ?>
          <div style="padding:8px 0;border-bottom:1px solid var(--gray-100)">
            <a href="<?= url('view.php?id='.$f['id']) ?>" style="font-size:13px;font-weight:500"><?= e(mb_strimwidth($f['title'],0,40,'…')) ?></a>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <div>
      <div class="card">
        <div class="card-h">
          <?php if(is_teacher()): ?>Корҳои донишҷӯёни ман<?php else: ?>Корҳои ман<?php endif; ?>
          (<?= count($my_works) ?>)
        </div>
        <div class="card-b">
          <?php if(empty($my_works)): ?>
          <div class="empty"><div class="ic">∅</div><h3>Кор вуҷуд надорад</h3>
          <?php if(is_student()): ?>
          <a href="<?= url('upload.php') ?>" class="btn btn-pri" style="margin-top:14px">+ Кори нав</a>
          <?php endif; ?>
          </div>
          <?php else: ?>
          <div class="tbl-wrap">
            <table class="dtbl">
              <thead><tr><th>Унвон</th><?php if(is_teacher()): ?><th>Донишҷӯ</th><?php endif; ?><th>Намуд</th><th>Сол</th><th>Ҳолат</th><th>Амал</th></tr></thead>
              <tbody>
                <?php foreach($my_works as $w): ?>
                <tr>
                  <td><?= e(mb_strimwidth($w['title'],0,40,'…')) ?></td>
                  <?php if(is_teacher()): ?><td><?= e($w['student_name']??'—') ?></td><?php endif; ?>
                  <td><?= work_type_label($w['type']) ?></td>
                  <td><?= $w['year'] ?></td>
                  <td><span class="badge b-<?= $w['status'] ?>"><?= status_label($w['status']) ?></span></td>
                  <td>
                    <a href="<?= url('view.php?id='.$w['id']) ?>" class="btn btn-pri btn-sm">Дидан</a>
                    <?php if(is_teacher() && $w['status']==='pending'): ?>
                    <a href="<?= url('admin.php?do=approve&id='.$w['id']) ?>" class="btn btn-success btn-sm" onclick="return confirm('Тасдиқ?')">✓</a>
                    <a href="<?= url('admin.php?do=reject&id='.$w['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Рад?')">×</a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
