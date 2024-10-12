<?php

require_once __DIR__ . '/loadConfig.php';
require_once __DIR__ . '/src/communicate.php';
require_once __DIR__ . '/src/csrf.php';
require_once __DIR__ . '/src/util.php';
require_once __DIR__ . '/src/mail.php';
session_start();

$errmsg = null;
$sendmail = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['name'])) {
        http_response_code(400);
        $errmsg = 'Name not given';
    } elseif (!csrf_check_post()) {
        http_response_code(400); // Bad request
        $errmsg = 'CSRF Check failed';
    } else {
        csrf_reset();
        $username = $_POST['name'];
        $result = request(array(
            'service_name' => 'password_reset_start',
            'username' => $username,
        ));
        if ($result['ok'] == false) {
            if ($result['err'] !== 'Missing email') {
                $errmsg = 'Start confirmation failed: ' . $result['err'];
            }
        } else {
            $confirm_code = rawurlencode($result['confirm_code']);
            $email = $result['email'];
            $rootURL = getRootURL();
            $verifyLink = "{$rootURL}/forgetPasswordConfirm.php?emailToken={$confirm_code}";
            $sendmail = true;
        }
    }
}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">

    <title>Request resetting password - <?php echo htmlspecialchars($emoWebPanelName) ?></title>
</head>

<body>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errmsg == null) : ?>
        <p>Please check your inbox for the confirmation email.</p>
        <p>If you cannot find the email, check your spam folder.</p>
    <?php else : ?>
        <h1>Request resetting password</h1>
        <?php if ($errmsg != null) {
            $disp_errmsg = htmlspecialchars($errmsg);
            echo "<p>{$disp_errmsg}</p>";
        } ?>
        <form method="post">
            <label for="name">Name:</label>
            <input type="name" id="name" name="name" />
            <?php csrf_form_field(); ?>
            <input type="submit" value="submit" />
        </form>
    <?php endif; ?>
</body>

</html>
<?php
session_write_close();
fastcgi_finish_request();
if ($sendmail === true) {
    // Send the email

    $mail = prepareMail();
    $mail->addAddress($email, $username);

    $mail->Subject = 'Password Reset';
    $mail->Body = <<<EOF
    Dear {$username},

    Click on the following link to reset your password:
    
    {$verifyLink}

    This link will be valid for 10 minutes. You may ignore this email if you haven't requested changing your password.

    Yours truly,
    {$emoWebPanelSMTPFromName}
    EOF;

    $mail->send();
}
?>