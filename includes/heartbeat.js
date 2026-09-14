(function () {

    const HEARTBEAT_INTERVAL = 60000; // 60 seconds

    function sendHeartbeat() {

        fetch('/cecsms/includes/heartbeat.php', {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .catch(function () {
            // Ignore heartbeat connection errors
        });
    }

    // Send immediately when the page loads
    sendHeartbeat();

    // Continue sending every 60 seconds
    setInterval(sendHeartbeat, HEARTBEAT_INTERVAL);

})();