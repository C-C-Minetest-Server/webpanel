<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . "/../loadConfig.php";

function prepareMail(): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = $GLOBALS['emoWebPanelSMTPHost'];
    $mail->SMTPAuth   = $GLOBALS['emoWebPanelSMTPAuth'];
    $mail->Username   = $GLOBALS['emoWebPanelSMTPUsername'];
    $mail->Password   = $GLOBALS['emoWebPanelSMTPPassword'];
    $mail->SMTPSecure = $GLOBALS['emoWebPanelSMTPSecure'];
    $mail->Port       = $GLOBALS['emoWebPanelSMTPPort'];

    $mail->setFrom($GLOBALS['emoWebPanelSMTPFrom'], $GLOBALS['emoWebPanelSMTPFromName']);

    return $mail;
}
