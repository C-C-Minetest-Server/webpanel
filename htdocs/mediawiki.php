<?php

require_once __DIR__ . '/loadConfig.php';
require_once __DIR__ . '/src/communicate.php';
require_once __DIR__ . '/src/csrf.php';
require_once __DIR__ . '/src/util.php';
require_once __DIR__ . '/src/mail.php';

require __DIR__ . '/src/ensureLogin.php';
$username = $_SESSION['username'];

$showelse = true;
if (
    empty($emoWebPanelMWAPI) ||
    empty($emoWebPanelMWName) ||
    empty($emoWebPanelMWBotPassword) ||
    empty($emoWebPanelMWSyncPrivs)
) {
    http_response_code(500);
    $errmsg = 'MediaWiki isn\'t enabled on this server.';
    $showelse = false;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['type']) || $_POST['type'] !== 'link') {
        http_response_code(400);
        $errmsg = 'Invalid request type';
    } elseif (!csrf_check_post()) {
        http_response_code(400); // Bad request
        $errmsg = 'CSRF Check failed';
    } else {
        if (!isset($_POST['uname'])) {
            http_response_code(400); // Bad request
            $errmsg = 'Missing username';
            goto endany;
        }
        $mediawiki_name = $_POST['uname'];
        $mediawiki_name = ucfirst($mediawiki_name);
        $mediawiki_name = str_replace(" ", "_", $mediawiki_name);

        $start_confirm_result = request(array(
            'service_name' => 'mediawiki_start_confirm',
            'username' => $username,
            'mediawiki_name' => $mediawiki_name
        ));
        if ($start_confirm_result['ok'] == false) {
            $errmsg = 'Start confirmation failed: ' . $start_confirm_result['err'];
            goto endany;
        } else {
            $confirm_code = rawurlencode($start_confirm_result['confirm_code']);
            $rootURL = getRootURL();
            $verifyLink = "{$rootURL}/mediawikiConfirm.php?emailToken={$confirm_code}";
        }

        $cookie_filename = tempnam(sys_get_temp_dir(), 'webpanelcookie');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_filename);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_filename);

        // Get login token
        curl_setopt($ch, CURLOPT_URL, $emoWebPanelMWAPI . '?' . http_build_query(array(
            'action' => 'query',
            'meta' => 'tokens',
            'type' => 'login',
            'format' => 'json',
            'errorformat' => 'plaintext',
        )));
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        $output = curl_exec($ch);
        $output_json = json_decode($output, true);
        if (isset($output_json['errors'])) {
            $errmsg = 'Error obtaining login token: ' . json_encode($output_json['errors']);
            goto endmw;
        }

        // login
        curl_setopt($ch, CURLOPT_URL, $emoWebPanelMWAPI);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'action' => 'login',
            'lgname' => $emoWebPanelMWName,
            'lgpassword' => $emoWebPanelMWBotPassword,
            'lgtoken' => $output_json['query']['tokens']['logintoken'],
            'format' => 'json',
            'errorformat' => 'plaintext',
        ));
        $output = curl_exec($ch);
        $output_json = json_decode($output, true);
        if (isset($output_json['errors'])) {
            $errmsg = 'Error logging in: ' . json_encode($output_json['errors']);
            goto endmw;
        }

        // Get CSRF token
        curl_setopt($ch, CURLOPT_URL, $emoWebPanelMWAPI . '?' . http_build_query(array(
            'action' => 'query',
            'meta' => 'tokens',
            'type' => 'csrf',
            'format' => 'json',
            'assert' => 'bot',
            'errorformat' => 'plaintext',
        )));
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        $output = curl_exec($ch);
        $output_json = json_decode($output, true);
        if (isset($output_json['errors'])) {
            $errmsg = 'Error obtaining email CSRF token: ' . json_encode($output_json['errors']);
            goto endmw;
        }

        // Send email to user
        $email_message = <<<EOF
                Dear {$mediawiki_name},

                Someone has requested linking your account ({$mediawiki_name}) to a in-game account ({$username}).

                Click on the following link to verify your MediaWiki account:

                {$verifyLink}

                This link will be valid for 10 minutes. If you haven't requested adding / changing your email, change your password as soon as possible.
                
                Yours truly,
                {$emoWebPanelSMTPFromName}
                EOF;
        curl_setopt($ch, CURLOPT_URL, $emoWebPanelMWAPI);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'action' => 'emailuser',
            'target' => $mediawiki_name,
            'subject' => 'MediaWiki account verification',
            'text' => $email_message,
            'token' => $output_json['query']['tokens']['csrftoken'],
            'format' => 'json',
            'assert' => 'bot',
            'errorformat' => 'plaintext',
        ));
        $output = curl_exec($ch);
        $output_json = json_decode($output, true);
        if (isset($output_json['errors'])) {
            $errmsg = 'Error sending email: ' . json_encode($output_json['errors']);
            goto endmw;
        }

        $errmsg = 'Successfully sent email. Check your inbox.';

        endmw:
        unlink($cookie_filename);

        endany:
    }
}

$mw_username = 'Not yet linked';
$mw_account_result = request(array(
    'service_name' => 'mediawiki_get',
    'username' => $username,
));
if ($mw_account_result['ok'] == false) {
    $mw_username = 'Error retrieving linked username: ' . $mw_account_result['err'];
} elseif ($mw_account_result['meidawik_name'] !== '') {
    $mw_username = $mw_account_result['meidawik_name'];
}

$allow_full_sync = false;
$get_privs_result = request(array(
    'service_name' => 'auth_get_privs',
    'username' => $username,
));
if ($get_privs_result['ok'] == false) {
    $errmsg = 'Error retrieving in-game privileges: ' . $get_privs_result['err'];
} elseif (isset($get_privs_result['privs']['server']) && $get_privs_result['privs']['server'] === true) {
    $allow_full_sync = true;
}
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">

    <title>MediaWiki Integration - <?php echo htmlspecialchars($emoWebPanelName); ?></title>
</head>

<body>
    <?php if (isset($errmsg)) : ?>
        <p><?php echo htmlspecialchars($errmsg) ?></p>
    <?php endif; ?>
    <?php if ($showelse) : ?>
        <h1>MediaWiki Integration</h1>

        <section id="link">
            <h2>Link MediaWiki Account</h2>
            <p>Current linked account: <?php echo htmlspecialchars($mw_username) ?></p>
            <p>Make sure to connect your email address in MediaWiki before proceeding.</p>
            <form method="POST">
                <label for="link-uname">MediaWiki username:</label>
                <input type="hidden" name="type" value="link" />
                <input type="text" id="link-uname" name="uname" required />
                <input type="submit" value="Submit" />
                <?php csrf_form_field(); ?>
            </form>
        </section>

        <section id="sync">
            <h2>Manual Sync</h2>
            <p>You may have to run this if your privileges are out of sync. This often happens after granting or revoking privileges.</p>
            <form action="./mediawikiConfirm.php" method="GET">
                <!-- no panic, we have validation in mediawikiConfirm.php -->
                <input type="hidden" id="sync-for-uname" name="uname" value="<?php echo htmlspecialchars($username) ?>" />
                <input type="submit" value="Sync" />
            </form>
        </section>

        <?php if ($allow_full_sync) : ?>
            <section id="sync-for">
                <h2>Perform sync on another user</h2>
                <p>After granting or revoking a synced privilege, please run a sync as soon as possible.</p>
                <form action="./mediawikiConfirm.php" method="GET">
                    <label for="sync-for-uname">MediaWiki username:</label>
                    <input type="text" id="sync-for-uname" name="uname" required />
                    <input type="submit" value="Submit" />
                </form>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</body>

</html>