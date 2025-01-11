<?php

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

// 1. Web frontend

// Protocol, hostname and port
// e.g. https://webpanel-twi.1f616emo.xyz
$emoWebPanelHost = null;

// Root of the site
// e.g. /webpanel -> https://webpanel-twi.1f616emo.xyz/webpanel
$emoWebPanelRoot = '/';

// Name of the site
$emoWebPanelName = '1F616EMO Survival Server';

// 2. Backend connection

// Hostname / IP of the backend
// e.g. 127.0.0.1
$emoWebPanelAddress = 'host.docker.internal';

// Port of the backend
$emoWebPanelPort = 30300;

// Secret for connecting to the backend
$emoWebPanelSecret = '<SECRET>';

// 3. PHPMailer settings

$emoWebPanelSMTPHost = 'mail.example.com';
$emoWebPanelSMTPAuth = true;
$emoWebPanelSMTPUsername = 'noreply-mt@example.com';
$emoWebPanelSMTPPassword = '<SECRET>';
$emoWebPanelSMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$emoWebPanelSMTPPort = 465;

// 4. Email settings

// Email address to send emails from
// This is usually the same as $emoWebPanelSMTPUsername
// e.g. noreply-mt@example.com
$emoWebPanelSMTPFrom = $emoWebPanelSMTPUsername;

// Display name used in emails
$emoWebPanelSMTPFromName = $emoWebPanelName;

// Time before a confirmation email would time out
// Textual representation of webpanel.email_confirm_timeout
$emoWebPanelEmailTimeout = '10 minutes';

// 5. MediaWiki Integration

// URL to MediaWiki API
// e.g. https://wiki.example.com/w/api.php
$emoWebPanelMWAPI = null;

// Username of the in-game privileges worker
$emoWebPanelMWName = null;

// Bot Password of the in-game privileges worker
$emoWebPanelMWBotPassword = null;

// List of privileges to be synced by the worker
$emoWebPanelMWSyncPrivs = null;
