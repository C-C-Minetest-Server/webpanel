<?php

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/loadConfig.php';
require_once __DIR__ . '/src/communicate.php';
require_once __DIR__ . '/src/csrf.php';
require_once __DIR__ . '/src/util.php';
session_start();

if (!isset($_SESSION['username'])) {
    http_response_code(303); // See Other
    header("Location: {$emoWebPanelRoot}/login.php?returnto={$emoWebPanelRoot}/email.php");
    die();
}

$errmsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['email'])) {
        $errmsg = 'Email not given';
    } elseif (!csrf_check_post()) {
        http_response_code(400); // Bad request
        $errmsg = 'CSRF Check failed';
    } else {
        csrf_reset();
        $email = $_POST['email'];
        $username = $_SESSION['username'];
        $result = request(array(
            'service_name' => 'email_start_confirm',
            'username' => $username,
            'email' => $email
        ));
        if ($result['ok'] == false) {
            $errmsg = 'Start confirmation failed: ' . $result['err'];
        } else {
            $confirm_code = $result['confirm_code'];
            $rootURL = getRootURL();
            $verifyLink = "{$rootURL}emailConfirm.php?emailToken={$confirm_code}";
            // Send the email

            try {
                $mail = new PHPMailer(true);

                $mail->isSMTP();
                $mail->Host       = $emoWebPanelSMTPHost;
                $mail->SMTPAuth   = $emoWebPanelSMTPAuth;
                $mail->Username   = $emoWebPanelSMTPUsername;
                $mail->Password   = $emoWebPanelSMTPPassword;
                $mail->SMTPSecure = $emoWebPanelSMTPSecure;
                $mail->Port       = $emoWebPanelSMTPPort;

                $mail->setFrom($emoWebPanelSMTPFrom, $emoWebPanelSMTPFromName);
                $mail->addAddress($email, $username);

                $mail->Subject = 'Email verification';
                $mail->Body = <<<EOF
                Dear {$username},

                Click on the following link to verify your email:
                
                {$verifyLink}

                This link will be valid for 10 minutes. If you haven't requested adding / changing your email, change your password as soon as possible.

                Yours truly,
                {$emoWebPanelSMTPFromName}
                EOF;

                $mail->send();
            } catch (Exception $e) {
                http_response_code(500);
                $errmsg = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        }
    }
}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">

    <title>Set email address - <?php echo htmlspecialchars($emoWebPanelName) ?></title>
</head>

<body>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errmsg == null) : ?>
        <p>Please check your inbox for the confirmation email.</p>
        <p>If you cannot find the email, check your spam folder.</p>
    <?php else : ?>
        <h1>Set email</h1>
        <?php 
        $disp_username = htmlspecialchars($_SESSION['username']);
        echo "<p>Logged in as: {$disp_username}</p>";
        if ($errmsg != null) {
            $disp_errmsg = htmlspecialchars($errmsg);
            echo "<p>{$disp_errmsg}</p>";
        }
        ?>
        <form method="post">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" />
            <?php csrf_form_field(); ?>
            <input type="submit" value="submit" />
        </form>
        <p>By providing and confirming your email address, you agree that the server owner and moderators will have access to your email address for moderation purposes.</p>
    <?php endif; ?>
</body>

</html>