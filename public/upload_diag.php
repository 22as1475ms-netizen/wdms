<?php
header('Content-Type: text/plain');
echo 'upload_max_filesize=' . ini_get('upload_max_filesize') . PHP_EOL;
echo 'post_max_size=' . ini_get('post_max_size') . PHP_EOL;
echo 'max_file_uploads=' . ini_get('max_file_uploads') . PHP_EOL;
echo 'session_save_path=' . session_save_path() . PHP_EOL;
?>
