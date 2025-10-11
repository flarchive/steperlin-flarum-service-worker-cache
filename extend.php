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
 * @version 1.3.0
 */

namespace SteperLin\ServiceWorkerCache;

use Flarum\Extend;
use Flarum\Frontend\Document;
use SteperLin\ServiceWorkerCache\Listeners\AddServiceWorkerAssets;
use SteperLin\ServiceWorkerCache\Listeners\ExtensionLifecycleListener;
use SteperLin\ServiceWorkerCache\Middleware\RegisterServiceWorker;

return [
    // HTTP中间件 - 拦截所有对/service-worker.js的请求
    (new Extend\Middleware('forum'))
        ->add(RegisterServiceWorker::class),
        
    (new Extend\Middleware('api'))
        ->add(RegisterServiceWorker::class),

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
                                
                                // 如果注册失败，尝试再次注册（可能是网络问题）
                                setTimeout(() => {
                                    console.log("[SW Cache] 🔄 Retrying Service Worker registration...");
                                    registerServiceWorker();
                                }, 5000);
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
    
    // 事件监听器注册 - 用于扩展功能和生命周期管理
    (new Extend\Event())
        ->listen(\Flarum\Frontend\Events\Rendering::class, AddServiceWorkerAssets::class)
        ->subscribe(ExtensionLifecycleListener::class),
        
    // API路由扩展（用于缓存控制和部署状态）
    (new Extend\Routes('api'))
        ->get('/service-worker/status', 'service-worker.status', function() {
            return \Laminas\Diactoros\Response\JsonResponse::fromJsonString(json_encode([
                'enabled' => true,
                'version' => '1.3.0',
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
];