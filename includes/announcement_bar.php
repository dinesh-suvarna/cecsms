<?php
require_once __DIR__ . "/announcement_fetcher.php";

// Fetch the query result once and pass it directly to render
$announcements = get_role_announcements();
render_announcement_bar($announcements);
?>