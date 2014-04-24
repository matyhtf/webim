PHPWebIM
========

使用PHP+Swoole实现的网页即时聊天工具，在线体验地址：[http://webim.swoole.com/](http://webim.swoole.com/)

* 全异步非阻塞Server，可以同时支持数百万TCP连接在线
* 基于websocket+flash_websocket支持所有浏览器/客户端/移动端
* 支持单聊/群聊/组聊等功能
* 支持永久保存聊天记录
* 基于Server PUSH的即时内容更新，登录/登出/状态变更/消息等会内容即时更新
* 支持发送连接/图片/语音/视频/文件（开发中）
* 支持Web端直接管理所有在线用户和群组（开发中）

安装
----
swoole扩展
```shell
pecl install swoole
```

swoole框架
```shell
composer install
```

运行
----
将client目录配置到Nginx/Apache的虚拟主机目录中，使client/index.html可访问。
修改client/config.js中，IP和端口为对应的配置。
```shell
php webim_server.php
```
