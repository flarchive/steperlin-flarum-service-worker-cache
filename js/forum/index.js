import app from 'flarum/app';
import { extend } from 'flarum/extend';
import SettingsPage from 'flarum/components/SettingsPage';
import ServiceWorkerSettings from './components/ServiceWorkerSettings';

app.initializers.add('steperlin-service-worker-cache', () => {
  extend(SettingsPage.prototype, 'settingsItems', function(items) {
    items.add('service-worker',
      <ServiceWorkerSettings />
    );
  });
  
  // 注册 Service Worker
  if ('serviceWorker' in navigator) {
    console.log('🚀 Service Worker is supported, attempting to register...');
    
    navigator.serviceWorker.register('/service-worker.js', {
      scope: '/'
    })
    .then(function(registration) {
      console.log('✅ Service Worker registered successfully:', registration);
      console.log('📍 Scope:', registration.scope);
      
      // 强制检查更新
      registration.update().then(() => {
        console.log('🔄 Forced Service Worker update check completed');
      });
      
      // 监听更新
      registration.addEventListener('updatefound', () => {
        console.log('🔄 Service Worker update found');
        const newWorker = registration.installing;
        if (newWorker) {
          newWorker.addEventListener('statechange', () => {
            console.log('🔄 Service Worker state changed:', newWorker.state);
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              console.log('✨ New Service Worker installed, activating immediately');
              // 自动激活新的Service Worker
              newWorker.postMessage({type: 'SKIP_WAITING'});
              // 刷新页面以使用新的Service Worker
              setTimeout(() => {
                console.log('🔄 Reloading page to use new Service Worker');
                window.location.reload();
              }, 100);
            }
          });
        }
      });
      
      // 检查是否有等待中的Service Worker
      if (registration.waiting) {
        console.log('⏳ Service Worker is waiting to activate');
      }
      
      // 检查是否有正在安装的Service Worker
      if (registration.installing) {
        console.log('📦 Service Worker is installing');
      }
      
      // 检查当前激活的Service Worker
      if (registration.active) {
        console.log('✅ Service Worker is active and running');
      }
    })
    .catch(function(error) {
      console.error('❌ Service Worker registration failed:', error);
    });
    
    // 监听Service Worker的消息
    navigator.serviceWorker.addEventListener('message', function(event) {
      console.log('📨 Message from Service Worker:', event.data);
    });
    
    // 监听Service Worker控制器变化
    navigator.serviceWorker.addEventListener('controllerchange', function() {
      console.log('🎛️ Service Worker controller changed - page is now controlled by new SW');
    });
    
  } else {
    console.warn('⚠️ Service Worker is not supported in this browser');
  }
});