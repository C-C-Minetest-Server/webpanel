<?php
/**
 * Ensure INTERNAL_SECRET Header is set in the request for accessing internal APIs.
 * 
 * If a request arrives without this header and the appropriate value, 403 will be returned.
 * 500 will be returned if $emoWebPanelInternalSecret is not set.
 */

if (empty($emoWebPanelInternalSecret)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Internal secret not set.';
    die();
}

if (empty($_SERVER['HTTP_INTERNAL_SECRET'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Internal secret not provided.';
    die();
}

if (trim($_SERVER['HTTP_INTERNAL_SECRET']) !== trim($emoWebPanelInternalSecret)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Internal secret mismatch.';
    die();
}
