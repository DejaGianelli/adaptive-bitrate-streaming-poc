<?php
$configs = require_once "configs.php";
require_once("functions.php");

initialize_session();
guard();

$database = $configs["database"];

$pdo = new PDO("mysql:host={$database["host"]};dbname={$database["db_name"]}", $database["username"], $database["password"]);

$video_id = uniqid();
$title = "Default Title";

$stmt = $pdo->prepare("INSERT INTO videos (id, title) VALUES (:id, :title)");
$stmt->bindParam(":id", $video_id);
$stmt->bindParam(":title", $title);
$stmt->execute();

$uploads_dir = "/storage/videos/{$video_id}";
mkdir($uploads_dir);

$filename = $_FILES["video"]["name"];
$tmp_file = $_FILES["video"]["tmp_name"];

$upload_file = $uploads_dir . "/" . $filename;
if (!move_uploaded_file($tmp_file, $upload_file)) {
    throw new Exception("Could not upload file!");
}

# Gets video metadata in json format
$command = "ffprobe -v error -show_format -of json -show_streams {$upload_file}";
//echo "Running command > {$command} <br/>";
$output_json = shell_exec($command);
//echo "<pre>$output_json</pre>";

# Convert from json to associative array
$video_metadata = json_decode($output_json, true);

$video_resolution = get_video_stream_metadata($video_metadata)["height"];

$path_parts = pathinfo($upload_file);

// Recommended Bitrate Ladder (H.264)
$bit_rate_ladder = [
    240  => ["resolution" => "240p", "bitrate" => "500k", "maxrate" => "1000k"],
    360  => ["resolution" => "360p", "bitrate" => "1000k", "maxrate" => "2000k"],
    480  => ["resolution" => "480p", "bitrate" => "1500k", "maxrate" => "3000k"],
    720  => ["resolution" => "720p", "bitrate" => "3000k", "maxrate" => "6000k"],
    1080 => ["resolution" => "1080p", "bitrate" => "5000k", "maxrate" => "10000k"],
];

$bit_rate_ladder = array_filter($bit_rate_ladder, function($k) use ($video_resolution) {
    return $k <= $video_resolution;
}, ARRAY_FILTER_USE_KEY);

// Creating representation dirs
foreach ($bit_rate_ladder as $res => $configs) {
    shell_exec("mkdir -p {$uploads_dir}/{$res}p");
}

// Creating representation videos
$representations_count = count($bit_rate_ladder);

$cmd =  "(ffmpeg -i {$upload_file} " .
        "-filter_complex \"" .
        "[0:v:0]split={$representations_count}";

foreach ($bit_rate_ladder as $res => $configs) {
    $cmd .= "[v{$res}]";
}

$cmd .= ";";

foreach ($bit_rate_ladder as $res => $configs) {
    $cmd .= "[v{$res}]scale=-2:{$res}[v{$res}out];";
}

$cmd .= "\" ";
$cmd .= "-c:a:0 aac -b:a:0 128k ";
$cmd .= "-g 48 -keyint_min 48 ";
$cmd .= "-crf 22 ";
$cmd .= "-profile:v high ";
$cmd .= "-movflags +faststart ";
$cmd .= "-movflags +faststart ";

foreach ($bit_rate_ladder as $res => $configs) {
        $bitrate = $configs["bitrate"];
        $maxrate = $configs["maxrate"];

    	$cmd .= "-map [v{$res}out] -map 0:a:0 ";
    	$cmd .= "-c:v:0 libx264 ";
    	$cmd .= "-b:v:0 {$bitrate} ";
    	$cmd .= "-maxrate {$bitrate} ";
    	$cmd .= "-bufsize {$maxrate} ";
    	$cmd .= "-f {$path_parts['extension']} ";
    	$cmd .= "{$uploads_dir}/{$res}p/output.{$path_parts['extension']} ";
}

$cmd .= ") && ";

// Creating DASH segments
$cmd .= "(ffmpeg ";

foreach ($bit_rate_ladder as $res => $configs) {
    $cmd .= "-i {$uploads_dir}/{$res}p/output.mp4 ";
}

for ($i = 0; $i < $representations_count; $i++) {
   $cmd .= "-map {$i} ";
}

$cmd .= "-c copy ";
$cmd .= "-use_timeline 1 -use_template 1 ";
$cmd .= "-adaptation_sets \"id=0,streams=v id=1,streams=a\" ";
$cmd .= "-init_seg_name \"init-stream\\\$RepresentationID\\\$.m4s\" ";
$cmd .= "-media_seg_name \"chunk-stream\\\$RepresentationID\\\$-\\\$Number%05d\\\$.m4s\" ";
$cmd .= "-f dash {$uploads_dir}/manifest.mpd ) && ";


$cmd .= "sed -i 's|initialization=\"init-|initialization=\"segment.php?videoid={$video_id}\&amp;file=init-|g' {$uploads_dir}/manifest.mpd && ";
$cmd .= "sed -i 's|media=\"chunk-|media=\"segment.php?videoid={$video_id}\&amp;file=chunk-|g' {$uploads_dir}/manifest.mpd ";

$full_cmd = "nohup bash -c " . escapeshellarg($cmd) . " > /dev/null 2>&1 &";
//echo "Running command > {$full_cmd} <br/>";
shell_exec($full_cmd);

header("Location: /");
exit();