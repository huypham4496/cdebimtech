<?php
declare(strict_types=1);

/**
 * includes/helpers.php
 * - userIdOrRedirect(): Lấy user id từ session (dò nhiều key). Nếu chưa login → tự chọn trang login phù hợp.
 * - cde_table_exists(), cde_column_exists(): Helpers an toàn cho schema.
 * - addActivity(): Ghi log vào project_activities (nếu có).
 * - deleteProjectCascade(): Xóa dự án + toàn bộ dữ liệu trong các bảng project_* (dự phòng projects_).
 */

if (!function_exists('userIdOrRedirect')) {
  function userIdOrRedirect(): int {
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }

    // Các key session phổ biến
    $cands = [
      $_SESSION['user_id']         ?? null,
      $_SESSION['id']              ?? null,
      $_SESSION['user']['id']      ?? null,
      $_SESSION['auth']['user_id'] ?? null,
      $_SESSION['auth']['id']      ?? null,
    ];
    foreach ($cands as $v) {
      if (is_numeric($v) && (int)$v > 0) return (int)$v;
    }

    // Không tìm thấy → điều hướng tới trang đăng nhập khả dụng nhất
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $login = '/index.php';
    foreach (['/pages/login.php','/login.php','/index.php'] as $cand) {
      if ($docRoot && is_file($docRoot . $cand)) { $login = $cand; break; }
    }
    header('Location: ' . $login);
    exit;
  }
}

if (!function_exists('cde_table_exists')) {
  function cde_table_exists(PDO $pdo, string $table): bool {
    try {
      $q = $pdo->prepare("
        SELECT COUNT(*)
          FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :t
      ");
      $q->execute([':t' => $table]);
      return (int)$q->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
  }
}

if (!function_exists('cde_column_exists')) {
  function cde_column_exists(PDO $pdo, string $table, string $column): bool {
    try {
      $q = $pdo->prepare("
        SELECT COUNT(*)
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :t
           AND column_name = :c
      ");
      $q->execute([':t' => $table, ':c' => $column]);
      return (int)$q->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
  }
}

if (!function_exists('addActivity')) {
  function addActivity(PDO $pdo, int $projectId, int $userId, string $action, ?string $detail=null): void {
    if (!cde_table_exists($pdo, 'project_activities')) return;
    try {
      $stm = $pdo->prepare("
        INSERT INTO project_activities(project_id,user_id,action,detail,created_at)
        VALUES (:pid,:uid,:ac,:dt,NOW())
      ");
      $stm->execute([
        ':pid' => $projectId,
        ':uid' => $userId,
        ':ac'  => $action,
        ':dt'  => $detail
      ]);
    } catch (Throwable $e) { /* ignore nếu bảng/field không tồn tại */ }
  }
}

/**
 * XÓA DỰ ÁN SÂU:
 * - Xóa các bảng đặc biệt có quan hệ gián tiếp:
 *     + file_versions ← project_files(file_id)
 *     + project_color_items ← project_color_groups(group_id)
 * - Xóa tất cả bảng có cột project_id và tên bắt đầu bằng project_% (dự phòng projects_%)
 * - Xóa project_group_members (thừa kế an toàn)
 * - Cuối cùng xóa bản ghi trong projects
 * - Transaction + tùy chọn tắt FOREIGN_KEY_CHECKS để an toàn
 */
if (!function_exists('deleteProjectCascade')) {
  function deleteProjectCascade(PDO $pdo, int $projectId, bool $disableFKChecks = true): void {
    if ($projectId <= 0) { throw new InvalidArgumentException('Invalid project id'); }

    $pdo->beginTransaction();
    try {
      if ($disableFKChecks) { $pdo->exec("SET FOREIGN_KEY_CHECKS=0"); }

      // (1) file_versions theo project_files
      if (cde_table_exists($pdo,'file_versions') && cde_table_exists($pdo,'project_files')) {
        $sql = "
          DELETE fv FROM file_versions fv
          JOIN project_files pf ON pf.id = fv.file_id
          WHERE pf.project_id = :pid
        ";
        $pdo->prepare($sql)->execute([':pid'=>$projectId]);
      }

      // (2) project_color_items theo project_color_groups
      if (cde_table_exists($pdo,'project_color_items') && cde_table_exists($pdo,'project_color_groups')) {
        $sql = "
          DELETE pci FROM project_color_items pci
          JOIN project_color_groups pcg ON pcg.id = pci.group_id
          WHERE pcg.project_id = :pid
        ";
        $pdo->prepare($sql)->execute([':pid'=>$projectId]);
      }

      // (3) LẤY DANH SÁCH BẢNG THEO TIỀN TỐ (KHÔNG DÙNG ESCAPE)
      $tables = $pdo->query("
        SELECT TABLE_NAME
          FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND (TABLE_NAME LIKE 'project_%' OR TABLE_NAME LIKE 'projects_%')
      ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

      // Xóa theo project_id nếu có cột
      foreach ($tables as $t) {
        $tn = (string)$t;
        // Bỏ qua bảng đã xử lý đặc biệt ở trên (tránh xóa 2 lần)
        if (in_array($tn, ['file_versions','project_color_items'], true)) continue;
        if (!cde_column_exists($pdo, $tn, 'project_id')) continue;

        $stmt = $pdo->prepare("DELETE FROM `$tn` WHERE project_id = :pid");
        $stmt->execute([':pid' => $projectId]);
      }

      // (4) Xóa thành viên nhóm (thừa kế an toàn)
      if (cde_table_exists($pdo,'project_group_members') && cde_column_exists($pdo,'project_group_members','project_id')) {
        $pdo->prepare("DELETE FROM project_group_members WHERE project_id=:pid")->execute([':pid'=>$projectId]);
      }

      // (5) Xóa dự án chính
      if (cde_table_exists($pdo,'projects')) {
        $pdo->prepare("DELETE FROM projects WHERE id=:pid")->execute([':pid'=>$projectId]);
      }

      if ($disableFKChecks) { $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); }
      $pdo->commit();

      // Log
      try { addActivity($pdo, $projectId, userIdOrRedirect(), 'delete', 'Cascade delete project_*'); } catch (Throwable $e) {}
    } catch (Throwable $e) {
      if ($disableFKChecks) { $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); }
      $pdo->rollBack();
      throw $e;
    }
  }
}
