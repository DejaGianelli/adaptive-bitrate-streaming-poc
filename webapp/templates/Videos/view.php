<?php
$this->Html->scriptBlock('
    const MANIFEST_URL = "' . $videoManifestUrl . '";
', ['block' => 'script']);
$this->Html->script('shaka-player.compiled.debug', ['block' => true]);
$this->Html->script('view_video_page', ['block' => true]);
?>

<h1><?= $videoTitle; ?></h1>
<video id="video" width="640" poster="//shaka-player-demo.appspot.com/assets/poster.jpg" controls></video>

<script>
    const MANIFEST_URL = '<?php echo $videoManifestUrl ?>';
</script>