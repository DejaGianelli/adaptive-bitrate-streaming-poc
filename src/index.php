<?php
$configs = require_once "configs.php";
require_once("functions.php");

$database = $configs["database"];

$pdo = new PDO("mysql:host={$database["host"]};dbname={$database["db_name"]}", $database["username"], $database["password"]);
$stmt = $pdo->prepare("SELECT id, title FROM videos ORDER BY id DESC");
$stmt->execute();
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

initialize_session();
guard();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POC Video Server</title>
</head>

<body>
    <a href="/logout.php">Logout</a>

    <h1>Upload Video</h1>

    <form method="post" action="process_video.php" enctype="multipart/form-data">
        <label for="avatar">Choose a video:</label>
        <input type="file" id="video-upload-input" name="video" />
        <input type="submit" value="Upload" />
    </form>

    <h2>Videos</h2>

    <table>
        <thead>
            <tr>
                <th>Id</th>
                <th>Title</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($videos as $video) { ?>
            <tr>
                <td><?= $video["id"]; ?></td>
                <td><?= $video["title"]; ?></td>
                <td><a href="/video.php?videoid=<?= $video["id"]; ?>">Edit</a></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>

</body>

</html>