<?php
/**
 * Check Extension Status in Flarum 2.0
 */

// Detect correct path
$currentDir = __DIR__;
if (strpos($currentDir, 'vendor/steperlin/flarum-service-worker-cache') !== false) {
    chdir(__DIR__ . '/../../..');
} else {
    chdir(__DIR__);
}
$flarumRoot = getcwd();

require 'vendor/autoload.php';

echo "=== Checking Extension Status ===\n\n";

// Method 1: Check database
try {
    $config = include 'config.php';
    $db = $config['database'];
    
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}",
        $db['username'],
        $db['password']
    );
    
    $prefix = $db['prefix'];
    
    // Flarum 2.0 uses 'settings' table with key 'extensions_enabled'
    $stmt = $pdo->prepare("SELECT value FROM {$prefix}_settings WHERE `key` = 'extensions_enabled'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "Extensions from database ({$prefix}_settings):\n";
        $extensions = json_decode($result['value'], true);
        foreach ($extensions as $ext) {
            $marker = (strpos($ext, 'service-worker') !== false) ? '✅' : '  ';
            echo "$marker $ext\n";
        }
    } else {
        echo "⚠️  No extensions_enabled key in settings table\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: {$e->getMessage()}\n";
}

echo "\n";

// Method 2: Check storage/flarum.json
if (file_exists('storage/flarum.json')) {
    echo "Extensions from storage/flarum.json:\n";
    $storage = json_decode(file_get_contents('storage/flarum.json'), true);
    if (isset($storage['extensions_enabled'])) {
        foreach ($storage['extensions_enabled'] as $ext) {
            $marker = (strpos($ext, 'service-worker') !== false) ? '✅' : '  ';
            echo "$marker $ext\n";
        }
    } else {
        echo "⚠️  No extensions_enabled in storage/flarum.json\n";
    }
} else {
    echo "⚠️  storage/flarum.json not found\n";
}

echo "\n";

// Method 3: Use Flarum API
echo "Using Flarum API to check extensions:\n";
try {
    $paths = new \Flarum\Foundation\Paths([
        'base' => $flarumRoot,
        'public' => "$flarumRoot/public",
        'storage' => "$flarumRoot/storage",
        'vendor' => "$flarumRoot/vendor",
    ]);
    
    $config = new \Flarum\Foundation\Config(
        include "$flarumRoot/config.php"
    );
    
    $site = new \Flarum\Foundation\InstalledSite(
        $paths,
        $config
    );
    
    $app = $site->bootApp();
    $container = $app->getContainer();
    
    // Get extension manager
    if ($container->bound('flarum.extensions')) {
        $extensions = $container->make('flarum.extensions');
        $enabled = $extensions->getEnabledExtensions();
        
        echo "Enabled extensions from Extension Manager:\n";
        foreach ($enabled as $ext) {
            $id = $ext->getId();
            $marker = (strpos($id, 'service-worker') !== false) ? '✅' : '  ';
            echo "$marker $id (v{$ext->getVersion()})\n";
        }
    } else {
        echo "⚠️  Extension manager not available\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "   File: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n=== Check Complete ===\n";
