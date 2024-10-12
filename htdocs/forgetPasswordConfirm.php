<?php

require_once __DIR__ . '/loadConfig.php';
require_once __DIR__ . '/src/csrf.php';
require_once __DIR__ . '/src/communicate.php';
require_once __DIR__ . '/src/util.php';
require_once __DIR__ . '/src/mail.php';
session_start();

$msg = null;
$sendmail = false;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['emailToken'])) {
        http_response_code(400); // Bad request
        $msg = 'Invalid request.';
    } else {
        $confirm_code = $_GET['emailToken'];
        $result = request(array(
            'service_name' => 'password_reset_end',
            'confirm_code' => $confirm_code
        ));
        if ($result['ok'] == false) {
            $msg = 'Confirmation failed: ' . $result['err'];
        } else {
            $_SESSION['reset_name'] = $result['name'];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['password'])) {
        http_response_code(400);
        $errmsg = 'Password not given';
    } elseif (!csrf_check_post()) {
        http_response_code(400); // Bad request
        $errmsg = 'CSRF Check failed';
    } else {
        $username = $_SESSION['reset_name'];
        $result = request(array(
            'service_name' => 'auth_set_password',
            'username' => $username,
            'password' => $_POST['password'],
            'get_email' => true,
        ));
        if ($result['ok'] == false) {
            $msg = 'Confirmation failed: ' . $result['err'];
        } else {
            $msg = 'Successfully set password.';
        }
        if (isset($result['email']) && $result['email'] !== '') {
            $email = $result['email'];
            $sendmail = true;
        }
    }
} else {
    http_response_code(405); // Method not allowed
    header('Allow: GET, POST');
    die();
}

?>
<!DOCTYPE HTMl>
<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">

    <title>Reset password - <?php echo htmlspecialchars($emoWebPanelName) ?></title>
</head>

<body>
    <?php if ($msg !== null) : ?>
        <p><?php echo htmlspecialchars($msg); ?></p>
        <p><a href="<?php echo getRootURL() . '/'; ?>">Go back to main page</a></p>
    <?php else : ?>
        <h1>Reset password: <?php echo htmlspecialchars($_SESSION['reset_name']); ?></h1>
        <form method="post" id="passwordreset">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" /><br />
            <label for="confirm">Type it again:</label>
            <input type="password" id="confirm" /><br />
            <?php csrf_form_field(); ?>
            <input type="submit" value="submit" />
        </form>
        <script>
            document.getElementById("passwordreset").addEventListener("submit", function(e) {
                const elems = this.elements;
                if (elems.password.value !== elems.confirm.value) {
                    e.preventDefault();
                    alert("Password mismatch!");
                }
            })
        </script>
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

    $mail->Subject = 'Your password has been changed';
    $mail->Body = <<<EOF
        Dear {$username},

        Someone have changed your password. If you haven't requested adding / changing your password, change your password as soon as possible.

        Yours truly,
        {$emoWebPanelSMTPFromName}
        EOF;

    $mail->send();
}
?>