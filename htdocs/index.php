<?php

require_once __DIR__ . '/loadConfig.php';
session_start();
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">

    <title><?php echo htmlspecialchars($emoWebPanelName) ?></title>
</head>

<body style="font-family: sans-serif; text-align: center">
    <h1><?php echo htmlspecialchars($emoWebPanelName) ?></h1>
    <p><?php if (isset($_SESSION['username'])) : ?>
            Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?> - <a href="logout.php">Logout</a>
        <?php else : ?>
            Not logged in - <a href="login.php">Login</a>
        <?php endif; ?></p>
    <ul>
        <?php if (isset($_SESSION['username'])) : ?>
            <li><a href="email.php">Set / change email</a></li>
            <li><a href="mediawiki.php">Mediawiki Integration</a></li>
        <?php endif; ?>
        <li><a href="forgetPassword.php">Forget password?</a></li>
    </ul>
</body>

</html>