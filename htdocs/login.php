<?php

// GET: Show the form only
// POST: Do Authentication, if succeed, redirect, otherwise, show form

require_once __DIR__ . '/loadConfig.php';
require_once __DIR__ . '/src/communicate.php';
require_once __DIR__ . '/src/csrf.php';
session_start();

$errmsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['name']) || !isset($_POST['password'])) {
        http_response_code(400); // Bad request
        $errmsg = 'Name or password not given';
    } elseif (!csrf_check_post()) {
        http_response_code(400); // Bad request
        $errmsg = 'CSRF Check failed';
    } else {
        csrf_reset();
        $username = $_POST['name'];
        $password = $_POST['password'];
        $result = request(array(
            'service_name' => 'auth_name_password',
            'username' => $username,
            'password' => $password
        ));
        if (isset($result['auth'])) {
            if ($result['auth'] == true) {
                $_SESSION['username'] = $username;

                $redirect = isset($_GET['returnto']) ? $_GET['returnto'] : ($emoWebPanelRoot . '/');
                http_response_code(303); // See Other
                header("Location: {$redirect}");
                die();
            } else {
                $errmsg = "Auth failed: " . $result['auth_err'];
            }
        } else {
            http_response_code(500);
            $errmsg = "Query failed: " .  $result['err'];
        }
    }
}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">

    <title>Login - <?php echo htmlspecialchars($emoWebPanelName) ?></title>
</head>

<body style="font-family: sans-serif;">
    <h1>Login</h1>
    <?php
    if ($errmsg != null) {
        $disp_errmsg = htmlspecialchars($errmsg);
        echo "<p>ERROR: {$disp_errmsg}</p>";
    }
    if (isset($_SESSION['username'])) {
        $disp_username = htmlspecialchars($_SESSION['username']);
        echo "<p>Logged in as: {$disp_username}</p>";
    }
    ?>
    <form method="post">
        <label for="name">Username:</label>
        <input type="text" id="name" name="name" required/><br />
        <label for="password">Password: </label>
        <input type="password" id="password" name="password"/>
        <?php csrf_form_field() ?><input type="submit" value="Login" />
    </form>
    <p><a href="forgetPassword">Reset password</a></p>
</body>

</html>