<?php
// api/project_colors.php
// REST-like API cho nhóm màu dự án

header('Content-Type: application/json; charset=utf-8');

try {
    // Sửa đường dẫn include theo hệ thống của bạn
    require_once __DIR__ . '/../includes/db.php'; // ../includes từ /api/
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Không khởi tạo được kết nối CSDL ($pdo).');
    }

    // --- Cấu hình ---
    $TABLE = 'project_color_groups';
    $AUTO_ADD_MISSING_COLOR_COLUMN = true;
    $COLOR_CANDIDATES = ['hex_code','color_hex','color','code','hex'];
    $NAME_CANDIDATES  = ['name','group_name','title','label'];

    // --- Helpers ---
    function detectColumn(PDO $pdo, string $table, array $candidates): ?string {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table`");
        $stmt->execute();
        $cols = array_map(fn($r) => $r['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) return $c;
        }
        return null;
    }

    // Xác định cột NAME & COLOR
    $nameCol  = detectColumn($pdo, $TABLE, $NAME_CANDIDATES);
    if (!$nameCol) {
        // tối thiểu cần cột name để hoạt động; tạo nếu thiếu
        $pdo->exec("ALTER TABLE `$TABLE` ADD `name` VARCHAR(191) NOT NULL AFTER `project_id`");
        $nameCol = 'name';
    }

    $colorCol = detectColumn($pdo, $TABLE, $COLOR_CANDIDATES);
    if (!$colorCol) {
        if ($AUTO_ADD_MISSING_COLOR_COLUMN) {
            $pdo->exec("ALTER TABLE `$TABLE` ADD `hex_code` CHAR(7) NULL AFTER `$nameCol`");
            $colorCol = 'hex_code';
        } else {
            throw new RuntimeException("Không tìm thấy cột màu trong bảng `$TABLE`.");
        }
    }

    // Đảm bảo có cột id & project_id
    $hasId = detectColumn($pdo, $TABLE, ['id']) === 'id';
    $hasProjectId = detectColumn($pdo, $TABLE, ['project_id']) === 'project_id';
    if (!$hasProjectId) {
        throw new RuntimeException("Thiếu cột `project_id` trong bảng `$TABLE`.");
    }
    if (!$hasId) {
        throw new RuntimeException("Thiếu cột `id` (khoá chính) trong bảng `$TABLE`.");
    }

    // Lấy method & project_id
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $projectId = null;

    if ($method === 'GET' || $method === 'DELETE') {
        $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    } else {
        // POST / PUT dữ liệu có thể là JSON
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            // Fallback cho form-encoded
            $data = $_POST;
        }
        $projectId = isset($data['project_id']) ? (int)$data['project_id'] : 0;
        $_PARSED = $data;
    }

    if ($projectId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Thiếu project_id']);
        exit;
    }

    if ($method === 'GET') {
        $search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
        $sql = "SELECT `id`, `$nameCol` AS `name`, `$colorCol` AS `hex_code`
                FROM `$TABLE` WHERE `project_id` = :pid";
        $params = [':pid' => $projectId];
        if ($search !== '') {
            $sql .= " AND `$nameCol` LIKE :kw";
            $params[':kw'] = '%'.$search.'%';
        }
        $sql .= " ORDER BY `id` DESC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    if ($method === 'POST' || $method === 'PUT') {
        $id   = isset($_PARSED['id']) ? (int)$_PARSED['id'] : 0;
        $name = isset($_PARSED['name']) ? trim((string)$_PARSED['name']) : '';
        // nhận từ input type=color hoặc ô text: ưu tiên value hợp lệ
        $hex  = isset($_PARSED['hex_code']) ? trim((string)$_PARSED['hex_code']) : '';
        if ($hex === '' && isset($_PARSED['hex_code_text'])) {
            $hex = trim((string)$_PARSED['hex_code_text']);
        }
        if ($hex !== '') {
            if ($hex[0] !== '#') $hex = '#'.$hex;
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => 'Mã màu không hợp lệ.']);
                exit;
            }
        } else {
            $hex = null;
        }
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Vui lòng nhập tên nhóm.']);
            exit;
        }

        if ($id > 0) {
            $sql = "UPDATE `$TABLE` SET `$nameCol` = :n, `$colorCol` = :c WHERE `id` = :id AND `project_id` = :pid";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':n' => $name,
                ':c' => $hex,
                ':id'=> $id,
                ':pid'=> $projectId
            ]);
            echo json_encode(['ok' => true, 'message' => 'Đã cập nhật nhóm màu.']);
            exit;
        } else {
            $sql = "INSERT INTO `$TABLE` (`project_id`, `$nameCol`, `$colorCol`) VALUES (:pid, :n, :c)";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':pid' => $projectId,
                ':n'   => $name,
                ':c'   => $hex
            ]);
            echo json_encode(['ok' => true, 'message' => 'Đã tạo nhóm màu.', 'id' => (int)$pdo->lastInsertId()]);
            exit;
        }
    }

    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Thiếu id để xoá.']);
            exit;
        }
        $st = $pdo->prepare("DELETE FROM `$TABLE` WHERE `id` = :id AND `project_id` = :pid");
        $st->execute([':id' => $id, ':pid' => $projectId]);
        echo json_encode(['ok' => true, 'message' => 'Đã xoá nhóm màu.']);
        exit;
    }

    // Phương thức không hỗ trợ
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Có lỗi xảy ra. ' . $e->getMessage()
    ]);
}
