<?php
session_start();
require 'vendor/autoload.php';

use JKD\SSO\Client\Provider\Keycloak;

$config = require 'sso_config.php';

$provider = new Keycloak($config);

$authUrl = $provider->getAuthorizationUrl();
$_SESSION['oauth2state'] = $provider->getState();
header('Location: ' . $authUrl);
exit;
