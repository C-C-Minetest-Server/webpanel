<?php

require_once __DIR__ . '/loadConfig.php';
session_start();

unset($_SESSION['username']);

$redirect = isset($_GET['returnto']) ? $_GET['returnto'] : ($emoWebPanelRoot . "/");
http_response_code(303); // See Other
header("Location: {$redirect}");
die();
