/**
 * Created by zouzhiqiang on 2019/6/3.
 */



//进入当前页面时,链接webscoket
var url = 'ws://127.0.0.1:4000';
var ws = new WebSocket(url);

ws.onopen = function()
{
    console.log("链接服务器");
};
ws.onmessage = function (evt)
{
    var received_msg = JSON.parse(evt.data);
    console.log(received_msg);
    if(received_msg.cmd == 'message'){
        receiveMessage(received_msg.data.content);
    }
    if(received_msg.cmd == 'heart'){
        console.log('心跳中...')
    }
};

ws.onclose = function()
{
    // 关闭 websocket
    console.log('连接已关闭');
};

//发送心跳
setInterval(function () {
    ws.send(getSendData('heart',{"data":"heart"}));
},29000);

function getSendData(cmd,data)
{
    var sendData = {"cmd":cmd,"data":data,"code":0};
    return JSON.stringify(sendData);
}

function login()
{
    var user = JSON.parse(localStorage.getItem('user'));
    ws.send(getSendData('login',{"phone":user.telephone}));//发送登录信息
}

function receiveMessage(msg)
{
    var html = '<div class="answer">'
        +'<div class="heard_img left"><img src="../img/user2.png"></div>'
        +'<div class="answer_text">'
        +'<p>'+msg+'</p>'
        +'<i></i>'
        +'</div>'
        +'</div>';
    $('.speak_box').append(html);
}

function sendMessage(msg)
{
    if(msg){
        var toUser = JSON.parse(localStorage.getItem('toUser'));
        ws.send(getSendData('message',{"content":msg,"toUser":toUser.telephone}));//发送消息
        $('#word').val('');
        var html = '<div class="question">'
            +'<div class="heard_img right">'
            +'<img src="../img/user1.jpg">'
            +'</div>'
            +'<div class="question_text clear" style="max-width: 545px;">'
            +'<p>'+msg+'</p>'
            +'<i></i>'
            +'</div>'
            +'</div>';
        $('.speak_box').append(html);
    }else{
        $('#word').focus();
    }

}