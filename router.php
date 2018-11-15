<?php

$CONFIG = [
    'cache_file' => 'cache.bin',
    'cache_life' => 1800,
    'cache_limit' => 1000,
];

function cacheing($assets) {
    global $CONFIG;
    $assets_path = implode('/', [$assets, '*.jpg']);
    $tmp = glob($assets_path);
    $cache = [
        'total' => count($tmp),
        $assets => array_map(function ($path) use ($assets) {
            return substr($path, strlen($assets) + 1, -4);
        }, array_slice($tmp, 0, $CONFIG["cache_limit"]))
    ];
    file_put_contents($CONFIG['cache_file'], serialize($cache), LOCK_EX);
    return $cache;
}

function cache_handler($assets) {
    global $CONFIG;
    if (is_file($CONFIG['cache_file']) && filectime($CONFIG['cache_file']) < $CONFIG['cache_life']) {
        $cache = unserialize(file_get_contents($CONFIG['cache_file'], FILE_USE_INCLUDE_PATH));
        if (!array_key_exists($assets, $cache)) {
            $cache = cacheing($assets);
        }
    } else {
        $cache = cacheing($assets);
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
        $meta['ids'] = array_slice($cache[$assets], $_GET["offset"], $_GET["length"]);
        echo json_encode($meta);
        break;
    default:
        header("Content-Type: application/json");
        $obj = [ 'method' => $method, 'status' => FALSE ];
        echo json_encode($obj);
    }
}

function http_handler($uri, $assets, $cache, $assets_list) {
    $html = file_get_contents($uri, FILE_USE_INCLUDE_PATH);
    $html = preg_replace('/\{year\}/', $assets, $html);
    $html = preg_replace('/\{total\}/', $cache['total'], $html);
    $btns = array_map(function ($val) use ($assets) {
        $cls = $assets == $val ? 'active' : '';
        return "<li><a class=\"{$cls}\" href=\"./index.html?assets={$val}\">{$val}</a></li>";
    }, $assets_list);
    echo preg_replace('/\{buttons\}/', implode(PHP_EOL, $btns), $html);
}

if (preg_match('/^\/index\.html\?*.*$/', $_SERVER["REQUEST_URI"])) {
    $assets_list = array_filter(scandir(dirname(__FILE__)), function ($path) {
        return is_dir($path) && !preg_match('/^\..*$/', $path);
    });
    $assets_list = array_values($assets_list);

    $assets = $_GET["assets"] ?: $assets_list[0];
    $cache = cache_handler($assets);

    if (isset($_GET["method"])) {
        api_handler($_GET["method"], $assets, $cache);
    } else {
        http_handler('index.html', $assets, $cache, $assets_list);
    }
} else {
    header('Location: /index.html');
}
?>
