<?php
$type = $_SESSION['notify_type'] ?? '';
$msg  = $_SESSION['notify_msg'] ?? '';

unset($_SESSION['notify_type'], $_SESSION['notify_msg']);

if($msg): ?>
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1055;">
    <div class="toast align-items-center text-bg-<?= $type ?> border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000" data-bs-autohide="true">
        <div class="d-flex">
            <div class="toast-body"><?= htmlspecialchars($msg) ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    var toastEl = document.querySelector('.toast');
    if(toastEl){
        var toast = new bootstrap.Toast(toastEl);
        toast.show();
    }
});
</script>
<?php endif; ?>