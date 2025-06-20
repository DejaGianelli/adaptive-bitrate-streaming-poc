<?php
$configs = require_once "configs.php";
require_once("functions.php");

$database = $configs["database"];

initialize_session();
guard();

$pdo = new PDO("mysql:host={$database["host"]};dbname={$database["db_name"]}", $database["username"], $database["password"]);

$video_id = $_GET["videoid"];
$manifest_uri = "http://localhost:8080/manifest.php?videoid={$video_id}";

$stmt = $pdo->prepare("SELECT id, title FROM videos WHERE id = :id");
$stmt->bindParam(":id", $video_id);
$stmt->execute();
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    http_response_code(404);
    echo "Video not found";
    exit;
}
?>

<head>
    <script src="./shaka-player.compiled.debug.js"></script>
    <script>
        const manifestUri = '<?php echo $manifest_uri ?>';

        function initApp() {
            // Install built-in polyfills to patch browser incompatibilities.
            shaka.polyfill.installAll();

            // Check to see if the browser supports the basic APIs Shaka needs.
            if (shaka.Player.isBrowserSupported()) {
                // Everything looks good!
                initPlayer();
            } else {
                // This browser does not have the minimum set of APIs we need.
                console.error('Browser not supported!');
            }
        }

        async function initPlayer() {
            // Create a Player instance.
            const video = document.getElementById('video');
            const player = new shaka.Player();
            await player.attach(video);

            // Attach player to the window to make it easy to access in the JS console.
            window.player = player;

            // Listen for error events.
            player.addEventListener('error', onErrorEvent);

            // Try to load a manifest.
            // This is an asynchronous process.
            try {
                await player.load(manifestUri);
                // This runs if the asynchronous load is successful.
                console.log('The video has now been loaded!');
            } catch (e) {
                // onError is executed if the asynchronous load fails.
                onError(e);
            }
        }

        function onErrorEvent(event) {
            // Extract the shaka.util.Error object from the event.
            onError(event.detail);
        }

        function onError(error) {
            // Log the error.
            console.error('Error code', error.code, 'object', error);
        }

        document.addEventListener('DOMContentLoaded', initApp);
    </script>
</head>
<body>
    <a href="/">Home</a>
    <h1><?= $video["title"]; ?></h1>
    <video id="video" width="640" poster="//shaka-player-demo.appspot.com/assets/poster.jpg" controls></video>
</body>