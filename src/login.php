<?php
$configs = require_once "configs.php";
require_once("functions.php");
initialize_session();

$database = $configs["database"];

$pdo = new PDO("mysql:host={$database["host"]};dbname={$database["db_name"]}", $database["username"], $database["password"]);


if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $username = $_POST["username"];
    $pwd = $_POST["password"];
    
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :username");
    $stmt->bindParam(":username", $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo "<p>User not found!</p>";
    } else {
        if (password_verify($pwd, $user["password_hash"])) {
            $_SESSION['user_id']    = $user["id"];
            $_SESSION['username']   = $user["username"];
            $_SESSION['is_logged']  = true;
            $_SESSION['logged_at']  = time();
            header("Location: http://localhost:8080");
            exit();
        } else {
            echo "<p>Invalid credentials!</p>";
        }
    }
}

echo "<p>Session ID: " . session_id() . "</p>";

if (array_key_exists("is_logged", $_SESSION)) {
    echo "<p>Olá {$_SESSION['username']}" . "</p>";
    echo "<p>Logged in at: " . $_SESSION['logged_at'] . "</p>";
}
?>

<h1>Login</h1>
<form action="login.php" method="post" enctype="application/x-www-form-urlencoded">
    <input name="username" type="text" />
    <input name="password" type="password" />
    <input type="submit" value="Enter"/>
</form>