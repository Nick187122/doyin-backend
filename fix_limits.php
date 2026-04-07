<?php
function updateFile($file, $search, $replace, $appendIfMissing = false) {
    if (!file_exists($file)) return;
    $content = file_get_contents($file);
    if (strpos($content, $search) !== false && !$appendIfMissing) return;

    if ($appendIfMissing && strpos($content, $search) === false) {
        $content = str_replace('listen 80;', "listen 80;\n    $search", $content);
    } else {
        $content = preg_replace('/' . preg_quote($search, '/') . '\s*=\s*.*/', $replace, $content);
    }
    file_put_contents($file, $content);
}

// 1. Update Nginx client_max_body_size
$nginxFile = '/etc/nginx/sites-available/doyin';
if (file_exists($nginxFile)) {
    $nginx = file_get_contents($nginxFile);
    if (strpos($nginx, 'client_max_body_size') === false) {
        $nginx = str_replace('server {', "server {\n    client_max_body_size 50M;", $nginx);
        file_put_contents($nginxFile, $nginx);
    }
}

// 2. Update PHP fpm upload limits
$phpFile = '/etc/php/8.3/fpm/php.ini';
if (file_exists($phpFile)) {
    $php = file_get_contents($phpFile);
    $php = preg_replace('/^upload_max_filesize\s*=.*/m', 'upload_max_filesize = 50M', $php);
    $php = preg_replace('/^post_max_size\s*=.*/m', 'post_max_size = 50M', $php);
    file_put_contents($phpFile, $php);
}

echo "Limits updated.\\n";
