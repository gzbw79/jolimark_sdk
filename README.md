### 映美云打印 SDK

# 说明
本SDK仅个人项目使用不保证能适用所有项目
映美云打印官方开放平台接口文档
- [Documentation](http://open.jolimark.com/doc/)

## 安装
> composer require gzlbw/jolimark_sdk 

## 使用
```php
$handle = new \Gzlbw\JolimarkSdk\Handler("appid", "appkey");
$handle->printRichHtmlCode(....);
```

## 目前已有的接口
- 打印映美规范HTML页面-传HTML代码
- 查询打印任务状态
- 查询未打印的任务
- 取消待打印任务
- 获取 MQTT 配置
- 关闭 MQTT 推送
- 绑定打印机
- 解绑打印机
- 检查打印机绑定结果
- 查询打印机当前状态
- 查询打印机基础信息
