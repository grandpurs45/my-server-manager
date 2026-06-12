<?php
define('MSM_AUTH_PUBLIC', true);

require_once __DIR__ . '/includes/bootstrap.php';

$authManager->logout();

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/logout.php');
$scriptDirectory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$baseUrl = ($scriptDirectory === '' || $scriptDirectory === '.') ? '/' : $scriptDirectory . '/';

header('Location: ' . $baseUrl . 'login.php');
exit;
