<?php
session_start();
require_once __DIR__ . '/../../config.php';

// Connect to the database
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Ensure admin access
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: ../../pages/login.php');
    exit;
}

// Handle status updates (Approve / Reject)
$msg   = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $new     = $_POST['update_status'];

    if (in_array($new, ['pending','approved','rejected'], true)) {
        // 1) Update order status
        $pdo->prepare("UPDATE subscription_orders SET status = ? WHERE id = ?")
            ->execute([$new, $orderId]);

        // 2) If approved, update user's subscription
        if ($new === 'approved') {
            // Fetch order details
            $stmt = $pdo->prepare(
                'SELECT user_id, subscription_id, duration FROM subscription_orders WHERE id = ?'
            );
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            $userId   = $order['user_id'];
            $planId   = $order['subscription_id'];
            $years    = (int)$order['duration']; // 0 means lifetime

            if ($years === 0) {
                // permanent subscription
                $expiry = null;
            } else {
                // add N years to today
                $dt = new DateTime();
                $dt->modify("+{$years} years");
                $expiry = $dt->format('Y-m-d');
            }

            // Update users table
            $updateUser = $pdo->prepare(
                'UPDATE users SET subscription_id = ?, subscription_expires_at = ? WHERE id = ?'
            );
            $updateUser->execute([
                $planId,
                $expiry, // will bind NULL for lifetime
                $userId
            ]);
        }

        $msg = "Order #{$orderId} marked “" . ucfirst($new) . "”.";
    } else {
        $error = 'Invalid status.';
    }

    // Preserve filter
    $filter = $_GET['status_filter'] ?? '';
    header(
        'Location: payment_requests.php'
        . '?msg=' . urlencode($msg)
        . ($filter !== '' ? '&status_filter=' . urlencode($filter) : '')
    );
    exit;
}

// Flash messages
$msg   = $_GET['msg']   ?? '';
$error = $_GET['error'] ?? '';

// Status filter options
$statuses = [
    ''           => 'All',
    'pending'    => 'Pending',
    'approved'   => 'Approved',
    'rejected'   => 'Rejected',
];
$filter = $_GET['status_filter'] ?? '';

// Fetch payment requests with optional status filtering
$sql = "
SELECT 
  so.id,
  u.username             AS user,
  s.name                 AS subscription,
  so.duration,
  COALESCE(so.voucher_code, '-') AS voucher,
  CONCAT(so.discount_percent, '%') AS discount,
  CONCAT(FORMAT(so.amount_paid,2), ' VND') AS amount_paid,
  so.memo,
  so.status,
  DATE_FORMAT(so.created_at, '%Y-%m-%d %H:%i') AS requested_at
FROM subscription_orders so
JOIN users u ON so.user_id = u.id
JOIN subscriptions s ON so.subscription_id = s.id
";

if ($filter !== '' && isset($statuses[$filter])) {
    $sql .= " WHERE so.status = :status";
}
$sql .= " ORDER BY so.created_at DESC";

$stmt = $pdo->prepare($sql);
if ($filter !== '' && isset($statuses[$filter])) {
    $stmt->execute([':status' => $filter]);
} else {
    $stmt->execute();
}
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Requests</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet"
        href="/assets/css/sidebar_admin.css?v=<?= filemtime(__DIR__.'/../../assets/css/sidebar_admin.css') ?>">
  <link rel="stylesheet"
        href="/assets/css/payment_requests.css?v=<?= filemtime(__DIR__.'/../../assets/css/payment_requests.css') ?>">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar_admin.php'; ?>

  <main class="main-content">
    <section class="requests-container">
      <h1>Payment Requests</h1>

      <?php if ($msg): ?>
        <div class="alert success"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="get" class="filter-form">
        <label for="status_filter">Filter by status:</label>
        <select name="status_filter" id="status_filter" onchange="this.form.submit()">
          <?php foreach ($statuses as $key => $label): ?>
            <option value="<?= $key ?>" <?= $filter === $key ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <?php if (empty($requests)): ?>
        <p class="no-data">No payment requests found.</p>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="requests-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>User</th>
                <th>Subscription</th>
                <th>Duration</th>
                <th>Voucher</th>
                <th>Discount</th>
                <th>Amount Paid</th>
                <th>Status</th>
                <th>Memo</th>
                <th>Requested At</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($requests as $r): ?>
              <tr>
                <td><?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['user']) ?></td>
                <td><?= htmlspecialchars($r['subscription']) ?></td>
                <td><?= htmlspecialchars($r['duration']) ?></td>
                <td><?= htmlspecialchars($r['voucher']) ?></td>
                <td><?= htmlspecialchars($r['discount']) ?></td>
                <td><?= htmlspecialchars($r['amount_paid']) ?></td>
                <td>
                  <span class="status-pill status-<?= $r['status'] ?>">
                    <?= ucfirst($r['status']) ?>
                  </span>
                </td>
                <td><code><?= htmlspecialchars($r['memo']) ?></code></td>
                <td><?= $r['requested_at'] ?></td>
                <td>
                  <?php if ($r['status'] === 'pending'): ?>
                  <form method="post" class="action-form">
                    <input type="hidden" name="order_id" value="<?= $r['id'] ?>">
                    <button type="submit" name="update_status" value="approved" class="btn-approve" title="Approve">
                      <i class="fas fa-check"></i>
                    </button>
                    <button type="submit" name="update_status" value="rejected" class="btn-reject" title="Reject">
                      <i class="fas fa-times"></i>
                    </button>
                  </form>
                  <?php else: ?>
                    <span class="status-pill status-<?= $r['status'] ?>">
                      <?= ucfirst($r['status']) ?>
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </section>
  </main>
</body>
</html>
