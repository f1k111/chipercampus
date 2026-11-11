<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

security_headers();
init_session();
logout();
header('Location: ' . BASE_URL . '/login.php');
exit;
