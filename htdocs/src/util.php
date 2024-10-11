<?php

require_once __DIR__ . "/../loadConfig.php";

function getRootURL()
{
    global $emoWebPanelHost, $emoWebPanelRoot;
    if (isset($emoWebPanelHost)) {
        return $emoWebPanelHost . $emoWebPanelRoot;
    }
    $protocol = (!empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
    $port = $_SERVER['SERVER_PORT'] ? ':' . $_SERVER['SERVER_PORT'] : '';
    return $protocol . $_SERVER['SERVER_NAME'] . $port . $emoWebPanelRoot;
}
