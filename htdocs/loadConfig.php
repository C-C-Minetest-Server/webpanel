<?php

// Default settings

$emoWebPanelRoot = '/';
$emoWebPanelName = 'Minetest Web Panel';
$emoWebPanelAddress = '127.0.0.1';
$emoWebPanelPort = 30300;
$emoWebPanelEmailTimeout = '10 minutes';

require_once __DIR__ . '/config.php';

foreach (
    array(
        "emoWebPanelRoot",
        "emoWebPanelName",

        "emoWebPanelAddress",
        "emoWebPanelPort",
        "emoWebPanelSecret",

        "emoWebPanelSMTPHost",
        "emoWebPanelSMTPFrom",
        "emoWebPanelSMTPFromName",
    ) as $varname
) {
    if (!isset($$varname)) {
        throw new Exception("Configuration variable {$varname} cannot be null.");
    }
}
