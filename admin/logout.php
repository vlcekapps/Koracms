<?php
require_once __DIR__ . '/../db.php';
logout();
header('Location: ' . BASE_URL . '/admin/login.php');
exit;
