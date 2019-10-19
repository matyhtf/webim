WebIM
========

使用PHP+Swoole实现的网页即时聊天工具，在线体验地址：[http://webim.swoole.com/](http://webim.swoole.com/)

* 全异步非阻塞Server，可以同时支持数百万TCP连接在线
* 基于websocket+flash_websocket支持所有浏览器/客户端/移动端
* 支持单聊/群聊/组聊等功能
* 支持永久保存聊天记录，使用MySQL存储
* 基于Server PUSH的即时内容更新，登录/登出/状态变更/消息等会内容即时更新
* 用户列表和在线信息使用Redis存储
* 支持发送连接/图片/语音/视频/文件（开发中）
* 支持Web端直接管理所有在线用户和群组（开发中）

> 最新的版本已经可以原生支持IE系列浏览器了，基于Http长连接

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
将`webroot`目录配置到Nginx/Apache的虚拟主机目录中，使`webroot/`可访问。

详细部署说明
----

__1. 安装composer(php依赖包工具)__

```shell
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

注意：如果未将php解释器程序设置为环境变量PATH中，需要设置。因为composer文件第一行为#!/usr/bin/env php，并不能修改。
更加详细的对composer说明：http://blog.csdn.net/zzulp/article/details/18981029

__2. composer install__

切换到PHPWebIM项目目录，执行指令composer install，如很慢则

```shell
composer install --prefer-dist
```

__3. Ningx配置__

* 这里未使用swoole_framework提供的Web AppServer  
* Apache请参照Nginx配置，自行修改实现
* 这里使用了`im.swoole.com`作为域名，需要配置host或者改成你的域名

```shell
server {
    listen       80;
    server_name  im.swoole.com;
    index index.html index.php;
    
    location / {
        root   /path/to/webim/webroot;

        proxy_set_header X-Real-IP $remote_addr;
        if (!-e $request_filename) {
            rewrite ^/(.*)$ /index.php;
        }
    }
    
    location ~ .*\.(php|php5)?$ {
	    fastcgi_pass  127.0.0.1:9000;
	    fastcgi_index index.php;
	    include fastcgi.conf;
    }
}
```

__4. 修改配置__

* 配置`configs/db.php`中数据库信息，将聊天记录存储到MySQL中
* 配置`configs/redis.php`中的Redis服务器信息，将用户列表和信息存到Redis中

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
    'url' => 'ws://im.swoole.com:9503',
    //用于Comet跨域，必须设置为web页面的URL
    //比如你的网站静态页面放在 http://im.swoole.com:8888/main.html
    //这里就是 http://im.swoole.com:8888
    'origin' => 'http://im.swoole.com:8888',
);
```

* server.host server.port 项为WebIM服务器即WebSocket服务器的IP与端口，其他选择项根据具体情况修改
* server.url对应的就是服务器IP或域名以及websocket服务的端口，这个就是提供给浏览器的WebSocket地址
* server.origin为Comet跨域设置，必须修改origin才可以支持IE等不支持WebSocket的浏览器

__5. 启动WebSocket服务器__

```shell
php webim/webim_server.php
```

IE浏览器不支持WebSocket，需要使用FlashWebSocket模拟，请修改flash_policy.php中对应的端口，然后启动flash_policy.php。
```shell
php webim/flash_policy.php
```

__6. 绑定host与访问聊天窗口（可选）__

如果URL直接使用IP:PORT，这里不需要设置。

```shell
vi /etc/hosts
```

增加

```shell
127.0.0.1 im.swoole.com
```

用浏览器打开：http://im.swoole.com/

快速了解项目架构
----

1.目录结构

```
+ webim
  |- webim_server.php //WebSocket协议服务器
  |+ swoole.ini // WebSocket协议实现配置
  |+ configs //配置文件目录
  |+ webroot
    |+ static
    |- config.js // WebSocket配置
  |+ log // swoole日志及WebIM日志
  |+ src // WebIM 类文件储存目录
    |+ Store
      |- File.php // 默认用内存tmpfs文件系统(linux /dev/shm)存放天着数据，如果不是linux请手动修改$shm_dir
      |- Redis.php // 将聊天数据存放到Redis
    |- Server.php // 继承实现WebSocket的类，完成某些业务功能
  |+ vendor // 依赖包目录
```

2.Socket Server与Socket Client通信数据格式

如：登录

Client发送数据

```js
{"cmd":"login","name":"xdy","avatar":"http://tp3.sinaimg.cn/1586005914/50/5649388281/1"}
```

Server响应登录

```js
{"cmd":"login", "fd": "31", "name":"xdy","avatar":"http://tp3.sinaimg.cn/1586005914/50/5649388281/1"}
```

可以看到cmd属性，client与server发送时数据都有指定，主要是用于client或者server的回调处理函数。

3.需要理清的几种协议或者服务的关系

http协议：超文本传输协议。单工通信，等着客户端请求之后响应。

WebSocket协议：是HTML5一种新的协议，它是实现了浏览器与服务器全双工通信。服务器端口与客户端都可以推拉数据。

Web服务器：此项目中可以用基于Swoole的App Server充当Web服务器，也可以用传统的nginx/apache作为web服务器

Socket服务器：此项目中浏览器的WebSocket客户端连接的服务器，swoole_framework中有实现WebSocket协议PHP版本的服务器。

WebSocket Client：实现html5的浏览器都支持WebSocket对象，如不支持此项目中有提供flash版本的实现。
