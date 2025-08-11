<?php
// pages/partials/project_tab_overview.php — Overview with Storage donut using subscriptions.max_storage_gb

$projectId = $project['id'] ?? (int)($_GET['id'] ?? 0);
$status = $project['status'] ?? 'active';
$location = $project['location'] ?? '';
$tag = $project['tags'] ?? '';
$created = $project['start_date'] ?? '';

// ---- Storage summary (used) ----
$storage = null;
if (function_exists('storageSummaryForProjectOwner')) {
  try { $storage = storageSummaryForProjectOwner($pdo, (int)$userId); } catch (Throwable $e) { $storage = null; }
}
$usedBytes = null;
if (is_array($storage)) {
  $usedBytes = $storage['used_bytes'] ?? $storage['used'] ?? null;
  if (!$usedBytes && isset($storage['used_mb'])) $usedBytes = (float)$storage['used_mb'] * 1024 * 1024;
}
if (!function_exists('formatBytes')) {
  function formatBytes(int $bytes): string {
    $units = ['B','KB','MB','GB','TB']; $i = 0; $n = $bytes;
    while ($n >= 1024 && $i < count($units)-1) { $n /= 1024; $i++; }
    return sprintf('%.2f %s', $n, $units[$i]);
  }
}
$usedH = isset($storage['used_h']) ? $storage['used_h'] : ($usedBytes ? formatBytes((int)$usedBytes) : '—');

// ---- Total limit from subscriptions.max_storage_gb ----
$sub = null;
if (function_exists('currentUserSubscription')) {
  try { $sub = currentUserSubscription($pdo, (int)$userId); } catch (Throwable $e) { $sub = null; }
}
$totalBytesLimit = null;
if (is_array($sub) && isset($sub['max_storage_gb'])) {
  $totalBytesLimit = (float)$sub['max_storage_gb'] * 1024 * 1024 * 1024;
}
$totalH = $totalBytesLimit ? formatBytes((int)$totalBytesLimit) : '—';

// ---- Percent (prefer plan limit) ----
$percent = null;
if ($totalBytesLimit && $totalBytesLimit > 0 && $usedBytes !== null) {
  $percent = ($usedBytes / $totalBytesLimit) * 100.0;
} else {
  // fallback: if storage provided total bytes elsewhere
  $totalBytes = null;
  if (is_array($storage)) {
    $totalBytes = $storage['total_bytes'] ?? $storage['total'] ?? null;
    if (!$totalBytes && isset($storage['total_mb'])) $totalBytes = (float)$storage['total_mb'] * 1024 * 1024;
    if (isset($storage['percent'])) $percent = (float)$storage['percent'];
  }
  if ($percent === null && $usedBytes && $totalBytes) $percent = ($usedBytes / $totalBytes) * 100.0;
}

// ---- UI ----
?>
<div class="ov-grid">

  <!-- Left column -->
  <div class="col-left">
    <section class="card">
      <h3 class="card-title">Project Summary</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div><div class="muted">Status</div>
          <div class="status-badge <?= $status==='completed'?'completed':'active' ?>"><?= htmlspecialchars(ucfirst($status)) ?></div>
        </div>
        <div><div class="muted">Created Date</div><div><strong><?= htmlspecialchars($created ?: '—') ?></strong></div></div>
        <div><div class="muted">Location</div><div><strong><?= htmlspecialchars($location ?: '—') ?></strong></div></div>
        <div><div class="muted">Tag</div><div><strong><?= htmlspecialchars($tag ?: '—') ?></strong></div></div>
      </div>
    </section>

    <section class="card chart-card">
      <h3 class="card-title">Progress</h3>
      <div class="chart-area">
        <canvas id="ovProgress" width="400" height="400" aria-label="Progress chart"></canvas>
      </div>
      <div class="chart-legend">Planned vs. Actual progress</div>
    </section>

    <section class="card chart-card">
      <h3 class="card-title">Storage</h3>
      <div class="chart-area">
        <canvas
          id="ovStorage"
          data-donut
          data-label="Storage"
          <?= $percent !== null ? 'data-percent="'.(float)$percent.'"' : '' ?>
          <?= $usedBytes ? 'data-used="'.(float)$usedBytes.'"' : '' ?>
          <?= $totalBytesLimit ? 'data-total="'.(float)$totalBytesLimit.'"' : '' ?>
          width="400" height="400" aria-label="Storage chart">
        </canvas>
      </div>
      <div class="chart-legend">
        Used: <strong><?= htmlspecialchars($usedH) ?></strong> ·
        Plan limit: <strong><?= htmlspecialchars($totalH) ?></strong>
      </div>
    </section>
  </div>

  <!-- Right column -->
  <div class="col-right">
    <section class="card">
      <h3 class="card-title">Team</h3>
      <?php
      $members = [];
      if (function_exists('listProjectMembers')) {
        try { $members = listProjectMembers($pdo, $projectId); } catch (Throwable $e) { $members = []; }
      }
      ?>
      <?php if ($members): ?>
        <ul style="list-style:none;margin:0;padding:0;display:grid;gap:8px;">
          <?php foreach ($members as $m): ?>
            <li style="display:flex;align-items:center;justify-content:space-between;gap:10px;border:1px solid var(--border);border-radius:12px;padding:8px 10px;">
              <div style="display:flex;align-items:center;gap:10px;">
                <span class="badge"><?= htmlspecialchars($m['role'] ?? 'Member') ?></span>
                <strong><?= htmlspecialchars($m['name'] ?? ('#'.$m['user_id'])) ?></strong>
              </div>
              <span class="muted">ID #<?= (int)($m['user_id'] ?? 0) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="muted">No members to show.</div>
      <?php endif; ?>
    </section>

    <section class="card">
      <h3 class="card-title">Recent Files</h3>
      <?php
      $recentFiles = [];
      if (function_exists('listProjectFiles')) {
        try { $recentFiles = listProjectFiles($pdo, $projectId, 6); } catch (Throwable $e) { $recentFiles = []; }
      }
      ?>
      <?php if ($recentFiles): ?>
        <div class="table-responsive">
          <table class="table">
            <thead><tr><th>Name</th><th>Size</th><th>By</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($recentFiles as $f): ?>
                <tr>
                  <td data-th="Name"><?= htmlspecialchars($f['name'] ?? $f['filename'] ?? 'file') ?></td>
                  <td data-th="Size"><?= htmlspecialchars($f['size_h'] ?? '') ?></td>
                  <td data-th="By">#<?= (int)($f['user_id'] ?? 0) ?></td>
                  <td data-th="Date"><?= htmlspecialchars($f['created_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="muted">No recent files.</div>
      <?php endif; ?>
    </section>

    <section class="card">
      <h3 class="card-title">Recent Activity</h3>
      <?php
      $activities = [];
      if (function_exists('listProjectActivities')) {
        try { $activities = listProjectActivities($pdo, $projectId, 8); } catch (Throwable $e) { $activities = []; }
      }
      ?>
      <?php if ($activities): ?>
        <ul style="list-style:none;margin:0;padding:0;display:grid;gap:8px;">
          <?php foreach ($activities as $a): ?>
            <li style="display:flex;align-items:center;justify-content:space-between;gap:10px;border:1px solid var(--border);border-radius:12px;padding:8px 10px;">
              <div><strong><?= htmlspecialchars($a['action'] ?? 'Update') ?></strong> <span class="muted"><?= htmlspecialchars($a['detail'] ?? '') ?></span></div>
              <div class="muted"><?= htmlspecialchars($a['created_at'] ?? '') ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="muted">No recent activity.</div>
      <?php endif; ?>
    </section>
  </div>

</div>
