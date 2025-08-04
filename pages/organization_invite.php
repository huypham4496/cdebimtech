<!-- File: pages/organization_invite.php -->
<?php
session_start();
require_once __DIR__ . '/../config.php';

// PDO Connection
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
);

// Ensure logged in
if (empty($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user']['id'];

// Validate token
$token = $_GET['token'] ?? '';
$stmt = $pdo->prepare(
    "SELECT oi.id AS inv_id, oi.status, oi.token, o.id AS org_id, o.name
     FROM organization_invitations oi
     JOIN organizations o ON oi.organization_id = o.id
     WHERE oi.token = :token"
);
$stmt->execute([':token'=>$token]);
$inv = $stmt->fetch();

if (!$inv) {
    die('Invalid invitation token.');
}

// Handle accept/reject
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'accept') {
        // Mark accepted
        $pdo->prepare(
            "UPDATE organization_invitations SET status='accepted', responded_at=NOW() WHERE id = :id"
        )->execute([':id'=>$inv['inv_id']]);
        // Add member
        $pdo->prepare(
            "INSERT INTO organization_members (organization_id, user_id, role, subscribed_id)
             VALUES (:org, :user, 'member', :sub)"
        )->execute([
            ':org'=>$inv['org_id'],
            ':user'=>$userId,
            ':sub'=>$inv['org_id'] // or original creator if needed
        ]);
        $message = 'You have joined the organization.';
    } else {
        // Mark rejected
        $pdo->prepare(
            "UPDATE organization_invitations SET status='rejected', responded_at=NOW() WHERE id = :id"
        )->execute([':id'=>$inv['inv_id']]);
        $message = 'You have rejected the invitation.';
    }
    // Show result
    echo "<!DOCTYPE html>
<html lang=\"en\">
<head>
  <meta charset=\"UTF-8\">
  <meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">
  <title>Invitation Response</title>
  <link rel=\"stylesheet\" href=\"../assets/css/invite.css\">
</head>
<body>
  <div class=\"invite-container\">
    <div class=\"invite-card\">
      <p>{$message}</p>
      <a href=\"dashboard.php\">Go to Dashboard</a>
    </div>
  </div>
</body>
</html>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Organization Invitation</title>
  <link rel="stylesheet" href="../assets/css/invite.css">
</head>
<body>
  <div class="invite-container">
    <div class="invite-card">
      <h2>Invitation to Join "<?= htmlspecialchars($inv['name']) ?>"</h2>
      <p>You have been invited to join the organization "<?= htmlspecialchars($inv['name']) ?>".</p>
      <form method="POST">
        <button name="action" value="accept" class="btn-primary">Accept</button>
        <button name="action" value="reject" class="btn-secondary">Reject</button>
      </form>
    </div>
  </div>
</body>
</html>