## 后端接口分模块服务前端配置说明
```javascript
<!-- 
  模块名称：基础
  前缀：/api
  注册：$axios
  使用方式：this.$axios......
 -->
VUE_APP_BASE_API = '/api'

<!-- 
  模块名称：病案指标
  前缀：/bazb
  注册：$axios2
  使用方式：this.$axios2......
 -->
VUE_APP_BASE_API2 = '/bazb'

<!-- 
  模块名称：病案搜索
  前缀：/bass
  注册：$axios3
  使用方式：this.$axios3......
 -->
VUE_APP_BASE_API3 = '/bass'

```

## 医院嵌入页面说明

|名称|路由|是否需要登录|链接|
|:---|:---:|---:|:---:|
|聚合搜索|hospital-search|是|http://localhost:8083/#/login?preUrl=hospital|
|聚合指标|embedIndex-home|是|http://localhost:8083/#/login?preUrl=embedIndex|
|聚合医院评审|reviewIndex-home|是|http://localhost:8083/#/login?preUrl=reviewIndex|
|医生站聚合搜索|whitelist-search|否|http://localhost:8083/#/whitelist-search?code=123456|
|住院病历-专业检索|reviewIndex-home|是|http://localhost:8083/#/login?preUrl=whitelist-search-specialty|


## 打包说明
测试环境              npm run build:stage
生产环境(医院)     npm run build:prod
npm config get registry