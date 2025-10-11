<?php
/**
 * Service Worker Extension Diagnostic Script v2
 * Compatible with Flarum 2.0
 */

echo "=== Service Worker Extension Diagnostic ===\n\n";

// Detect Flarum root - check if we're in extension dir or root
$currentDir = __DIR__;
echo "Script location: $currentDir\n";

// If script is in extension directory: /path/to/flarum/vendor/steperlin/flarum-service-worker-cache/
// We need to go up 3 levels to reach Flarum root
if (strpos($currentDir, 'vendor/steperlin/flarum-service-worker-cache') !== false) {
    chdir(__DIR__ . '/../../..');
} else {
    // Script is in Flarum root already
    chdir(__DIR__);
}

$flarumRoot = getcwd();
echo "Flarum Root: $flarumRoot\n\n";

// Verify this is actually Flarum root
if (!file_exists('composer.json') || !file_exists('flarum')) {
    echo "❌ ERROR: Not in Flarum root directory!\n";
    echo "   Please run this script from Flarum root or copy it to vendor/steperlin/flarum-service-worker-cache/\n";
    exit(1);
}

// 1. Check composer package
echo "=== Composer Package ===\n";
$composerLock = json_decode(file_get_contents('composer.lock'), true);
$packageFound = false;
foreach ($composerLock['packages'] ?? [] as $package) {
    if ($package['name'] === 'steperlin/flarum-service-worker-cache') {
        $packageFound = true;
        echo "✅ Package installed\n";
        echo "   Version: {$package['version']}\n";
        break;
    }
}
if (!$packageFound) {
    echo "❌ Package NOT found in composer.lock\n";
    echo "   Run: composer require steperlin/flarum-service-worker-cache\n";
    exit(1);
}
echo "\n";

// 2. Check files
echo "=== Critical Files ===\n";
$extPath = 'vendor/steperlin/flarum-service-worker-cache';

$files = [
    'service-worker.js' => "$extPath/service-worker.js",
    'extend.php' => "$extPath/extend.php",
    'Middleware' => "$extPath/src/Middleware/RegisterServiceWorker.php",
    'Responder' => "$extPath/src/Http/ServiceWorkerResponder.php",
    'Controller' => "$extPath/src/Http/ServiceWorkerController.php",
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name exists\n";
        if ($name === 'service-worker.js') {
            $content = file_get_contents($path);
            if (preg_match('/v([\d.]+)/', $content, $m)) {
                echo "   Version in file: {$m[1]}\n";
            }
        }
    } else {
        echo "❌ $name NOT found: $path\n";
    }
}
echo "\n";

// 3. Check extension enabled status
echo "=== Extension Status ===\n";
$configPath = 'config.php';
if (file_exists($configPath)) {
    $config = include $configPath;
    
    // Flarum 2.0 stores enabled extensions in config
    if (isset($config['extensions'])) {
        echo "Enabled extensions (from config.php):\n";
        $swEnabled = false;
        foreach ($config['extensions'] as $ext) {
            if (strpos($ext, 'service-worker') !== false) {
                echo "✅ $ext\n";
                $swEnabled = true;
            }
        }
        if (!$swEnabled) {
            echo "❌ Service Worker extension NOT enabled\n";
            echo "   Run: php flarum extension:enable steperlin-service-worker-cache\n";
        }
    } else {
        echo "⚠️  No 'extensions' key in config.php\n";
    }
} else {
    echo "❌ config.php not found\n";
}

// Also check storage/flarum.json if exists
if (file_exists('storage/flarum.json')) {
    $storage = json_decode(file_get_contents('storage/flarum.json'), true);
    if (isset($storage['extensions_enabled'])) {
        echo "\nEnabled extensions (from storage/flarum.json):\n";
        foreach ($storage['extensions_enabled'] as $ext) {
            if (strpos($ext, 'service-worker') !== false) {
                echo "✅ $ext\n";
            }
        }
    }
}
echo "\n";

// 4. Test class loading
echo "=== Class Loading Test ===\n";
require 'vendor/autoload.php';

$classes = [
    'SteperLin\\ServiceWorkerCache\\Middleware\\RegisterServiceWorker',
    'SteperLin\\ServiceWorkerCache\\Http\\ServiceWorkerResponder',
    'SteperLin\\ServiceWorkerCache\\Http\\ServiceWorkerController',
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ $class loaded\n";
    } else {
        echo "❌ $class NOT loaded\n";
        echo "   Run: composer dump-autoload\n";
    }
}
echo "\n";

// 5. Test middleware instantiation
echo "=== Middleware Instantiation Test ===\n";
try {
    // Use Flarum 2.0 compatible Paths object
    $paths = new \Flarum\Foundation\Paths([
        'base' => $flarumRoot,
        'public' => "$flarumRoot/public",
        'storage' => "$flarumRoot/storage",
        'vendor' => "$flarumRoot/vendor",
    ]);
    
    $site = new \Flarum\Foundation\InstalledSite(
        $paths,
        include "$flarumRoot/config.php"
    );
    
    echo "✅ Flarum site booted successfully\n";
    
    // Try to get container
    $app = $site->bootApp();
    $container = $app->getContainer();
    echo "✅ Container available\n";
    
    // Check if middleware is registered
    if ($container->bound('flarum.forum.middleware')) {
        echo "✅ Forum middleware pipeline exists\n";
    } else {
        echo "❌ Forum middleware pipeline not found\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
}
echo "\n";

// 6. Direct file test
echo "=== Direct File Access Test ===\n";
$swPath = "$extPath/service-worker.js";
if (file_exists($swPath)) {
    $content = file_get_contents($swPath);
    $size = strlen($content);
    echo "✅ service-worker.js readable\n";
    echo "   Size: $size bytes\n";
    echo "   First 100 chars: " . substr($content, 0, 100) . "...\n";
} else {
    echo "❌ Cannot read service-worker.js\n";
}
echo "\n";

// 7. Recommendations
echo "=== Recommendations ===\n";
echo "1. If extension not enabled:\n";
echo "   php flarum extension:enable steperlin-service-worker-cache\n";
echo "   php flarum cache:clear\n\n";
echo "2. If class loading failed:\n";
echo "   composer dump-autoload\n";
echo "   php flarum cache:clear\n\n";
echo "3. If using PHP-FPM with opcache:\n";
echo "   sudo systemctl restart php-fpm\n\n";
echo "4. Test the endpoints:\n";
echo "   curl -I https://beiduofen.top/service-worker.js\n";
echo "   curl -I https://beiduofen.top/index.php?service-worker=1\n\n";

echo "=== Diagnostic Complete ===\n";
