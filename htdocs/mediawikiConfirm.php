<?php

require_once __DIR__ . '/loadConfig.php';
require_once __DIR__ . '/src/communicate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method not allowed
    header('Allow: GET');
    die();
}

if (
    empty($emoWebPanelMWAPI) ||
    empty($emoWebPanelMWName) ||
    empty($emoWebPanelMWBotPassword) ||
    empty($emoWebPanelMWSyncPrivs)
) {
    http_response_code(500);
    $msg = 'MediaWiki isn\'t enabled on this server.';
    goto endexec;
}

if (isset($_GET['emailToken'])) {
    // email verification routine

    $confirm_code = $_GET['emailToken'];
    $result = request(array(
        'service_name' => 'mediawiki_do_confirm',
        'confirm_code' => $confirm_code
    ));
    if ($result['ok'] == false) {
        $msg = 'Confirmation failed: ' . $result['err'];
    } else {
        $msg = 'Successfully linked account.';
        $sync_username = $result['username'];
        $sync_mediawiki_username = $result['new_mediawiki_name'];
        $sync_old_mediawiki_username = isset($result['old_mediawiki_name']) ? $result['old_mediawiki_name'] : '';
    }
} else {
    if (isset($_GET['uname'])) {
        require __DIR__ . '/src/ensureLogin.php';
        $username = $_SESSION['username'];

        $ratelimit_result = request(array(
            'service_name' => 'mediawiki_in_ratelimit',
            'username' => $username
        ));
        if ($ratelimit_result['ok'] == true && $ratelimit_result['ratelimit'] > 0) {
            $msg = 'You\'re in rate limit! Try again in ' . $ratelimit_result['ratelimit'] . ' seconds.';
        } else {
            if ($username !== $_GET['uname']) {
                $get_privs_result = request(array(
                    'service_name' => 'auth_get_privs',
                    'username' => $username,
                ));
                if ($get_privs_result['ok'] == false) {
                    http_response_code(500);
                    $msg = 'Error retrieving in-game privileges: ' . $get_privs_result['err'];
                } elseif (!isset($get_privs_result['privs']['server']) || $get_privs_result['privs']['server'] !== true) {
                    http_response_code(403);
                    $msg = 'Not allowed to sync for others!';
                } else {
                    $sync_username = $_GET['uname'];
                }
            } else {
                $sync_username = $_GET['uname'];
            }
        }
    } else {
        http_response_code(400);
        $msg = 'Neither confirmation token nor sync username provided.';
    }
}

if (isset($sync_username)) {
    if (!isset($sync_mediawiki_username)) {
        // Get corrisponding MediaWiki username
        $mw_account_result = request(array(
            'service_name' => 'mediawiki_get',
            'username' => $sync_username,
        ));
        if ($mw_account_result['ok'] == false) {
            $msg = 'Error retrieving linked username: ' . $mw_account_result['err'];
            goto endany;
        } elseif ($mw_account_result['meidawik_name'] !== '') {
            $sync_mediawiki_username = $mw_account_result['meidawik_name'];
        } else {
            $msg = 'MediaWiki account not linked.';
            goto endany;
        }
    }

    // Get in-game privileges
    $get_privs_sync_result = request(array(
        'service_name' => 'auth_get_privs',
        'username' => $sync_username,
    ));
    if ($get_privs_sync_result['ok'] == false) {
        http_response_code(500);
        $msg = 'Error retrieving in-game privileges of target: ' . $get_privs_result['err'];
        goto endany;
    }
    $add_groups = array();
    $remove_groups = array();
    $add_groups[] = 'ingame-*';
    foreach ($emoWebPanelMWSyncPrivs as $privilege) {
        if (isset($get_privs_sync_result['privs'][$privilege]) && $get_privs_sync_result['privs'][$privilege] === true) {
            $add_groups[] = 'ingame-' . $privilege;
        } else {
            $remove_groups[] = 'ingame-' . $privilege;
        }
    }

    request(array(
        'service_name' => 'mediawiki_init_ratelimit',
        'username' => $username
    ));

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
        $msg = 'Error obtaining login token: ' . json_encode($output_json['errors']);
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
        $msg = 'Error logging in: ' . json_encode($output_json['errors']);
        goto endmw;
    }

    // Get userrights token for user group changing
    curl_setopt($ch, CURLOPT_URL, $emoWebPanelMWAPI . '?' . http_build_query(array(
        'action' => 'query',
        'meta' => 'tokens',
        'type' => 'userrights',
        'format' => 'json',
        'assert' => 'bot',
        'errorformat' => 'plaintext',
    )));
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, null);
    $output = curl_exec($ch);
    $output_json = json_decode($output, true);
    if (isset($output_json['errors'])) {
        $msg = 'Error obtaining user rights userrights token: ' . json_encode($output_json['errors']);
        goto endmw;
    }

    // Change user rights
    curl_setopt($ch, CURLOPT_URL, $emoWebPanelMWAPI);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array(
        'action' => 'userrights',
        'user' => $sync_mediawiki_username,
        'add' => implode('|', $add_groups),
        'remove' => implode('|', $remove_groups),
        'tags' => 'webpanel-ingame-privs-sync',
        'format' => 'json',
        'assert' => 'bot',
        'errorformat' => 'plaintext',
        'token' => $output_json['query']['tokens']['userrightstoken'],
    ));
    $output = curl_exec($ch);
    $output_json = json_decode($output, true);
    if (isset($output_json['errors'])) {
        $msg = 'Error modifying user rights: ' . json_encode($output_json['errors']);
        goto endmw;
    }

    // Prepare log
    $log_text =
        "\n* " . date("Y/m/d H:i:s") . " operation on [[User:" . $sync_mediawiki_username .
        "|]] (in-game: $sync_username)\n" .
        "** In-game privileges: " . implode(", ", array_keys($get_privs_sync_result["privs"])) . "\n" .
        "** Added: " . implode(", ", $add_groups) . "\n" .
        "** Removed: " . implode(", ", $remove_groups) . "\n";
    
    // Remove rights from old user (if any)
    if (!empty($sync_old_mediawiki_username)) {
        $old_remove_groups = array();
        $old_remove_groups[] = 'ingame-*';
        foreach ($emoWebPanelMWSyncPrivs as $privilege) {
            $old_remove_groups[] = 'ingame-' . $privilege;
        }
        // Get userrights token for user group changing
        curl_setopt($ch, CURLOPT_URL, $emoWebPanelMWAPI . '?' . http_build_query(array(
            'action' => 'query',
            'meta' => 'tokens',
            'type' => 'userrights',
            'format' => 'json',
            'assert' => 'bot',
            'errorformat' => 'plaintext',
        )));
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        $output = curl_exec($ch);
        $output_json = json_decode($output, true);
        if (isset($output_json['errors'])) {
            $msg = 'Error obtaining user rights userrights token: ' . json_encode($output_json['errors']);
            goto endmw;
        }

        // Change user rights
        curl_setopt($ch, CURLOPT_URL, $emoWebPanelMWAPI);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'action' => 'userrights',
            'user' => $sync_old_mediawiki_username,
            'remove' => implode('|', $old_remove_groups),
            'tags' => 'webpanel-ingame-privs-sync',
            'format' => 'json',
            'assert' => 'bot',
            'errorformat' => 'plaintext',
            'token' => $output_json['query']['tokens']['userrightstoken'],
        ));
        $output = curl_exec($ch);
        $output_json = json_decode($output, true);
        if (isset($output_json['errors'])) {
            $msg = 'Error modifying old user rights: ' . json_encode($output_json['errors']);
            goto endmw;
        }

        $log_text .= "** Changing linked account from [[User:{$sync_old_mediawiki_username}|]], ";
        $log_text .= "removed all synced user groups.\n";
    }

    // Get CSRF token for logging
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
        $msg = 'Error obtaining user rights csrf token: ' . json_encode($output_json['errors']);
        goto endmw;
    }

    // Do logging
    curl_setopt($ch, CURLOPT_URL, $emoWebPanelMWAPI);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array(
        'action' => 'edit',
        'title' => 'User:' . $emoWebPanelMWName . '/log',
        'appendtext' => $log_text,
        'tags' => 'webpanel-ingame-privs-sync',
        'format' => 'json',
        'assert' => 'bot',
        'errorformat' => 'plaintext',
        'token' => $output_json['query']['tokens']['csrftoken'],
    ));
    $output = curl_exec($ch);
    $output_json = json_decode($output, true);
    if (isset($output_json['errors'])) {
        $msg = 'Error logging changes: ' . json_encode($output_json['errors']);
        goto endmw;
    }

    if (!isset($msg)) {
        $msg = 'Successfully synced privileges.';
    }

    endmw:
    unlink($cookie_filename);

    endany:
}

endexec: // bottom of the above scripts!
?>
<!DOCTYPE HTML>
<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">

    <title>MediaWiki Integration - <?php echo htmlspecialchars($emoWebPanelName) ?></title>
</head>

<body>
    <p><?php echo $msg; ?></p>
</body>

</html>