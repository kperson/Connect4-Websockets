<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Play Connect 4</title>
        <style type="text/css">
            #board{
                text-align: center;
            }
            #board tbody{
                background-color: #0033cc;
            }

            #board tbody tr{
                height:60px;
            }
            #board td{
                width:60px;
                border:1px solid black;
            }
        </style>


        <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.3/jquery.min.js"></script>
        <script type="text/javascript">
        var HOST = 'ws://192.168.1.111:8083/connect4s/server.php';
        var SOCKET;
        var YOUR_ID;
        var OPP_ID;
        var OPP_NAME;
        var USER_LIST = new Array();
        var GAME_ID;
        var MOVE_NUM;
        var BOARD = new Array(7);
        var GAME_STATUS;

        function winner(){
            var rs = -1;
            for(var r = 0; r < 7; r++){
                for(var c = 0; c < 7; c++){
                    rs = checkDirection(r, c, 1, 1);
                    if(rs != -1){
                        break;
                    }
                    rs = checkDirection(r, c, -1, -1);
                    if(rs != -1){
                        break;
                    }
                    rs = checkDirection(r, c, 0, 1);
                    if(rs != -1){
                        break;
                    }
                    rs = checkDirection(r, c, 1, 0);
                    if(rs != -1){
                        break;
                    }
                }
                if(rs != -1){
                    break;
                }
            }
            return rs;
        }

        function checkDirection(r, c, x, y){
            if(BOARD[r][c] != -1){
                var ct = 0;
                while(ct < 4){
                    var my_x = ct * x + r;
                    var my_y = ct * y + c;
                    if(my_x > 6 || my_y > 6 || my_y < 0 || my_x < 0){
                        return -1;
                    }
                    if(BOARD[my_x][my_y] != BOARD[r][c]){
                        return -1;
                    }
                    ct++;
                }
                return BOARD[r][c];
            }
            return -1;
        }

        function populateBoard(){
            for(i = 0; i < BOARD.length; i++){
                BOARD[i] = new Array(7);
            }
            for(r = 0; r < BOARD.length; r++){
                for(c = 0; c < BOARD[r].length; c++){
                    BOARD[r][c] = -1;
                }
            }
        }

        function initUI(){
            $('#login').hide();
            $('#play-area').hide();
            $('#board').hide();
            showMessage('Connecting...');
        }

        function showMessage(msg){
            $('#status').html(msg);
        }

        function showLogin(){
            $('#login').show();
            showMessage('Please enter your user name.');
        }

        function bindUserList(){
            var html = '';
            for(var i = 0; i < USER_LIST.length; i++){
                html += '<li><a href="' + USER_LIST[i] + '">'  + USER_LIST[i] + '</a></li>';
            }
            $('#user-list').html(html);
        }



        function showPlayArea(){
            $('#login').hide();
            $('#play-area').show();
            showMessage('Select a user below.');
        }

        function loginConfirm(data){
            if(data.stat == 'ok'){
                YOUR_ID = data.id;
                showPlayArea();
            }
            else{
                showMessage('This name is already registered.');
            }
        }

        function connectConfirm(data){
            if(data.stat == 'busy'){
                showMessage('This user is busy right now.');
            }
            else if(data.stat == 'wait'){
              showMessage('Waitng for user to connect...');
              OPP_ID = data.oppId;
            }
            else{
              showMessage('User not found.');
            }
        }

        function newUser(data){
            if(!(data.userName in USER_LIST)){
                USER_LIST.push(data.userName);
                bindUserList();
            }
        }

        function join(data){
            var answer = confirm('Would you like to play connect4 with ' + data.userName + '?');
            if(answer){
                connectToUser(data.userName);
            }
            else{
                //rejectGame(data.oppId);
            }
        }

        function startGame(data){
            populateBoard();
            GAME_ID = data.gameId;
            GAME_STATUS = 'open';
            WHOSE_TURN = data.turn;
            OPP_NAME = data.userName;
            MOVE_NUM = 1;
            clearBoardUI();
            initGameUI();
        }

        function clearBoardUI(){
            $('#board tbody td').html('&nbsp;');
        }

        function initGameUI(){
             if(WHOSE_TURN == YOUR_ID){
                $('#status').html("Your Turn");
            }
            else{
                $('#status').html(OPP_NAME + "'s Turn");
            }
            $('#board').show();
            $('#user-list').hide();
        }


        function login(name){
            var obj = new Object;
            obj.userName = name;
            obj.funct = 'login';
            SOCKET.send(JSON.stringify(obj));
        }


        function connectToUser(userName){
            var obj = new Object;
            obj.funct = 'connect';
            obj.userName = userName;
            SOCKET.send(JSON.stringify(obj));
        }

        function controller(data){
            if(data.funct == 'loginConfirm'){
                loginConfirm(data);
            }
            else if(data.funct == 'newUser'){
                newUser(data);
            }
            else if(data.funct == 'connectConfirm'){
                connectConfirm(data);
            }
            else if(data.funct == 'join'){
                join(data);
            }
            else if(data.funct == 'startGame'){
                startGame(data);
            }
            else if(data.funct == 'getMove'){
                getMove(data);
            }
            else if(data.funct == 'deleteMember'){
                deleteMember(data);
            }
            else if(data.funct == 'confirmWin'){
                confirmWin(data);
            }
        }

        function confirmWin(data){
            GAME_STATUS = 'over';
            if(data.winner == YOUR_ID){
                showMessage('You Won!');
            }
            else{
                showMessage(OPP_NAME + ' won, step your game up next time!');
            }
            $('#board').hide();
            $('#user-list').show();
        }

        function sendBla(){
            var obj = new Object;
            obj.funct = 'bla';
            SOCKET.send(JSON.stringify(obj));
        }


        function handShake(){
            try{
                SOCKET = new WebSocket(HOST);
                SOCKET.onopen = function(msg){
                    sendBla();
                    showLogin();
                };
                SOCKET.onmessage = function(msg){
                    console.log(msg);
                    controller(jQuery.parseJSON(msg.data));
                };
                SOCKET.onclose = function(msg){
                    showMessage('You have been disconnected from the server.');
                };
            }
            catch(ex){
                showMessage(ex);
            }
        }

        function requestWin(){
            var obj = new Object;
            obj.funct = 'win';
            obj.gameId = GAME_ID;
            SOCKET.send(JSON.stringify(obj));
        }

        function getMove(data){
            if(data.moveNum == MOVE_NUM){
                updateBoard(data.row, data.column, data.playerId);
                var myWinner = winner();
                console.log(myWinner);
                if(myWinner != -1){
                    requestWin();
                }
            }
        }

        function deleteMember(data){
            if(data.userName == OPP_NAME && GAME_STATUS == 'open'){
                GAME_STATUS = 'closed';
                showMessage(OPP_NAME + ' has left the game. Select another user.');
                $('#user-list').show();
                $('#board').hide();
            }
            for(var i = 0; i < USER_LIST.length; i++){
                if(USER_LIST[i] == data.userName){
                    USER_LIST.splice(i, 1);
                    bindUserList();
                    break;
                }
            }
        }

        function sendMove(gameId, column, moveNum){
            var obj = new Object;
            obj.gameId = gameId;
            obj.column = column;
            obj.moveNum = moveNum;
            obj.funct = 'sendMove';
            SOCKET.send(JSON.stringify(obj));
        }

        function updateBoard(r, column, playerId){
            BOARD[r][column] = playerId;
            WHOSE_TURN = (playerId == YOUR_ID) ? OPP_ID : YOUR_ID;
            updateMoveUI(playerId, r, column);
            MOVE_NUM++;
        }

        function updateMoveUI(playerId, row, column){
            var newrow = row + 1;
            var newcol = column + 1;
            var selector = '#' + newrow + '' + newcol + '';
            if(playerId == YOUR_ID){
                $(selector).html('<img src="images/black.jpg" width="100%" height="100%" />');
                showMessage(OPP_NAME + "'s Turn");
            }
            else{
                $(selector).html('<img src="images/red.jpg" width="100%" height="100%" />');
                showMessage('Your Turn');
            }
        }



        function makeMove(playerId, column){
            for(var r = 0; r < 7; r++){
                if(BOARD[r][column] == -1){
                    showMessage('Sending...');
                    if(YOUR_ID == playerId){
                        sendMove(GAME_ID, column, MOVE_NUM);
                        break;
                    }
                }
            }
        }

        $(document).ready(function() {

            initUI();
            if(("WebSocket" in window)){

                handShake();

                $('#loginButton').click(function() {
                    var name = $.trim($('#userNameInput').val());
                    if(name != ''){
                        login(name);
                    }
                    else{
                        showMessage('Please enter a user name.');
                    }
                });

                $('#user-list a').live('click', function() {
                    var user_name = $(this).attr('href');
                    connectToUser(user_name);
                    return false;
                });


                $('#board button').click(function() {
                    if(WHOSE_TURN == YOUR_ID && GAME_STATUS == 'open'){
                        var column = parseInt($(this).attr('id').substr(1));
                        makeMove(YOUR_ID, column - 1);
                    }
                });
            }
            else{
                showMessage('Browser not supported: try <a href="http://www.google.com/chrome">Google Chrome</a> or <a href="http://www.apple.com/safari/">Apple Safari</a>.');
            }
        });
        </script>
    </head>
    <body>
        <h2><span id="status">&nbsp;</span><span id="clock"></span></h2>
        <div id="login">
            <p>
                <label for="userName">Username: </label><input id="userNameInput" type="text" name="userName" /><button id="loginButton">Login</button>
            <p>
        </div>
        <div id="play-area">
            <ul id="user-list">
            </ul>
            <table id="board" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <td><button id="b1">Drop</button></td>
                        <td><button id="b2">Drop</button></td>
                        <td><button id="b3">Drop</button></td>
                        <td><button id="b4">Drop</button></td>
                        <td><button id="b5">Drop</button></td>
                        <td><button id="b6">Drop</button></td>
                        <td><button id="b7">Drop</button></td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td id="71">&nbsp;</td>
                        <td id="72">&nbsp;</td>
                        <td id="73">&nbsp;</td>
                        <td id="74">&nbsp;</td>
                        <td id="75">&nbsp;</td>
                        <td id="76">&nbsp;</td>
                        <td id="77">&nbsp;</td>
                    </tr>

                    <tr>
                        <td id="61">&nbsp;</td>
                        <td id="62">&nbsp;</td>
                        <td id="63">&nbsp;</td>
                        <td id="64">&nbsp;</td>
                        <td id="65">&nbsp;</td>
                        <td id="66">&nbsp;</td>
                        <td id="67">&nbsp;</td>
                    </tr>

                    <tr>
                        <td id="51">&nbsp;</td>
                        <td id="52">&nbsp;</td>
                        <td id="53">&nbsp;</td>
                        <td id="54">&nbsp;</td>
                        <td id="55">&nbsp;</td>
                        <td id="56">&nbsp;</td>
                        <td id="57">&nbsp;</td>
                    </tr>

                    <tr>
                        <td id="41">&nbsp;</td>
                        <td id="42">&nbsp;</td>
                        <td id="43">&nbsp;</td>
                        <td id="44">&nbsp;</td>
                        <td id="45">&nbsp;</td>
                        <td id="46">&nbsp;</td>
                        <td id="47">&nbsp;</td>
                    </tr>

                    <tr>
                        <td id="31">&nbsp;</td>
                        <td id="32">&nbsp;</td>
                        <td id="33">&nbsp;</td>
                        <td id="34">&nbsp;</td>
                        <td id="35">&nbsp;</td>
                        <td id="36">&nbsp;</td>
                        <td id="37">&nbsp;</td>
                    </tr>

                    <tr>
                        <td id="21">&nbsp;</td>
                        <td id="22">&nbsp;</td>
                        <td id="23">&nbsp;</td>
                        <td id="24">&nbsp;</td>
                        <td id="25">&nbsp;</td>
                        <td id="26">&nbsp;</td>
                        <td id="27">&nbsp;</td>
                    </tr>

                    <tr>
                        <td id="11">&nbsp;</td>
                        <td id="12">&nbsp;</td>
                        <td id="13">&nbsp;</td>
                        <td id="14">&nbsp;</td>
                        <td id="15">&nbsp;</td>
                        <td id="16">&nbsp;</td>
                        <td id="17">&nbsp;</td>
                    </tr>
                </tbody>
</table>

        </div>
    </body>
</html>
