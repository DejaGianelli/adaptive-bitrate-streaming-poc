<?php
$configs = require_once "configs.php";
require_once("functions.php");

initialize_session();
guard();

$database = $configs["database"];

$video_id = $_GET["videoid"];

$pdo = new PDO("mysql:host={$database["host"]};dbname={$database["db_name"]}", $database["username"], $database["password"]);

$stmt = $pdo->prepare("SELECT id FROM videos WHERE id = :id");
$stmt->bindParam(":id", $video_id);
$stmt->execute();
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    http_response_code(404);
    echo "Video not found";
    exit;
}

$filename = $_GET["file"];
$path = "/storage/videos/{$video_id}/{$filename}";

header('Content-Type: video/iso.segment');
readfile($path);
exit;