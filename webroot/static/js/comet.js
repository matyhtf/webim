function Comet(url) {
    this.url = url.replace('ws://', 'http://');
    this.connected = false;
    this.session_id = '';
    this.send_queue = [];
    this.sending = false;
    jQuery.support.cors = true;

    this.send = function (msg) {
        this.send_queue.push(msg);
        //当前状态是否可以发送数据
        if (this.connected && !this.sending) {
            this.sendMessage();
        }
    };

    this.sendMessage = function () {
        if (this.send_queue.length == 0) {
            this.sending = false;
            return;
        }

        var websocket = this;
        var msg = this.send_queue.pop();
        this.sending = true;

        $.ajax({
            type: "POST",
            dataType: "json",
            url: this.url + '/pub',
            data: {type: 'pub', message: msg, session_id: websocket.session_id},
            success: function (data, textStatus) {
                //发送数据成功
                if (data.success == "1") {
                    //继续发送
                    websocket.sendMessage();
                } else {
                    console.log("ErrorMessage: " + data);
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                var e = {};
                e.data = textStatus;
                websocket.onerror(e);
            }
        });
    };

    //连接到服务器
    this.connect = function () {
        var websocket = this;
        $.ajax({
            type: "POST",
            dataType: "json",
            url: this.url + '/connect',
            data: {'type': 'connect'},
            success: function (data, textStatus) {
                //发送数据成功
                if (data.success == "1") {
                    websocket.session_id = data.session_id;
                    websocket.connected = true;
                    websocket.loop();
                    websocket.onopen({});
                } else {
                    console.log("ErrorMessage: " + data);
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                var e = {};
                e.data = textStatus;
                alert("connect to server [" + websocket.url + "] failed. Error: " + errorThrown);
            }
        });
    };

    this.loop = function () {
        var websocket = this;
        $.ajax({
            type: "POST",
            dataType: "json",
            url: websocket.url + '/sub',
            timeout: 80000,     //ajax请求超时时间80秒
            data: {time: "80", session_id: websocket.session_id, type: 'sub'}, //80秒后无论结果服务器都返回数据
            success: function (data, textStatus) {
                var e = {'data': data.data};
                //从服务器得到数据，显示数据并继续查询
                if (data.success == "1") {
                    websocket.onmessage(e);
                }
                //未从服务器得到数据，继续查询
                else if (data.success == "0") {
                    //$("#msg").append("<br>[无数据]");
                } else {
                    console.log("ErrorMessage: " + data);
                }
                websocket.loop();
            },
            //Ajax请求超时，继续查询
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                if (textStatus == "timeout") {
                    websocket.loop();
                } else {
                    console.log("Server Error: " + textStatus);
                    var e = {};
                    e.data = textStatus;
                    websocket.onclose(e);
                }
            }
        });
    };
    this.connect();
}
