<?php
require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/core/session_auth.php';

start_secure_session();
logout_user();
redirect_to(SITE_URL . 'vue_authentification.php');
?>
