<?php
$target = $_GET['target'] ?? 'final.php';
header('Location: ' . $target, true, 302);
exit;
