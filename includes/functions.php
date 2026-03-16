<?php
function notify($type, $msg){
    $_SESSION['notify_type'] = $type; // success, danger, warning, info
    $_SESSION['notify_msg']  = $msg;
}