<?php

// Development-only fixture server. Never include this file from public/index.php.
if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'testing') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$prefix = '/ff14tools/partyfinder';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Match nginx's directory redirect when route() emits the base without a slash.
if ($path === $prefix) {
    header('Location: ' . $prefix . '/' . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    return;
}
$assets = [
    $prefix . '/vendor/tabler/1.7.1/tabler.min.css' => ['vendor/tabler/1.7.1/tabler.min.css', 'text/css'],
    $prefix . '/vendor/tabler/1.7.1/tabler.min.js' => ['vendor/tabler/1.7.1/tabler.min.js', 'text/javascript'],
    $prefix . '/css/partyfinder.css' => ['css/partyfinder.css', 'text/css'],
    $prefix . '/js/partyfinder.js' => ['js/partyfinder.js', 'text/javascript'],
];
if (isset($assets[$path])) {
    header('Content-Type: ' . $assets[$path][1]);
    readfile($root . '/public/' . $assets[$path][0]);
    return;
}
if ($path === '/favicon.ico') {
    http_response_code(204);
    return;
}

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
config(['cache.default' => 'array', 'session.driver' => 'array']);
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Http::fake([
    'jp.finalfantasyxiv.com/lodestone/blog/*' => Illuminate\Support\Facades\Http::response(
        file_get_contents(__DIR__ . '/../Fixtures/lodestone_blog_page.html'),
    ),
    'jp.finalfantasyxiv.com/lodestone/character/*' => Illuminate\Support\Facades\Http::response(
        file_get_contents(__DIR__ . '/../Fixtures/lodestone_blog_entry.html'),
    ),
]);

// Exercise the same base path as the nginx deployment, including assets/cookies.
$_SERVER['SCRIPT_NAME'] = $prefix . '/index.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['SCRIPT_FILENAME'] = $root . '/public/index.php';
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
