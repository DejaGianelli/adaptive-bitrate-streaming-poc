<?php
require_once("functions.php");

initialize_session();
session_destroy();
header("Location: http://localhost:8080/login.php");
exit();
?>