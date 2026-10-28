<?php
require_once __DIR__ . '/config/app.php';
if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
