<?php
$file = 'test.txt';
$content = 'This is a test.';

if (file_put_contents($file, $content) !== false) {
    echo "File writing successful";
} else {
    echo "File writing failed";
}
?>
