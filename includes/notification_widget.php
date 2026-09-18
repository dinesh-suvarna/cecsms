<?php
$pending_count =$pending_count ?? 0;
$notif_res = $notif_res ?? null;

// Display the notification bell ONLY for the SuperAdmin role
if (isset($role) &&$role === ROLE_SUPERADMIN): 
?>
<div class="dropdown me-1" id="notificationWidget">
    <button class="btn btn-light position-relative border shadow-sm rounded-circle p-0 d-flex align-items-center justify-content-center" 
            style="width: 34px; height: 34px;" data-bs-toggle="dropdown">
        <i class="bi bi-bell text-muted fs-6"></i>
        <?php if ($pending_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 9px;">
                <?= $pending_count ?>
            </span>
        <?php endif; ?>
    </button>

    <div class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-0 rounded-3 overflow-hidden animate-fade-in" style="width: 320px;">
        <div class="p-3 border-bottom bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold small">Stock Transitions</h6>
                <span class="badge bg-emerald-soft extra-small"><?= $pending_count ?> Pending</span>
            </div>
        </div>

        <div class="max-vh-50 overflow-y-auto" style="max-height: 350px;">
            <?php if ($notif_res && $notif_res->num_rows > 0): ?>
                <?php while($n = $notif_res->fetch_assoc()): 
                    $type = strtoupper(str_replace('_requested', '',$n['status']));
                    $icon = ($type == 'REPAIR') ? 'bi-tools text-info' : (($type == 'RETURN') ? 'bi-arrow-left-circle text-warning' : 'bi-trash text-danger');$bg = ($type == 'REPAIR') ? 'bg-info-subtle' : (($type == 'RETURN') ? 'bg-warning-subtle' : 'bg-danger-subtle');
                ?>
                    <a href="/cecsms/divisions/returned_assets.php" class="dropdown-item p-3 border-bottom d-flex gap-3 align-items-start whitespace-normal">
                        <div class="<?= $bg ?> rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; flex-shrink: 0;">
                            <i class="bi <?= $icon ?> fs-6"></i>
                        </div>
                        <div class="w-100">
                            <div class="d-flex justify-content-between">
                                <p class="mb-0 small fw-bold text-dark"><?= htmlspecialchars($n['division_name']) ?></p>
                                <span class="text-muted" style="font-size: 9px;"><?= date('H:i', strtotime($n['created_at'] ?? 'now')) ?></span>
                            </div>
                            <p class="mb-1 text-muted" style="font-size: 0.78rem;">
                                <strong><?= $type ?>:</strong> <?= htmlspecialchars($n['item_name']) ?>
                            </p>
                            <?php if(!empty($n['notes'])): ?>
                                <div class="bg-light p-1 px-2 rounded small text-muted italic" style="font-size: 0.72rem; border-left: 3px solid #dee2e6;">
                                    "<?= htmlspecialchars($n['notes']) ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="p-4 text-center">
                    <i class="bi bi-check2-circle fs-1 text-muted opacity-25"></i>
                    <p class="text-muted small mt-2 mb-0">All caught up!</p>
                </div>
            <?php endif; ?>
        </div>

        <a href="/cecsms/divisions/returned_assets.php" class="dropdown-item text-center p-2 small fw-bold text-primary bg-light border-top">
            View All Approvals
        </a>
    </div>
</div>

<!-- LIVE POLLING SCRIPT -->
<script>
$(document).ready(function() {
    function fetchNotifications() {
        $.ajax({
            url: '/cecsms/admin/get_notifications.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                let widget = $('#notificationWidget');
                if (response.count > 0) {
                    let badge = widget.find('button .badge');
                    if (badge.length) {
                        badge.text(response.count);
                    } else {
                        widget.find('button').append(`<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 9px;">${response.count}</span>`);
                    }
                    widget.find('.dropdown-menu .bg-emerald-soft').text(response.count + ' Pending');
                } else {
                    widget.find('button .badge').remove();
                    widget.find('.dropdown-menu .bg-emerald-soft').text('0 Pending');
                }
                widget.find('.overflow-y-auto').html(response.html);
            }
        });
    }
    fetchNotifications();
    // Poll every 5 seconds to keep counts synchronized
    setInterval(fetchNotifications, 5000);
});
</script>
<?php endif; ?>