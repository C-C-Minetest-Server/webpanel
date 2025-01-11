<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username'])) {
    http_response_code(303); // See Other
    header("Location: {$emoWebPanelRoot}/login.php?returnto={$emoWebPanelRoot}/email.php");
    die();
}