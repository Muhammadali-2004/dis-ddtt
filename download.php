<?php
require_once __DIR__ . '/includes/auth.php';
$db = db();
$id = intval($_GET['id']??0);
if (!$id) { header('Location: '.url('works.php')); exit; }

$st = $db->prepare("SELECT * FROM works WHERE id=:i AND status='approved'");
$st->execute([':i'=>$id]); $w = $st->fetch();
if (!$w || !$w['file_path']) { header('Location: '.url('works.php')); exit; }

$path = realpath(UPLOAD_DIR . basename($w['file_path']));
$upload_real = realpath(UPLOAD_DIR);
if (!$path || !$upload_real || strncmp($path, $upload_real, strlen($upload_real)) !== 0 || !file_exists($path)) {
    header('Location: '.url('works.php')); exit;
}

$db->prepare("UPDATE works SET downloads=downloads+1 WHERE id=:i")->execute([':i'=>$id]);
$db->prepare("INSERT INTO download_logs(work_id,user_id,ip_address) VALUES(:w,:u,:ip)")
   ->execute([':w'=>$id, ':u'=>$_SESSION['uid']??null, ':ip'=>$_SERVER['REMOTE_ADDR']??'']);
log_activity($_SESSION['uid']??null, 'download', "Кор #$id");

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'application/octet-stream';
$safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $w['file_name']?:$w['file_path']);
header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="'.$safe.'"');
header('Content-Length: '.filesize($path));
readfile($path);
exit;
