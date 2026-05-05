<?php
/**
 * Test Panel Logout
 */
session_start();
session_destroy();
header('Location: test_login.php');
exit;
