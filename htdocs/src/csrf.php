<?php

function csrf_start()
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['token'] = $token;
    return $token;
}

function csrf_reset()
{
    unset($_SESSION['token']);
}

function csrf_check($token)
{
    return hash_equals($_SESSION['token'], $token);
}

function csrf_check_post()
{
    return isset($_POST['token']) ? csrf_check($_POST['token']) : false;
}

function csrf_form_field()
{
    $token = isset($_SESSION['token']) ? $_SESSION['token'] : csrf_start();
    echo "<input type=\"hidden\" name=\"token\" value=\"{$token}\" />";
}
