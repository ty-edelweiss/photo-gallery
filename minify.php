<?php

$path = $argv[1] ?: '.';

$html = file_get_contents('index.html', FILE_USE_INCLUDE_PATH);
$src = file_get_contents('router.php', FILE_USE_INCLUDE_PATH);

function minify_html($html) {
    $search = ['/\>[^\S ]+/s', '/[^\S ]+\</s', '/(?!=\$)(\s)+/s'];
    $replace = ['>', '<', '\\1'];
    return preg_replace($search, $replace, $html);
}

$minimum_html = minify_html($html);
$optimized_src = preg_replace(['/<\?php\\n\\n/', '/\\nfunction http_handler\(.*\) .+$/s'], ['', ''], $src);

$buffer = <<< 'EOL'
<?php
ini_set('display_errors', "Off");

$HTML = <<< 'EOM'
{html}
EOM;

{src}
function http_handler($assets, $cache, $assets_list) {
    global $HTML;
    $btns = array_map(function ($val) use ($assets) {
        $cls = $assets == $val ? 'active' : '';
        return "<li><a class=\"btn {$cls}\" href=\"./index.php?assets={$val}\">{$val}</a></li>";
    }, $assets_list);
    $search = ['/\{year\}/', '/\{total\}/', '/\{buttons\}/'];
    $replace = [$assets, $cache['total'], implode(PHP_EOL, $btns)];
    echo preg_replace($search, $replace, $HTML);
}

if (preg_match('/^\/index\.php\?*.*$/', $_SERVER["REQUEST_URI"])) {
    $assets_list = array_filter(scandir(dirname(__FILE__)), function ($path) {
        return is_dir($path) && !preg_match('/^\..*$/', $path);
    });
    $assets_list = array_values($assets_list);

    $assets = $_GET["assets"] ?: $assets_list[0];
    $cache = cache_handler($assets);

    if (isset($_GET["method"])) {
        api_handler($_GET["method"], $assets, $cache);
    } else {
        http_handler($assets, $cache, $assets_list);
    }
} else {
    header('Location: /index.php');
}

?>
EOL;

$buffer = preg_replace(['/\{html\}/', '/\{src\}/'], [$minimum_html, $optimized_src], $buffer);

$path = trim($path, '/');
file_put_contents('gallery.php', $buffer);

?>
