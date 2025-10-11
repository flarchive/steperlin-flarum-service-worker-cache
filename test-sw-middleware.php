<?php
/**
 * Test Service Worker Middleware
 * 
 * This script tests if the Service Worker middleware is properly registered
 * and can serve the service-worker.js file.
 */

use Flarum\Foundation\InstalledSite;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Uri;

require __DIR__.'/../../vendor/autoload.php';

echo "=== Service Worker Middleware Test ===\n\n";

// Load Flarum site
$site = new InstalledSite(
    dirname(__DIR__, 2),
    include dirname(__DIR__, 2) . '/config.php'
);

$container = $site->bootApp()->getContainer();

// Get middleware pipeline for forum
$middleware = $container->make('flarum.forum.middleware');

echo "Testing /service-worker.js endpoint:\n";
echo "-----------------------------------\n";

// Create test request
$request = ServerRequestFactory::fromGlobals()
    ->withUri(new Uri('https://beiduofen.top/service-worker.js'))
    ->withMethod('GET');

try {
    // Create a simple handler that returns 404 if middleware doesn't intercept
    $handler = new class implements \Psr\Http\Server\RequestHandlerInterface {
        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            return new \Laminas\Diactoros\Response\TextResponse('Not intercepted by middleware', 404);
        }
    };
    
    // Run through middleware stack
    $response = $middleware->pipe($handler)->process($request, $handler);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "\nResponse Headers:\n";
    foreach ($response->getHeaders() as $name => $values) {
        echo sprintf("  %-25s: %s\n", $name, implode(', ', $values));
    }
    
    $body = $response->getBody()->getContents();
    echo "\nBody Length: " . strlen($body) . " bytes\n";
    echo "Body Preview (first 300 chars):\n";
    echo substr($body, 0, 300) . "\n";
    
    if ($response->getStatusCode() === 200) {
        if (strpos($body, 'Flarum Service Worker') !== false) {
            echo "\n✅ SUCCESS: Service Worker file is being served!\n";
            
            // Check version
            if (preg_match('/v([\d.]+)/', $body, $matches)) {
                echo "   Version detected: {$matches[1]}\n";
            }
        } else {
            echo "\n⚠️  WARNING: Got 200 but content doesn't look like Service Worker\n";
        }
    } else {
        echo "\n❌ FAILED: Expected 200, got " . $response->getStatusCode() . "\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n\nTesting /index.php?service-worker=1 endpoint:\n";
echo "---------------------------------------------\n";

$request2 = ServerRequestFactory::fromGlobals()
    ->withUri(new Uri('https://beiduofen.top/index.php?service-worker=1'))
    ->withQueryParams(['service-worker' => '1'])
    ->withMethod('GET');

try {
    $handler = new class implements \Psr\Http\Server\RequestHandlerInterface {
        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            return new \Laminas\Diactoros\Response\TextResponse('Not intercepted by middleware', 404);
        }
    };
    
    $response2 = $middleware->pipe($handler)->process($request2, $handler);
    
    echo "Status Code: " . $response2->getStatusCode() . "\n";
    $body2 = $response2->getBody()->getContents();
    echo "Body Length: " . strlen($body2) . " bytes\n";
    
    if ($response2->getStatusCode() === 200 && strpos($body2, 'Flarum Service Worker') !== false) {
        echo "✅ SUCCESS: Dynamic route is working!\n";
    } else {
        echo "❌ FAILED: Dynamic route not working\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
