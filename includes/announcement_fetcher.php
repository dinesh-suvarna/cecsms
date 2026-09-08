<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../config/db.php";

/**
 * Fetch announcements targeted to the current user's role
 */
function get_role_announcements() {
    global $conn;
    $current_role = $_SESSION['role'] ?? 'Staff';

    $sql = "SELECT * FROM system_announcements 
            WHERE is_active = 1 
            AND (target_role = ? OR target_role = 'All') 
            ORDER BY id DESC LIMIT 3";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("s", $current_role);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Render announcements with vibrating bell and collapsible notification bar
 * Accept result set directly to prevent duplicate DB queries
 */
function render_announcement_bar($announcements = null) {
    if ($announcements === null) {
        $announcements = get_role_announcements();
    }

    if ($announcements && $announcements->num_rows > 0):
        $counter = 0;
?>
<style>
/* Continuous Bell Vibration Animation */
@keyframes bellVibrate {
    0% { transform: rotate(0); }
    15% { transform: rotate(14deg); }
    30% { transform: rotate(-14deg); }
    45% { transform: rotate(10deg); }
    60% { transform: rotate(-10deg); }
    75% { transform: rotate(6deg); }
    85% { transform: rotate(-6deg); }
    100% { transform: rotate(0); }
}

.bell-vibrate {
    display: inline-block;
    animation: bellVibrate 1.5s infinite ease-in-out;
    transform-origin: top center;
}

.pulse-badge {
    box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7);
    animation: pulseGlow 1.8s infinite;
}

@keyframes pulseGlow {
    0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.5); }
    70% { box-shadow: 0 0 0 8px rgba(13, 110, 253, 0); }
    100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
}
</style>

<?php 
        while ($row = $announcements->fetch_assoc()):
            $counter++;
            $collapse_id = "broadcastCollapse_" . $counter;
?>
<div class="alert alert-dismissible fade show p-0 mb-3 shadow-sm border-0 overflow-hidden" 
     style="background-color: #ffffff; border-left: 4px solid #0d6efd !important;" role="alert">
    
    <!-- Header Bar (Always Visible) -->
    <div class="d-flex align-items-center justify-content-between p-3" 
         style="cursor: pointer;" 
         data-bs-toggle="collapse" 
         data-bs-target="#<?= $collapse_id ?>" 
         aria-expanded="false" 
         aria-controls="<?= $collapse_id ?>">
        
        <div class="d-flex align-items-center gap-2">
            <!-- Vibrating Bell Icon Container -->
            <div class="bg-primary-subtle p-2 rounded-circle me-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="bi bi-bell-fill text-primary bell-vibrate"></i>
            </div>
            
            <span class="badge bg-primary text-uppercase pulse-badge" style="font-size: 0.68rem;"><?= htmlspecialchars($row['sender_role']) ?></span>
            
            <span class="text-dark fw-bold small text-truncate" style="max-width: 280px;">
                <?= htmlspecialchars($row['title']) ?>
            </span>
            
            <small class="text-muted extra-small ms-1 d-none d-sm-inline">(Click to view message)</small>
        </div>

        <div class="d-flex align-items-center gap-2">
            <small class="text-muted" style="font-size: 0.75rem;"><?= date('M d, H:i', strtotime($row['created_at'])) ?></small>
            <i class="bi bi-chevron-down text-secondary small me-2"></i>
            <button type="button" class="btn-close" data-bs-dismiss="alert" onclick="event.stopPropagation();" aria-label="Close"></button>
        </div>
    </div>

    <!-- Collapsible Broadcast Message Body -->
    <div class="collapse" id="<?= $collapse_id ?>">
        <div class="px-3 pb-3 pt-0 border-top bg-light-subtle">
            <div class="pt-2 text-secondary small">
                <?= nl2br(htmlspecialchars($row['message'])) ?>
            </div>
        </div>
    </div>
</div>
<?php 
        endwhile;
    endif;
}
?>