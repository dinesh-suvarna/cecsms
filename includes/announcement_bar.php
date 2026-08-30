<?php
require_once __DIR__ . "/announcement_fetcher.php";

$announcements = get_role_announcements();

if ($announcements && $announcements->num_rows > 0):
    while ($row = $announcements->fetch_assoc()):
?>
<div class="alert alert-dismissible fade show d-flex align-items-center justify-content-between p-3 mb-3 shadow-sm border-0" 
     style="background-color: #f8f9fa; border-left: 4px solid #0d6efd !important;" role="alert">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-megaphone-fill text-primary me-2"></i>
        <span class="badge bg-primary text-uppercase" style="font-size: 0.7rem;"><?= htmlspecialchars($row['sender_role']) ?></span>
        <span class="text-dark small">
            <strong><?= htmlspecialchars($row['title']) ?>:</strong> <?= htmlspecialchars($row['message']) ?>
        </span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <small class="text-muted" style="font-size: 0.75rem;"><?= date('M d, H:i', strtotime($row['created_at'])) ?></small>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php 
    endwhile;
endif; 
?>