<?php
/*
    Freeiris WRT, PBXSERVER
    Copyright (C) Sun bing <hoowa.sun@gmail.com>
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
$CONF_MY = array();  //server variable
// common
$CONF_MY['version'] = '2.0';
$CONF_MY['name'] = basename($_SERVER['SCRIPT_NAME'],'.php');
$CONF_MY['cli_args'] = array();
$CONF_MY['basedir'] = getcwd();
// log and error
$CONF_MY['stderr'] = fopen("php://stderr","w");
$CONF_MY['output_level'] = false;
$CONF_MY['log_file'] = '/tmp/'.$CONF_MY['name'].'.log';
$CONF_MY['log_handle'] = null;
$CONF_MY['runmode'] = 0;
// socket and server
$CONF_MY['server_host'] = '127.0.0.1';
$CONF_MY['server_port'] = '4573';
$CONF_MY['sock'] = null;
$CONF_MY['pid'] = '/var/run/'.$CONF_MY['name'].'.pid';
$CONF_MY['allow_run'] = true;
$CONF_MY['allow_fork'] = true;
$PROCESS = array();  //client variable
$CHILDRENS = array();  //children lists

require($CONF_MY['basedir']."/../lib/function.php");
require($CONF_MY['basedir']."/../lib/agispeedy_lib.php");

/*-------------------------------------------------------------------------
  incoming args check
-------------------------------------------------------------------------*/
foreach ($argv as $id=>$each) {
    if ($id===0)
        continue;
    $CONF_MY['cli_args'][$each]=true;
}

if (isset($CONF_MY['cli_args']['--verbose'])==false && isset($CONF_MY['cli_args']['--quiet'])==false) {
    print   wordwrap("AGISPEEDY-PHP version ".$CONF_MY['version']."\n".
            "Sun bing <hoowa.sun@gmail.com>\n".
            "\n".
            "Usage: ".$_SERVER['SCRIPT_NAME']." [--verbose|--quiet] [msgopt]\n".
            "  --verbose     Service in front and default messages level 4.\n".
            "  --quiet       Service as background default no messages, enable [msgopt]".
            "                  for save message into '".$CONF_MY['log_file']."'.\n".
            "msgopt: \n".
            "  --debug       Message Level 4.\n".
            "  --info        Message Level 3.\n".
            "  --notice      Message Level 2.\n".
            "  --warning     Message Level 1.\n".
            "  --error       Message Level 0.\n\n");
    exit;
}

if (isset($CONF_MY['cli_args']['--verbose'])==true) {
    $CONF_MY['runmode']=0; // 0 means verbose
    $CONF_MY['output_level']=4;
} else {
    $CONF_MY['runmode']=1; // 1 means quiet
}

if (isset($CONF_MY['cli_args']['--debug'])==true) {
    $CONF_MY['output_level'] = 4;
} elseif (isset($CONF_MY['cli_args']['--info'])==true) {
    $CONF_MY['output_level'] = 3;
} elseif (isset($CONF_MY['cli_args']['--notice'])==true) {
    $CONF_MY['output_level'] = 2;
} elseif (isset($CONF_MY['cli_args']['--warning'])==true) {
    $CONF_MY['output_level'] = 1;
} elseif (isset($CONF_MY['cli_args']['--error'])==true) {
    $CONF_MY['output_level'] = 0;
}

if ($CONF_MY['output_level'] !== false && $CONF_MY['runmode'] != 0) {
    $CONF_MY['log_handle'] = fopen($CONF_MY['log_file'],"a");    //logfile handler
}

/*-------------------------------------------------------------------------
  Server Runtime
-------------------------------------------------------------------------*/
server_message(': Agispeedy - AGI ApplicationServer '.$CONF_MY['version'].' starting...',3,$CONF_MY['runmode'],$CONF_MY['output_level']);
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
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];
    $CONF_MY = &$GLOBALS['CONF_MY'];

    // run me as background
    if ($CONF_MY['runmode']==1) {
        $pid = pcntl_fork();
        //fork failed
        if ($pid == -1) {
            server_message('['.__FUNCTION__.']: fork failure!',0,$CONF_MY['runmode'],$CONF_MY['output_level']);
            exit();
        //in parent to close the parent
        } elseif ($pid) {
            exit();
        //in child
        } else {
        }
    }

    // followed now is server main
    // register sig for main server
    pcntl_signal(SIGTERM  , 'server_sig_main');
    pcntl_signal(SIGINT   , 'server_sig_main');
    pcntl_signal(SIGHUP   , 'server_sig_main');
    pcntl_signal(SIGCHLD  , 'server_sig_main_chld');

    // pid check and process
    posix_setsid();
    umask(0);
    $CONF_MY['pid']=posix_getpid();

    // Checking myself in pid and locker make
    $CONF_MY['pid_file']='/var/run/'.$CONF_MY['name'].'.pid';
    if (func_checkpid($CONF_MY['pid_file'],$CONF_MY['name']) == true) {
        server_message('['.__FUNCTION__.']: Service alreadly exists in memory, Please kill the old and try me again!',0,$CONF_MY['runmode'],$CONF_MY['output_level']);
        exit;
    }
    if (file_put_contents($CONF_MY['pid_file'],$CONF_MY['pid'],LOCK_EX)===false) {
        server_message('['.__FUNCTION__.']: Write pid file failure, abort.!',0,$CONF_MY['runmode'],$CONF_MY['output_level']);
        exit;
    }

    // Load all hook functions
//    if (function_exists('hooks_configure')==true) // run hooks
//        hooks_configure();

    socket_open(); //try to open socket
}

// save to stop server and cleanup all
function server_stop()
{
//    $SERVER = &$GLOBALS['SERVER'];
//
//    server_message('['.__FUNCTION__.']: Server stopping...',3,$CONF_MY['runmode'],$CONF_MY['output_level']);
//
//    //last hooks
//    if (function_exists('hooks_server_close')==true) // run hooks
//        hooks_server_close();
//
//    $GLOBALS['ALLOW_RUN'] = false;  // tune allow server continue to run
//
//    // close main socket
//  if(is_resource($CONF_MY['sock']))
//        socket_close($CONF_MY['sock']);
//
//    // cleanup all children
//    server_children_cleanup();
//
//    //close memory
//    utils_mem_idle_close();
//
//    // close locker stderr
//    if (is_resource($GLOBALS['HANDLE_STDERR']))
//        fclose($GLOBALS['HANDLE_STDERR']);
//
//    if (is_resource($GLOBALS['HANDLE_LOGFILE']))
//        fclose($GLOBALS['HANDLE_LOGFILE']);
//
//    //delete pid
//    if (isset($CONF_MY['pid_file']) && is_file($CONF_MY['pid_file']))
//        unlink($CONF_MY['pid_file']);
}

//// in server: cleanup children
//function server_children_cleanup()
//{
//    $GLOBALS['ALLOW_FORK'] = false;
//    foreach ($GLOBALS['CHILDRENS'] as $each_pid=>$value) {
//        server_message('['.__FUNCTION__.']: Children ['.$each_pid.'] terminated...',3,$GLOBALS['SERVER']['runmode'],$GLOBALS['SERVER']['output_level']);
//        posix_kill($each_pid,9);
//    }
//    $GLOBALS['CHILDRENS']=array();
//}
//

// server loop for waitting for incoming
function server_loop()
{
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];
    $CONF_MY = &$GLOBALS['CONF_MY'];
    $CHILDRENS = &$GLOBALS['CHILDRENS'];

    // create children loop
    $trymask=0;
    $waitusec=50000;
    while ($GLOBALS['CONF_MY']['allow_run']==true)
    {

        // 检测是否临时禁止接受新进入,如果禁止就进入休眠等待进入下次循环
        if ($GLOBALS['CONF_MY']['allow_fork'] == false) {
            usleep($waitusec);
            continue;
        }

        // 接受新进入(non-block模式下会立即返回)
        $connection = @socket_accept($CONF_MY['sock']);
        if ($connection === false)    { // 如果返回false(可能是non-block立即返回)，进入下次循环
            usleep($waitusec);
            continue;
        } elseif ($connection <= 0)  { // 返回无效,进入下次循环
            usleep($waitusec);
            continue;
        } 

        // 正常连接抵达,临时屏蔽信号
        pcntl_sigprocmask(SIG_BLOCK,array(SIGTERM,SIGINT,SIGCHLD,SIGHUP));

        // 并发达到上限,关闭当前的进入下次循环
        if (count($CHILDRENS) > $CONF_MAIN['agispeedy_maxconn']) {
            server_message('['.__FUNCTION__.']: Connection max of limits!',0,$CONF_MY['runmode'],$CONF_MY['output_level']);
            pcntl_sigprocmask(SIG_UNBLOCK,array(SIGTERM,SIGINT,SIGCHLD,SIGHUP)); //解除屏蔽信号
            socket_close($connection); //关闭连接
            usleep($waitusec);
            continue;
        }

        // 正常,开始产生新进程
        $pid = pcntl_fork();
        if ($pid == -1) {
            server_message('['.__FUNCTION__.']: Children fork failure!',0,$CONF_MY['runmode'],$CONF_MY['output_level']);
            pcntl_sigprocmask(SIG_UNBLOCK,array(SIGTERM,SIGINT,SIGCHLD,SIGHUP)); //解除屏蔽信号
            break;
        } elseif ($pid == 0) {//in child
            //in child signal must default and unlock in children
            pcntl_signal(SIGTERM  , SIG_DFL);
            pcntl_signal(SIGINT   , SIG_DFL);
            pcntl_signal(SIGCHLD  , SIG_DFL);
            pcntl_signal(SIGHUP   , SIG_DFL);
            pcntl_sigprocmask(SIG_UNBLOCK,array(SIGTERM,SIGINT,SIGCHLD,SIGHUP)); //解除屏蔽信号
            //run children work
            server_children_work();
            exit;
        } else {//in parent
            server_message('['.__FUNCTION__.']: children '.$pid.' created!',4,$CONF_MY['runmode'],$CONF_MY['output_level']);
            //recored childrens
            $CHILDRENS[$pid]=null;
            // unblock
            pcntl_sigprocmask(SIG_UNBLOCK,array(SIGTERM,SIGINT,SIGCHLD,SIGHUP)); //解除屏蔽信号
            echo "done\n";
            exit;
            continue;
        }

        usleep($waitusec);
    }

}

//// in children
//// work wait for apcept
//function server_children_work()
//{
//    $SERVER = &$GLOBALS['SERVER'];
//    $CLIENT = &$GLOBALS['CLIENT'];
//    $CONF = &$GLOBALS['CONF'];
//
//    $newpid=posix_getpid();
//    $CLIENT['pid']=$newpid;
//    //$CLIENT['create_time']=time();
//
//    if (function_exists('hooks_fork_children')==true) // run hooks
//        hooks_fork_children();
//
//    /*
//        maybe php bug.
//        flock work with accept will block connect in
//    */
//    //$locker = fopen($CONF_MY['pid_file'],'w'); //locker accept
//    //flock($locker,LOCK_EX);
//    //server_message('['.__FUNCTION__.']: Waitting for request!',4,$CONF_MY['runmode'],$CONF_MY['output_level']);
//    //$a = time();
//
//    $connection = @socket_accept($CONF_MY['sock']);
//
//    //$b = time();
//    //flock($locker,LOCK_UN);
//
//    utils_mem_idle_dec();
//
//    if ($connection === false)    {
//        usleep(1000); //sleep 0.0001 sec
//
//    // here incoming
//    } elseif ($connection > 0)  {
//        socket_close($CONF_MY['sock']);// we have incoming and close main sock
//        $CLIENT['sock']=$connection;
//        server_message('['.__FUNCTION__.']: catch one!',3,$CONF_MY['runmode'],$CONF_MY['output_level']);
//
//        if (function_exists('hooks_connection_accept')==true) // run hooks
//            hooks_connection_accept();
//
//        server_process_connection();
//
//        // after done close client sock
//        server_process_exit();
//    }
//}
//
//// in children
//// close and clean and exit children
//function server_process_exit()
//{
//    $SERVER = &$GLOBALS['SERVER'];
//    $CLIENT = &$GLOBALS['CLIENT'];
//    $CONF = &$GLOBALS['CONF'];
//
//    socket_close($CLIENT['sock']);
//    server_message('['.__FUNCTION__.']: exit!',3,$CONF_MY['runmode'],$CONF_MY['output_level']);
//
//    if (function_exists('hooks_connection_close')==true) // run hooks
//        hooks_connection_close();
//
//    exit;
//}
//
//// in children
//// when an request connection now
//function server_process_connection()
//{
//    $SERVER = &$GLOBALS['SERVER'];
//    $CLIENT = &$GLOBALS['CLIENT'];
//    $CONF = &$GLOBALS['CONF'];
//
//    // AGI OBJECT
//    $agi = new agispeedy_agi($CLIENT['sock']);
//    $agi->loadenviromentvars();  // enviroments load
//
//    // check request
//    if(isset($agi->scriptname) && !empty($agi->scriptname))
//    {
//        if (function_exists('hooks_asterisk_connection')==true) // run hooks
//            hooks_asterisk_connection();
//        // try to load
//        if (file_exists($CONF['general']['agiscripts_path']."/agi_".$agi->scriptname.".php")==true)  {
//            server_message('['.__FUNCTION__.']: loading agi_'.$agi->scriptname.'.php',3,$CONF_MY['runmode'],$CONF_MY['output_level']);
//            require($CONF['general']['agiscripts_path']."/agi_".$agi->scriptname.".php");
//            if (function_exists('agi_main')==true) {
//                agi_main($agi);
//            } else {
//                $agi->evaluate("HANGUP");
//                server_message('['.__FUNCTION__.']: No Entry function agi_main in agi_'.$agi->scriptname.'.php',1,$CONF_MY['runmode'],$CONF_MY['output_level']);
//            }
//
//        } else {
//            $agi->evaluate("HANGUP");
//            server_message('['.__FUNCTION__.']: agi_'.$agi->scriptname.'.php not found!',1,$CONF_MY['runmode'],$CONF_MY['output_level']);
//        }
//
//        return(true);
//    }
//
//    $agi->evaluate("HANGUP");
//
//return(true);
//}
//
/*-------------------------------------------------------------------------
  SIG control
-------------------------------------------------------------------------*/
// for main server sig
function server_sig_main($sig)
{
    switch($sig) {
        case SIGTERM  :   server_stop();exit();break;
        case SIGINT   :   server_stop();exit();break;
        case SIGHUP   :   server_stop();exit();break;
    }
}
// for main server sig chld only
function server_sig_main_chld()
{
    while (($pid = pcntl_waitpid(-1, $status,WNOHANG)) > 0)
    {
        if (array_key_exists($pid,$GLOBALS['CHILDRENS'])) {
            unset($GLOBALS['CHILDRENS'][$pid]);
        }
    }
}


/*-------------------------------------------------------------------------
  Sockets Functions
-------------------------------------------------------------------------*/
// open and create socket
function socket_open()
{
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];
    $CONF_MY = &$GLOBALS['CONF_MY'];

    // main socket create
    if(($CONF_MY['sock'] = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP))===FALSE) {
        server_message('['.__FUNCTION__.']: Call to socket_create failed to create socket: '.socket_strerror($CONF_MY['sock']),0,$CONF_MY['runmode'],$CONF_MY['output_level']);
        server_stop();
        exit();
    }
    if (!socket_set_option($CONF_MY['sock'], SOL_SOCKET, SO_REUSEADDR, 1)) {   //not lookup server name in DNS
        server_message('['.__FUNCTION__.']: Unable to set option on socket: '.socket_strerror($CONF_MY['sock']),0,$CONF_MY['runmode'],$CONF_MY['output_level']);
        server_stop();
        exit();
    }
    if(($ret = @socket_bind($CONF_MY['sock'], $CONF_MY['server_host'], $CONF_MY['server_port']))===FALSE) {    //bind
        server_message('['.__FUNCTION__.']: Call to socket_bind failed to bind socket: '.socket_strerror($ret),0,$CONF_MY['runmode'],$CONF_MY['output_level']);
        server_stop();
        exit();
    }
    if(($ret = @socket_listen($CONF_MY['sock'], 256))===FALSE ) {    //listen
        server_message('['.__FUNCTION__.']: Call to socket listen failed to listen to socket: '.socket_strerror($ret),0,$CONF_MY['runmode'],$CONF_MY['output_level']);
        $server_stop();
        exit();
    }
    socket_set_nonblock($CONF_MY['sock']);     //perfork close nonblocking
    server_message('['.__FUNCTION__.']: Services on '.$CONF_MY['server_host'].':'.$CONF_MY['server_port'],3,$CONF_MY['runmode'],$CONF_MY['output_level']);

//    if (function_exists('hooks_socket_blind')==true) // run hooks
//        hooks_socket_blind();
}
//
////// wonderful close socket
////function socket_close_graceful()
////{
////    $SERVER = &$GLOBALS['SERVER'];
////    socket_shutdown($CONF_MY['sock'], 1);  //remote host yet can read
////    usleep(500);//wait remote host
////    socket_shutdown($CONF_MY['sock'], 0);//close reading
////    socket_close($CONF_MY['sock']);//finaly we can free resource
////    $CONF_MY['sock']=NULL;
////}
////
//// read socket response
//function socket_read_response($sock,$eof="\012")
//{
//    $szRead = null;//buffer of read
//    $timeout = 60;//timeout read
//
//    if(is_resource($sock)===FALSE)
//        return $szRead;
//
//  socket_set_nonblock($sock);
//  $iTimeStart = time(); //begin time
//  $iTimeLastRead = 0; //last read time
//    $select_tv_usec = 10000; //microseconds 0.001s
//  $bFOREVER = true;
//  for(;$bFOREVER;)
//    {
//      if(is_resource($sock)===FALSE) // may be socket disconnect to return
//            return $szRead;
//
//        //does this io can read?
//        $r = array($sock);
//        $w =NULL;
//        $e= NULL;
//        $vSelect = @socket_select($r, $w, $e, 0,$select_tv_usec);
//        if($vSelect===FALSE)// Select Error
//        {
//            server_message('['.__FUNCTION__.']: socket_select() failed, reason: '.socket_strerror(socket_last_error()),1,$CONF_MY['runmode'],$CONF_MY['output_level']);
//            break;
//        }
//
//        $iTimeNow = time();//current time
//        // select timeouted this time no data
//      if($vSelect==0) {
//
//            //has data and (no $EOF or only $EOF), check timeout 5sec
//            if ($iTimeLastRead > 0 && (strpos($szRead, $eof) === false || $szRead == $eof)) {
//                if  (($iTimeNow-$iTimeLastRead) > $timeout) {
//                    server_message('['.__FUNCTION__.']: Timed out no more data.',4,$GLOBALS['SERVER']['runmode'],$GLOBALS['SERVER']['output_level']);
//                    break;
//                }
//
//            //has data and has $EOF, end of read
//            } elseif ($iTimeLastRead > 0 && strpos($szRead, $eof) !== false) {
//                break;
//
//            //no data and always no data, check timeout 5sec
//            } elseif (($iTimeNow-$iTimeStart) > $timeout) {
//                server_message('['.__FUNCTION__.']: Timed out for data.',4,$GLOBALS['SERVER']['runmode'],$GLOBALS['SERVER']['output_level']);
//                break;
//            }
//          continue;
//      }// Nothing Happened
//
//        //read
//      foreach($r as $rs)
//      {
//          if (is_resource($rs)===FALSE)
//                return $szRead;
//
//          $szReadThis=@socket_read($rs,2048,PHP_BINARY_READ);
//          if ($szReadThis===FALSE || strlen($szReadThis)==0){
//                $bFOREVER=FALSE;
//                break;
//            }
//
//            server_message('['.__FUNCTION__.']: read a bit.',4,$GLOBALS['SERVER']['runmode'],$GLOBALS['SERVER']['output_level']);
//
//            $iTimeLastRead=time();
//          $szRead .= str_replace("\015\012","\012",$szReadThis);  //replace \r\n to \n
//          break;
//      }
//
//    }//End Forever
//
//    //exit direct
//    if ($szRead == "HANGUP\012") {
//        server_process_exit();
//    }
//
//    server_message('['.__FUNCTION__.']: read ('.strlen($szRead).')bytes end.',3,$GLOBALS['SERVER']['runmode'],$GLOBALS['SERVER']['output_level']);
//
//return $szRead;
//}
//
//// send to socket command and read response
//function socket_send_command($szCommand,$thisSock,$eof="\012")
//{
//  $szSocketRead = null;
//
//  if(!is_resource($thisSock))
//      return $szSocketRead;
//
//    server_message('['.__FUNCTION__.']: Send "'.$szCommand.'"',3,$GLOBALS['SERVER']['runmode'],$GLOBALS['SERVER']['output_level']);
//
//    //write success
//  if(@socket_write($thisSock,$szCommand.$eof)!==FALSE) {
//
//        $szSocketRead=socket_read_response($thisSock);  // by the way read from socket response
//
//        server_message('['.__FUNCTION__.']: Received '.trim($szSocketRead),4,$GLOBALS['SERVER']['runmode'],$GLOBALS['SERVER']['output_level']);
//
//    //write failed
//    } else {
//        server_message('['.__FUNCTION__.']: Send failure :'.$szCommand.socket_strerror(socket_last_error()),3,$GLOBALS['SERVER']['runmode'],$GLOBALS['SERVER']['output_level']);
//
//        return $szSocketRead;
//    }
//
//return $szSocketRead;
//}
//
//

/*-------------------------------------------------------------------------
  Sockets Functions
-------------------------------------------------------------------------*/
/* output messages
1 messages string
2 msg level number
3 runmode number
4 output level number
*/
function server_message($msg,$msglevel,$runmode,$outputlevel=false)
{
    //output to verbose
    if ($runmode === 0) {
        $handle = &$GLOBALS['CONF_MY']['stderr'];
    } else {
        $handle = &$GLOBALS['CONF_MY']['log_handle'];
    }

    if ($outputlevel !== false && $msglevel <= $outputlevel) {
        $type=null;
        switch($msglevel) {
            case 4  :   $type='DEBUG';break;
            case 3  :   $type='INFO';break;
            case 2  :   $type='NOTICE';break;
            case 1  :   $type='WARNING';break;
            case 0  :   $type='ERROR';break;
        }
        fwrite($handle,"[".$type."][".time().",".posix_getpid()."]".$msg."\n");
    }

}
?>
