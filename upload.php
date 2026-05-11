<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$page_title = t('upload.title');
$db = db();
$err=''; $ok=''; $dup=null;

$teachers = $db->query("SELECT t.id, u.full_name FROM teachers t JOIN users u ON u.id=t.user_id ORDER BY u.full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $title = trim($_POST['title']??'');
    $type = $_POST['type']??'';
    $teacher_id = intval($_POST['teacher_id']??0);
    $subject = trim($_POST['subject']??'');
    $year = intval($_POST['year']??date('Y'));
    $kw = trim($_POST['keywords']??'');
    $desc = trim($_POST['description']??'');
    
    if (!$title||!$type) $err=t('upload.required');
    elseif (empty($_FILES['file']['name'])) $err=t('upload.no_file');
    else {
        $f = $_FILES['file'];
        $ext = strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
        $allowed_exts = [
            'pdf','doc','docx','odt','rtf','txt',
            'xls','xlsx','ods','csv',
            'ppt','pptx','odp',
            'zip','rar','7z','tar','gz',
            'jpg','jpeg','png','gif','bmp','tiff','tif',
            'epub','djvu'
        ];
        if (!in_array($ext, $allowed_exts, true)) $err=t('upload.bad_type');
        elseif ($f['size']>MAX_SIZE) $err=t('upload.too_big');
        else {
            // Find student record
            $sid_st = $db->prepare("SELECT id FROM students WHERE user_id=:u");
            $sid_st->execute([':u'=>$_SESSION['uid']]);
            $sid = $sid_st->fetchColumn();

            // Compute file hash for duplicate detection (SHA-256)
            $file_hash = hash_file('sha256', $f['tmp_name']);

            // Look for an existing work with the same content, excluding the current student's own works
            $dup_st = $db->prepare(
                "SELECT w.id, w.title, w.type, w.year, w.teacher_id,
                        u.full_name AS student_name
                 FROM works w
                 LEFT JOIN students s ON s.id = w.student_id
                 LEFT JOIN users    u ON u.id = s.user_id
                 WHERE w.file_hash = :h
                   AND (w.student_id IS NULL OR w.student_id <> :sid)
                 ORDER BY w.created_at ASC
                 LIMIT 1"
            );
            $dup_st->execute([':h'=>$file_hash, ':sid'=>$sid?:0]);
            $dup = $dup_st->fetch();

            if ($dup) {
                $err = sprintf(
                    'Ин кор қаблан бор карда шудааст: «%s» (%s, соли %d) — муаллиф: %s. Бор кардани такрорӣ манъ аст.',
                    $dup['title'],
                    work_type_label($dup['type']),
                    (int)$dup['year'],
                    $dup['student_name'] ?: 'номаълум'
                );
                log_activity($_SESSION['uid'], 'upload_blocked',
                    "Кӯшиши такрорӣ: «$title» ≡ кори №{$dup['id']}");
                // Notify the supervisor of the original work, if any
                if (!empty($dup['teacher_id'])) {
                    $sup_st = $db->prepare("SELECT user_id FROM teachers WHERE id=:t");
                    $sup_st->execute([':t'=>$dup['teacher_id']]);
                    $sup_uid = (int)$sup_st->fetchColumn();
                    if ($sup_uid) {
                        add_notification(
                            $sup_uid,
                            (int)$dup['id'],
                            'duplicate',
                            'Кӯшиши бори такрории кор аз ҷониби '.($_SESSION['uname'] ?? 'донишҷӯ').': «'.$title.'»'
                        );
                    }
                }
            } else {
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                $fname = uniqid('w_').time().'.'.$ext;
                if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR.$fname)) {
                    $db->prepare("INSERT INTO works(title,student_id,teacher_id,type,subject,year,keywords,description,file_path,file_name,file_size,file_hash,status) VALUES(:t,:sid,:tid,:tp,:s,:y,:k,:d,:fp,:fn,:fs,:fh,'pending')")
                       ->execute([':t'=>$title,':sid'=>$sid?:null,':tid'=>$teacher_id?:null,':tp'=>$type,':s'=>$subject,':y'=>$year,':k'=>$kw,':d'=>$desc,':fp'=>$fname,':fn'=>$f['name'],':fs'=>$f['size'],':fh'=>$file_hash]);
                    log_activity($_SESSION['uid'], 'upload', "Бор кард: $title");
                    $ok = t('upload.success');
                } else $err=t('upload.save_error');
            }
        }
    }
}
include 'includes/header.php';
?>

<div class="wrap" style="max-width:840px">
  <div class="page-title">
    <div><h2><?= e(t('upload.title')) ?></h2><p><?= e(t('upload.subtitle')) ?></p></div>
  </div>

  <?php if($dup): ?>
    <div class="alert alert-err" style="line-height:1.55">
      <b><?= e(t('upload.dup.title')) ?></b><br>
      <?= e(t('upload.dup.lead')) ?>
      <ul style="margin:8px 0 4px 18px">
        <li><b><?= e(t('upload.dup.work_title')) ?>:</b> <?= e($dup['title']) ?></li>
        <li><b><?= e(t('upload.dup.type')) ?>:</b> <?= e(work_type_label($dup['type'])) ?></li>
        <li><b><?= e(t('upload.dup.year')) ?>:</b> <?= (int)$dup['year'] ?></li>
        <li><b><?= e(t('upload.dup.author')) ?>:</b> <?= e($dup['student_name'] ?: t('upload.dup.unknown')) ?></li>
      </ul>
      <?= e(t('upload.dup.footer')) ?>
    </div>
  <?php elseif($err): ?>
    <div class="alert alert-err"><?= e($err) ?></div>
  <?php endif; ?>
  <?php if($ok): ?>
  <div class="alert alert-ok"><?= e($ok) ?> <a href="<?= url('profile.php') ?>" style="margin-left:10px">→ <?= e(t('nav.profile')) ?></a></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-h"><?= e(t('upload.section')) ?></div>
    <div class="card-b">
      <form method="POST" enctype="multipart/form-data">
        <div class="drop-zone" id="dz" onclick="document.getElementById('fi').click()">
          <div class="ic">⇡</div>
          <h4><?= e(t('upload.dz.headline')) ?></h4>
          <p><?= e(t('upload.dz.types')) ?></p>
        </div>
        <input type="file" id="fi" name="file" accept=".pdf,.doc,.docx,.odt,.rtf,.txt,.xls,.xlsx,.ods,.csv,.ppt,.pptx,.odp,.zip,.rar,.7z,.tar,.gz,.jpg,.jpeg,.png,.gif,.bmp,.tiff,.tif,.epub,.djvu" style="display:none">
        <div class="fp" id="fp"><span id="fname"></span></div>

        <div style="margin-top:24px"></div>

        <div class="fg">
          <label><?= e(t('upload.field.title')) ?> <span class="req">*</span></label>
          <input name="title" class="fc" value="<?= e($_POST['title']??'') ?>" required>
        </div>

        <div class="fgrid">
          <div class="fg">
            <label><?= e(t('upload.field.type')) ?> <span class="req">*</span></label>
            <select name="type" class="fc" required>
              <option value=""><?= e(t('upload.field.type.choose')) ?></option>
              <option value="курсовая"><?= e(t('work.type.course')) ?></option>
              <option value="дипломная"><?= e(t('work.type.diploma')) ?></option>
              <option value="магистр"><?= e(t('work.type.master')) ?></option>
              <option value="мақола"><?= e(t('work.type.article')) ?></option>
              <option value="реферат"><?= e(t('work.type.referat')) ?></option>
            </select>
          </div>
          <div class="fg">
            <label><?= e(t('upload.field.year')) ?> <span class="req">*</span></label>
            <select name="year" class="fc" required>
              <?php for($y=date('Y');$y>=2015;$y--): ?>
              <option value="<?= $y ?>" <?= ($_POST['year']??date('Y'))==$y?'selected':'' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="fg fcol">
            <label><?= e(t('upload.field.teacher')) ?></label>
            <select name="teacher_id" class="fc">
              <option value=""><?= e(t('upload.field.teacher.choose')) ?></option>
              <?php foreach($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= e($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg fcol">
            <label><?= e(t('upload.field.subject')) ?></label>
            <input name="subject" class="fc" value="<?= e($_POST['subject']??'') ?>" placeholder="<?= e(t('upload.field.subject.placeholder')) ?>">
          </div>
          <div class="fg fcol">
            <label><?= e(t('upload.field.keywords')) ?></label>
            <input name="keywords" class="fc" value="<?= e($_POST['keywords']??'') ?>" placeholder="<?= e(t('upload.field.keywords.placeholder')) ?>">
          </div>
          <div class="fg fcol">
            <label><?= e(t('upload.field.description')) ?></label>
            <textarea name="description" class="fc" rows="5"><?= e($_POST['description']??'') ?></textarea>
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px">
          <button type="submit" class="btn btn-pri btn-lg" style="flex:1;justify-content:center"><?= e(t('btn.upload')) ?></button>
          <a href="<?= url('works.php') ?>" class="btn btn-ghost btn-lg"><?= e(t('btn.cancel')) ?></a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const dz=document.getElementById('dz'),fi=document.getElementById('fi'),fp=document.getElementById('fp'),fn=document.getElementById('fname');
fi.addEventListener('change',show);
dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('over')});
dz.addEventListener('dragleave',()=>dz.classList.remove('over'));
dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('over');fi.files=e.dataTransfer.files;show()});
function show(){if(!fi.files[0])return;fn.textContent='✓ '+fi.files[0].name+' ('+(fi.files[0].size/1048576).toFixed(1)+' MB)';fp.style.display='block'}
</script>

<?php include 'includes/footer.php'; ?>
