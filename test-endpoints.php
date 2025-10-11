<?php
/**
 * Test Service Worker Endpoints in Flarum 2.0
 */

chdir(__DIR__);
require 'vendor/autoload.php';

echo "=== Testing Service Worker Endpoints ===\n\n";

// Test 1: Direct file check
echo "Test 1: Check if service-worker.js file exists\n";
$swPath = 'vendor/steperlin/flarum-service-worker-cache/service-worker.js';
if (file_exists($swPath)) {
    $content = file_get_contents($swPath);
    echo "✅ File exists: " . strlen($content) . " bytes\n";
    if (preg_match('/v([\d.]+)/', $content, $m)) {
        echo "   Version: {$m[1]}\n";
    }
} else {
    echo "❌ File not found\n";
}
echo "\n";

// Test 2: Check middleware registration
echo "Test 2: Check middleware registration\n";
try {
    $paths = new \Flarum\Foundation\Paths([
        'base' => getcwd(),
        'public' => getcwd() . '/public',
        'storage' => getcwd() . '/storage',
        'vendor' => getcwd() . '/vendor',
    ]);
    
    $config = new \Flarum\Foundation\Config(include 'config.php');
    $site = new \Flarum\Foundation\InstalledSite($paths, $config);
    $app = $site->bootApp();
    
    echo "✅ Flarum booted\n";
    
    // Get extension manager
    $extensions = $app->getContainer()->make('flarum.extensions');
    $ext = $extensions->getExtension('steperlin-service-worker-cache');
    
    if ($ext) {
        echo "✅ Extension loaded: {$ext->getId()}\n";
        echo "   Version: {$ext->getVersion()}\n";
        
        // Check if enabled
        $enabled = $extensions->getEnabledExtensions();
        $isEnabled = false;
        foreach ($enabled as $e) {
            if ($e->getId() === 'steperlin-service-worker-cache') {
                $isEnabled = true;
                break;
            }
        }
        echo "   Enabled: " . ($isEnabled ? 'Yes' : 'No') . "\n";
    } else {
        echo "❌ Extension not found in manager\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}
echo "\n";

// Test 3: Simulate request to /service-worker.js
echo "Test 3: Simulate request to /service-worker.js\n";
try {
    $request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals()
        ->withUri(new \Laminas\Diactoros\Uri('https://beiduofen.top/service-worker.js'))
        ->withMethod('GET');
    
    echo "Request URI: {$request->getUri()}\n";
    echo "Request Path: {$request->getUri()->getPath()}\n";
    
    // Test middleware
    $middleware = new \SteperLin\ServiceWorkerCache\Middleware\RegisterServiceWorker(
        $app->getContainer()->make(\SteperLin\ServiceWorkerCache\Http\ServiceWorkerResponder::class)
    );
    
    // Create a handler that returns 404 if not intercepted
    $handler = new class implements \Psr\Http\Server\RequestHandlerInterface {
        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            return new \Laminas\Diactoros\Response\TextResponse('Not intercepted', 404);
        }
    };
    
    $response = $middleware->process($request, $handler);
    
    echo "Response Status: {$response->getStatusCode()}\n";
    $body = $response->getBody()->getContents();
    echo "Response Size: " . strlen($body) . " bytes\n";
    
    if ($response->getStatusCode() === 200) {
        echo "✅ Middleware intercepted request successfully!\n";
        if (strpos($body, 'Flarum Service Worker') !== false) {
            echo "✅ Content looks correct\n";
        }
    } else {
        echo "❌ Middleware did not intercept\n";
        echo "Response preview: " . substr($body, 0, 100) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "   {$e->getFile()}:{$e->getLine()}\n";
}
echo "\n";

// Test 4: Test route controller
echo "Test 4: Test route controller directly\n";
try {
    $controller = $app->getContainer()->make(\SteperLin\ServiceWorkerCache\Http\ServiceWorkerController::class);
    
    $request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals()
        ->withUri(new \Laminas\Diactoros\Uri('https://beiduofen.top/service-worker.js'))
        ->withMethod('GET');
    
    $response = $controller($request);
    
    echo "Response Status: {$response->getStatusCode()}\n";
    $body = $response->getBody()->getContents();
    echo "Response Size: " . strlen($body) . " bytes\n";
    
    if ($response->getStatusCode() === 200) {
        echo "✅ Controller works!\n";
    } else {
        echo "❌ Controller failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}

echo "\n=== Test Complete ===\n";
