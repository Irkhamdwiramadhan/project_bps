<?php
session_start();
require 'vendor/autoload.php';
use JKD\SSO\Client\Provider\Keycloak;

$config = require 'sso_config.php';
$provider = new Keycloak($config);

// Validasi state
if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    unset($_SESSION['oauth2state']);
    exit('Invalid state');
}

try {
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    $user = $provider->getResourceOwner($token);

    $_SESSION['pegawai'] = [
        'nama' => $user->getName(),
        'nip' => $user->getNip(),
        'email' => $user->getEmail(),
        'jabatan' => $user->getJabatan(),
    ];

    header('Location: dashboard.php');
    exit;
} catch (Exception $e) {
    exit('Login gagal: ' . $e->getMessage());
}
