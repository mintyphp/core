<?php
$code = (int)($_GET['code'] ?? 200);
http_response_code($code);
echo 'Status: ' . $code;
