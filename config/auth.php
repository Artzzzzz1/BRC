<?php
// config/auth.php

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: ../public/login.php');
        exit;
    }
}

function requireUser() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: ../public/login.php');
        exit;
    }
    // Redirect workers/admins/owners to their own dashboards
    $role = $_SESSION['role'] ?? 'customer';
    if ($role === 'admin')  { header('Location: ../adminSide/admin-dashboard.php');   exit; }
    if ($role === 'owner')  { header('Location: ../ownerSide/owner-dashboard.php');   exit; }
    if ($role === 'worker') { header('Location: ../workerSide/worker-dashboard.php'); exit; }
}

function requireAdmin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: ../public/login.php');
        exit;
    }
    // Admin AND owner can access admin pages
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'owner'])) {
        header('Location: ../public/login.php');
        exit;
    }
}

function requireOwner() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: ../public/login.php');
        exit;
    }
    if (($_SESSION['role'] ?? '') !== 'owner') {
        header('Location: ../public/login.php');
        exit;
    }
}

function requireWorker() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: ../public/login.php');
        exit;
    }
    if (!in_array($_SESSION['role'] ?? '', ['worker', 'admin', 'owner'])) {
        header('Location: ../public/login.php');
        exit;
    }
}

function userInitials() {
    $name = $_SESSION['full_name'] ?? '';
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) return strtoupper(substr($parts[0],0,1) . substr($parts[1],0,1));
    return strtoupper(substr($name,0,2));
}

function userId()      { return $_SESSION['user_id'] ?? null; }
function userRole()    { return $_SESSION['role'] ?? 'customer'; }
function userName()    { return $_SESSION['full_name'] ?? ''; }

function isAdmin()  { return in_array($_SESSION['role'] ?? '', ['admin', 'owner']); }
function isOwner()  { return ($_SESSION['role'] ?? '') === 'owner'; }
function isWorker() { return ($_SESSION['role'] ?? '') === 'worker'; }
function currentUserId() { return $_SESSION['user_id'] ?? null; }
function currentRole()   { return $_SESSION['role'] ?? 'customer'; }
