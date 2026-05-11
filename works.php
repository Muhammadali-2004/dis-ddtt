<?php
require_once __DIR__ . '/includes/auth.php';
$page_title = t('works.title');
$db = db();

$q    = trim($_GET['q'] ?? '');
$typ  = trim($_GET['typ'] ?? '');
$yr   = intval($_GET['yr'] ?? 0);
$fac  = intval($_GET['fac'] ?? 0);
$spec = intval($_GET['spec'] ?? 0);
$mine = !empty($_GET['mine']) && logged_in();
$sort = in_array($_GET['s'] ?? '', ['new','old','views','dl']) ? $_GET['s'] : 'new';
$page = max(1, intval($_GET['p'] ?? 1));
$per  = 10;

$where = [];
$params = [];
if ($mine) {
    $where[] = "s.user_id = :cur_uid";
    $params[':cur_uid'] = (int)$_SESSION['uid'];
} else {
    $where[] = "w.status='approved'";
}

if ($q) {
    $where[] = "(LOWER(w.title) LIKE LOWER(:q) OR LOWER(w.subject) LIKE LOWER(:q2) OR LOWER(w.keywords) LIKE LOWER(:q3) OR LOWER(u.full_name) LIKE LOWER(:q4))";
    $params[':q'] = "%$q%"; $params[':q2'] = "%$q%"; $params[':q3'] = "%$q%"; $params[':q4'] = "%$q%";
}
if ($typ)  { $where[] = "w.type=:typ"; $params[':typ'] = $typ; }
if ($yr)   { $where[] = "w.year=:yr"; $params[':yr'] = $yr; }
if ($fac)  { $where[] = "s.faculty_id=:fac"; $params[':fac'] = $fac; }
if ($spec) { $where[] = "s.specialty_id=:spec"; $params[':spec'] = $spec; }

$faculties   = $db->query("SELECT * FROM faculties ORDER BY name")->fetchAll();
$specialties = $db->query("SELECT * FROM specialties ORDER BY name")->fetchAll();

$wsql = implode(' AND ', $where);
$order = match($sort) {
    'old' => 'w.created_at ASC',
    'views' => 'w.views DESC',
    'dl' => 'w.downloads DESC',
    default => 'w.created_at DESC'
};

$cnt_stmt = $db->prepare("
    SELECT COUNT(*) FROM works w
    LEFT JOIN students s ON s.id=w.student_id
    LEFT JOIN users u ON u.id=s.user_id
    WHERE $wsql
");
$cnt_stmt->execute($params);
$total = (int)$cnt_stmt->fetchColumn();
$pages = max(1, ceil($total/$per));
$page = min($page, $pages);
$offset = ($page-1)*$per;

$stmt = $db->prepare("
    SELECT w.*, u.full_name AS student_name, t_user.full_name AS teacher_name
    FROM works w
    LEFT JOIN students s ON s.id=w.student_id
    LEFT JOIN users u ON u.id=s.user_id
    LEFT JOIN teachers t ON t.id=w.teacher_id
    LEFT JOIN users t_user ON t_user.id=t.user_id
    WHERE $wsql
    ORDER BY $order
    LIMIT :lim OFFSET :off
");
foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $per, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$works = $stmt->fetchAll();

$years_sql = $mine
    ? "SELECT DISTINCT w.year FROM works w JOIN students s ON s.id=w.student_id WHERE s.user_id=".(int)$_SESSION['uid']." ORDER BY w.year DESC"
    : "SELECT DISTINCT year FROM works WHERE status='approved' ORDER BY year DESC";
$years = $db->query($years_sql)->fetchAll(PDO::FETCH_COLUMN);

function qurl($extra=[]) {
    $p = array_merge($_GET, $extra);
    unset($p['p']);
    return url('works.php?'.http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== '0')));
}

include 'includes/header.php';
?>

<div class="wrap">
  <div class="page-title">
    <div>
      <h2><?= e($mine ? t('works.title.mine') : t('works.title')) ?></h2>
      <p><?= e(t($mine ? 'works.count_mine' : 'works.count_one', ['n'=>number_format($total)])) ?></p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?php if (logged_in()): ?>
        <a href="<?= url('works.php') ?>" class="btn <?= $mine?'btn-out':'btn-dark' ?> btn-sm"><?= e(t('works.tab.all')) ?></a>
        <a href="<?= url('works.php?mine=1') ?>" class="btn <?= $mine?'btn-dark':'btn-out' ?> btn-sm"><?= e(t('works.tab.mine')) ?></a>
        <a href="<?= url('upload.php') ?>" class="btn btn-pri"><?= e(t('btn.new_work')) ?></a>
      <?php endif; ?>
    </div>
  </div>

  <div class="search-panel">
    <form method="GET" action="<?= url('works.php') ?>">
      <?php if ($mine): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
      <div class="search-grid">
        <div class="fg" style="margin:0">
          <label><?= e(t('works.search')) ?></label>
          <input type="text" name="q" class="fc" value="<?= e($q) ?>" placeholder="<?= e(t('works.search.placeholder')) ?>">
        </div>
        <div class="fg" style="margin:0">
          <label><?= e(t('works.type')) ?></label>
          <select name="typ" class="fc">
            <option value=""><?= e(t('work.type.any')) ?></option>
            <option value="курсовая" <?= $typ==='курсовая'?'selected':'' ?>><?= e(t('work.type.course')) ?></option>
            <option value="дипломная" <?= $typ==='дипломная'?'selected':'' ?>><?= e(t('work.type.diploma')) ?></option>
            <option value="магистр" <?= $typ==='магистр'?'selected':'' ?>><?= e(t('work.type.master')) ?></option>
            <option value="мақола" <?= $typ==='мақола'?'selected':'' ?>><?= e(t('work.type.article')) ?></option>
            <option value="реферат" <?= $typ==='реферат'?'selected':'' ?>><?= e(t('work.type.referat')) ?></option>
          </select>
        </div>
        <div class="fg" style="margin:0">
          <label><?= e(t('works.year')) ?></label>
          <select name="yr" class="fc">
            <option value=""><?= e(t('work.type.any')) ?></option>
            <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= $yr==$y?'selected':'' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg" style="margin:0">
          <label><?= e(t('works.faculty')) ?></label>
          <select name="fac" class="fc" id="fac_sel">
            <option value=""><?= e(t('work.type.any')) ?></option>
            <?php foreach ($faculties as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $fac==$f['id']?'selected':'' ?>><?= e($f['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg" style="margin:0">
          <label><?= e(t('works.specialty')) ?></label>
          <select name="spec" class="fc" id="spec_sel">
            <option value=""><?= e(t('work.type.any')) ?></option>
            <?php foreach ($specialties as $sp): ?>
            <option value="<?= $sp['id'] ?>" <?= $spec==$sp['id']?'selected':'' ?> data-fac="<?= $sp['faculty_id'] ?>"><?= e($sp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg" style="margin:0">
          <label><?= e(t('works.order')) ?></label>
          <select name="s" class="fc">
            <option value="new" <?= $sort==='new'?'selected':'' ?>><?= e(t('works.sort.new')) ?></option>
            <option value="old" <?= $sort==='old'?'selected':'' ?>><?= e(t('works.sort.old')) ?></option>
            <option value="views" <?= $sort==='views'?'selected':'' ?>><?= e(t('works.sort.views')) ?></option>
            <option value="dl" <?= $sort==='dl'?'selected':'' ?>><?= e(t('works.sort.dl')) ?></option>
          </select>
        </div>
        <div class="fg" style="margin:0;display:flex;gap:8px">
          <button type="submit" class="btn btn-pri" style="flex:1"><?= e(t('btn.search')) ?></button>
          <?php if ($q||$typ||$yr||$fac||$spec): ?>
          <a href="<?= url('works.php'.($mine?'?mine=1':'')) ?>" class="btn btn-ghost">×</a>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <?php if (empty($works)): ?>
  <div class="empty">
    <div class="ic">❍</div>
    <h3><?= e(t('works.empty.title')) ?></h3>
    <p><?= e(t('works.empty.lead')) ?></p>
  </div>
  <?php else: ?>
  <div class="works-list">
    <?php foreach ($works as $i=>$w): ?>
    <div class="work-item">
      <div class="num"><?= str_pad($offset+$i+1, 2, '0', STR_PAD_LEFT) ?></div>
      <div class="body">
        <h3><a href="<?= url('view.php?id='.$w['id']) ?>"><?= e($w['title']) ?></a></h3>
        <div class="meta">
          <span><?= e(t('works.item.author')) ?>: <strong><?= e($w['student_name'] ?? '—') ?></strong></span>
          <?php if ($w['teacher_name']): ?>
          <span><?= e(t('works.item.teacher')) ?>: <?= e($w['teacher_name']) ?></span>
          <?php endif; ?>
          <span><?= e(t('works.item.year')) ?> <?= $w['year'] ?></span>
          <span><?= work_type_label($w['type']) ?></span>
          <?php if ($w['subject']): ?>
          <span><?= e(t('works.item.subject')) ?>: <?= e($w['subject']) ?></span>
          <?php endif; ?>
          <span><?= $w['views'] ?> <?= e(t('works.item.views')) ?></span>
          <span><?= $w['downloads'] ?> <?= e(t('works.item.downloads')) ?></span>
        </div>
      </div>
      <div class="right">
        <span class="badge b-<?= e($w['status']) ?>"><?= e(status_label($w['status'])) ?></span>
        <a href="<?= url('view.php?id='.$w['id']) ?>" class="btn btn-pri btn-sm"><?= e(t('btn.view')) ?></a>
        <?php if ($w['file_path'] && ($w['status']==='approved' || $mine || can_review())): ?>
        <a href="<?= url('download.php?id='.$w['id']) ?>" class="btn btn-out btn-sm"><?= e(t('btn.download')) ?></a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pager">
    <?php if ($page > 1): ?>
    <a href="<?= qurl(['p'=>$page-1]) ?>">‹</a>
    <?php endif; ?>
    <?php for ($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
      <?php if ($i==$page): ?>
      <span class="cur"><?= $i ?></span>
      <?php else: ?>
      <a href="<?= qurl(['p'=>$i]) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?>
    <a href="<?= qurl(['p'=>$page+1]) ?>">›</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<script>
(function(){
  var fac=document.getElementById('fac_sel'), spec=document.getElementById('spec_sel');
  if(!fac||!spec) return;
  var opts=Array.from(spec.options);
  fac.addEventListener('change', function(){
    var val=this.value;
    spec.innerHTML='';
    opts.forEach(function(o){
      if(!val || !o.dataset.fac || o.dataset.fac===val || o.value==='') spec.appendChild(o.cloneNode(true));
    });
  });
})();
</script>
<?php include 'includes/footer.php'; ?>
