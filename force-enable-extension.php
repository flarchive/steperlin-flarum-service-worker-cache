<?php
/**
 * Force Enable Service Worker Extension
 */

chdir(__DIR__);
$flarumRoot = getcwd();

echo "=== Force Enable Service Worker Extension ===\n\n";

try {
    $config = include 'config.php';
    $db = $config['database'];
    
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['database']};charset={$db['charset']}",
        $db['username'],
        $db['password']
    );
    
    $prefix = $db['prefix'];
    
    echo "Step 1: Check current extensions...\n";
    $stmt = $pdo->prepare("SELECT value FROM {$prefix}_settings WHERE `key` = 'extensions_enabled'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $extensions = json_decode($result['value'], true);
        echo "Current extensions: " . implode(', ', $extensions) . "\n\n";
    } else {
        $extensions = [];
        echo "No extensions currently enabled\n\n";
    }
    
    echo "Step 2: Add steperlin-service-worker-cache...\n";
    if (!in_array('steperlin-service-worker-cache', $extensions)) {
        $extensions[] = 'steperlin-service-worker-cache';
        echo "✅ Added to list\n";
    } else {
        echo "✅ Already in list\n";
    }
    
    echo "\nStep 3: Update database...\n";
    $json = json_encode($extensions);
    
    if ($result) {
        // Update existing record
        $stmt = $pdo->prepare("UPDATE {$prefix}_settings SET value = ? WHERE `key` = 'extensions_enabled'");
        $stmt->execute([$json]);
        echo "✅ Updated extensions_enabled\n";
    } else {
        // Insert new record
        $stmt = $pdo->prepare("INSERT INTO {$prefix}_settings (`key`, value) VALUES ('extensions_enabled', ?)");
        $stmt->execute([$json]);
        echo "✅ Inserted extensions_enabled\n";
    }
    
    echo "\nStep 4: Verify...\n";
    $stmt = $pdo->prepare("SELECT value FROM {$prefix}_settings WHERE `key` = 'extensions_enabled'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $extensions = json_decode($result['value'], true);
    
    echo "Enabled extensions:\n";
    foreach ($extensions as $ext) {
        $marker = (strpos($ext, 'service-worker') !== false) ? '✅' : '  ';
        echo "$marker $ext\n";
    }
    
    echo "\n=== SUCCESS ===\n";
    echo "Extension enabled in database!\n\n";
    echo "Now run these commands:\n";
    echo "1. php flarum cache:clear\n";
    echo "2. sudo systemctl restart php8.3-fpm\n";
    echo "3. curl -I https://beiduofen.top/service-worker.js\n";
    
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}
