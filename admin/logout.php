<?php
/**
 * Log out and return to the login screen.
 */

require_once __DIR__ . '/../includes/auth.php';

logout_user();
redirect(BASE_URL . '/admin/login.php');
