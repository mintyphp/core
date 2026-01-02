<?php

/** @var array<string,string> $_GET */
$code = (int)($_GET['code'] ?? 200);
http_response_code($code);
echo 'Status: ' . $code;
