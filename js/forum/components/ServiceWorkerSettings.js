import Component from 'flarum/Component';
import Button from 'flarum/components/Button';

export default class ServiceWorkerSettings extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.swStatus = '检查中...';
    this.cacheCount = 0;
    this.checkServiceWorkerStatus();
    this.checkCacheStatus();
  }

  view() {
    return (
      <div className="Form-group">
        <label>Service Worker 缓存控制台</label>
        
        <div className="helpText">
          本站使用Service Worker来缓存资源，提升浏览速度。
        </div>
        
        <div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">
          <h4>状态信息</h4>
          <p><strong>Service Worker状态:</strong> {this.swStatus}</p>
          <p><strong>缓存数量:</strong> {this.cacheCount} 个</p>
        </div>
        
        <div style="margin: 10px 0;">
          <Button
            className="Button Button--primary"
            onclick={() => this.checkServiceWorkerStatus()}
            style="margin-right: 10px;"
          >
            🔍 检查状态
          </Button>
          
          <Button
            className="Button"
            onclick={() => this.clearCache()}
            style="margin-right: 10px;"
          >
            🗑️ 清除缓存
          </Button>
          
          <Button
            className="Button"
            onclick={() => this.forceRefresh()}
            style="margin-right: 10px;"
          >
            🔄 强制刷新SW
          </Button>
          
          <Button
            className="Button"
            onclick={() => this.showCacheDetails()}
          >
            📊 缓存详情
          </Button>
        </div>
        
        <div style="margin: 10px 0;">
          <small style="color: #666;">
            💡 打开浏览器开发者工具的Console查看详细日志
          </small>
        </div>
      </div>
    );
  }

  async checkServiceWorkerStatus() {
    console.log('🔍 Checking Service Worker status...');
    
    if (!('serviceWorker' in navigator)) {
      this.swStatus = '❌ 不支持Service Worker';
      this.redraw();
      return;
    }

    try {
      const registration = await navigator.serviceWorker.getRegistration('/');
      
      if (!registration) {
        this.swStatus = '❌ 未注册';
        console.log('❌ No Service Worker registration found');
      } else {
        let status = '✅ 已注册';
        
        if (registration.active) {
          status += ' & 运行中';
          console.log('✅ Service Worker is active:', registration.active);
        }
        
        if (registration.waiting) {
          status += ' (有等待更新)';
          console.log('⏳ Service Worker is waiting:', registration.waiting);
        }
        
        if (registration.installing) {
          status += ' (正在安装)';
          console.log('📦 Service Worker is installing:', registration.installing);
        }
        
        this.swStatus = status;
        console.log('📍 Service Worker scope:', registration.scope);
      }
    } catch (error) {
      this.swStatus = '❌ 检查失败';
      console.error('❌ Error checking Service Worker status:', error);
    }
    
    this.redraw();
  }

  async checkCacheStatus() {
    if (!('caches' in window)) {
      console.log('❌ Cache API not supported');
      return;
    }

    try {
      const cacheNames = await caches.keys();
      this.cacheCount = cacheNames.length;
      console.log('📦 Found caches:', cacheNames);
      this.redraw();
    } catch (error) {
      console.error('❌ Error checking cache status:', error);
    }
  }

  async clearCache() {
    console.log('🗑️ Starting cache cleanup...');
    
    if (!('caches' in window)) {
      alert('您的浏览器不支持Cache API');
      return;
    }

    try {
      const cacheNames = await caches.keys();
      console.log('🗑️ Deleting caches:', cacheNames);
      
      for (let name of cacheNames) {
        await caches.delete(name);
        console.log(`✅ Deleted cache: ${name}`);
      }
      
      this.cacheCount = 0;
      this.redraw();
      
      console.log('✅ All caches cleared successfully');
      alert('✅ 缓存已全部清除！');
    } catch (error) {
      console.error('❌ Error clearing caches:', error);
      alert('❌ 清除缓存时出错，请查看控制台');
    }
  }

  async forceRefresh() {
    console.log('🔄 Force refreshing Service Worker...');
    
    if (!('serviceWorker' in navigator)) {
      alert('Service Worker 不被支持');
      return;
    }

    try {
      const registration = await navigator.serviceWorker.getRegistration('/');
      
      if (registration) {
        console.log('🔄 Updating Service Worker registration...');
        await registration.update();
        console.log('✅ Service Worker update triggered');
        
        // 如果有等待中的worker，激活它
        if (registration.waiting) {
          console.log('🔄 Activating waiting Service Worker...');
          registration.waiting.postMessage({type: 'SKIP_WAITING'});
        }
        
        alert('🔄 Service Worker已强制刷新！');
        
        // 重新检查状态
        setTimeout(() => this.checkServiceWorkerStatus(), 1000);
      } else {
        alert('❌ 未找到Service Worker注册');
      }
    } catch (error) {
      console.error('❌ Error refreshing Service Worker:', error);
      alert('❌ 刷新失败，请查看控制台');
    }
  }

  async showCacheDetails() {
    console.log('📊 Showing cache details...');
    
    if (!('caches' in window)) {
      alert('您的浏览器不支持Cache API');
      return;
    }

    try {
      const cacheNames = await caches.keys();
      console.log('📊 === 缓存详细信息 ===');
      
      for (let cacheName of cacheNames) {
        const cache = await caches.open(cacheName);
        const requests = await cache.keys();
        console.log(`📦 Cache: ${cacheName} (${requests.length} items)`);
        
        for (let request of requests.slice(0, 10)) { // 只显示前10个
          console.log(`  - ${request.url}`);
        }
        
        if (requests.length > 10) {
          console.log(`  ... and ${requests.length - 10} more items`);
        }
      }
      
      console.log('📊 === 详细信息结束 ===');
      alert(`📊 缓存详情已输出到控制台\n共 ${cacheNames.length} 个缓存`);
    } catch (error) {
      console.error('❌ Error showing cache details:', error);
      alert('❌ 获取缓存详情失败，请查看控制台');
    }
  }
}