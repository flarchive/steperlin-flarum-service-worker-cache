<?php

/**
 * Flarum Service Worker Cache Extension
 * 
 * 自动化缓存扩展，无需手动配置即可使用
 * 通过Service Worker实现智能缓存策略，显著提升论坛性能
 * 
 * @package SteperLin\ServiceWorkerCache
 * @author Steper Lin <steper.lin@icloud.com>
 * @license Apache-2.0
 * @version 1.3.3
 */

namespace SteperLin\ServiceWorkerCache;

use Flarum\Extend;
use Flarum\Frontend\Document;
use SteperLin\ServiceWorkerCache\Listeners\AddServiceWorkerAssets;
use SteperLin\ServiceWorkerCache\Listeners\ExtensionLifecycleListener;
use SteperLin\ServiceWorkerCache\Middleware\RegisterServiceWorker;
use SteperLin\ServiceWorkerCache\Http\ServiceWorkerController;

return [
    // HTTP中间件 - 拦截所有对/service-worker.js的请求
    (new Extend\Middleware('forum'))
        ->add(RegisterServiceWorker::class),
        
    (new Extend\Middleware('api'))
        ->add(RegisterServiceWorker::class),

    // 前端资源注册 - 简化的Service Worker注册脚本避免卡住
    (new Extend\Frontend('forum'))
        ->content(function (Document $document) {
            // 简化的Service Worker注册脚本，避免页面加载卡住
            $document->foot[] = '
            <script>
                (function() {
                    "use strict";
                    
                    console.log("[SW Cache] 🚀 Initializing Service Worker registration...");
                    
                    if (!("serviceWorker" in navigator)) {
                        console.warn("[SW Cache] ⚠️ Service Worker not supported in this browser");
                        return;
                    }

                    function buildServiceWorkerUrl() {
                        var baseUrl = (window.app && app.forum && typeof app.forum.attribute === "function")
                            ? app.forum.attribute("baseUrl")
                            : window.location.origin;
                        if (!baseUrl) {
                            baseUrl = window.location.origin;
                        }
                        return baseUrl.replace(/\/?$/, "/") + "index.php?service-worker=1";
                    }

                    function deriveScopeFromUrl(url) {
                        try {
                            var parsed = new URL(url, window.location.href);
                            var path = parsed.pathname;
                            return path.slice(0, path.lastIndexOf("/") + 1) || "/";
                        } catch (error) {
                            console.warn("[SW Cache] ⚠️ Failed to parse Service Worker URL:", error);
                            return "/";
                        }
                    }
                    
                    function registerServiceWorker() {
                        try {
                            var swUrl = buildServiceWorkerUrl();
                            var scope = deriveScopeFromUrl(swUrl);

                            console.log("[SW Cache] 📡 Registering Service Worker...", swUrl, "scope:", scope);
                            
                            navigator.serviceWorker.register(swUrl, {
                                scope: scope,
                                updateViaCache: "none"
                            })
                            .then(function(registration) {
                                console.log("[SW Cache] ✅ Service Worker registered successfully");
                                console.log("[SW Cache] 📍 Scope:", registration.scope);
                                
                                registration.update().catch(function(error) {
                                    console.warn("[SW Cache] Update check failed:", error);
                                });
                            })
                            .catch(function(error) {
                                console.error("[SW Cache] ❌ Service Worker registration failed:", error);
                            });
                        } catch(error) {
                            console.error("[SW Cache] ❌ Service Worker registration error:", error);
                        }
                    }
                    
                    if (document.readyState === "loading") {
                        document.addEventListener("DOMContentLoaded", registerServiceWorker);
                    } else {
                        setTimeout(registerServiceWorker, 100);
                    }
                })();
            </script>';
        }),

    // 事件监听器注册 - 用于扩展功能和生命周期管理
    (new Extend\Event())
        ->listen(\Flarum\Frontend\Events\Rendering::class, AddServiceWorkerAssets::class)
        ->subscribe(ExtensionLifecycleListener::class),
        
    // API路由扩展（用于缓存控制和部署状态）
    (new Extend\Routes('api'))
        ->get('/service-worker/status', 'service-worker.status', function() {
            return \Laminas\Diactoros\Response\JsonResponse::fromJsonString(json_encode([
                'enabled' => true,
                'version' => '1.3.3',
                'cache_strategy' => 'intelligent',
                'flarum_version' => '2.0.0@beta',
                'deployment_status' => \SteperLin\ServiceWorkerCache\Installer::getStatus()
            ]));
        })
        ->post('/service-worker/redeploy', 'service-worker.redeploy', function() {
            $success = \SteperLin\ServiceWorkerCache\Installer::redeploy();
            return \Laminas\Diactoros\Response\JsonResponse::fromJsonString(json_encode([
                'success' => $success,
                'message' => $success ? 'Redeployment completed' : 'Redeployment failed',
                'status' => \SteperLin\ServiceWorkerCache\Installer::getStatus()
            ]));
        }),

    (new Extend\Routes('forum'))
        ->get('/service-worker.js', 'service-worker.asset', ServiceWorkerController::class),
];