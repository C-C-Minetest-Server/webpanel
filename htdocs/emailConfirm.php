<?php

require_once __DIR__ . '/loadConfig.php';
require_once __DIR__ . '/src/communicate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method not allowed
    header('Allow: GET');
    die();
}

$msg = 'Successfully set your account\'s email.';
if (!isset($_GET['emailToken'])) {
    http_response_code(400); // Bad request
    $msg = 'Invalid request.';
} else {
    $confirm_code = $_GET['emailToken'];
    $result = request(array(
        'service_name' => 'email_do_confirm',
        'confirm_code' => $confirm_code
    ));
    if ($result['ok'] == false) {
        $msg = 'Confirmation failed: ' . $result['err'];
    }
}
?>
<!DOCTYPE HTMl>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">

    <title>Confirm email address - <?php echo htmlspecialchars($emoWebPanelName) ?></title>
</head>

<body>
    <p><?php echo htmlspecialchars($msg); ?></p>
</body>

</html>