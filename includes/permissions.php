<?php
/**
 * permissions_merged_single.php
 * 
 * Hợp nhất UI cũ (giao diện + CSS/JS hiển thị thông báo nâng cấp/không đủ quyền)
 * và logic mới (kiểm tra gói từ bảng subscriptions, users, organization_members, organizations).
 * 
 * Bạn chỉ cần include duy nhất file này ở các page cần guard, ví dụ:
 *   require_once __DIR__ . '/includes/permissions.php';
 *   guardWorkDiaryAccess($pdo, (int)$_SESSION['user_id']);
 * 
 * Các hàm chính:
 *   - guardWorkDiaryAccess(PDO $pdo, int $userId): void
 *   - guardOrganizationMembersAccess(PDO $pdo, int $userId): void
 *   - guardOrganizationManageAccess(PDO $pdo, int $userId): void
 *   - getCurrentPlanForUser(PDO $pdo, int $userId): ?array
 *   - getUpgradeTargetPlan(PDO $pdo, int $userId, string $featureColumn): ?array
 *   - getFeaturePlanStatus(PDO $pdo, int $userId, string $featureColumn): array{has:bool, source:string, plan:?array}
 */

// Tùy chọn: nếu TRUE sẽ ném Exception thay vì render UI và exit.
if (!defined('PERMISSIONS_THROW_EXCEPTIONS')) {
    define('PERMISSIONS_THROW_EXCEPTIONS', false);
}

// Cho phép hiển thị chi tiết debug khi cần (đừng bật ở production)
if (!defined('PERMISSIONS_DEBUG')) {
    define('PERMISSIONS_DEBUG', false);
}

if (!class_exists('PermissionDeniedException')) {
    class PermissionDeniedException extends \Exception {}
}

if (!class_exists('Permissions')) {
    final class Permissions
    {
        /** Danh sách cột tính năng được phép kiểm tra (tránh SQL injection qua tên cột) */
        private const ALLOWED_FEATURE_COLUMNS = [
            'allow_work_diary',
            'allow_organization_members',
            'allow_organization_manage',
        ];

        /** Lấy ID gói (subscription_id) đang áp dụng cho user (ưu tiên gói chia sẻ từ tổ chức nếu có) */
        public static function getActiveSubscriptionId(PDO $pdo, int $userId): ?int
        {
            // 1) Kiểm tra xem user có là member trong tổ chức đang bật share_subscription và member đang dùng is_shared=1
            $sqlShared = "
                SELECT om.subscribed_id AS subscribed_id, s.price
                FROM organization_members om
                INNER JOIN organizations o ON o.id = om.organization_id
                INNER JOIN subscriptions s ON s.id = om.subscribed_id
                WHERE om.user_id = :uid
                  AND om.is_shared = 1
                  AND o.share_subscription = 1
                ORDER BY s.price DESC, om.id DESC
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sqlShared);
            $stmt->execute([':uid' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['subscribed_id'])) {
                return (int)$row['subscribed_id'];
            }

            // 2) Nếu không có gói chia sẻ hợp lệ, dùng gói cá nhân của user
            $sqlUser = "SELECT subscription_id FROM users WHERE id = :uid LIMIT 1";
            $stmt = $pdo->prepare($sqlUser);
            $stmt->execute([':uid' => $userId]);
            $subId = $stmt->fetchColumn();

            if ($subId !== false && $subId !== null) {
                return (int)$subId;
            }

            // 3) Không có -> fallback: nếu tồn tại gói Free (id=1) thì dùng, còn không thì trả null
            $sqlFree = "SELECT id FROM subscriptions WHERE id = 1 LIMIT 1";
            $freeId = $pdo->query($sqlFree)->fetchColumn();
            return $freeId ? (int)$freeId : null;
        }

        /** Lấy thông tin chi tiết gói theo ID */
        public static function getPlanById(PDO $pdo, int $subscriptionId): ?array
        {
            $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $subscriptionId]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            return $plan ?: null;
        }

        /** Lấy gói hiện tại của user (bao gồm xác định nguồn từ shared hay cá nhân) */
        public static function getCurrentPlanForUser(PDO $pdo, int $userId): ?array
        {
            $activeId = self::getActiveSubscriptionId($pdo, $userId);
            if ($activeId === null) {
                return null;
            }
            return self::getPlanById($pdo, $activeId);
        }

        /**
         * Kiểm tra user có cột tính năng = 1 hay không
         * @return array{has:bool, source:string, plan:?array}
         *   - has: true/false
         *   - source: 'organization_shared' | 'personal' | 'fallback' | 'none'
         *   - plan: mảng subscriptions hoặc null
         */
        public static function getFeaturePlanStatus(PDO $pdo, int $userId, string $featureColumn): array
        {
            $featureColumn = self::sanitizeFeatureColumn($featureColumn);

            // Thử lấy theo share_subscription trước (lấy gói shared có giá cao nhất)
            $sqlShared = "
                SELECT s.*
                FROM organization_members om
                INNER JOIN organizations o ON o.id = om.organization_id
                INNER JOIN subscriptions s ON s.id = om.subscribed_id
                WHERE om.user_id = :uid
                  AND om.is_shared = 1
                  AND o.share_subscription = 1
                ORDER BY s.price DESC, om.id DESC
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sqlShared);
            $stmt->execute([':uid' => $userId]);
            $sharedPlan = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($sharedPlan) {
                $has = isset($sharedPlan[$featureColumn]) ? (int)$sharedPlan[$featureColumn] === 1 : false;
                return ['has' => $has, 'source' => 'organization_shared', 'plan' => $sharedPlan];
            }

            // Nếu không có shared hợp lệ -> dùng gói cá nhân
            $sqlPersonal = "
                SELECT s.*
                FROM users u
                INNER JOIN subscriptions s ON s.id = u.subscription_id
                WHERE u.id = :uid
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sqlPersonal);
            $stmt->execute([':uid' => $userId]);
            $personalPlan = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($personalPlan) {
                $has = isset($personalPlan[$featureColumn]) ? (int)$personalPlan[$featureColumn] === 1 : false;
                return ['has' => $has, 'source' => 'personal', 'plan' => $personalPlan];
            }

            // Fallback Free (id=1) nếu tồn tại
            $sqlFree = "SELECT * FROM subscriptions WHERE id = 1 LIMIT 1";
            $freePlan = $pdo->query($sqlFree)->fetch(PDO::FETCH_ASSOC);
            if ($freePlan) {
                $has = isset($freePlan[$featureColumn]) ? (int)$freePlan[$featureColumn] === 1 : false;
                return ['has' => $has, 'source' => 'fallback', 'plan' => $freePlan];
            }

            return ['has' => false, 'source' => 'none', 'plan' => null];
        }

        /** Lấy gói mục tiêu để nâng cấp nhằm mở khóa 1 tính năng nhất định */
        public static function getUpgradeTargetPlan(PDO $pdo, int $userId, string $featureColumn): ?array
        {
            $featureColumn = self::sanitizeFeatureColumn($featureColumn);
            $currentPlan  = self::getCurrentPlanForUser($pdo, $userId);
            $currentPrice = $currentPlan ? (float)$currentPlan['price'] : -1.0;

            // Lấy gói có feature = 1, sắp xếp tăng dần theo price (và id để ổn định)
            $sql = "
                SELECT *
                FROM subscriptions
                WHERE {$featureColumn} = 1
                ORDER BY price ASC, id ASC
            ";
            $plans = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            if (!$plans) {
                return null;
            }
            foreach ($plans as $p) {
                if ($currentPrice < 0) {
                    return $p; // chưa có gói -> chọn rẻ nhất có tính năng
                }
                if ((float)$p['price'] >= $currentPrice && (int)$p['id'] !== (int)$currentPlan['id']) {
                    return $p;
                }
            }
            // Nếu không tìm thấy gói giá >= hiện tại (ví dụ đang highest), chọn gói rẻ nhất có tính năng
            return $plans[0] ?? null;
        }

        /** Hàm guard tổng quát cho 1 tính năng */
        public static function guardFeatureAccess(PDO $pdo, int $userId, string $featureColumn, string $featureLabel): void
        {
            $featureColumn = self::sanitizeFeatureColumn($featureColumn);
            $status = self::getFeaturePlanStatus($pdo, $userId, $featureColumn);
            if ($status['has'] === true) {
                return; // OK
            }

            // Không có quyền -> hiển thị UI hoặc throw
            $upgrade = self::getUpgradeTargetPlan($pdo, $userId, $featureColumn);
            if (PERMISSIONS_THROW_EXCEPTIONS) {
                $msg = "Tính năng '{$featureLabel}' không nằm trong gói của bạn. Vui lòng nâng cấp.";
                if ($upgrade) {
                    $msg .= " Gợi ý: nâng cấp sang gói '{$upgrade['name']}' (".number_format((float)$upgrade['price'], 0, ',', '.')." đ).";
                }
                throw new PermissionDeniedException($msg);
            }

            self::renderDeniedAndExit($featureLabel, $status['plan'], $upgrade, $featureColumn, $status['source']);
        }

        /** Render UI từ "giao diện cũ" (đơn file) và exit; tránh Fatal từ Exception */
        public static function renderDeniedAndExit(
            string $featureLabel,
            ?array $currentPlan,
            ?array $upgradePlan,
            string $featureColumn,
            string $source
        ): void {
            // Bạn có thể chỉnh route MUA/NÂNG CẤP ở đây cho phù hợp project
            $upgradeUrl = '/pages/subscriptions.php';
            $currentName = $currentPlan['name'] ?? 'Chưa đăng ký (Free)';
            $currentPrice = isset($currentPlan['price']) ? number_format((float)$currentPlan['price'], 0, ',', '.') . ' đ' : '0 đ';
            $upgradeName  = $upgradePlan['name'] ?? '—';
            $upgradePrice = $upgradePlan ? number_format((float)$upgradePlan['price'], 0, ',', '.') . ' đ' : '—';
            $sourceText   = [
                'organization_shared' => 'Gói đang dùng từ Tổ chức (chia sẻ)',
                'personal'            => 'Gói cá nhân của bạn',
                'fallback'            => 'Gói mặc định (Free)',
                'none'                => 'Không xác định',
            ][$source] ?? 'Không xác định';

            // HTML + CSS + JS gọn nhẹ, giữ tinh thần UI cũ: có sidebar, card, nút hành động
            echo '<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Quyền truy cập | '.htmlspecialchars($featureLabel).'</title>
<style>
    :root {
        --bg: #0f172a;
        --panel: #111827;
        --panel-2: #0b1220;
        --text: #e5e7eb;
        --muted: #9ca3af;
        --accent: #22c55e;
        --danger: #ef4444;
        --warning: #f59e0b;
        --card: #111827;
        --chip: #1f2937;
        --link: #38bdf8;
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
        margin: 0;
        background: linear-gradient(180deg, var(--bg) 0%, #0b1020 100%);
        color: var(--text);
        font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
    }
    .layout {
        display: grid;
        grid-template-columns: 260px 1fr;
        min-height: 100vh;
    }
    .sidebar {
        background: linear-gradient(180deg, #0b1020 0%, #0b1220 100%);
        padding: 24px 16px;
        border-right: 1px solid rgba(255,255,255,0.06);
        position: sticky;
        top: 0;
        height: 100vh;
    }
    .brand { font-weight: 700; letter-spacing: .5px; }
    .sidebar .nav { margin-top: 24px; display: grid; gap: 8px; }
    .nav a {
        display: block; padding: 10px 12px; border-radius: 10px;
        color: var(--text); text-decoration: none;
    }
    .nav a:hover { background: rgba(255,255,255,0.06); }
    .nav .active { background: rgba(56,189,248,0.12); color: #93c5fd; }
    .content {
        padding: 32px 28px;
    }
    .card {
        background: radial-gradient(1200px 500px at -10% -20%, rgba(56,189,248,0.14), transparent 40%),
                    radial-gradient(800px 400px at 120% 10%, rgba(34,197,94,0.12), transparent 45%),
                    var(--card);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 16px;
        padding: 22px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.35);
    }
    h1 { margin: 0 0 12px; font-size: 22px; }
    p { margin: 8px 0; color: var(--muted); }
    .grid { display: grid; gap: 14px; grid-template-columns: 1fr; }
    .row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .chip {
        background: var(--chip);
        color: #cbd5e1;
        border: 1px solid rgba(255,255,255,0.08);
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
    }
    .muted { color: var(--muted); }
    .actions { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
    .btn {
        padding: 10px 14px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);
        background: #0b1020; color: var(--text); text-decoration: none; display: inline-flex; gap: 8px; align-items: center;
    }
    .btn:hover { background: #111827; }
    .btn-primary { background: #0ea5e9; border-color: #0ea5e9; color: #001125; font-weight: 600; }
    .btn-primary:hover { filter: brightness(0.95); }
    .btn-danger { background: var(--danger); border-color: var(--danger); color: #fff; }
    .table {
        width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px;
    }
    .table th, .table td {
        padding: 10px 12px; border-bottom: 1px dashed rgba(255,255,255,0.08);
    }
    .table th { text-align: left; color: #cbd5e1; font-weight: 600; }
    .tag { padding: 6px 10px; border-radius: 8px; display: inline-block; }
    .tag-danger { background: rgba(239, 68, 68, .14); color: #fecaca; border: 1px solid rgba(239, 68, 68, .35); }
    .tag-warn { background: rgba(245, 158, 11, .14); color: #fde68a; border: 1px solid rgba(245, 158, 11, .35); }
    .tag-ok { background: rgba(34, 197, 94, .16); color: #bbf7d0; border: 1px solid rgba(34, 197, 94, .35); }
    .footer { margin-top: 18px; font-size: 12px; color: #94a3b8; }
    .kbd {
        border: 1px solid rgba(148,163,184,.5); padding: 2px 6px; border-radius: 6px; font-size: 12px; color: #cbd5e1;
        background: rgba(148,163,184,.12);
    }
</style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">CDE Suite</div>
        <div class="nav">
            <a href="/index.php">Dashboard</a>
            <a href="/pages/projects.php">Projects</a>
            <a href="/pages/organization.php">Organization</a>
            <a href="/pages/work_diary.php" class="active">Work Diary</a>
            <a href="/pages/settings.php">Settings</a>
        </div>
    </aside>
    <main class="content">
        <div class="card">
            <h1>Không đủ quyền truy cập: '.htmlspecialchars($featureLabel).'</h1>
            <p>Rất tiếc! Tính năng này hiện không có trong gói bạn đang sử dụng.</p>
            <div class="grid">
                <div>
                    <div class="row">
                        <span class="chip">Nguồn gói: '.htmlspecialchars($sourceText).'</span>
                        <span class="chip">Tính năng: <strong>'.htmlspecialchars($featureColumn).'</strong></span>
                    </div>
                    <table class="table">
                        <tr>
                            <th>Gói hiện tại</th><td>'.htmlspecialchars($currentName).'</td>
                            <td><span class="tag tag-danger">Chưa hỗ trợ</span></td>
                        </tr>
                        <tr>
                            <th>Gợi ý nâng cấp</th><td>'.htmlspecialchars($upgradeName).'</td>
                            <td>'.($upgradePlan ? '<span class="tag tag-ok">Có hỗ trợ</span>' : '<span class="tag tag-warn">Chưa có gói phù hợp</span>').'</td>
                        </tr>
                    </table>
                    <div class="actions">
                        <a class="btn" href="javascript:history.back()">&larr; Quay lại</a>
                        <a class="btn-primary btn" href="'.htmlspecialchars($upgradeUrl).'">Nâng cấp gói</a>
                    </div>
                    <div class="footer">
                        Mẹo: nhấn <span class="kbd">Alt</span> + <span class="kbd">&larr;</span> để quay lại nhanh.
                        '.(PERMISSIONS_DEBUG ? '<br><small>DEBUG: feature='.htmlspecialchars($featureColumn).', source='.htmlspecialchars($source).'</small>' : '').'
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>';
            exit;
        }

        /** Bảo vệ tên cột tính năng */
        private static function sanitizeFeatureColumn(string $featureColumn): string
        {
            $featureColumn = trim($featureColumn);
            if (!in_array($featureColumn, self::ALLOWED_FEATURE_COLUMNS, true)) {
                throw new \InvalidArgumentException('Không hợp lệ: feature column');
            }
            return $featureColumn;
        }
    }
}

// ======== Facade functions (tên quen thuộc trong code cũ) ========
// Bọc if (!function_exists(...)) để chống redeclare nếu file bị require nhiều lần

if (!function_exists('getCurrentPlanForUser')) {
    function getCurrentPlanForUser(PDO $pdo, int $userId): ?array {
        return Permissions::getCurrentPlanForUser($pdo, $userId);
    }
}

if (!function_exists('getUpgradeTargetPlan')) {
    function getUpgradeTargetPlan(PDO $pdo, int $userId, string $featureColumn): ?array {
        return Permissions::getUpgradeTargetPlan($pdo, $userId, $featureColumn);
    }
}

if (!function_exists('getFeaturePlanStatus')) {
    function getFeaturePlanStatus(PDO $pdo, int $userId, string $featureColumn): array {
        return Permissions::getFeaturePlanStatus($pdo, $userId, $featureColumn);
    }
}

if (!function_exists('guardFeatureAccess')) {
    function guardFeatureAccess(PDO $pdo, int $userId, string $featureColumn, string $featureLabel): void {
        Permissions::guardFeatureAccess($pdo, $userId, $featureColumn, $featureLabel);
    }
}

if (!function_exists('guardWorkDiaryAccess')) {
    function guardWorkDiaryAccess(PDO $pdo, int $userId): void {
        Permissions::guardFeatureAccess($pdo, $userId, 'allow_work_diary', 'Work Diary');
    }
}

if (!function_exists('guardOrganizationMembersAccess')) {
    function guardOrganizationMembersAccess(PDO $pdo, int $userId): void {
        Permissions::guardFeatureAccess($pdo, $userId, 'allow_organization_members', 'Organization Members');
    }
}

if (!function_exists('guardOrganizationManageAccess')) {
    function guardOrganizationManageAccess(PDO $pdo, int $userId): void {
        Permissions::guardFeatureAccess($pdo, $userId, 'allow_organization_manage', 'Organization Manage');
    }
}
