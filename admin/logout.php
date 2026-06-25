<?php

require_once __DIR__ . '/../db.php';
sendNoStoreNoIndexHeaders();

requireHttpMethods(['GET']);

logout();
header('Location: ' . BASE_URL . '/index.php');
exit;
