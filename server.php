<?php
set_time_limit(0);
ob_implicit_flush();

require_once('functions.php');

function sendMove($user, $request, $server){
    $rs = $server->addMove($request['gameId'], $request['column'], $request['moveNum'], $user->id);
    if(!empty($rs)){
        foreach($rs['players'] as $player){
            $member = $server->getByid($player);
            if(!empty($member)){
                $server->send($member->socket, json_encode(array('funct' => 'getMove', 'stat' => 'ok', 'row' => $rs['row'], 'column' => $rs['column'], 'moveNum' => $rs['moveNum'], 'playerId' => $rs['playerId'])));
            }
        }
    }
}

function win($user, $request, $server){
    $rs = $server->requestWin($request['gameId']);
    if(!empty($rs)){
        foreach($rs['players'] as $player){
            $member = $server->getByid($player);
            if(!empty($member)){
                $server->send($member->socket, json_encode(array('funct' => 'confirmWin', 'stat' => 'ok', 'winner' =>  $rs['winner'])));
            }
        }
    }
}

function connect($user, $request, $server) {
    $opp = $server->findByUserName($request['userName']);
    if(!empty($opp)){
        if($opp->status == 'BUSY'){
            $server->send($user->socket, json_encode(array('funct' => 'connectConfirm', 'stat' => 'busy')));
        }
        else{
            $game = $server->connectToUser($user, $opp);
            if($game != -1){
                $server->send($user->socket, json_encode(array('funct' => 'startGame', 'stat' => 'ok', 'gameId' => $game['id'], 'turn' => $game['turn'], 'userName' => $opp->userName)));
                $server->send($opp->socket, json_encode(array('funct' => 'startGame', 'stat' => 'ok', 'gameId' => $game['id'], 'turn' => $game['turn'], 'userName' => $user->userName)));
            }
            else{
                $server->send($user->socket, json_encode(array('funct' => 'connectConfirm', 'stat' => 'wait', 'oppId' => $opp->id)));
                $server->send($opp->socket, json_encode(array('funct' => 'join', 'stat' => 'ok', 'oppId' => $user->id, 'userName' => $user->userName)));
            }
        }
    }
    else{
        $server->send($user->socket, json_encode(array('funct' => 'connectConfirm', 'stat' => 'not_found')));
    }

}

function login($user, $request, $server) {
    if(!$server->hasUser($request['userName'])){
        $user->userName = trim($request['userName']);
        $server->send($user->socket, json_encode(array('funct' => 'loginConfirm', 'stat' => 'ok', 'id' => $user->id)));
        foreach($server->getUsers() as $member){
            if($user->id != $member->id && !empty($member->userName)){
                $server->send($member->socket, json_encode(array('funct' => 'newUser', 'userName' => $user->userName)));
            }
        }
        foreach($server->getUsers() as $member){
            if($user->id != $member->id && !empty($member->userName)){
                $server->send($user->socket, json_encode(array('funct' => 'newUser', 'userName' => $member->userName)));
            }
        }

    }
    else{
        $server->send($user->socket, json_encode(array('funct' => 'loginConfirm', 'stat' => 'exist')));
    }
}


function myError($user, $request, $response, $function) {
    $response->send($user->socket, json_encode(array('stat' => 400, 'function' => $function)));
}

function controller($user, $request, $response) {
    try {
        $functions = array('login', 'connect', 'sendMove', 'win');
        $data = json_decode($request, true);
        if (empty($data)) {
            myError($user, $request, $response, 'invalidJSON');
        }
        else if (!in_array($data['funct'], $functions)) {
            myError($user, $request, $response, 'notFound');
        }
        else {
            call_user_func($data['funct'], $user, $data, $response);
        }
    }
    catch (Exception $e) {
        myError($user, $request, $response, 'unknownError');
    }
}

require_once 'socketserver.php';
$webSocket = new WebSocketServer("192.168.1.111", 8083, 'controller');
$webSocket->run();
?>