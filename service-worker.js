// Flarum Service Worker - 缓存版本 v1.2.2 (Compatible with Flarum 2.0.0@beta)
const CACHE_NAME = 'flarum-cache-v1.1';
const STATIC_CACHE_NAME = 'flarum-static-v1.1';
const API_CACHE_NAME = 'flarum-api-v1.1';
const IMAGE_CACHE_NAME = 'flarum-image-v1.1'; // 新增专门的图片缓存

// API 缓存配置
const API_CACHE_CONFIG = {
    maxAge: 5 * 60 * 1000, // 5分钟缓存
    maxEntries: 1000 // 最多缓存100个API响应
};

// 静态资源缓存配置
const STATIC_CACHE_CONFIG = {
    maxEntries: 1000, // 最多缓存1000个静态资源
    maxAge: 7 * 24 * 60 * 60 * 1000 // 静态资源缓存7天
};

// 图片缓存配置
const IMAGE_CACHE_CONFIG = {
    maxEntries: 500, // 最多缓存500张图片
    maxAge: 30 * 24 * 60 * 60 * 1000 // 图片缓存30天
};

// HTML页面缓存配置
const HTML_CACHE_CONFIG = {
    maxEntries: 100, // 最多缓存100个HTML页面
    // 将HTML页面缓存有效期延长至24小时。
    // 这需要您在服务器端的 Flarum `config.php` 中设置一个更长的 `session.lifetime` (例如 > 1440 分钟)。
    // 这样可以在保证性能的同时，通过后台刷新机制来更新CSRF令牌。
    maxAge: 24 * 60 * 60 * 1000 // 24 hours in milliseconds
};

// 需要特殊处理的域名列表
const SPECIAL_DOMAINS = [
    'via.placeholder.com',
    'placehold.it',
    'placekitten.com',
    'imgur.com',
    'i.imgur.com'
];

// 安装事件 - 初始化缓存
self.addEventListener('install', event => {
    console.log('[Service Worker] 🚀 Installing new service worker...');
    
    event.waitUntil(
        Promise.all([
            caches.open(CACHE_NAME),
            caches.open(STATIC_CACHE_NAME),
            caches.open(API_CACHE_NAME),
            caches.open(IMAGE_CACHE_NAME)
        ])
        .then(() => {
            console.log('[Service Worker] ✅ All caches initialized successfully');
            console.log('[Service Worker] 🔄 Skipping waiting to activate immediately');
            return self.skipWaiting();
        })
        .catch(error => {
            console.error('[Service Worker] ❌ Installation failed:', error);
            return self.skipWaiting();
        })
    );
});

// 激活事件 - 清理旧缓存
self.addEventListener('activate', event => {
  console.log('[Service Worker] 🎯 Activating service worker...');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        console.log(`[Service Worker] 📦 Found ${cacheNames.length} existing caches:`, cacheNames);
        const oldCaches = cacheNames.filter(cacheName => {
          return cacheName.startsWith('flarum-') && 
                 cacheName !== CACHE_NAME &&
                 cacheName !== STATIC_CACHE_NAME &&
                 cacheName !== API_CACHE_NAME &&
                 cacheName !== IMAGE_CACHE_NAME &&
                 !cacheName.includes('v1.1'); // 保留v1.1版本缓存
        });
        
        if (oldCaches.length > 0) {
          console.log(`[Service Worker] 🗑️ Deleting ${oldCaches.length} old caches:`, oldCaches);
        } else {
          console.log('[Service Worker] 🧹 No old caches to delete');
        }
        
        return Promise.all(
          oldCaches.map(cacheName => {
            console.log(`[Service Worker] ❌ Deleting old cache: ${cacheName}`);
            return caches.delete(cacheName);
          })
        );
      })
      .then(() => {
        console.log('[Service Worker] ✅ Service worker activated successfully');
        console.log('[Service Worker] 🎛️ Claiming all clients');
        return self.clients.claim();
      })
      .catch(error => {
        console.error('[Service Worker] ❌ Activation failed:', error);
        return self.clients.claim();
      })
  );
});

// 请求拦截 - 缓存策略
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    
    console.log(`[Service Worker] 🌐 Intercepting request: ${event.request.method} ${url.href}`);
    
    // 只处理 HTTP 和 HTTPS 请求
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        console.log(`[Service Worker] ❌ Skipping non-HTTP(S) request: ${url.protocol}`);
        return;
    }
    
    // 只处理GET请求
    if (event.request.method !== 'GET') {
        console.log(`[Service Worker] ⏩ Skipping non-GET request, letting browser handle it: ${event.request.method} ${event.request.url}`);
        // 对于非GET请求，不进行任何处理，让浏览器自己发起请求
        // 这样可以确保CSRF令牌等会话信息是最新的
        return;
    }

    // 对于未登录用户的所有HTML页面导航，强制网络优先，以获取最新的CSRF令牌。
    // 我们通过检查Accept头和请求模式来识别HTML导航。
    const acceptHeader = event.request.headers.get('Accept') || '';
    const isNavigatingToHtml = event.request.mode === 'navigate' || acceptHeader.includes('text/html');
    const hasCsrfToken = event.request.headers.has('x-csrf-token');

    // 如果是HTML页面导航，并且看起来用户未登录（没有CSRF令牌），则网络优先。
    if (isNavigatingToHtml && !hasCsrfToken) {
        console.log(`[Service Worker] 🛡️ Network-first for unauthenticated HTML navigation to get fresh CSRF token: ${url.href}`);
        return event.respondWith(
            fetch(event.request).catch(() => {
                console.log(`[Service Worker] ⚠️ Network failed for navigation, falling back to cache for ${url.href}`);
                return caches.match(event.request);
            })
        );
    }
    
    // 检查是否是需要特殊处理的域名
    const isSpecialDomain = SPECIAL_DOMAINS.some(domain => url.hostname.includes(domain));
    
    // 检查是否是同域请求（只缓存本站资源和允许的CDN资源）
    const isCurrentDomain = url.origin === self.location.origin;
    const isLocalhost = url.hostname === 'localhost' || url.hostname === '127.0.0.1';
    const isCDNResource = url.hostname.includes('cdn.jsdelivr.net') || 
                         url.hostname.includes('cdnjs.cloudflare.com') ||
                         url.hostname.includes('unpkg.com');
    
    // 只处理本站资源或允许的CDN资源
    if (!isCurrentDomain && !isLocalhost && !isCDNResource && !isSpecialDomain) {
        console.log(`[Service Worker] 🚫 Skipping external domain: ${url.hostname}`);
        return;
    }
    
    // 调试信息：域名过滤状态
    console.log(`[Service Worker] 🔍 Domain check: ${url.hostname}, current:${isCurrentDomain}, localhost:${isLocalhost}, CDN:${isCDNResource}, special:${isSpecialDomain}`);
    
    // 获取不带查询参数的URL路径用于匹配文件类型
    const pathname = url.pathname.toLowerCase();
    
    // API 请求使用缓存优先策略（立即返回缓存，后台刷新）
    if (pathname.startsWith('/api/')) {
        console.log(`[Service Worker] 📡 Handling API request: ${pathname}`);
        event.respondWith(
            caches.match(event.request)
                .then(cachedResponse => {
                    // 如果有缓存，立即返回（无论是否过期）
                    if (cachedResponse) {
                        console.log(`[Service Worker] ✅ Found cached API response: ${url.pathname}`);
                        let needBackgroundRefresh = false;
                        
                        try {
                            const cachedData = JSON.parse(cachedResponse.headers.get('x-cache-timestamp') || '{}');
                            // 如果缓存已过期或较旧，标记需要后台刷新
                            if (!cachedData.timestamp || 
                                Date.now() - cachedData.timestamp > API_CACHE_CONFIG.maxAge / 2) {
                                needBackgroundRefresh = true;
                                console.log(`[Service Worker] 🔄 API cache stale, scheduling background refresh: ${url.pathname}`);
                            } else {
                                console.log(`[Service Worker] ⚡ API cache fresh, returning immediately: ${url.pathname}`);
                            }
                        } catch (e) {
                            // 如果解析失败，也需要后台刷新
                            needBackgroundRefresh = true;
                            console.warn('[Service Worker] ⚠️ Error parsing cache timestamp, scheduling refresh:', e);
                        }
                        
                        // 立即返回缓存数据
                        const response = cachedResponse.clone();
                        
                        // 如果需要后台刷新，启动异步更新
                        if (needBackgroundRefresh) {
                            // 使用 setTimeout 将刷新推迟到主响应之后
                            setTimeout(() => {
                                refreshCache(event.request, API_CACHE_NAME);
                            }, 0);
                        }
                        
                        return response;
                    }
                    
                    console.log(`[Service Worker] 🌐 No API cache found, fetching from network: ${url.pathname}`);
                    
                    // 无缓存时，等待网络请求
                    return fetch(event.request)
                        .then(networkResponse => {
                            if (!networkResponse || networkResponse.status !== 200) {
                                return networkResponse;
                            }
                            
                            // 克隆响应以便缓存
                            const responseToCache = networkResponse.clone();
                            const headers = new Headers(responseToCache.headers);
                            headers.append('x-cache-timestamp', JSON.stringify({
                                timestamp: Date.now()
                            }));
                            
                            // 创建新的响应对象
                            const modifiedResponse = new Response(responseToCache.body, {
                                status: responseToCache.status,
                                statusText: responseToCache.statusText,
                                headers: headers
                            });
                            
                            // 缓存API响应
                            caches.open(API_CACHE_NAME)
                                .then(cache => {
                                    cache.put(event.request, modifiedResponse);
                                    
                                    // 检查并清理过期缓存
                                    cache.keys().then(keys => {
                                        if (keys.length > API_CACHE_CONFIG.maxEntries) {
                                            // 删除最旧的缓存
                                            const oldestKey = keys[0];
                                            cache.delete(oldestKey);
                                        }
                                    });
                                });
                            
                            return networkResponse;
                        })
                        .catch(() => {
                            // 网络请求失败时返回错误响应
                            return new Response('Network request failed', {
                                status: 408,
                                headers: new Headers({ 'Content-Type': 'text/plain' })
                            });
                        });
                })
        );
        return;
    }
    
    // 不缓存管理面板
    if (pathname.startsWith('/admin')) {
        console.log(`[Service Worker] 🛡️ Skipping admin path: ${pathname}`);
        return;
    }
    
    // 处理图片请求（包括特殊域名）
    if (isSpecialDomain || pathname.match(/\.(png|jpe?g|gif|ico|webp|svg)$/)) {
        console.log(`[Service Worker] 🖼️ Handling image request: ${url.href} (Special domain: ${isSpecialDomain})`);
        event.respondWith(
            caches.match(event.request)
                .then(response => {
                    let needBackgroundRefresh = false;
                    
                    // 如果找到缓存响应，立即返回（无论是否过期）
                    if (response) {
                        try {
                            const cachedData = JSON.parse(response.headers.get('x-cache-timestamp') || '{}');
                            // 如果缓存已过期或较旧，标记需要后台刷新
                            if (!cachedData.timestamp || 
                                Date.now() - cachedData.timestamp > IMAGE_CACHE_CONFIG.maxAge / 2) {
                                needBackgroundRefresh = true;
                            }
                        } catch (e) {
                            // 如果解析失败，也需要后台刷新
                            needBackgroundRefresh = true;
                            console.warn('[Service Worker] Error parsing cache timestamp:', e);
                        }
                        
                        // 克隆响应以防止被修改
                        const cachedResponseClone = response.clone();
                        
                        // 需要后台刷新时，异步更新缓存
                        if (needBackgroundRefresh && !isSpecialDomain) {
                            setTimeout(() => {
                                refreshCache(event.request, IMAGE_CACHE_NAME);
                            }, 0);
                        }
                        
                        return cachedResponseClone;
                    }
                    
                    // 处理特殊域名
                    if (isSpecialDomain) {
                        // 直接返回网络响应，不尝试缓存
                        return fetch(event.request, { mode: 'no-cors' })
                            .catch(error => {
                                console.error('[Service Worker] Fetch failed for special domain:', error);
                                return response || new Response('Network request failed', {
                                    status: 408,
                                    headers: new Headers({ 'Content-Type': 'text/plain' })
                                });
                            });
                    }
                    
                    // 处理普通图片
                    return fetch(event.request, {
                        mode: 'cors',
                        credentials: 'omit'
                    })
                    .then(networkResponse => {
                        // 只缓存成功的响应
                        if (!networkResponse || networkResponse.status !== 200) {
                            return networkResponse;
                        }
                        
                        try {
                            // 克隆响应以便缓存
                            const responseToCache = networkResponse.clone();
                            
                            // 为图片添加时间戳
                            const headers = new Headers(responseToCache.headers);
                            headers.append('x-cache-timestamp', JSON.stringify({
                                timestamp: Date.now()
                            }));
                            
                            // 创建新的响应对象
                            const modifiedResponse = new Response(responseToCache.body, {
                                status: responseToCache.status,
                                statusText: responseToCache.statusText,
                                headers: headers
                            });
                            
                            // 缓存图片响应
                            caches.open(IMAGE_CACHE_NAME)
                                .then(cache => {
                                    try {
                                        cache.put(event.request, modifiedResponse);
                                        
                                        // 检查并清理过期缓存
                                        cache.keys().then(keys => {
                                            if (keys.length > IMAGE_CACHE_CONFIG.maxEntries) {
                                                // 删除最旧的缓存
                                                const oldestKey = keys[0];
                                                cache.delete(oldestKey);
                                            }
                                        });
                                    } catch (error) {
                                        console.error('[Service Worker] Error caching image response:', error);
                                    }
                                });
                        } catch (error) {
                            console.error('[Service Worker] Error processing image response:', error);
                        }
                        
                        return networkResponse;
                    })
                    .catch(error => {
                        console.error('[Service Worker] Fetch failed for image:', error);
                        return response || new Response('Network request failed', {
                            status: 408,
                            headers: new Headers({ 'Content-Type': 'text/plain' })
                        });
                    });
                })
        );
    }
    // 处理其他静态资源
    else if (pathname.match(/\.(js|css|woff2?|ttf|eot|map)$/)) {
        console.log(`[Service Worker] 📄 Handling static resource: ${url.href}`);
        event.respondWith(
            caches.match(event.request)
                .then(response => {
                    let needBackgroundRefresh = false;
                    
                    // 如果找到缓存响应，立即返回（无论是否过期）
                    if (response) {
                        try {
                            const cachedData = JSON.parse(response.headers.get('x-cache-timestamp') || '{}');
                            // 如果缓存已过期或较旧，标记需要后台刷新
                            if (!cachedData.timestamp || 
                                Date.now() - cachedData.timestamp > STATIC_CACHE_CONFIG.maxAge / 2) {
                                needBackgroundRefresh = true;
                            }
                        } catch (e) {
                            // 如果解析失败，也需要后台刷新
                            needBackgroundRefresh = true;
                            console.warn('[Service Worker] Error parsing cache timestamp:', e);
                        }
                        
                        // 克隆响应以防止被修改
                        const cachedResponseClone = response.clone();
                        
                        // 需要后台刷新时，异步更新缓存
                        if (needBackgroundRefresh) {
                            setTimeout(() => {
                                refreshCache(event.request, STATIC_CACHE_NAME);
                            }, 0);
                        }
                        
                        return cachedResponseClone;
                    }
                    
                    // 如果没有缓存或缓存过期，发起网络请求
                    return fetch(event.request, {
                        mode: 'cors',
                        credentials: 'omit'
                    })
                    .then(networkResponse => {
                        // 只缓存成功的响应
                        if (!networkResponse || networkResponse.status !== 200) {
                            return networkResponse;
                        }
                        
                        try {
                            // 克隆响应以便缓存
                            const responseToCache = networkResponse.clone();
                            
                            // 添加时间戳
                            const headers = new Headers(responseToCache.headers);
                            headers.append('x-cache-timestamp', JSON.stringify({
                                timestamp: Date.now()
                            }));
                            
                            // 创建新的响应对象
                            const modifiedResponse = new Response(responseToCache.body, {
                                status: responseToCache.status,
                                statusText: responseToCache.statusText,
                                headers: headers
                            });
                            
                            // 缓存响应
                            caches.open(STATIC_CACHE_NAME)
                                .then(cache => {
                                    try {
                                        cache.put(event.request, modifiedResponse);
                                        
                                        // 检查并清理过期缓存
                                        cache.keys().then(keys => {
                                            if (keys.length > STATIC_CACHE_CONFIG.maxEntries) {
                                                // 删除最旧的缓存
                                                const oldestKey = keys[0];
                                                cache.delete(oldestKey);
                                            }
                                        });
                                    } catch (error) {
                                        console.error('[Service Worker] Error caching static response:', error);
                                    }
                                });
                        } catch (error) {
                            console.error('[Service Worker] Error processing static response:', error);
                        }
                        
                        return networkResponse;
                    })
                    .catch(error => {
                        console.error('[Service Worker] Fetch failed for static resource:', error);
                        return response || new Response('Network request failed', {
                            status: 408,
                            headers: new Headers({ 'Content-Type': 'text/plain' })
                        });
                    });
                })
        );
    } else {
        // 判断请求类型用于更精确的日志
        let requestType = '其他';
        if (pathname === '/' || pathname.startsWith('/d/') || pathname.startsWith('/t/') || pathname.startsWith('/u/')) {
            requestType = 'HTML页面';
        } else if (pathname.includes('.')) {
            const extension = pathname.split('.').pop();
            requestType = `未知文件类型(.${extension})`;
        }
        
        // 调试信息：为什么这个请求没有匹配到静态资源？
        console.log(`[Service Worker] 🔍 DEBUG: pathname="${pathname}", matches static regex:`, pathname.match(/\.(js|css|woff2?|ttf|eot|map)$/));
        console.log(`[Service Worker] 🔍 DEBUG: matches image regex:`, pathname.match(/\.(png|jpe?g|gif|ico|webp|svg)$/));
        
        // HTML页面和其他请求使用缓存优先策略（立即返回缓存，后台刷新）
        console.log(`[Service Worker] 📝 Handling ${requestType}: ${url.href}`);
        event.respondWith(
            caches.match(event.request)
                .then(cachedResponse => {
                    // 如果有缓存，立即返回（无论是否过期）
                    if (cachedResponse) {
                        console.log(`[Service Worker] ✅ Found cached ${requestType} response: ${url.href}`);
                        let needBackgroundRefresh = false;
                        
                        try {
                            const cachedData = JSON.parse(cachedResponse.headers.get('x-cache-timestamp') || '{}');
                            // 如果缓存已过期或较旧，标记需要后台刷新
                            if (!cachedData.timestamp || 
                                Date.now() - cachedData.timestamp > HTML_CACHE_CONFIG.maxAge) {
                                needBackgroundRefresh = true;
                                console.log(`[Service Worker] 🔄 ${requestType} cache stale, scheduling background refresh: ${url.href}`);
                            } else {
                                console.log(`[Service Worker] ⚡ ${requestType} cache fresh, returning immediately: ${url.href}`);
                            }
                        } catch (e) {
                            // 如果解析失败，也需要后台刷新
                            needBackgroundRefresh = true;
                            console.warn(`[Service Worker] ⚠️ Error parsing ${requestType} cache timestamp, scheduling refresh:`, e);
                        }
                        
                        // 立即返回缓存数据
                        const response = cachedResponse.clone();
                        
                        // 如果需要后台刷新，启动异步更新
                        if (needBackgroundRefresh) {
                            setTimeout(() => {
                                refreshCache(event.request, CACHE_NAME);
                            }, 0);
                        }
                        
                        return response;
                    }
                    
                    console.log(`[Service Worker] 🌐 No ${requestType} cache found, fetching from network: ${url.href}`);
                    
                    // 无缓存时，等待网络请求
                    return fetch(event.request)
                        .then(networkResponse => {
                            if (!networkResponse || networkResponse.status !== 200) {
                                return networkResponse;
                            }
                            
                            // 克隆响应以便缓存
                            const responseToCache = networkResponse.clone();
                            const headers = new Headers(responseToCache.headers);
                            headers.append('x-cache-timestamp', JSON.stringify({
                                timestamp: Date.now()
                            }));
                            
                            // 创建新的响应对象
                            const modifiedResponse = new Response(responseToCache.body, {
                                status: responseToCache.status,
                                statusText: responseToCache.statusText,
                                headers: headers
                            });
                            
                            // 缓存响应
                            caches.open(CACHE_NAME)
                                .then(cache => {
                                    cache.put(event.request, modifiedResponse);
                                });
                            
                            return networkResponse;
                        })
                        .catch(() => {
                            // 网络请求失败时返回错误响应
                            return new Response('Network request failed', {
                                status: 408,
                                headers: new Headers({ 'Content-Type': 'text/plain' })
                            });
                        });
                })
        );
    }
});

// 后台刷新缓存的辅助函数
function refreshCache(request, cacheName) {
    console.log(`[Service Worker] 🔄 Starting background refresh: ${request.url}`);
    console.log(`[Service Worker] 📦 Using cache: ${cacheName}`);
    
    const fetchOptions = {
        mode: 'cors',
        credentials: 'omit'
    };
    
    // 特殊域名使用 no-cors 模式
    if (SPECIAL_DOMAINS.some(domain => request.url.includes(domain))) {
        fetchOptions.mode = 'no-cors';
        console.log(`[Service Worker] 🔒 Using no-cors mode for special domain: ${request.url}`);
    }
    
    // 发起网络请求
    fetch(request, fetchOptions)
        .then(networkResponse => {
            console.log(`[Service Worker] 📡 Background fetch response: ${networkResponse.status} for ${request.url}`);
            
            if (!networkResponse || networkResponse.status !== 200) {
                throw new Error(`Network response not ok: ${networkResponse.status}`);
            }
            
            // 克隆响应以便缓存
            const responseToCache = networkResponse.clone();
            const headers = new Headers(responseToCache.headers);
            headers.append('x-cache-timestamp', JSON.stringify({
                timestamp: Date.now()
            }));
            
            // 创建新的响应对象
            const modifiedResponse = new Response(responseToCache.body, {
                status: responseToCache.status,
                statusText: responseToCache.statusText,
                headers: headers
            });
            
            // 更新缓存
            return caches.open(cacheName)
                .then(cache => {
                    cache.put(request, modifiedResponse);
                    console.log(`[Service Worker] ✅ Background refresh complete for: ${request.url}`);
                });
        })
        .catch(error => {
            console.error(`[Service Worker] ❌ Background refresh failed for: ${request.url}`, error);
        });
}

// 消息处理 - 支持强制更新
self.addEventListener('message', event => {
  console.log('[Service Worker] 📨 Received message:', event.data);
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    console.log('[Service Worker] 🔄 Skipping waiting to activate immediately');
    self.skipWaiting();
  }
});

// 错误处理和日志
self.addEventListener('error', event => {
  console.error('[Service Worker] ❌ Error:', event.error);
});

self.addEventListener('unhandledrejection', event => {
  console.error('[Service Worker] ❌ Unhandled rejection:', event.reason);
});

console.log('[Service Worker] 🎉 Script loaded successfully - Flarum 2.0 Compatible v1.2.0');
console.log('[Service Worker] 📊 Flarum 2.0.0@beta Cache configuration v1.2.0:', {
    CACHE_NAME,
    STATIC_CACHE_NAME,
    API_CACHE_NAME,
    IMAGE_CACHE_NAME,
    API_CACHE_CONFIG,
    STATIC_CACHE_CONFIG,
    IMAGE_CACHE_CONFIG,
    HTML_CACHE_CONFIG
});
console.log('[Service Worker] 🔧 Resource classification rules:');
console.log('[Service Worker]   📡 API: /api/* (5min cache)');
console.log('[Service Worker]   🖼️ Images: .png, .jpg, .gif, .ico, .webp, .svg (30day cache)');
console.log('[Service Worker]   📄 Static: .js, .css, .woff2, .ttf, .eot, .map (7day cache)');
console.log('[Service Worker]   📝 HTML: /, /d/*, /t/*, /u/* (5sec cache - fast + fresh CSRF)');
console.log('[Service Worker]   🌐 Allowed CDNs: cdn.jsdelivr.net, cdnjs.cloudflare.com, unpkg.com');
console.log('[Service Worker]   🚫 External domains: blocked (except special domains and CDNs)');