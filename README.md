WebIM
========

使用`PHP`+`Swoole4`实现的网页即时聊天工具，在线体验地址：[http://webim.swoole.com/](http://webim.swoole.com/)

* 基于`Swoole4`协程实现，可以同时支持数百万`TCP`连接在线
* 基于`WebSocket`+`Http Comet`支持所有浏览器/客户端/移动端
* 支持单聊/群聊/组聊等功能
* 支持永久保存聊天记录，使用MySQL存储
* 基于`Server PUSH`的即时内容更新，登录/登出/状态变更/消息等会内容即时更新
* 用户列表和在线信息使用`Redis`存储
* 支持发送链接/图片/语音/视频/文件（开发中）
* 支持`Web`端直接管理所有在线用户和群组（开发中）

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

部署说明
----

### composer install

切换到PHPWebIM项目目录，执行指令composer install，如很慢则

```shell
composer install --prefer-dist
```

### 修改配置

* 配置`configs/db.php`中数据库信息，聊天记录会存储到`MySQL`中
* 配置`configs/redis.php`中的`Redis`服务器信息，用户列表和信息会存到`Redis`中

表结构
```sql
CREATE TABLE `webim_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `addtime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `name` varchar(64) COLLATE utf8mb4_bin NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_bin NOT NULL,
  `type` varchar(12) COLLATE utf8mb4_bin NOT NULL,
  `msg` text COLLATE utf8mb4_bin NOT NULL,
  `send_ip` varchar(20) COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin
```

* 修改`configs/webim.php`中的选项，设置服务器的URL和端口
```php
$config['server'] = array(
    //监听的HOST
    'host' => '0.0.0.0',
    //监听的端口
    'port' => '9503',
    //WebSocket的URL地址，供浏览器使用的
    'url' => 'ws://127.0.0.1:9503',
    //'url' => 'ws://im.swoole.com:9503',
);
```

* `server.host`，`server.port` 项为`WebIM`服务器即`WebSocket`服务器的地址与端口
* `server.url`是提供给浏览器的`WebSocket`地址，可以使用域名或者`IP`地址，注意端口必须与`server.port`一致，否则讲无法使用
* 监听`80`和`443`等`1024`以内端口需要`root`权限


### 启动服务器

```shell
php server.php
```


### 配置域名解析或者本地 Host（可选）__

如果`URL`直接使用`IP:PORT`，这里不需要设置。直接打开 `http://IP:PORT/` 即可

```shell
vi /etc/hosts
```

增加

```shell
127.0.0.1 im.swoole.com
```

* 用浏览器打开：http://im.swoole.com/



### 通信数据格式

如：登录

Client 发送数据：

```js
{"cmd":"login","name":"xdy","avatar":"http://tp3.sinaimg.cn/1586005914/50/5649388281/1"}
```

Server 响应登录

```js
{"cmd":"login", "fd": "31", "name":"xdy","avatar":"http://tp3.sinaimg.cn/1586005914/50/5649388281/1"}
```

可以看到`cmd`属性，`Client`与`Server`发送时数据都有指定，主要是用于`Client`或者`Server`的回调处理函数。

