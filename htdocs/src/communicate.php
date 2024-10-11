<?php

require_once __DIR__ . "/../loadConfig.php";

function request(array $data) {
    global $emoWebPanelSecret, $emoWebPanelAddress, $emoWebPanelPort;
    $data["secret"] = $emoWebPanelSecret;
    $json_data = json_encode($data);

    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_connect($socket, $emoWebPanelAddress, $emoWebPanelPort);
    socket_write($socket, $json_data, strlen($json_data));

    $ret_json = '';
    while ($out = socket_read($socket, 2048)) {
        $ret_json .= $out;
    }
    return json_decode($ret_json, true);
}
