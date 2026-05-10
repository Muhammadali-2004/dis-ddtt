<?php
require_once __DIR__ . '/includes/auth.php';
require_review();
$page_title = 'Панели идора';
$db = db();
$tab = $_GET['tab']??'dash';
$msg = $_GET['msg']??'';

// === ACTIONS ===
if (!empty($_GET['do']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);
    switch($_GET['do']) {
        case 'approve':
            $db->prepare("UPDATE works SET status='approved',approved_at=NOW(),approved_by=:u WHERE id=:i")
               ->execute([':u'=>$_SESSION['uid'], ':i'=>$id]);
            $wr = $db->prepare("SELECT w.title, s.user_id FROM works w LEFT JOIN students s ON s.id=w.student_id WHERE w.id=:i");
            $wr->execute([':i'=>$id]); $wrow = $wr->fetch();
            if ($wrow && $wrow['user_id']) add_notification((int)$wrow['user_id'], $id, 'approved', 'Кори "'.mb_strimwidth($wrow['title'],0,60,'…').'" тасдиқ шуд');
            log_activity($_SESSION['uid'], 'approve', "Тасдиқ кор #$id");
            header('Location: '.url('admin.php?tab=pend&msg=ok')); exit;
        case 'reject':
            $reason = $_GET['reason']??'';
            $db->prepare("UPDATE works SET status='rejected',rejection_reason=:r WHERE id=:i")
               ->execute([':r'=>$reason, ':i'=>$id]);
            $wr = $db->prepare("SELECT w.title, s.user_id FROM works w LEFT JOIN students s ON s.id=w.student_id WHERE w.id=:i");
            $wr->execute([':i'=>$id]); $wrow = $wr->fetch();
            if ($wrow && $wrow['user_id']) add_notification((int)$wrow['user_id'], $id, 'rejected', 'Кори "'.mb_strimwidth($wrow['title'],0,60,'…').'" рад шуд'.($reason?': '.$reason:''));
            log_activity($_SESSION['uid'], 'reject', "Рад кор #$id");
            header('Location: '.url('admin.php?tab=pend&msg=rej')); exit;
        case 'del':
            if (is_admin()) {
                $r = $db->prepare("SELECT file_path FROM works WHERE id=:i");
                $r->execute([':i'=>$id]); $row=$r->fetch();
                if ($row && $row['file_path']) @unlink(UPLOAD_DIR.$row['file_path']);
                $db->prepare("DELETE FROM works WHERE id=:i")->execute([':i'=>$id]);
                log_activity($_SESSION['uid'], 'delete', "Ҳазф кор #$id");
                header('Location: '.url('admin.php?msg=del')); exit;
            }
            break;
        case 'block':
            if (is_admin()) {
                $db->prepare("UPDATE users SET is_active=FALSE WHERE id=:i")->execute([':i'=>$id]);
                header('Location: '.url('admin.php?tab=users')); exit;
            }
            break;
        case 'unblock':
            if (is_admin()) {
                $db->prepare("UPDATE users SET is_active=TRUE WHERE id=:i")->execute([':i'=>$id]);
                header('Location: '.url('admin.php?tab=users')); exit;
            }
            break;
        case 'role':
            if (is_admin()) {
                $new = $_GET['role']??'student';
                if (in_array($new, ['admin','teacher','student'])) {
                    $db->prepare("UPDATE users SET role=:r WHERE id=:i")->execute([':r'=>$new, ':i'=>$id]);
                    log_activity($_SESSION['uid'], 'role_change', "Корбар #$id → $new");
                }
                header('Location: '.url('admin.php?tab=users&msg=role')); exit;
            }
            break;
    }
}

// Bulk approve/reject
if ($_SERVER['REQUEST_METHOD']==='POST' && can_review() && !empty($_POST['bulk_action']) && !empty($_POST['work_ids'])) {
    $ids = array_values(array_filter(array_map('intval', (array)$_POST['work_ids'])));
    $act = in_array($_POST['bulk_action'], ['approve','reject']) ? $_POST['bulk_action'] : '';
    if ($act && $ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($act === 'approve') {
            $db->prepare("UPDATE works SET status='approved',approved_at=NOW(),approved_by=? WHERE id IN ($ph) AND status='pending'")
               ->execute(array_merge([$_SESSION['uid']], $ids));
        } else {
            $db->prepare("UPDATE works SET status='rejected' WHERE id IN ($ph) AND status='pending'")
               ->execute($ids);
        }
        foreach ($ids as $wid) {
            $wr = $db->prepare("SELECT w.title, s.user_id FROM works w LEFT JOIN students s ON s.id=w.student_id WHERE w.id=?");
            $wr->execute([$wid]); $wrow = $wr->fetch();
            if ($wrow && $wrow['user_id']) {
                $msg = $act==='approve'
                    ? 'Кори "'.mb_strimwidth($wrow['title'],0,60,'…').'" тасдиқ шуд'
                    : 'Кори "'.mb_strimwidth($wrow['title'],0,60,'…').'" рад шуд';
                add_notification((int)$wrow['user_id'], $wid, $act==='approve'?'approved':'rejected', $msg);
            }
        }
        log_activity($_SESSION['uid'], 'bulk_'.$act, 'Массово '.count($ids).' кор');
        header('Location: '.url('admin.php?tab=pend&msg='.($act==='approve'?'ok':'rej'))); exit;
    }
}

// Add new user (admin only)
if ($_SERVER['REQUEST_METHOD']==='POST' && is_admin() && !empty($_POST['add_user'])) {
    $name = trim($_POST['name']??'');
    $email = trim($_POST['email']??'');
    $pass = $_POST['pass']??'';
    $role = $_POST['role']??'student';
    
    if ($name && $email && $pass && in_array($role, ['admin','teacher','student'])) {
        $check = $db->prepare("SELECT id FROM users WHERE email=:e");
        $check->execute([':e'=>$email]);
        if (!$check->fetchColumn()) {
            $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO users(full_name,email,password_hash,role) VALUES(:n,:e,:h,:r)")
                   ->execute([':n'=>$name, ':e'=>$email, ':h'=>password_hash($pass, PASSWORD_DEFAULT), ':r'=>$role]);
                $uid = $db->lastInsertId('users_id_seq');
                
                if ($role === 'teacher') {
                    $title = trim($_POST['academic_title']??'');
                    $degree = trim($_POST['academic_degree']??'');
                    $dept = trim($_POST['department']??'');
                    $db->prepare("INSERT INTO teachers(user_id,academic_title,academic_degree,department) VALUES(:u,:t,:d,:de)")
                       ->execute([':u'=>$uid, ':t'=>$title, ':d'=>$degree, ':de'=>$dept]);
                }
                $db->commit();
                log_activity($_SESSION['uid'], 'add_user', "Корбари нав ($role): $name");
                header('Location: '.url('admin.php?tab=users&msg=added')); exit;
            } catch (Exception $e) { $db->rollBack(); }
        }
    }
}

// Stats
$st = [
    'all' => (int)$db->query("SELECT COUNT(*) FROM works")->fetchColumn(),
    'pend' => (int)$db->query("SELECT COUNT(*) FROM works WHERE status='pending'")->fetchColumn(),
    'appr' => (int)$db->query("SELECT COUNT(*) FROM works WHERE status='approved'")->fetchColumn(),
    'rej' => (int)$db->query("SELECT COUNT(*) FROM works WHERE status='rejected'")->fetchColumn(),
    'users' => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'students' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
    'teachers' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn(),
    'admins' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn(),
    'dl' => (int)$db->query("SELECT COALESCE(SUM(downloads),0) FROM works")->fetchColumn(),
    'views' => (int)$db->query("SELECT COALESCE(SUM(views),0) FROM works")->fetchColumn(),
];

// Chart data: works by year
$chart_yr = $db->query("SELECT year, COUNT(*) cnt FROM works WHERE status='approved' GROUP BY year ORDER BY year")->fetchAll();
// Chart by type
$chart_tp = $db->query("SELECT type, COUNT(*) cnt FROM works WHERE status='approved' GROUP BY type")->fetchAll();

include 'includes/header.php';
?>

<div class="wrap">
  <div class="page-title">
    <div><h2>Панели <?= is_admin()?'Идора':'Тафтиш' ?></h2><p>Идоракунӣ ва тафтиши корҳо</p></div>
    <?php if(is_admin()): ?>
    <a href="<?= url('admin.php?tab=users') ?>" class="btn btn-pri">+ Илова кардани корбар</a>
    <?php endif; ?>
  </div>

  <?php if($msg==='ok'): ?><div class="alert alert-ok">Кор тасдиқ шуд</div><?php endif; ?>
  <?php if($msg==='rej'): ?><div class="alert alert-warn">Кор рад шуд</div><?php endif; ?>
  <?php if($msg==='del'): ?><div class="alert alert-err">Кор ҳазф шуд</div><?php endif; ?>
  <?php if($msg==='added'): ?><div class="alert alert-ok">Корбар илова шуд</div><?php endif; ?>
  <?php if($msg==='role'): ?><div class="alert alert-info">Нақш иваз шуд</div><?php endif; ?>

  <div class="layout-sidebar">
    <div class="sidebar">
      <div class="sb-h">Меню</div>
      <ul class="sb-menu">
        <li><a href="<?= url('admin.php?tab=dash') ?>" <?= $tab==='dash'?'class="on"':'' ?>>Умумӣ</a></li>
        <li><a href="<?= url('admin.php?tab=pend') ?>" <?= $tab==='pend'?'class="on"':'' ?>>Интизор<?= $st['pend']>0?'<span class="count">'.$st['pend'].'</span>':'' ?></a></li>
        <li><a href="<?= url('admin.php?tab=all') ?>" <?= $tab==='all'?'class="on"':'' ?>>Ҳамаи корҳо</a></li>
        <?php if(is_admin()): ?>
        <li><a href="<?= url('admin.php?tab=users') ?>" <?= $tab==='users'?'class="on"':'' ?>>Корбарон</a></li>
        <li><a href="<?= url('admin.php?tab=log') ?>" <?= $tab==='log'?'class="on"':'' ?>>Логи фаъолият</a></li>
        <?php endif; ?>
      </ul>
    </div>

    <div>
    
    <?php if($tab==='dash'): ?>
    <!-- Дашборд -->
    <div class="stats">
      <div class="stat"><div class="ic">❖</div><div><div class="val"><?= $st['all'] ?></div><div class="lbl">Ҷамъи корҳо</div></div></div>
      <div class="stat gold"><div class="ic">◷</div><div><div class="val"><?= $st['pend'] ?></div><div class="lbl">Интизор</div></div></div>
      <div class="stat green"><div class="ic">✓</div><div><div class="val"><?= $st['appr'] ?></div><div class="lbl">Тасдиқ</div></div></div>
      <div class="stat red"><div class="ic">⊘</div><div><div class="val"><?= $st['rej'] ?></div><div class="lbl">Рад</div></div></div>
      <div class="stat"><div class="ic">◉</div><div><div class="val"><?= $st['users'] ?></div><div class="lbl">Корбарон</div></div></div>
      <div class="stat green"><div class="ic">⇣</div><div><div class="val"><?= $st['dl'] ?></div><div class="lbl">Зеркашиҳо</div></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
      <div class="card">
        <div class="card-h">Корҳо аз рӯи сол</div>
        <div class="card-b"><canvas id="chart_yr"></canvas></div>
      </div>
      <div class="card">
        <div class="card-h">Корҳо аз рӯи намуд</div>
        <div class="card-b"><canvas id="chart_tp"></canvas></div>
      </div>
    </div>

    <div class="card">
      <div class="card-h">Охирин корҳо</div>
      <div class="card-b">
        <div class="tbl-wrap">
          <table class="dtbl">
            <thead><tr><th>#</th><th>Унвон</th><th>Намуд</th><th>Сол</th><th>Ҳолат</th><th>Сана</th><th>Амал</th></tr></thead>
            <tbody>
              <?php foreach($db->query("SELECT * FROM works ORDER BY created_at DESC LIMIT 10")->fetchAll() as $r): ?>
              <tr>
                <td><?= $r['id'] ?></td>
                <td><?= e(mb_strimwidth($r['title'],0,40,'…')) ?></td>
                <td><?= work_type_label($r['type']) ?></td>
                <td><?= $r['year'] ?></td>
                <td><span class="badge b-<?= $r['status'] ?>"><?= status_label($r['status']) ?></span></td>
                <td><?= date('d.m.Y',strtotime($r['created_at'])) ?></td>
                <td><a href="<?= url('view.php?id='.$r['id']) ?>" class="btn btn-pri btn-sm">Дидан</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    Chart.defaults.font.family = "'Manrope', 'Inter', sans-serif";
    Chart.defaults.color = '#44516e';
    new Chart(chart_yr, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($chart_yr,'year')) ?>,
        datasets:[{label:'Корҳо', data: <?= json_encode(array_column($chart_yr,'cnt')) ?>, backgroundColor:'#25406e', borderRadius:6, borderSkipped:false}]
      },
      options:{plugins:{legend:{display:false}}, responsive:true, scales:{y:{grid:{color:'#eef1f6'},ticks:{font:{size:12}}},x:{grid:{display:false},ticks:{font:{size:12}}}}}
    });
    new Chart(chart_tp, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode(array_map(fn($r)=>$r['type'], $chart_tp)) ?>,
        datasets:[{data: <?= json_encode(array_column($chart_tp,'cnt')) ?>, backgroundColor:['#25406e','#3a578c','#0e1c36','#b08940','#3d7a59','#a94348'], borderColor:'#ffffff', borderWidth:3}]
      },
      options:{plugins:{legend:{position:'bottom',labels:{padding:14,font:{size:12,weight:'500'}}}}}
    });
    </script>

    <?php elseif($tab==='pend'): ?>
    <!-- Корҳои интизор -->
    <div class="card">
      <div class="card-h">Дар интизори тасдиқ (<?= $st['pend'] ?>)</div>
      <div class="card-b">
        <?php $rows = $db->query("SELECT w.*, u.full_name student_name FROM works w LEFT JOIN students s ON s.id=w.student_id LEFT JOIN users u ON u.id=s.user_id WHERE w.status='pending' ORDER BY w.created_at ASC")->fetchAll(); ?>
        <?php if(empty($rows)): ?>
        <div class="empty"><div class="ic">❖</div><h3>Ҳама тасдиқ шудааст!</h3></div>
        <?php else: ?>
        <form method="POST">
          <div style="display:flex;gap:10px;margin-bottom:14px;align-items:center;padding:12px;background:var(--gray-50);border:1px solid var(--gray-200)">
            <label style="font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;font-weight:600">
              <input type="checkbox" id="chk_all" style="width:16px;height:16px"> Ҳамаро интихоб
            </label>
            <button type="submit" name="bulk_action" value="approve" class="btn btn-success btn-sm" onclick="return confirm('Ҳамаи интихобшуда тасдиқ шавад?')">✓ Тасдиқи массовӣ</button>
            <button type="submit" name="bulk_action" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('Ҳамаи интихобшуда рад шавад?')">× Ради массовӣ</button>
          </div>
          <div class="works-list">
            <?php foreach($rows as $i=>$r): ?>
            <div class="work-item">
              <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;width:60px">
                <input type="checkbox" name="work_ids[]" value="<?= $r['id'] ?>" class="work-chk" style="width:18px;height:18px;cursor:pointer">
                <span class="num" style="width:auto"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></span>
              </div>
              <div class="body">
                <h3><a href="<?= url('view.php?id='.$r['id']) ?>"><?= e($r['title']) ?></a></h3>
                <div class="meta">
                  <span>Муаллиф: <?= e($r['student_name'] ?? '—') ?></span>
                  <span><?= work_type_label($r['type']) ?></span>
                  <span>Соли <?= $r['year'] ?></span>
                  <span><?= time_ago($r['created_at']) ?></span>
                </div>
              </div>
              <div class="right">
                <a href="<?= url('admin.php?do=approve&id='.$r['id']) ?>" class="btn btn-success btn-sm" onclick="return confirm('Тасдиқ?')">Тасдиқ</a>
                <a href="<?= url('admin.php?do=reject&id='.$r['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Рад?')">Рад</a>
                <a href="<?= url('view.php?id='.$r['id']) ?>" class="btn btn-out btn-sm">Дидан</a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </form>
        <script>
        document.getElementById('chk_all').addEventListener('change',function(){
          document.querySelectorAll('.work-chk').forEach(function(c){c.checked=this.checked;},this);
        });
        </script>
        <?php endif; ?>
      </div>
    </div>

    <?php elseif($tab==='all'): ?>
    <!-- Ҳамаи корҳо -->
    <div class="card">
      <div class="card-h">Ҳамаи корҳо (<?= $st['all'] ?>)</div>
      <div class="card-b">
        <div class="tbl-wrap">
          <table class="dtbl">
            <thead><tr><th>ID</th><th>Унвон</th><th>Муаллиф</th><th>Намуд</th><th>Сол</th><th>Ҳолат</th><th>Дидан</th><th>Зеркашӣ</th><th>Амал</th></tr></thead>
            <tbody>
              <?php foreach($db->query("SELECT w.*, u.full_name AS author FROM works w LEFT JOIN students s ON s.id=w.student_id LEFT JOIN users u ON u.id=s.user_id ORDER BY w.created_at DESC LIMIT 100")->fetchAll() as $r): ?>
              <tr>
                <td><?= $r['id'] ?></td>
                <td><?= e(mb_strimwidth($r['title'],0,40,'…')) ?></td>
                <td><?= e($r['author']??'—') ?></td>
                <td><?= work_type_label($r['type']) ?></td>
                <td><?= $r['year'] ?></td>
                <td><span class="badge b-<?= $r['status'] ?>"><?= status_label($r['status']) ?></span></td>
                <td><?= $r['views'] ?></td>
                <td><?= $r['downloads'] ?></td>
                <td>
                  <a href="<?= url('view.php?id='.$r['id']) ?>" class="btn btn-pri btn-sm">Дидан</a>
                  <?php if(is_admin()): ?>
                  <a href="<?= url('admin.php?do=del&id='.$r['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Ҳазф?')">Ҳазф</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php elseif($tab==='users' && is_admin()): ?>
    <!-- Корбарон -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-h">Илова кардани корбари нав</div>
      <div class="card-b">
        <form method="POST">
          <input type="hidden" name="add_user" value="1">
          <div class="fgrid">
            <div class="fg">
              <label>Ном ва насаб <span class="req">*</span></label>
              <input name="name" class="fc" required>
            </div>
            <div class="fg">
              <label>Email <span class="req">*</span></label>
              <input type="email" name="email" class="fc" required>
            </div>
            <div class="fg">
              <label>Парол <span class="req">*</span></label>
              <input type="password" name="pass" class="fc" required>
            </div>
            <div class="fg">
              <label>Нақш <span class="req">*</span></label>
              <select name="role" class="fc" id="newrole" onchange="document.getElementById('teacher_fields').style.display=this.value==='teacher'?'contents':'none'">
                <option value="student">Донишҷӯ</option>
                <option value="teacher">Омӯзгор</option>
                <option value="admin">Администратор</option>
              </select>
            </div>
            <span id="teacher_fields" style="display:none">
              <div class="fg">
                <label>Унвони илмӣ</label>
                <input name="academic_title" class="fc" placeholder="Дотсент, профессор...">
              </div>
              <div class="fg">
                <label>Дараҷаи илмӣ</label>
                <input name="academic_degree" class="fc" placeholder="Номзади илм...">
              </div>
              <div class="fg fcol">
                <label>Кафедра / факулта</label>
                <input name="department" class="fc">
              </div>
            </span>
          </div>
          <button class="btn btn-pri" style="margin-top:12px">+ Илова кардан</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-h">Корбарон (<?= $st['users'] ?>: <?= $st['admins'] ?> admin, <?= $st['teachers'] ?> teacher, <?= $st['students'] ?> student)</div>
      <div class="card-b">
        <div class="tbl-wrap">
          <table class="dtbl">
            <thead><tr><th>ID</th><th>Ном</th><th>Email</th><th>Нақш</th><th>Ҳолат</th><th>Сана</th><th>Амал</th></tr></thead>
            <tbody>
              <?php foreach($db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll() as $u): ?>
              <tr>
                <td><?= $u['id'] ?></td>
                <td><?= e($u['full_name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td>
                  <span class="badge b-<?= e($u['role']) ?>"><?= e($u['role']) ?></span>
                </td>
                <td><?= $u['is_active']?'<span class="badge b-approved">Фаъол</span>':'<span class="badge b-rejected">Блок</span>' ?></td>
                <td><?= date('d.m.Y',strtotime($u['created_at'])) ?></td>
                <td>
                  <?php if($u['id']!=$_SESSION['uid']): ?>
                  <select onchange="if(this.value)location.href='<?= url('admin.php') ?>?do=role&id=<?= $u['id'] ?>&role='+this.value" class="fc" style="padding:4px 8px;font-size:12px;display:inline-block;width:auto;margin-right:4px">
                    <option value="">Иваз...</option>
                    <option value="admin" <?= $u['role']==='admin'?'disabled':'' ?>>Admin</option>
                    <option value="teacher" <?= $u['role']==='teacher'?'disabled':'' ?>>Teacher</option>
                    <option value="student" <?= $u['role']==='student'?'disabled':'' ?>>Student</option>
                  </select>
                  <?php if($u['is_active']): ?>
                  <a href="<?= url('admin.php?do=block&id='.$u['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Блок?')">Блок</a>
                  <?php else: ?>
                  <a href="<?= url('admin.php?do=unblock&id='.$u['id']) ?>" class="btn btn-success btn-sm">Фаъол</a>
                  <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php elseif($tab==='log' && is_admin()): ?>
    <div class="card">
      <div class="card-h">Логи фаъолияти охирин</div>
      <div class="card-b">
        <div class="tbl-wrap">
          <table class="dtbl">
            <thead><tr><th>Сана</th><th>Корбар</th><th>Амал</th><th>Тавзеҳ</th></tr></thead>
            <tbody>
              <?php foreach($db->query("SELECT a.*, u.full_name FROM activity_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 100")->fetchAll() as $l): ?>
              <tr>
                <td><?= date('d.m H:i',strtotime($l['created_at'])) ?></td>
                <td><?= e($l['full_name']??'—') ?></td>
                <td><span class="badge b-admin"><?= e($l['action']) ?></span></td>
                <td><?= e($l['description']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
