<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/config.php';

function logged_in(): bool { return !empty($_SESSION['uid']); }
function is_admin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }
function is_teacher(): bool { return ($_SESSION['role'] ?? '') === 'teacher'; }
function is_student(): bool { return ($_SESSION['role'] ?? '') === 'student'; }
function can_review(): bool { return in_array($_SESSION['role'] ?? '', ['admin','teacher']); }

function current_user(): ?array {
    if (!logged_in()) return null;
    return [
        'id'    => $_SESSION['uid'],
        'name'  => $_SESSION['uname'],
        'role'  => $_SESSION['role'],
        'email' => $_SESSION['email'] ?? ''
    ];
}

function require_login(): void {
    if (!logged_in()) { header('Location: '.url('login.php')); exit; }
}

function require_admin(): void {
    if (!is_admin()) { header('Location: '.url('index.php')); exit; }
}

function require_review(): void {
    if (!can_review()) { header('Location: '.url('index.php')); exit; }
}
