<?php

function initialize_session() {
    session_start([
        "name" => "VIDEO_SESS_ID"
    ]);
}

function guard() {
    if (!array_key_exists("is_logged", $_SESSION)) {
        header("Location: http://localhost:8080/login.php");
        exit();
    }
}

function get_video_stream_metadata($video_metadata) {
    return array_filter($video_metadata["streams"], function($stream) {
        return $stream["codec_type"] == "video";
    })[0];
}