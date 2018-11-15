<?php

$CONFIG = [
    'temp_dir' => 'tmp',
    'temp_file' => 'cache.bin',
    'temp_life' => 1800
];

if (!file_exists($CONFIG['temp_dir'])) {
    mkdir($CONFIG['temp_dir'], 0700);
}

function cacheing($assets) {
    $assets_path = implode('/', [$assets, '*.jpg']);
    return array_map(function ($path) use ($assets) {
        return substr($path, strlen($assets) + 1, -4);
    }, glob($assets_path));
}

function cache_handler($assets) {
    global $CONFIG;
    $cache_file = implode('/', [$CONFIG['temp_dir'], $CONFIG['temp_file']]);
    if (file_exists($cache_file) && filectime($cache_file) < $CONFIG['temp_life']) {
        $cache = unserialize(file_get_contents($cache_file, FILE_USE_INCLUDE_PATH));
        if (!array_key_exists($assets, $cache)) {
            $cache = [$assets => cacheing($assets)];
            file_put_contents($cache_file, serialize($cache), LOCK_EX);
        }
    } else {
        $cache = [$assets => cacheing($assets)];
        file_put_contents($cache_file, serialize($cache), LOCK_EX);
    }
    return $cache;
}

function api_handler($method, $assets, $cache) {
    switch ($method) {
    case 'image':
        header("Content-Type: image/jpg");
        $path = implode('/', [$assets, $_GET["id"] . '.jpg']);
        readfile($path, TRUE);
        break;
    case 'meta':
        header("Content-Type: application/json");
        $meta = [ 'method' => $method, 'status' => TRUE ];
        $meta['id'] = array_slice($cache[$assets], $_GET["offset"], $_GET["length"]);
        echo json_encode($meta);
        break;
    default:
        header("Content-Type: application/json");
        $obj = [ 'method' => $method, 'status' => FALSE ];
        echo json_encode($obj);
    }
}

function http_handler($uri, $assets, $cache) {
    $html = file_get_contents($uri, FILE_USE_INCLUDE_PATH);
    $html = preg_replace('/\{year\}/', $assets, $html);
    $html = preg_replace('/\{total\}/', count($cache[$assets]), $html);
    echo $html;
}

$assets = '2017';
if (preg_match('/^\/index\.html\?*.*$/', $_SERVER["REQUEST_URI"])) {
    $cache = cache_handler($assets);

    if (isset($_GET["method"])) {
        api_handler($_GET["method"], $assets, $cache);
    } else {
        http_handler('index.html', $assets, $cache);
    }
} else {
    header('Location: /index.html');
}
?>
