<?php
require_once __DIR__ . "/../app/bootstrap.php";
session_destroy();
session_regenerate_id();
session_unset();
header("Location: " . url("login.php"));
?>