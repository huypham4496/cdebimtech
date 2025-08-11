<?php
$summary = storageSummaryForProjectOwner($pdo, $projectId);
$allowedBytes = ($summary['allowed_gb'] ?? 0) * 1024 * 1024 * 1024;
$usedThis = (int)($summary['used_this'] ?? 0);
$usedOthers = (int)($summary['used_others'] ?? 0);
$usedTotal = $usedThis + $usedOthers;
$remaining = max(0, $allowedBytes - $usedTotal);
?>
<div class="overview-grid">
  <div class="card-sm" style="grid-column: span 4;">
    <h3>Dung lượng (tổng gói: <?= (int)($summary['allowed_gb'] ?? 0) ?> GB)</h3>
    <canvas id="pie" class="chart-pie"></canvas>
    <ul style="margin-top:8px; font-size:14px;">
      <li>Còn lại: <strong><?= htmlspecialchars(formatBytes($remaining)) ?></strong></li>
      <li>Đã dùng (Project này): <strong><?= htmlspecialchars(formatBytes($usedThis)) ?></strong></li>
      <li>Đã dùng (Project khác): <strong><?= htmlspecialchars(formatBytes($usedOthers)) ?></strong></li>
    </ul>
    <script>(function(){const c=document.getElementById('pie');if(!c)return;const x=c.getContext('2d');const d=[<?= $remaining ?>,<?= $usedThis ?>,<?= $usedOthers ?>];const t=d.reduce((a,b)=>a+b,0)||1;const colors=['#22c55e','#3b82f6','#f59e0b'];let s=-Math.PI/2;for(let i=0;i<d.length;i++){const sl=(d[i]/t)*Math.PI*2;x.beginPath();x.moveTo(130,130);x.arc(130,130,120,s,s+sl);x.closePath();x.fillStyle=colors[i%colors.length];x.fill();s+=sl;}})();</script>
  </div>
  <div class="card-sm" style="grid-column: span 4;">
    <h3>Thông tin tổng quan</h3>
    <p><strong>Trạng thái:</strong> <?= htmlspecialchars($project['status']) ?></p>
    <p><strong>Ngày bắt đầu:</strong> <?= htmlspecialchars($project['start_date'] ?? '') ?></p>
    <p><strong>Mô tả:</strong><br><?= nl2br(htmlspecialchars($project['description'] ?? '')) ?></p>
  </div>
  <div class="card-sm" style="grid-column: span 4;">
    <h3>Thống kê Vấn đề</h3>
    <ul><li>Hoàn thành: <strong>0</strong></li><li>Đóng: <strong>0</strong></li><li>Không chấp thuận: <strong>0</strong></li><li>Đang thực hiện: <strong>0</strong></li><li>Đã cập nhật: <strong>0</strong></li></ul>
    <small>(Bật tab Vấn đề để hiện số thật)</small>
  </div>
</div>
