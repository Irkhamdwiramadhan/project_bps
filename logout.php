<?php
session_start();
require 'vendor/autoload.php';
use JKD\SSO\Client\Provider\Keycloak;

$config = require 'sso_config.php';
$provider = new Keycloak($config);

session_destroy();

$logoutUrl = $provider->getLogoutUrl();
header('Location: ' . $logoutUrl);
exit;
