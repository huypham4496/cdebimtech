<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = realpath(__DIR__ . '/..'); $BASE = $BASE ?? '';
require $ROOT . '/config.php'; require $ROOT . '/includes/permissions.php'; require $ROOT . '/includes/helpers.php'; require $ROOT . '/includes/projects.php';
$uid = $_SESSION['user_id'] ?? 0; if (!$uid) { header('Location: /index.php'); exit; }
$pid = (int)($_POST['project_id'] ?? 0); $embed = trim($_POST['embed'] ?? '');
if (!$pid || $embed==='') { header('Location: ' . $BASE . '/pages/project_view.php?id='.$pid.'&tab=kmz'); exit; }
if (!canManageProject($pdo, $uid, $pid)) { http_response_code(403); exit('No permission'); }
$stm = $pdo->prepare("REPLACE INTO project_kmz(project_id, embed_html) VALUES (:pid,:html)"); $stm->execute([':pid'=>$pid, ':html'=>$embed]);
header('Location: ' . $BASE . '/pages/project_view.php?id='.$pid.'&tab=kmz'); exit;
