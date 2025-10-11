app.initializers.add('steperlin-service-worker-cache', () => {
  // 这里不执行任何操作，只是让扩展正确初始化
  console.log('[service-worker-cache] Admin initialized');
});