<?php
require_once __DIR__ . "/includes/auth.php";
$_SESSION = [];
session_destroy();
setcookie("lembrar_token", "", time() - 3600, "/");
header("Location: login");
exit;
