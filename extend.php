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
 * @version 1.1.0
 */

namespace SteperLin\ServiceWorkerCache;

use Flarum\Extend;
use Flarum\Frontend\Document;
use SteperLin\ServiceWorkerCache\Listeners\AddServiceWorkerAssets;
use SteperLin\ServiceWorkerCache\Middleware\RegisterServiceWorker;

return [
    // 前端资源注册 - 自动注入Service Worker注册脚本
    (new Extend\Frontend('forum'))
        ->content(function (Document $document) {
            // 智能Service Worker注册脚本
            $document->foot[] = '
            <script>
                (function() {
                    "use strict";
                    
                    console.log("[SW Cache] 🚀 Initializing Service Worker registration...");
                    
                    // 检查浏览器支持
                    if (!("serviceWorker" in navigator)) {
                        console.warn("[SW Cache] ⚠️ Service Worker not supported in this browser");
                        return;
                    }
                    
                    // 等待页面完全加载
                    function registerServiceWorker() {
                        try {
                            console.log("[SW Cache] 📡 Registering Service Worker...");
                            
                            navigator.serviceWorker.register("/service-worker.js", {
                                scope: "/",
                                updateViaCache: "none" // 确保始终检查更新
                            })
                            .then(function(registration) {
                                console.log("[SW Cache] ✅ Service Worker registered successfully");
                                console.log("[SW Cache] 📍 Scope:", registration.scope);
                                
                                // 自动检查更新
                                registration.update().then(() => {
                                    console.log("[SW Cache] 🔄 Update check completed");
                                });
                                
                                // 监听更新事件
                                registration.addEventListener("updatefound", function() {
                                    console.log("[SW Cache] 🆕 New Service Worker version found");
                                    const newWorker = registration.installing;
                                    
                                    if (newWorker) {
                                        newWorker.addEventListener("statechange", function() {
                                            if (newWorker.state === "installed" && navigator.serviceWorker.controller) {
                                                console.log("[SW Cache] ✨ New version ready, activating...");
                                                newWorker.postMessage({type: "SKIP_WAITING"});
                                                
                                                // 可选：自动刷新页面使用新版本
                                                setTimeout(() => {
                                                    if (confirm("发现新版本的缓存系统，是否立即应用？")) {
                                                        window.location.reload();
                                                    }
                                                }, 1000);
                                            }
                                        });
                                    }
                                });
                            })
                            .catch(function(error) {
                                console.error("[SW Cache] ❌ Service Worker registration failed:", error);
                            });
                        } catch(error) {
                            console.error("[SW Cache] ❌ Service Worker registration error:", error);
                        }
                    }
                    
                    // 页面加载完成后注册
                    if (document.readyState === "loading") {
                        document.addEventListener("DOMContentLoaded", registerServiceWorker);
                    } else {
                        registerServiceWorker();
                    }
                    
                    // 监听Service Worker消息
                    navigator.serviceWorker.addEventListener("message", function(event) {
                        console.log("[SW Cache] 📨 Message from SW:", event.data);
                    });
                    
                    // 监听控制器变化
                    navigator.serviceWorker.addEventListener("controllerchange", function() {
                        console.log("[SW Cache] 🎛️ Service Worker controller changed");
                    });
                })();
            </script>';
        }),
    
    // Service Worker路由注册 - 自动处理/service-worker.js请求
    (new Extend\Routes('forum'))
        ->get('/service-worker.js', 'service-worker', RegisterServiceWorker::class),
        
    // 事件监听器注册 - 用于扩展功能
    (new Extend\Event())
        ->listen(\Flarum\Frontend\Events\Rendering::class, AddServiceWorkerAssets::class),
        
    // 管理员界面（可选）
    // (new Extend\Frontend('admin'))
    //     ->js(__DIR__.'/js/dist/admin.js')
    //     ->css(__DIR__.'/resources/less/admin.less'),
        
    // API路由扩展（用于缓存控制）
    (new Extend\Routes('api'))
        ->get('/service-worker/status', 'service-worker.status', function() {
            return \Laminas\Diactoros\Response\JsonResponse::fromJsonString(json_encode([
                'enabled' => true,
                'version' => '1.1.0',
                'cache_strategy' => 'intelligent',
                'flarum_version' => '2.0.0@beta'
            ]));
        }),
];