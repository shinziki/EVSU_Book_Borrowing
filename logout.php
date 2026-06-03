<?php
require_once 'config/functions.php';

$timedOut = isset($_GET['timeout']) && $_GET['timeout'] === '1';

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

if ($timedOut) {
    header('Location: login.php?reason=session_expired');
} else {
    header('Location: login.php');
}
exit; 