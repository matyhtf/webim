var ws = {};
var client_id = 0;
var userlist = {};
var GET = getRequest();
var face_count = 19;

$(document).ready(function () {
    //使用原生WebSocket
    if (window.WebSocket || window.MozWebSocket)
    {
        ws = new WebSocket(webim.server);
    }
    //使用flash websocket
    else if (webim.flash_websocket)
    {
        WEB_SOCKET_SWF_LOCATION = "/static/flash-websocket/WebSocketMain.swf";
        $.getScript("/static/flash-websocket/swfobject.js", function () {
            $.getScript("/static/flash-websocket/web_socket.js", function () {
                ws = new WebSocket(webim.server);
            });
        });
    }
    //使用http xhr长轮循
    else
    {
        ws = new Comet(webim.server);
    }
    listenEvent();
});

function listenEvent() {
    /**
     * 连接建立时触发
     */
    ws.onopen = function (e) {
        //连接成功
        console.log("connect webim server success.");
        //发送登录信息
        msg = new Object();
        msg.cmd = 'login';
        msg.name = user.nickname;
        msg.avatar = user.avatar;
        ws.send($.toJSON(msg));
    };

    //有消息到来时触发
    ws.onmessage = function (e) {
        var message = $.evalJSON(e.data);
        var cmd = message.cmd;
        if (cmd == 'login')
        {
            client_id = $.evalJSON(e.data).fd;
            //获取在线列表
            ws.send($.toJSON({cmd : 'getOnline'}));
            //获取历史记录
            ws.send($.toJSON({cmd : 'getHistory'}));
            //alert( "收到消息了:"+e.data );
        }
        else if (cmd == 'getOnline')
        {
            showOnlineList(message);
        }
        else if (cmd == 'getHistory')
        {
            showHistory(message);
        }
        else if (cmd == 'newUser')
        {
            showNewUser(message);
        }
        else if (cmd == 'fromMsg')
        {
            showNewMsg(message);
        }
        else if (cmd == 'offline')
        {
            var cid = message.fd;
            delUser(cid);
            showNewMsg(message);
        }
    };

    /**
     * 连接关闭事件
     */
    ws.onclose = function (e) {
        $(document.body).html("<h1 style='text-align: center'>连接已断开，请刷新页面重新登录。</h1>");
    };

    /**
     * 异常事件
     */
    ws.onerror = function (e) {
        $(document.body).html("<h1 style='text-align: center'>服务器[" + webim.server +
            "]: 拒绝了连接. 请检查服务器是否启动. </h1>");
        console.log("onerror: " + e.data);
    };
}

document.onkeydown = function (e) {
    var ev = document.all ? window.event : e;
    if (ev.keyCode == 13) {
        sendMsg($('#msg_content').val(), 'text');
        return false;
    } else {
        return true;
    }
};

function selectUser(userid) {
    $('#userlist').val(userid);
}

/**
 * 显示所有在线列表
 * @param dataObj
 */
function showOnlineList(dataObj) {
    var li = '';
    var option = "<option value='0' id='user_all' >所有人</option>";

    for (var i = 0; i < dataObj.list.length; i++) {
        li = li + "<li id='inroom_" + dataObj.list[i].fd + "'>" +
        "<a href=\"javascript:selectUser('"
        + dataObj.list[i].fd + "')\">" + "<img src='" + dataObj.list[i].avatar
        + "' title='" + dataObj.list[i].name + "' width='50' height='50'></a></li>";

        userlist[dataObj.list[i].fd] = dataObj.list[i].name;

        if (dataObj.list[i].fd != client_id) {
            option = option + "<option value='" + dataObj.list[i].fd + "' id='user_" + dataObj.list[i].fd + "'>"
                + dataObj.list[i].name + "</option>"
        }
    }
    $('#left-userlist').html(li);
    $('#userlist').html(option);
}

/**
 * 显示所有在线列表
 * @param dataObj
 */
function showHistory(dataObj) {
    var msg;
    if (debug) {
        console.dir(dataObj);
    }
    for (var i = 0; i < dataObj.history.length; i++) {
        msg = dataObj.history[i]['msg'];
        if (!msg) continue;
        msg['time'] = dataObj.history[i]['time'];
        msg['user'] = dataObj.history[i]['user'];
        if (dataObj.history[i]['type'])
        {
            msg['type'] = dataObj.history[i]['type'];
        }
        msg['channal'] = 3;
        showNewMsg(msg);
    }
}

/**
 * 当有一个新用户连接上来时
 * @param dataObj
 */
function showNewUser(dataObj) {
    if (!userlist[dataObj.fd]) {
        userlist[dataObj.fd] = dataObj.name;
        if (dataObj.fd != client_id) {
            $('#userlist').append("<option value='" + dataObj.fd + "' id='user_" + dataObj.fd + "'>" + dataObj.name + "</option>");

        }
        $('#left-userlist').append(
            "<li id='inroom_" + dataObj.fd + "'>" +
                '<a href="javascript: selectUser(\'' + dataObj.fd + '\')">' + "<img src='" + dataObj.avatar
                + "' width='50' height='50'></a></li>");
    }
}

/**
 * 显示新消息
 */
function showNewMsg(dataObj) {

    var content;
    if (!dataObj.type || dataObj.type == 'text') {
        content = xssFilter(dataObj.data);
    }
    else if (dataObj.type == 'image') {
        var image = eval('(' + dataObj.data + ')');
        content = '<br /><a href="' + image.url + '" target="_blank"><img src="' + image.thumb + '" /></a>';
    }

    var fromId = dataObj.from;
    var channal = dataObj.channal;

    content = parseXss(content);
    var said = '';
    var time_str;

    if (dataObj.time) {
        time_str = GetDateT(dataObj.time)
    } else {
        time_str = GetDateT()
    }

    $("#msg-template .msg-time").html(time_str);
    if (fromId == 0) {
        $("#msg-template .userpic").html("");
        $("#msg-template .content").html(
            "<span style='color: green'>【系统消息】</span> " + content);
    }
    else {
        var html = '';
        var to = dataObj.to;

        //历史记录
        if (channal == 3)
        {
            said = '对大家说: ';
            html += '<span style="color: green">【历史记录】</span><span style="color: orange">' + dataObj.user.name + said;
            html += '</span>';
        }
        //如果说话的是我自己
        else {
            if (client_id == fromId) {
                if (channal == 0) {
                    said = '我对大家说:';
                }
                else if (channal == 1) {
                    said = "我悄悄的对" + userlist[to] + "说:";
                }
                html += '<span style="color: orange">' + said + ' </span> ';
            }
            else {
                if (channal == 0) {
                    said = '对大家说:';
                }
                else if (channal == 1) {
                    said = "悄悄的对我说:";
                }

                html += '<span style="color: orange"><a href="javascript:selectUser('
                    + fromId + ')">' + userlist[fromId] + said;
                html += '</a></span> '
            }
        }
        html += content + '</span>';
        $("#msg-template .content").html(html);
    }
    $("#chat-messages").append($("#msg-template").html());
    $('#chat-messages')[0].scrollTop = 1000000;
}

function xssFilter(val) {
    val = val.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\x22/g, '&quot;').replace(/\x27/g, '&#39;');
    return val;
}

function parseXss(val) {
    for (var i = 1; i < 20; i++) {
        val = val.replace('#' + i + '#', '<img src="/static/img/face/' + i + '.gif" />');
    }
    val = val.replace('&amp;', '&');
    return val;
}

function GetDateT(time_stamp) {
    var d;
    d = new Date();

    if (time_stamp) {
        d.setTime(time_stamp * 1000);
    }
    var h, i, s;
    h = d.getHours();
    i = d.getMinutes();
    s = d.getSeconds();

    h = ( h < 10 ) ? '0' + h : h;
    i = ( i < 10 ) ? '0' + i : i;
    s = ( s < 10 ) ? '0' + s : s;
    return h + ":" + i + ":" + s;
}

function getRequest() {
    var url = location.search; // 获取url中"?"符后的字串
    var theRequest = new Object();
    if (url.indexOf("?") != -1) {
        var str = url.substr(1);

        strs = str.split("&");
        for (var i = 0; i < strs.length; i++) {
            var decodeParam = decodeURIComponent(strs[i]);
            var param = decodeParam.split("=");
            theRequest[param[0]] = param[1];
        }

    }
    return theRequest;
}

function selectUser(userid) {
    $('#userlist').val(userid);
}

function delUser(userid) {
    $('#user_' + userid).remove();
    $('#inroom_' + userid).remove();
    delete (userlist[userid]);
}

function sendMsg(content, type) {
    var msg = {};

    if (typeof content == "string") {
        content = content.replace(" ", "&nbsp;");
    }

    if (!content) {
        return false;
    }

    if ($('#userlist').val() == 0) {
        msg.cmd = 'message';
        msg.from = client_id;
        msg.channal = 0;
        msg.data = content;
        msg.type = type;
        ws.send($.toJSON(msg));
    }
    else {
        msg.cmd = 'message';
        msg.from = client_id;
        msg.to = $('#userlist').val();
        msg.channal = 1;
        msg.data = content;
        msg.type = type;
        ws.send($.toJSON(msg));
    }
    showNewMsg(msg);
    $('#msg_content').val('')
}

$(document).ready(function () {
    var a = '';
    for (var i = 1; i < 20; i++) {
        a = a + '<a class="face" href="#" onclick="selectFace(' + i + ');return false;"><img src="/static/img/face/' + i + '.gif" /></a>';
    }
    $("#show_face").html(a);
});

(function ($) {
    $.fn.extend({
        insertAtCaret: function (myValue) {
            var $t = $(this)[0];
            if (document.selection) {
                this.focus();
                sel = document.selection.createRange();
                sel.text = myValue;
                this.focus();
            }
            else if ($t.selectionStart || $t.selectionStart == '0') {

                var startPos = $t.selectionStart;
                var endPos = $t.selectionEnd;
                var scrollTop = $t.scrollTop;
                $t.value = $t.value.substring(0, startPos) + myValue + $t.value.substring(endPos, $t.value.length);
                this.focus();
                $t.selectionStart = startPos + myValue.length;
                $t.selectionEnd = startPos + myValue.length;
                $t.scrollTop = scrollTop;
            }
            else {

                this.value += myValue;
                this.focus();
            }
        }
    })
})(jQuery);

function selectFace(id) {
    var img = '<img src="/static/img/face/' + id + '.gif" />';
    $("#msg_content").insertAtCaret('#' + id + '#');
    closeChatFace();
}

function showChatFace() {
    $("#chat_face").attr("class", "chat_face chat_face_hover");
    $("#show_face").attr("class", "show_face show_face_hovers");
}

function closeChatFace() {
    $("#chat_face").attr("class", "chat_face");
    $("#show_face").attr("class", "show_face");
}

function toggleFace() {
    $("#chat_face").toggleClass("chat_face_hover");
    $("#show_face").toggleClass("show_face_hovers");
}

