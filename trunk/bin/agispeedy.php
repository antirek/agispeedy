#!/usr/bin/php
<?php
/*
    Agispeedy - The Agispeedy is robust AGI Application Server 
               implemention in asterisk.
    Author Sun bing <hoowa.sun@gmail.com>

    See http://agispeedy.googlecode.com for more information about the 
    Agispeedy project.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
    MA 02110-1301, USA.
*/


/*-------------------------------------------------------------------------
  PHP Enviroment sets
-------------------------------------------------------------------------*/
error_reporting(E_ALL); //Everything report
set_time_limit(0);  //Tuneoff time limit
ob_implicit_flush();    //flash
declare(ticks = 1);    //fix som bugs with SIG

/*-------------------------------------------------------------------------
  Basic variables
-------------------------------------------------------------------------*/
define('AST_STATE_DOWN', 0);
define('AST_STATE_RESERVED', 1);
define('AST_STATE_OFFHOOK', 2);
define('AST_STATE_DIALING', 3);
define('AST_STATE_RING', 4);
define('AST_STATE_RINGING', 5);
define('AST_STATE_UP', 6);
define('AST_STATE_BUSY', 7);
define('AST_STATE_DIALING_OFFHOOK', 8);
define('AST_STATE_PRERING', 9);

$VERSION = '0.8';
$CONF = null;
$SERVER = array();
$CLIENT = array();
$ALLOW_RUN = true;
$ALLOW_FORKCHILD = true;

/*-------------------------------------------------------------------------
  incoming args check
-------------------------------------------------------------------------*/
if (count($argv) == 2 && $argv[1]=='--debug') {
    $SERVER['runmode']=0;
} elseif (count($argv) == 2 && $argv[1]=='--quiet') {
    $SERVER['runmode']=1;
} elseif (count($argv) == 2 && $argv[1]=='--log') {
    $SERVER['runmode']=2;
} elseif (count($argv) == 2 && $argv[1]=='--logfull') {
    $SERVER['runmode']=3;
} else {
    print   "AGISPEEDY-PHP version ".$VERSION."\n".
            "Author: Sun bing <hoowa.sun@gmail.com>\n".
            "This is free software, and you are welcome to modify and redistribute it\n".
            "under the GPL version 2 license.\n".
            "This software comes with ABSOLUTELY NO WARRANTY.\n".
            "\n".
            "Usage: ".$_SERVER['SCRIPT_NAME']." [options]\n".
            "  --debug       Display more messages on screen.\n".
            "  --quiet       Service as background.\n".
            "  --log         Quiet and Write ERROR/WARNNING into '/tmp/agispeedy.log'.\n".
            "  --logfull     Quiet and Write all messages into '/tmp/agispeedy.log'.\n";
    exit;
}


/*-------------------------------------------------------------------------
  Initilization
-------------------------------------------------------------------------*/
// server config
$SERVER['name'] = basename($_SERVER['SCRIPT_NAME'],'.php');
$SERVER['workdir'] = getcwd();  //work directory
$SERVER['sock'] = null;  // sock handle
$SERVER['pid'] = null;

/*
    READ CONF
    find config files : 
    /agispeedy/etc/agispeedy.conf
    /etc/asterisk.conf
    /etc/freeiris/agispeedy.conf
    /etc/asterisk/agispeedy.conf
*/
if (is_file('/agispeedy/etc/agispeedy.conf')==true) {
    $SERVER['config_file'] = '/agispeedy/etc/agispeedy.conf';
    $CONF = parse_ini_file($SERVER['config_file'],true);

} elseif (is_file('/etc/agispeedy.conf')==true) {
    $SERVER['config_file'] = '/etc/agispeedy.conf';
    $CONF = parse_ini_file($SERVER['config_file'],true);

} elseif (is_file('/etc/freeiris/agispeedy.conf')==true) {
    $SERVER['config_file'] = '/etc/freeiris/agispeedy.conf';
    $CONF = parse_ini_file($SERVER['config_file'],true);

} elseif (is_file('/etc/asterisk/agispeedy.conf')==true) {
    $SERVER['config_file'] = '/etc/asterisk/agispeedy.conf';
    $CONF = parse_ini_file($SERVER['config_file'],true);

} else {
    utils_message('[ERROR]['.__FUNCTION__.']: Not Found agispeedy.conf service abort.',0);
    exit;
}

// checking configure
if (is_dir($CONF['general']['agiscripts_path'])==false) {
    utils_message('[ERROR]['.__FUNCTION__.']: agiscripts_path is not exists in agispeedy.conf',0);
    exit;
}
if (is_dir($CONF['general']['pidfile_path'])==false) {
    utils_message('[ERROR]['.__FUNCTION__.']: pidfile_path is not exists in agispeedy.conf',0);
    exit;
}
if (is_null($CONF['daemon']['host'])==true) {
    utils_message('[ERROR]['.__FUNCTION__.']: host is null in agispeedy.conf',0);
    exit;
}
if (is_numeric($CONF['daemon']['port'])==false) {
    utils_message('[ERROR]['.__FUNCTION__.']: port must be in 0-9 digits in agispeedy.conf',0);
    exit;
}
if (is_numeric($CONF['daemon']['max_idle_servers'])==false || $CONF['daemon']['max_idle_servers'] < 2 || $CONF['daemon']['max_idle_servers'] > 64) {
    utils_message('[ERROR]['.__FUNCTION__.']: max_idle_servers must be in 0-9 digits and must be >= 2 and <= 64 in agispeedy.conf',0);
    exit;
}
if (is_numeric($CONF['daemon']['max_connections'])==false || $CONF['daemon']['max_connections'] < 4 || $CONF['daemon']['max_connections'] > 4096) {
    utils_message('[ERROR]['.__FUNCTION__.']: max_connections must be in 0-9 digits and must be >= 4 and <= 4096 in agispeedy.conf',0);
    exit;
}
if ($CONF['daemon']['max_connections'] < $CONF['daemon']['max_idle_servers']) {
    utils_message('[ERROR]['.__FUNCTION__.']: max_connections must be greater than perfork_idle_servers in agispeedy.conf',0);
    exit;
}

// Register shm memory idle variable
// which chldidle saving in shm sharedmemory
//
$SERVER['shm_chldidle_id'] = mem_open(substr(time(),3).'0',$CONF['daemon']['mem_idle_size']);
$SERVER['shm_forkchld_id'] = mem_open(substr(time(),3).'1',$CONF['daemon']['mem_forkchld_size']);

// SIGNAL Operator
// 
pcntl_signal(SIGTERM	, 'sig_handler');
pcntl_signal(SIGINT	    , 'sig_handler');
pcntl_signal(SIGCHLD    , 'sig_chld_handler');
pcntl_signal(SIGHUP     , 'sig_handler');
function sig_handler($sig)
{
    //signal not in parent server
    if (isset($GLOBALS['SERVER']['pid']) && $GLOBALS['SERVER']['pid'] != posix_getpid()) {
        exit;
    }
    // in parent server
    switch($sig) {
        case SIGTERM	:   server_stop();exit();break;
        case SIGINT	    :	server_stop();exit();break;
        case SIGHUP 	:   server_stop();exit();break;
    }
}
// children exit call this function
function sig_chld_handler()
{
    $SERVER = &$GLOBALS['SERVER'];
    $CONF = &$GLOBALS['CONF'];

    $pid = pcntl_waitpid(-1, $status,WNOHANG);

    //if killed is chld_idle remove from shm_chldidle_id
    $chld_idle_string = mem_get($SERVER['shm_chldidle_id'],$CONF['daemon']['mem_idle_size']);
    if (strpos($chld_idle_string,",".$pid)!==false) {
        mem_set($SERVER['shm_chldidle_id'],$CONF['daemon']['mem_idle_size'],str_replace(",".$pid,"",$chld_idle_string));
    }

    //if killed is normal
    $fork_chld_string = mem_get($SERVER['shm_forkchld_id'],$CONF['daemon']['mem_forkchld_size']);
    if (strpos($fork_chld_string,',') !== false) {
        mem_set($SERVER['shm_forkchld_id'],$CONF['daemon']['mem_forkchld_size'],preg_replace("/\,".$pid."=([0-9]+)/","",$fork_chld_string,1));
        utils_message('[DEBUG]['.__FUNCTION__.']: Released children "'.$pid.'" process.',4);
    }

}

// change user/group
//$CONF['daemon']['group'] = trim($CONF['daemon']['group']);
//if ($CONF['daemon']['group'] != null) {
//    $senfen = posix_getgrnam($CONF['daemon']['group']);
//    if (isset($senfen) && is_array($senfen)==true) {
//        $SERVER['gid'] = $senfen['gid'];
//        if (posix_setgid($SERVER['gid'])==false) {
//            utils_message('[ERROR]: Unkonwn Group '.$CONF['daemon']['group'].' in agispeedy.conf!',0);
//            exit;
//        }
//    } else {
//        utils_message('[ERROR]: Unkonwn Group '.$CONF['daemon']['group'].' in agispeedy.conf!',0);
//        exit;
//    }
//} else {
//    $SERVER['gid'] = posix_getgid();
//}
//$CONF['daemon']['user'] = trim($CONF['daemon']['user']);
//if ($CONF['daemon']['user'] != null) {
//    $senfen = posix_getpwnam($CONF['daemon']['user']);
//    if (isset($senfen) && is_array($senfen)==true) {
//        $SERVER['uid'] = $senfen['uid'];
//        if (posix_setuid($SERVER['uid'])==false) {
//            utils_message('[ERROR]: Unkonwn User '.$CONF['daemon']['user'].' in agispeedy.conf!',0);
//            exit;
//        }
//    } else {
//        utils_message('[ERROR]: Unkonwn User '.$CONF['daemon']['user'].' in agispeedy.conf!',0);
//        exit;
//    }
//} else {
//    $SERVER['uid'] = posix_getuid();
//}

/*-------------------------------------------------------------------------
  Server Runtime
-------------------------------------------------------------------------*/
utils_message('[NOTICE]: Agispeedy - AGI ApplicationServer '.$VERSION.' starting...',4);
server_start(); //start the server
server_loop();  //server looping for services
server_stop(); // cleanup all
exit;

/*-------------------------------------------------------------------------
  Server functions
  perfork and process
-------------------------------------------------------------------------*/
// start the server
function server_start()
{
    $SERVER = &$GLOBALS['SERVER'];
    $CONF = &$GLOBALS['CONF'];

    // run me as background
    if ($SERVER['runmode']===1 || $SERVER['runmode']===2 || $SERVER['runmode']===3) {
        $pid = pcntl_fork();
        //fork failed
        if ($pid == -1) {
            utils_message('[ERROR]['.__FUNCTION__.']: fork failure!',0);
            exit();
        //in parent to close the parent
    	} elseif ($pid)	{
            exit();
        //in child
    	} else {
        }
    }

    // pid check and process
    usleep(100000);
    posix_setsid();
    //chdir('/');
    umask(0);
    $SERVER['pid']=posix_getpid();
    // Checking myself in pid
    $SERVER['pid_file']=$CONF['general']['pidfile_path'].'/'.$SERVER['name'].'.pid';
    if (utils_checkpid($SERVER['pid_file'],$SERVER['name']) == true) {
        utils_message('[ERROR]['.__FUNCTION__.']: I was alreadly exists in memory, Please kill the old and try me again!',0);
        exit;
    }
    if (file_put_contents($SERVER['pid_file'],$SERVER['pid'],LOCK_EX)===false) {
        utils_message('[ERROR]['.__FUNCTION__.']: Write pid file failure, abort.!',0);
        exit;
    }

    // Load all hook functions
    if(file_exists($SERVER['workdir']."/agispeedy_hooks.php")) {
        require_once($SERVER['workdir']."/agispeedy_hooks.php");
    }
    if (function_exists('hooks_configure')==true) // run hooks
        hooks_configure();

    socket_open(); //try to open socket

}

// save to stop server and cleanup all
function server_stop()
{
    $SERVER = &$GLOBALS['SERVER'];

    utils_message('[DEBUG]['.__FUNCTION__.']: Server stopping...',4);

    $GLOBALS['ALLOW_RUN'] = false;  //stop server loop

    server_children_cleanup();

	if(is_resource($SERVER['sock']))
        socket_close($SERVER['sock']);

    shmop_close($SERVER['shm_chldidle_id']);
    shmop_close($SERVER['shm_forkchld_id']);

    //delete pid
    usleep(100000);
    unlink($SERVER['pid_file']);

    if (function_exists('hooks_server_close')==true) // run hooks
        hooks_server_close();

}

// cleanup children
function server_children_cleanup()
{
    $SERVER = &$GLOBALS['SERVER'];
    $CONF = &$GLOBALS['CONF'];
    $GLOBALS['ALLOW_FORKCHILD'] = false;    //stop fork children

    $fork_chld_string = mem_get($SERVER['shm_forkchld_id'],$CONF['daemon']['mem_forkchld_size']);

    foreach (explode(",",$fork_chld_string) as $each) {
        if (trim($each)==null)
            continue;
        $kv = explode("=",$each);
        $each_pid = trim($kv[0]);
        utils_message('[DEBUG]['.__FUNCTION__.']: Children ['.$each_pid.'] terminated...',4);
        posix_kill($each_pid,9);
    }

}

// server loop for waitting for incoming
function server_loop()
{
    $SERVER = &$GLOBALS['SERVER'];
    $CONF = &$GLOBALS['CONF'];

    // start background timer children
    $pid = pcntl_fork();
    //fork failed
    if ($pid == -1) {
        utils_message('[ERROR]['.__FUNCTION__.']: fork failure!',0);
        exit();
    //in parent nothing to do
    } elseif ($pid)	{
    //in child
    } else {
        // remember to shm
        $fork_chld_string = mem_get($SERVER['shm_forkchld_id'],$CONF['daemon']['mem_forkchld_size']);
        mem_set($SERVER['shm_forkchld_id'],$CONF['daemon']['mem_forkchld_size'],$fork_chld_string.','.posix_getpid().'='.time());
        server_timeout_checker();
        exit;
    }

    // create perfork children loop
    while ($GLOBALS['ALLOW_RUN']==true)
    {

        // get all in idle
        $chld_idle_string = mem_get($SERVER['shm_chldidle_id'],$CONF['daemon']['mem_idle_size']);
        $chld_idle_count = str_word_count($chld_idle_string,0,',');
        // get all in forkchld
        $fork_chld_string = mem_get($SERVER['shm_forkchld_id'],$CONF['daemon']['mem_forkchld_size']);
        $fork_chld_count = str_word_count($fork_chld_string,0,',');

        // create new perfork children
        if ($chld_idle_count < $CONF['daemon']['max_idle_servers'] && $GLOBALS['ALLOW_FORKCHILD'] == true) {

            // max of connections
            if ($fork_chld_count > $CONF['daemon']['max_connections']) {
                utils_message('[WARNNING]['.__FUNCTION__.']: Connection max of limits!',0);
                usleep(10000);
                continue;
            }

            // current
            $pid = pcntl_fork();
            if ($pid == -1) {
                utils_message('[ERROR]['.__FUNCTION__.']: Children fork failure!',0);
                die;
            }
            if ($pid == 0) {//in child
                server_children_work();
                exit;
            } else {//in parent
                // set to shm forkchld
                mem_set($SERVER['shm_forkchld_id'],$CONF['daemon']['mem_forkchld_size'],$fork_chld_string.','.$pid.'='.time());
                // set to shm idle
                mem_set($SERVER['shm_chldidle_id'],$CONF['daemon']['mem_idle_size'],$chld_idle_string.','.$pid);
                continue;
            }
        }

        usleep(1000);
    }

}

// in children
// work wait for apcept
function server_children_work()
{
    $SERVER = &$GLOBALS['SERVER'];
    $CLIENT = &$GLOBALS['CLIENT'];
    $CONF = &$GLOBALS['CONF'];

    $newpid=posix_getpid();
    $CLIENT['pid']=$newpid;
    utils_message('[NOTICE]['.__FUNCTION__.']: children created!',4);

    $lock = fopen($SERVER['pid_file'],'r');
    if ($lock==false) {
        utils_message('[ERROR]['.__FUNCTION__.']: pid file open failed!',0);
        exit;
    }

    if (function_exists('hooks_fork_children')==true) // run hooks
        hooks_fork_children();

    flock($lock,LOCK_EX);
    $connection = @socket_accept($SERVER['sock']);
    flock($lock,LOCK_UN);

    //remove me from chld_idle
    $chld_idle_string = mem_get($SERVER['shm_chldidle_id'], $CONF['daemon']['mem_idle_size']);
    $chld_idle_string = str_replace(",".$CLIENT['pid'],"",$chld_idle_string);
    mem_set($SERVER['shm_chldidle_id'], $CONF['daemon']['mem_idle_size'],$chld_idle_string.(str_pad("\0",$CONF['daemon']['mem_idle_size']-strlen($chld_idle_string))));

    if ($connection === false)	{
        usleep(1000); //sleep 0.0001 sec

    // here incoming
    } elseif ($connection > 0)  {
        socket_close($SERVER['sock']);// we have incoming and close main sock
        $CLIENT['sock']=$connection;
        utils_message('[NOTICE]['.__FUNCTION__.']: handle request!',4);

        server_process_connection();

        // after done close client sock
        socket_close($CLIENT['sock']);
        utils_message('[NOTICE]['.__FUNCTION__.']: exit!',4);

        if (function_exists('hooks_connection_close')==true) // run hooks
            hooks_connection_close();

        exit;
    }
}

// when an request connection now
function server_process_connection()
{
    $SERVER = &$GLOBALS['SERVER'];
    $CLIENT = &$GLOBALS['CLIENT'];
    $CONF = &$GLOBALS['CONF'];

    // enviroments
    $CLIENT['agi']=array();
    $agienv = agi_loadenviromentvars();
    $CLIENT['agi']['input'] = $agienv[0];
    $CLIENT['agi']['params'] = $agienv[1];

    // fix scriptname if end with ?
    // check params in url mode like asterisk 1.4
    $scriptname = $CLIENT['agi']['input']['agi_network_script'];
    if (strpos($scriptname,'?')!==false) {
        $fullname = explode("?",$scriptname);
        $scriptname = $fullname[0];
        //have params
        if (isset($fullname[1])) {
            foreach (explode("&",$fullname[1]) as $each) {
                $kv = explode("=",$each);
                if (count($kv) < 1)
                    continue;
                $kv[0] = trim($kv[0]);
                if (isset($kv[1])) {
                    $kv[1] = trim($kv[1]);
                    $CLIENT['agi']['params'][$kv[0]]=$kv[1];
                } else {
                    $CLIENT['agi']['params'][$kv[0]]=null;
                }
            }
        }
    }

    // check request
    if(isset($scriptname) && !empty($scriptname))
    {
        if (function_exists('hooks_asterisk_connection')==true) // run hooks
            hooks_asterisk_connection();

        // try to load
        if (file_exists($CONF['general']['agiscripts_path']."/agi_".$scriptname.".php")==true)  {
            utils_message('[NOTICE]['.__FUNCTION__.']: loading agi_'.$scriptname.'.php',4);
            require($CONF['general']['agiscripts_path']."/agi_".$scriptname.".php");
            if (function_exists('agi_main')==true) {
                agi_main();
            } else {
                utils_message('[WARNNING]['.__FUNCTION__.']: No Entry function AGI_main in agi_'.$scriptname.'.php',1);
            }

        } else {
            utils_message('[WARNNING]['.__FUNCTION__.']: agi_'.$scriptname.'.php not found!',1);
        }

        return;
    }

    socket_send_command("HANGUP",$CLIENT['sock']);

return(true);
}


// notice: this function only timeout checker in special chilren
function server_timeout_checker()
{
    $SERVER = &$GLOBALS['SERVER'];
    $CONF = &$GLOBALS['CONF'];

    while ($GLOBALS['ALLOW_RUN']==true)
    {
        $fork_chld_string = mem_get($SERVER['shm_forkchld_id'],$CONF['daemon']['mem_forkchld_size']);

        foreach (explode(",",$fork_chld_string) as $each) {
            if (trim($each)==null)//not null
                continue;

            $kv = explode("=",$each);
            $each_pid=trim($kv[0]);

            if ($each_pid == posix_getpid()) //ignore my self
                continue;

            if (!isset($kv[1])) //no time ignore
                continue;

            $worktime=trim($kv[1]);

            if ((time()-$worktime) > $CONF['daemon']['max_children_lifesec']) {
                utils_message('[DEBUG]['.__FUNCTION__.']: Children ['.$each_pid.'] execution timed out!',4);
                usleep(200000);
                posix_kill($each_pid,9);
            }
        }

        sleep(1);
    }

}


/*-------------------------------------------------------------------------
  Socket functions
  socket and read and write
-------------------------------------------------------------------------*/

// open and create socket
function socket_open()
{
    $SERVER = &$GLOBALS['SERVER'];
    $CONF = &$GLOBALS['CONF'];

    // main socket create
    if(($SERVER['sock'] = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP))===FALSE) {
        utils_message('[ERROR]['.__FUNCTION__.']: Call to socket_create failed to create socket: '.socket_strerror($SERVER['sock']),0);
        server_stop();
        exit();
    }
    if (!socket_set_option($SERVER['sock'], SOL_SOCKET, SO_REUSEADDR, 1)) {   //not lookup server name in DNS
        utils_message('[ERROR]['.__FUNCTION__.']: Unable to set option on socket: '.socket_strerror($SERVER['sock']),0);
        server_stop();
        exit();
    }
    if(($ret = @socket_bind($SERVER['sock'], $CONF['daemon']['host'], $CONF['daemon']['port']))===FALSE) {    //bind
        utils_message('[ERROR]['.__FUNCTION__.']: Call to socket_bind failed to bind socket: '.socket_strerror($ret),0);
        server_stop();
        exit();
    }
    if(($ret = @socket_listen($SERVER['sock'], 0))===FALSE ) {    //listen
        utils_message('[ERROR]['.__FUNCTION__.']: Call to socket listen failed to listen to socket: '.socket_strerror($ret),0);
        $server_stop();
        exit();
    }
    //socket_set_nonblock($SERVER['sock']);     //perfork close nonblocking
    utils_message('[NOTICE]['.__FUNCTION__.']: Services on '.$CONF['daemon']['host'].':'.$CONF['daemon']['port'],4);

    if (function_exists('hooks_socket_blind')==true) // run hooks
        hooks_socket_blind();
}

// wonderful close socket
function socket_close_graceful()
{
    $SERVER = &$GLOBALS['SERVER'];
	socket_shutdown($SERVER['sock'], 1);  //remote host yet can read
	usleep(500);//wait remote host
	socket_shutdown($SERVER['sock'], 0);//close reading
	socket_close($SERVER['sock']);//finaly we can free resource
	$SERVER['sock']=NULL;
}

// read socket response
function socket_read_response($sock,$eof="\012")
{
    $szRead = null;//buffer of read
    $timeout = 60;//timeout read

    if(is_resource($sock)===FALSE)
        return $szRead;

	socket_set_nonblock($sock);
	$iTimeStart	= time(); //begin time
	$iTimeLastRead = 0; //last read time
    $select_tv_usec = 1000; //microseconds 0.0001s
	$bFOREVER = true;
	for(;$bFOREVER;)
    {
		if(is_resource($sock)===FALSE) // may be socket disconnect to return
            return $szRead;

        //does this io can read?
        $vSelect = socket_select($r = array($sock), $w =NULL,$e= NULL, 0,$select_tv_usec);
        if($vSelect===FALSE)// Select Error
        {
            utils_message('[WARNNING]['.__FUNCTION__.']: socket_select() failed, reason: '.socket_strerror(socket_last_error()),1);
            break;
        }

        $iTimeNow = time();//current time
        // select timeouted this time no data
		if($vSelect==0)	{

            //has data and (no $EOF or only $EOF), check timeout 5sec
            if ($iTimeLastRead > 0 && (strpos($szRead, $eof) === false || $szRead == $eof)) {
                if  (($iTimeNow-$iTimeLastRead) > $timeout) {
                    utils_message('[NOTICE]['.__FUNCTION__.']: Timed out no more data.',2);
                    break;
                }

            //has data and has $EOF, end of read
            } elseif ($iTimeLastRead > 0 && strpos($szRead, $eof) !== false) {
                break;

            //no data and always no data, check timeout 5sec
            } elseif (($iTimeNow-$iTimeStart) > $timeout) {
                utils_message('[NOTICE]['.__FUNCTION__.']: Timed out for data.',2);
                break;
            }
			continue;
		}// Nothing Happened

        //read
		foreach($r as $rs)
		{
			if (is_resource($rs)===FALSE)
                return $szRead;

			$szReadThis=socket_read($rs,2048,PHP_BINARY_READ);
			if ($szReadThis===FALSE || strlen($szReadThis)==0){
                $bFOREVER=FALSE;
                break;
            }

            utils_message('[NOTICE]['.__FUNCTION__.']: read('.strlen($szReadThis).') bytes',4);

            $iTimeLastRead=time();
			$szRead .= str_replace("\015\012","\012",$szReadThis);  //replace \r\n to \n
			break;
		}

    }//End Forever

    utils_message('[NOTICE]['.__FUNCTION__.']: read end.',4);

return $szRead;
}

// send to socket command and read response
function socket_send_command($szCommand,$thisSock,$eof="\012")
{
	$szSocketRead = null;

	if(!is_resource($thisSock))
    	return $szSocketRead;

    utils_message('[NOTICE]['.__FUNCTION__.']: Sending '.$szCommand,4);

    //write success
	if(@socket_write($thisSock,$szCommand.$eof)!==FALSE) {
		$szSocketRead=socket_read_response($thisSock);  // by the way read from socket response
        utils_message('[NOTICE]['.__FUNCTION__.']: Received '.trim($szSocketRead),4);

    //write failed
    } else {
        utils_message('[NOTICE]['.__FUNCTION__.']: Send failure :'.$szCommand.socket_strerror(socket_last_error()),4);

        return $szSocketRead;
    }

return $szSocketRead;
}

/*-------------------------------------------------------------------------
  Utils for functions
-------------------------------------------------------------------------*/

// memory control
// create an memcontrol
function mem_open($hexid,$size)
{
    $shmid = null;
    // shm for chldidle pid
    for ($i=1;$i<=3;$i++) {
        $shmid = @shmop_open($hexid,'n',0777,$size);
        if ($shmid != true) {
            sleep(1);
        } else {
            break;
        }
    }
    if ($shmid===false) {
        utils_message('[ERROR]['.__FUNCTION__.']: failure to request shm memory.',0);
        exit;
    }
    return($shmid);
}

// memory control
// get data from memory
function mem_get($shmid,$size)
{
    $string = shmop_read($shmid, 0, $size);
    $string = trim($string);

    return($string);
}

// memory control
// set data to memory
function mem_set($shmid,$size,$string)
{
    $string = $string.(str_pad("\0",$size-strlen($string)));

    $bytes = shmop_write($shmid, $string, 0);

    return($bytes);
}


// output messages
function utils_message($szMessage,$msgLevel=4)
{
    $rpType = $GLOBALS['SERVER']['runmode'];

    // --debug == ALL DISPLAY
    if ($rpType === 0) {

        $szMessage = "[".time().",".posix_getpid()."]".$szMessage;
        $stdError = fopen("php://stderr","w");
        fwrite($stdError,$szMessage."\n");
        fclose($stdError);

    // --quiet == 0 ONLY ERROR DISPLAY
    } elseif ($rpType === 1 && $msgLevel === 0) {

        $szMessage = "[".time().",".posix_getpid()."]".$szMessage;
        $stdError = fopen("php://stderr","w");
        fwrite($stdError,$szMessage."\n");
        fclose($stdError);

    // --log == 1 ALL NOT DISPLAY, BUT SAVE AS WARNING
    } elseif ($rpType === 2 && $msgLevel <= 1) {

        $szMessage = "[".time().",".posix_getpid()."]".$szMessage;
        system("echo '".$szMessage."' >> /tmp/agispeedy.log");

    // --logfull == 4 ALL SAVE
    } elseif ($rpType === 3 && $msgLevel <= 4) {

        $szMessage = "[".time().",".posix_getpid()."]".$szMessage;
        system("echo '".$szMessage."' >> /tmp/agispeedy.log");

    }

}

// check pid is exists
function utils_checkpid($pid,$thisname)
{
	$exists = false;

	if (is_file($pid)==true) {

		$pid_number = `cat $pid`;
		$pid_number = trim($pid_number);

		// if this process is exists?
		if (is_file("/proc/".$pid_number."/cmdline")) {
			$pid_cmdline = `cat /proc/$pid_number/cmdline`;
			$pid_cmdline = trim($pid_cmdline);

			$scriptname = preg_replace("/\//","\\\/",$thisname);

			// if equal name
			if (preg_match("/".$scriptname."/i",$pid_cmdline)) {
				$exists = true;
			// not equal
			} else {
				$exists = false;
			}
		// this process is no exists
		} else {
			$exists = false;
		}
	}

return($exists);
}

// AGI ENV VARIABLES TO ARRAY
function utils_envresult2array($szResultIn)
{
    $aResultOut=Array();
    $aParams=array();

    $szResultIn	= trim($szResultIn,"\r\n");
    $aResultIn	= explode("\n",$szResultIn);

    foreach($aResultIn as $key => $value)
    {
        $name = substr($value, 0, strpos($value, ':'));
        if (preg_match("/^agi_arg_([0-9]+)/",$name)) {
            $kv = explode('=',trim(substr($value, strpos($value, ':') + 1)));
            $kv[0]=trim($kv[0]);
            if (isset($kv[1])) {
                $aParams[$kv[0]]=$kv[1];
            } else {
                $aParams[$kv[0]]=null;
            }
        } else {
            $aResultOut[$name] = trim(substr($value, strpos($value, ':') + 1));
        }
    }

return (array($aResultOut,$aParams));
}

// AGI COMMAND RESULT DATA TO ARRAY
function utils_agiresult2array($szResultIn)
{
    $code = substr($szResultIn,0,3);
    $chunk = substr($szResultIn,4);
    $result = null;
    $data = null;
    if ($code=='200') {

        $fristspeace = strpos($chunk, " ");
        if ($fristspeace==false) {
            $kv = explode('=',$chunk);
            $kv[1] = trim($kv[1]);
            $result = $kv[1];
        } else {
            $result = substr($chunk,0,$fristspeace);
            $kv = explode('=',$result);
            $kv[1] = trim($kv[1]);
            $result = $kv[1];
            $data = substr($chunk,$fristspeace);
            $data = trim($data);
            $data = ltrim($data,"(");
            $data = rtrim($data,")");
        }

    } else {

        if (isset($string)){
            $result = substr($string,4);
        }

    }

    return(array('code'=>$code, 'result'=>$result, 'data'=>$data));
}

/*-------------------------------------------------------------------------
  AGI Class
-------------------------------------------------------------------------*/

// load enviroments from agi header
function agi_loadenviromentvars()
{
    $CLIENT = &$GLOBALS['CLIENT'];

    $szSocketRead=socket_read_response($CLIENT['sock'],"\012\012");  //ENVIROMENT is \n\n end of
    $agienv = utils_envresult2array($szSocketRead);

    return($agienv);
}

// send an agi send and waitting for receive
function agi_evaluate($command)
{
    $CLIENT = &$GLOBALS['CLIENT'];
    $response = socket_send_command($command,$CLIENT['sock']);
    $response = utils_agiresult2array($response);
    return($response);
}

/* Answer channel if not already in answer state.
* @link http://www.voip-info.org/wiki-answer
* @return array, see evaluate for return information.  ['result'] is 0 on success, -1 on failure.
*/
function agi_answer()
{
    return agi_evaluate('ANSWER');
}

/* Get the status of the specified channel. If no channel name is specified, return the status of the current channel.
*
* @link http://www.voip-info.org/wiki-channel+status
* @param string $channel
* @return array, see evaluate for return information. ['data'] contains description.
*/
function agi_channel_status($channel='')
{
    $ret = agi_evaluate("CHANNEL STATUS $channel");
    switch($ret['result'])
    {
        case -1: $ret['data'] = trim("There is no channel that matches $channel"); break;
        case AST_STATE_DOWN: $ret['data'] = 'Channel is down and available'; break;
        case AST_STATE_RESERVED: $ret['data'] = 'Channel is down, but reserved'; break;
        case AST_STATE_OFFHOOK: $ret['data'] = 'Channel is off hook'; break;
        case AST_STATE_DIALING: $ret['data'] = 'Digits (or equivalent) have been dialed'; break;
        case AST_STATE_RING: $ret['data'] = 'Line is ringing'; break;
        case AST_STATE_RINGING: $ret['data'] = 'Remote end is ringing'; break;
        case AST_STATE_UP: $ret['data'] = 'Line is up'; break;
        case AST_STATE_BUSY: $ret['data'] = 'Line is busy'; break;
        case AST_STATE_DIALING_OFFHOOK: $ret['data'] = 'Digits (or equivalent) have been dialed while offhook'; break;
        case AST_STATE_PRERING: $ret['data'] = 'Channel has detected an incoming call and is waiting for ring'; break;
        default: $ret['data'] = "Unknown ({$ret['result']})"; break;
    }
    return $ret;
}

/**
* Deletes an entry in the Asterisk database for a given family and key.
*
* @link http://www.voip-info.org/wiki-database+del
* @param string $family
* @param string $key
* @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise.
*/
function agi_database_del($family, $key)
{
    return agi_evaluate("DATABASE DEL \"$family\" \"$key\"");
}

/**
* Deletes a family or specific keytree within a family in the Asterisk database.
*
* @link http://www.voip-info.org/wiki-database+deltree
* @param string $family
* @param string $keytree
* @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise.
*/
function agi_database_deltree($family, $keytree='')
{
    $cmd = "DATABASE DELTREE \"$family\"";
    if($keytree != '') $cmd .= " \"$keytree\"";
    return agi_evaluate($cmd);
}

/**
* Retrieves an entry in the Asterisk database for a given family and key.
*
* @link http://www.voip-info.org/wiki-database+get
* @param string $family
* @param string $key
* @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 failure. ['data'] holds the value
*/
function agi_database_get($family, $key)
{
    return agi_evaluate("DATABASE GET \"$family\" \"$key\"");
}

/**
* Adds or updates an entry in the Asterisk database for a given family, key, and value.
*
* @param string $family
* @param string $key
* @param string $value
* @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise
*/
function agi_database_put($family, $key, $value)
{
    $value = str_replace("\n", '\n', addslashes($value));
    return agi_evaluate("DATABASE PUT \"$family\" \"$key\" \"$value\"");
}


/**
* Sets a global variable, using Asterisk 1.6 syntax.
*
* @link http://www.voip-info.org/wiki/view/Asterisk+cmd+Set
*
* @param string $pVariable
* @param string|int|float $pValue
* @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise
*/
function agi_set_global_var($pVariable, $pValue)
{
    if (is_numeric($pValue))
        return agi_evaluate("Set({$pVariable}={$pValue},g);");
    else
        return agi_evaluate("Set({$pVariable}=\"{$pValue}\",g);");
}


/**
* Sets a variable, using Asterisk 1.6 syntax.
*
* @link http://www.voip-info.org/wiki/view/Asterisk+cmd+Set
*
* @param string $pVariable
* @param string|int|float $pValue
* @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise
*/
function agi_set_var($pVariable, $pValue)
{
    if (is_numeric($pValue))
        return agi_evaluate("Set({$pVariable}={$pValue});");
    else
        return agi_evaluate("Set({$pVariable}=\"{$pValue}\");");
}


/**
* Executes the specified Asterisk application with given options.
*
* @link http://www.voip-info.org/wiki-exec
* @link http://www.voip-info.org/wiki-Asterisk+-+documentation+of+application+commands
* @param string $application
* @param mixed $options
* @return array, see evaluate for return information. ['result'] is whatever the application returns, or -2 on failure to find application
*/
function agi_exec($application, $options)
{
    if(is_array($options)) $options = join('|', $options);
    return agi_evaluate("EXEC $application $options");
}

/**
* Plays the given file and receives DTMF data.
*
* This is similar to STREAM FILE, but this command can accept and return many DTMF digits,
* while STREAM FILE returns immediately after the first DTMF digit is detected.
*
* Asterisk looks for the file to play in /var/lib/asterisk/sounds by default.
*
* If the user doesn't press any keys when the message plays, there is $timeout milliseconds
* of silence then the command ends. 
*
* The user has the opportunity to press a key at any time during the message or the
* post-message silence. If the user presses a key while the message is playing, the
* message stops playing. When the first key is pressed a timer starts counting for
* $timeout milliseconds. Every time the user presses another key the timer is restarted.
* The command ends when the counter goes to zero or the maximum number of digits is entered,
* whichever happens first. 
*
* If you don't specify a time out then a default timeout of 2000 is used following a pressed
* digit. If no digits are pressed then 6 seconds of silence follow the message. 
*
* If you don't specify $max_digits then the user can enter as many digits as they want. 
*
* Pressing the # key has the same effect as the timer running out: the command ends and
* any previously keyed digits are returned. A side effect of this is that there is no
* way to read a # key using this command.
*
* @example examples/ping.php Ping an IP address
*
* @link http://www.voip-info.org/wiki-get+data
* @param string $filename file to play. Do not include file extension.
* @param integer $timeout milliseconds
* @param integer $max_digits
* @return array, see evaluate for return information. ['result'] holds the digits and ['data'] holds the timeout if present.
*
* This differs from other commands with return DTMF as numbers representing ASCII characters.
*/
function agi_get_data($filename, $timeout=NULL, $max_digits=NULL)
{
    return agi_evaluate(rtrim("GET DATA $filename $timeout $max_digits"));
}

/**
* Fetch the value of a variable.
*
* Does not work with global variables. Does not work with some variables that are generated by modules.
*
* @link http://www.voip-info.org/wiki-get+variable
* @link http://www.voip-info.org/wiki-Asterisk+variables
* @param string $variable name
* @param boolean $getvalue return the value only
* @return array, see evaluate for return information. ['result'] is 0 if variable hasn't been set, 1 if it has. ['data'] holds the value. returns value if $getvalue is TRUE
*/
function agi_get_variable($variable,$getvalue=FALSE)
{
    $res=agi_evaluate("GET VARIABLE $variable");

    if($getvalue==FALSE)
      return($res);

    return($res['data']);
}


/**
* Fetch the value of a full variable.
*
*
* @link http://www.voip-info.org/wiki/view/get+full+variable
* @link http://www.voip-info.org/wiki-Asterisk+variables
* @param string $variable name
* @param string $channel channel
* @param boolean $getvalue return the value only 
* @return array, see evaluate for return information. ['result'] is 0 if variable hasn't been set, 1 if it has. ['data'] holds the value.  returns value if $getvalue is TRUE
*/
function agi_get_fullvariable($variable,$channel=FALSE,$getvalue=FALSE)
{
  if($channel==FALSE){
    $req = $variable;
  } else {
    $req = $variable.' '.$channel;
  }
  
  $res=agi_evaluate('GET VARIABLE FULL '.$req);
  
  if($getvalue==FALSE)
    return($res);
  
  return($res['data']);
  
}

/**
* Hangup the specified channel. If no channel name is given, hang up the current channel.
*
* With power comes responsibility. Hanging up channels other than your own isn't something
* that is done routinely. If you are not sure why you are doing so, then don't.
*
* @link http://www.voip-info.org/wiki-hangup
* @example examples/dtmf.php Get DTMF tones from the user and say the digits
* @example examples/input.php Get text input from the user and say it back
* @example examples/ping.php Ping an IP address
*
* @param string $channel
* @return array, see evaluate for return information. ['result'] is 1 on success, -1 on failure.
*/
function agi_hangup($channel='')
{
    return agi_evaluate("HANGUP $channel");
}

/**
* Does nothing.
*
* @link http://www.voip-info.org/wiki-noop
* @return array, see evaluate for return information.
*/
function agi_noop($string="")
{
    return agi_evaluate("NOOP \"$string\"");
}

/**
* Receive a character of text from a connected channel. Waits up to $timeout milliseconds for
* a character to arrive, or infinitely if $timeout is zero.
*
* @link http://www.voip-info.org/wiki-receive+char
* @param integer $timeout milliseconds
* @return array, see evaluate for return information. ['result'] is 0 on timeout or not supported, -1 on failure. Otherwise 
* it is the decimal value of the DTMF tone. Use chr() to convert to ASCII.
*/
function agi_receive_char($timeout=-1)
{
    return agi_evaluate("RECEIVE CHAR $timeout");
}

/**
* Record sound to a file until an acceptable DTMF digit is received or a specified amount of
* time has passed. Optionally the file BEEP is played before recording begins.
*
* @link http://www.voip-info.org/wiki-record+file
* @param string $file to record, without extension, often created in /var/lib/asterisk/sounds
* @param string $format of the file. GSM and WAV are commonly used formats. MP3 is read-only and thus cannot be used.
* @param string $escape_digits
* @param integer $timeout is the maximum record time in milliseconds, or -1 for no timeout.
* @param integer $offset to seek to without exceeding the end of the file.
* @param boolean $beep
* @param integer $silence number of seconds of silence allowed before the function returns despite the 
* lack of dtmf digits or reaching timeout.
* @return array, see evaluate for return information. ['result'] is -1 on error, 0 on hangup, otherwise a decimal value of the 
* DTMF tone. Use chr() to convert to ASCII.
*/
function agi_record_file($file, $format, $escape_digits='', $timeout=-1, $offset=NULL, $beep=false, $silence=NULL)
{
    $cmd = trim("RECORD FILE $file $format \"$escape_digits\" $timeout $offset");
    if($beep) $cmd .= ' BEEP';
    if(!is_null($silence)) $cmd .= " s=$silence";
    return agi_evaluate($cmd);
}

/**
* Say the given digit string, returning early if any of the given DTMF escape digits are received on the channel.
*
* @link http://www.voip-info.org/wiki-say+digits
* @param integer $digits
* @param string $escape_digits
* @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no 
* digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
*/
function agi_say_digits($digits, $escape_digits='')
{
    return agi_evaluate("SAY DIGITS $digits \"$escape_digits\"");
}

/**
* Say the given number, returning early if any of the given DTMF escape digits are received on the channel.
*
* @link http://www.voip-info.org/wiki-say+number
* @param integer $number
* @param string $escape_digits
* @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no 
* digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
*/
function agi_say_number($number, $escape_digits='')
{
    return agi_evaluate("SAY NUMBER $number \"$escape_digits\"");
}

/**
* Say the given character string, returning early if any of the given DTMF escape digits are received on the channel.
*
* @link http://www.voip-info.org/wiki-say+phonetic
* @param string $text
* @param string $escape_digits
* @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no 
* digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
*/
function agi_say_phonetic($text, $escape_digits='')
{
    return agi_evaluate("SAY PHONETIC $text \"$escape_digits\"");
}

/**
* Say a given time, returning early if any of the given DTMF escape digits are received on the channel.
*
* @link http://www.voip-info.org/wiki-say+time
* @param integer $time number of seconds elapsed since 00:00:00 on January 1, 1970, Coordinated Universal Time (UTC).
* @param string $escape_digits
* @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no 
* digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
*/
function agi_say_time($time=NULL, $escape_digits='')
{
    if(is_null($time)) $time = time();
    return agi_evaluate("SAY TIME $time \"$escape_digits\"");
}

/**
* Send the specified image on a channel.
*
* Most channels do not support the transmission of images.
*
* @link http://www.voip-info.org/wiki-send+image
* @param string $image without extension, often in /var/lib/asterisk/images
* @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if the image is sent or 
* channel does not support image transmission.
*/
function agi_send_image($image)
{
    return agi_evaluate("SEND IMAGE $image");
}

/**
* Send the given text to the connected channel.
*
* Most channels do not support transmission of text.
*
* @link http://www.voip-info.org/wiki-send+text
* @param $text
* @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if the text is sent or 
* channel does not support text transmission.
*/
function agi_send_text($text)
{
    return agi_evaluate("SEND TEXT \"$text\"");
}

/**
* Cause the channel to automatically hangup at $time seconds in the future.
* If $time is 0 then the autohangup feature is disabled on this channel.
*
* If the channel is hungup prior to $time seconds, this setting has no effect.
*
* @link http://www.voip-info.org/wiki-set+autohangup
* @param integer $time until automatic hangup
* @return array, see evaluate for return information.
*/
function agi_set_autohangup($time=0)
{
    return agi_evaluate("SET AUTOHANGUP $time");
}

/**
* Changes the caller ID of the current channel.
*
* @link http://www.voip-info.org/wiki-set+callerid
* @param string $cid example: "John Smith"<1234567>
* This command will let you take liberties with the <caller ID specification> but the format shown in the example above works 
* well: the name enclosed in double quotes followed immediately by the number inside angle brackets. If there is no name then
* you can omit it. If the name contains no spaces you can omit the double quotes around it. The number must follow the name
* immediately; don't put a space between them. The angle brackets around the number are necessary; if you omit them the
* number will be considered to be part of the name.
* @return array, see evaluate for return information.
*/
//function agi_set_callerid($cid)
//{
//    return agi_evaluate("SET CALLERID $cid");
//}

/**
* Sets the context for continuation upon exiting the application.
*
* Setting the context does NOT automatically reset the extension and the priority; if you want to start at the top of the new 
* context you should set extension and priority yourself. 
*
* If you specify a non-existent context you receive no error indication (['result'] is still 0) but you do get a 
* warning message on the Asterisk console.
*
* @link http://www.voip-info.org/wiki-set+context
* @param string $context 
* @return array, see evaluate for return information.
*/
function agi_set_context($context)
{
    return agi_evaluate("SET CONTEXT $context");
}

/**
* Set the extension to be used for continuation upon exiting the application.
*
* Setting the extension does NOT automatically reset the priority. If you want to start with the first priority of the 
* extension you should set the priority yourself. 
*
* If you specify a non-existent extension you receive no error indication (['result'] is still 0) but you do 
* get a warning message on the Asterisk console.
*
* @link http://www.voip-info.org/wiki-set+extension
* @param string $extension
* @return array, see evaluate for return information.
*/
function agi_set_extension($extension)
{
    return agi_evaluate("SET EXTENSION $extension");
}

/**
* Enable/Disable Music on hold generator.
*
* @link http://www.voip-info.org/wiki-set+music
* @param boolean $enabled
* @param string $class
* @return array, see evaluate for return information.
*/
function agi_set_music($enabled=true, $class='')
{
    $enabled = ($enabled) ? 'ON' : 'OFF';
    return agi_evaluate("SET MUSIC $enabled $class");
}

/**
* Set the priority to be used for continuation upon exiting the application.
*
* If you specify a non-existent priority you receive no error indication (['result'] is still 0)
* and no warning is issued on the Asterisk console.
*
* @link http://www.voip-info.org/wiki-set+priority
* @param integer $priority
* @return array, see evaluate for return information.
*/
function agi_set_priority($priority)
{
    return agi_evaluate("SET PRIORITY $priority");
}

/**
* Sets a variable to the specified value. The variables so created can later be used by later using ${<variablename>}
* in the dialplan.
*
* These variables live in the channel Asterisk creates when you pickup a phone and as such they are both local and temporary. 
* Variables created in one channel can not be accessed by another channel. When you hang up the phone, the channel is deleted 
* and any variables in that channel are deleted as well.
*
* @link http://www.voip-info.org/wiki-set+variable
* @param string $variable is case sensitive
* @param string $value
* @return array, see evaluate for return information.
*/
function agi_set_variable($variable, $value)
{
    $value = str_replace("\n", '\n', addslashes($value));
    return agi_evaluate("SET VARIABLE $variable \"$value\"");
}

/**
* Play the given audio file, allowing playback to be interrupted by a DTMF digit. This command is similar to the GET DATA 
* command but this command returns after the first DTMF digit has been pressed while GET DATA can accumulated any number of 
* digits before returning.
*
* @example examples/ping.php Ping an IP address
*
* @link http://www.voip-info.org/wiki-stream+file
* @param string $filename without extension, often in /var/lib/asterisk/sounds
* @param string $escape_digits
* @param integer $offset
* @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no 
* digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
*/
function agi_stream_file($filename, $escape_digits='', $offset=0)
{
    return agi_evaluate("STREAM FILE $filename \"$escape_digits\" $offset");
}

/**
* Enable or disable TDD transmission/reception on the current channel.
*
* @link http://www.voip-info.org/wiki-tdd+mode
* @param string $setting can be on, off or mate
* @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 if the channel is not TDD capable.
*/
function agi_tdd_mode($setting)
{
    return agi_evaluate("TDD MODE $setting");
}

/**
* Sends $message to the Asterisk console via the 'verbose' message system.
*
* If the Asterisk verbosity level is $level or greater, send $message to the console.
*
* The Asterisk verbosity system works as follows. The Asterisk user gets to set the desired verbosity at startup time or later 
* using the console 'set verbose' command. Messages are displayed on the console if their verbose level is less than or equal 
* to desired verbosity set by the user. More important messages should have a low verbose level; less important messages 
* should have a high verbose level.
*
* @link http://www.voip-info.org/wiki-verbose
* @param string $message
* @param integer $level from 1 to 4
* @return array, see evaluate for return information.
*/
function agi_verbose($message, $level=1)
{
    foreach(explode("\n", str_replace("\r\n", "\n", print_r($message, true))) as $msg)
    {
      @syslog(LOG_WARNING, $msg);
      $ret = agi_evaluate("VERBOSE \"$msg\" $level");
    }
    return $ret;
}

/**
* Waits up to $timeout milliseconds for channel to receive a DTMF digit.
*
* @link http://www.voip-info.org/wiki-wait+for+digit
* @param integer $timeout in millisecons. Use -1 for the timeout value if you want the call to wait indefinitely.
* @return array, see evaluate for return information. ['result'] is 0 if wait completes with no 
* digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
*/
function agi_wait_for_digit($timeout=-1)
{
    return agi_evaluate("WAIT FOR DIGIT $timeout");
}


// AGI to run application

/**
* Set absolute maximum time of call.
*
* Note that the timeout is set from the current time forward, not counting the number of seconds the call has already been up. 
* Each time you call AbsoluteTimeout(), all previous absolute timeouts are cancelled. 
* Will return the call to the T extension so that you can playback an explanatory note to the calling party (the called party 
* will not hear that)
*
* @link http://www.voip-info.org/wiki-Asterisk+-+documentation+of+application+commands
* @link http://www.dynx.net/ASTERISK/AGI/ccard/agi-ccard.agi
* @param $seconds allowed, 0 disables timeout
* @return array, see evaluate for return information.
*/
function agi_exec_absolutetimeout($seconds=0)
{
    return agi_exec('AbsoluteTimeout', $seconds);
}

/**
* Executes an AGI compliant application.
*
* @param string $command
* @return array, see evaluate for return information. ['result'] is -1 on hangup or if application requested hangup, or 0 on non-hangup exit.
* @param string $args
*/
function agi_exec_agi($command, $args)
{
    return agi_exec("AGI $command", $args);
}

/**
* Set Language.
*
* @param string $language code
* @return array, see evaluate for return information.
*/
function agi_exec_setlanguage($language='en')
{
    return agi_exec('Set', 'CHANNEL(language)='. $language);
}

/**
* Do ENUM Lookup.
*
* Note: to retrieve the result, use
*   get_variable('ENUM');
*
* @param $exten
* @return array, see evaluate for return information.
*/
function agi_exec_enumlookup($exten)
{
    return agi_exec('EnumLookup', $exten);
}

/**
* Dial.
*
* Dial takes input from ${VXML_URL} to send XML Url to Cisco 7960
* Dial takes input from ${ALERT_INFO} to set ring cadence for Cisco phones
* Dial returns ${CAUSECODE}: If the dial failed, this is the errormessage.
* Dial returns ${DIALSTATUS}: Text code returning status of last dial attempt.
*
* @link http://www.voip-info.org/wiki-Asterisk+cmd+Dial
* @param string $type
* @param string $identifier
* @param integer $timeout
* @param string $options
* @param string $url
* @return array, see evaluate for return information.
*/
function agi_exec_dial($type, $identifier, $timeout=NULL, $options=NULL, $url=NULL)
{
    return agi_exec('Dial', trim("$type/$identifier".$this->option_delim.$timeout.$this->option_delim.$options.$this->option_delim.$url, $this->option_delim));
}

/**
* Goto.
*
* This function takes three arguments: context,extension, and priority, but the leading arguments
* are optional, not the trailing arguments.  Thuse goto($z) sets the priority to $z.
*
* @param string $a
* @param string $b;
* @param string $c;
* @return array, see evaluate for return information.
*/
function agi_exec_goto($a, $b=NULL, $c=NULL)
{
    return agi_exec('Goto', trim($a.$this->option_delim.$b.$this->option_delim.$c, $this->option_delim));
}

?>