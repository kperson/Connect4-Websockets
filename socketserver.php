<?php
class WebSocketServer {

    protected $address = null;

    protected $port = null;

    protected $users = array();

    protected $master = null;

    protected $sockets = array();

    protected $callback = null;

    protected $maxConnection = 99;

    protected $conns = array();

    protected $games = array();

    public function __construct($address, $port, $callback) {

        $this->address = $address;
        $this->port = $port;
        $this->callback = $callback;

        $this->connectMaster($address, $port);
    }

    public function addMove($gameId, $column, $moveNum, $playerId){
        $game = &$this->games[$gameId];
        if($game['turn'] == $playerId && $moveNum == $game['moves'] + 1){
            for($r = 0; $r < 7; $r++){
                if($game['grid'][$r][$column] == -1){
                    $game['grid'][$r][$column] = $playerId;
                    $game['turn'] = ($game['turn'] == $game['one']) ? $game['two'] : $game['one'];
                    $game['moves'] = $game['moves'] + 1;
                    return array('row' => $r, 'column' => $column, 'playerId' => $playerId, 'moveNum' => $game['moves'], 'players' => array($game['two'], $game['one']));
                }
            }
        }
        return null;
    }

    public function requestWin($gameId){
        $game = $this->games[$gameId];
        if(!empty($game)){
            $winner = winner(array('grid' => $game['grid'], 'size' => $games['moves']));
            return array('winner' => $winner, 'players' => array($game['two'], $game['one']));
        }
        return null;
    }

    public function getById($id){
        foreach($this->users as $user){
            if($id == $user->id){
                return $user;
            }
        }
        return null;
    }

    public function connectToUser($me, $you){
        foreach($this->conns as $c){
            if(($c['two'] == $me->id && $c['one'] == $you->id) && time() < $c['time'] + 30 && $c['active'] == 1){
                $game_id = sha1(mt_rand(0, mt_getrandmax()).'fniyhksmi53?');
                $turn = (mt_rand(0, 1) == 1) ? $me->id : $you->id;
                $game['id'] = $game_id;
                $game['turn'] = $turn;
                $game['moves'] = 0;
                $game['winner'] = 'PENDING';
                $game['one'] = $me->id;
                $game['two'] = $you->id;
                $arr = array();
                for($r = 0; $r < 7; $r++){
                    for($c = 0; $c < 7; $c++){
                        $arr[$r][$c] = -1;
                    }
                }
                $game['grid'] = $arr;
                $this->games[$game_id] = $game;
                $c['active'] = 0;
                return $game;
            }
        }
        $mycon['one'] = $me->id;
        $mycon['two'] = $you->id;
        $mycon['time'] = time();
        $mycon['active'] = 1;
        $this->conns[] = $mycon;
        return -1;
    }

    public function hasUser($userName){
        foreach($this->users as $user){
            if(trim($userName) == $user->userName){
                return true;
            }
        }
        return false;
    }

    public function findByUserName($userName){
        foreach($this->users as $user){
            if($userName == $user->userName){
                return $user;
            }
        }
        return null;
    }

    public function getUsers() {
        return $this->users;
    }

    protected function connectMaster() {
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("socket_create() failed");
        $this->sockets[] = $this->master;
        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("socket_option() failed");
        socket_bind($this->master, $this->address, $this->port) or die("socket_bind() failed");
        socket_listen($this->master, 20) or die("socket_listen() failed");
        return $this->master;
    }

    public function run() {
        while(true){
            $changed = $this->sockets;
            socket_select($changed, $write=NULL, $except=NULL,NULL);
            foreach($changed as $socket){
                if ($socket == $this->master) {
                    $client = socket_accept($this->master);
                    if($client < 0){ console("socket_accept() failed"); continue; }
                    else{ $this->connect($client); }
                }
				else {
                    $bytes = @socket_recv($socket, $buffer, 2048, 0);
                    if($bytes == 0) {
                        $this->disconnect($socket);
                    }
					else {
                        $user = $this->getUserBySocket($socket);
                        if(! $user->handshake) {
                            $user->doHandshake($buffer);
                        }
						else {
                            $user->lastAction = time();
                            // call the callback function
                            if ($this->callback) {
                                call_user_func($this->callback, $user, $this->unwrap($buffer), $this);
                            }
                        }
                    }
                }
            }
        }
    }


    public function deleteMember($userName){
        foreach($this->users as $user){
            if(!empty($user->userName) && $id != $user->id){
                $this->send($user->socket, json_encode(array('funct' => 'deleteMember', 'userName' => $userName)));
            }
        }
    }

    public function connect($socket) {
        $this->users[] = new WebSocketUser($socket);
        $this->sockets[] = $socket;
    }

    public function disconnect($socket) {
        $member = $this->getUserBySocket($socket);
        if(!empty($member)){
            $this->deleteMember($member->userName);
        }

        if ($this->users) {
            $found=null;
            $n = count($this->users);
            for($i=0;$i<$n;$i++){
                if($this->users[$i]->socket == $socket){ $found=$i; break; }
            }

            if(!is_null($found)){
				array_splice($this->users, $found, 1);
			}
            $index = array_search($socket, $this->sockets);
            socket_close($socket);
            $this->say($socket." DISCONNECTED!");
            if($index>=0){ array_splice($this->sockets, $index, 1); }
        }
    }

    public function getUserBySocket($socket) {
        $found=null;
        foreach($this->users as $user) {
            if($user->socket==$socket) {
                $found=$user;
                break;
            }
        }
        return $found;
    }

    public function send($client, $msg){
        $this->say("> ".$msg);
        $msg = $this->wrap($msg);
        @socket_write($client, $msg, strlen($msg));
    }

    private function say($msg="") { echo $msg."\n"; }
    private function wrap($msg="") { return chr(0).$msg.chr(255); }
    private function unwrap($msg="") { return substr($msg, 1, strlen($msg)-2); }
}


/**
 * WebSocketUser Class
 */
class WebSocketUser {

    public $id = null;

    public $socket = null;

    public $handshake = false;

    public $ip = null;

    public $lastAction = null;

    public $data = array();

    public $userName = null;


    public function __construct($socket) {
        $this->id = uniqid();
        $this->socket = $socket;

        socket_getpeername($socket, $ip);
        $this->ip = $ip;
    }


    public function doHandshake($buffer) {

        list($resource, $headers, $securityCode) = $this->handleRequestHeader($buffer);

        $securityResponse = '';
        if (isset($headers['Sec-WebSocket-Key1']) && isset($headers['Sec-WebSocket-Key2'])) {
            $securityResponse = $this->getHandshakeSecurityKey($headers['Sec-WebSocket-Key1'], $headers['Sec-WebSocket-Key2'], $securityCode);
        }

        if ($securityResponse) {
            $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                "Upgrade: WebSocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Origin: " . $headers['Origin'] . "\r\n" .
                "Sec-WebSocket-Location: ws://" . $headers['Host'] . $resource . "\r\n" .
                "\r\n".$securityResponse;
        } else {
            $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                "Upgrade: WebSocket\r\n" .
                "Connection: Upgrade\r\n" .
                "WebSocket-Origin: " . $headers['Origin'] . "\r\n" .
                "WebSocket-Location: ws://" . $headers['Host'] . $resource . "\r\n" .
                "\r\n";
        }

        socket_write($this->socket, $upgrade.chr(0), strlen($upgrade.chr(0)));

        $this->handshake = true;
        return true;
    }

    private function handleSecurityKey($key) {
        preg_match_all('/[0-9]/', $key, $number);
        preg_match_all('/ /', $key, $space);
        if ($number && $space) {
            return implode('', $number[0]) / count($space[0]);
        }
        return '';
    }

    private function getHandshakeSecurityKey($key1, $key2, $code) {
        return md5(
            pack('N', $this->handleSecurityKey($key1)).
            pack('N', $this->handleSecurityKey($key2)).
            $code,
            true
        );
    }

    private function handleRequestHeader($request) {
        $resource = $code = null;
        preg_match('/GET (.*?) HTTP/', $request, $match) && $resource = $match[1];
        preg_match("/\r\n(.*?)\$/", $request, $match) && $code = $match[1];
        $headers = array();
        foreach(explode("\r\n", $request) as $line) {
            if (strpos($line, ': ') !== false) {
                list($key, $value) = explode(': ', $line);
                $headers[trim($key)] = trim($value);
            }
        }
        return array($resource, $headers, $code);
    }
}
?>