<?php

use Cake\Routing\Router;

$this->Html->script('create_video_page', ['block' => true]);
$this->Html->script('https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js', ['block' => true]);
?>

<h1>Upload Video</h1>

<div id="loading-indicator"></div>

<form id="upload-video-form" method="post" action="<?= Router::url(['_name' => 'videos:createPage']) ?>" enctype="multipart/form-data">
    <label>Choose a video:</label>
    <input type="file" name="file" accept=".mp4,video/mp4"/>
    <input type="submit" value="Upload" />
</form>