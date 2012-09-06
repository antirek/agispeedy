<?php
error_reporting(E_ALL); //Everything report
set_time_limit(0);  //Tuneoff time limit
ob_implicit_flush();    //flash
declare(ticks = 1);    //fix som bugs with SIG

$sock=null;
$CHILDRENS=array();

$stderr = fopen('php://stderr', 'w');

//socket create
$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($sock, '0.0.0.0', '4573');
socket_listen($sock, 256);

while (true)
{
    $connection = @socket_accept($sock);
    if ($connection === false)	{
        usleep(1000); //sleep 0.0001 sec

    // here incoming
    } elseif ($connection > 0)  {

        $request = read($connection,"\012\012");
        fwrite($stderr,"-".$request."-");

//        socket_write($connection,$request."\012");

        //exit
        break;

//        socket_close($SERVER['sock']);// we have incoming and close main sock
//        $CLIENT['sock']=$connection;
//        utils_message('['.__FUNCTION__.']: catch one!',3,$SERVER['runmode'],$SERVER['output_level']);
//
//        if (function_exists('hooks_connection_accept')==true) // run hooks
//            hooks_connection_accept();
//
//        server_process_connection();
//
//         after done close client sock
//        server_process_exit();
    }
}

//socket close
socket_close($sock);

function read($handle)
{
    $data=null;
    $still=true;

/*
先检测是否能读，如果能读就立即读取，读取完毕后再检测一次是否能读，如果不能读了就立即返回

读的过程中，如果没有遇到\n\r会一直等待下去(因为当前数据有丢失的了)
*/

    while ($still) {
        //does this io can read?
        $r = array($handle);
        $w =NULL;
        $e= NULL;
        $vSelect = socket_select($r, $w, $e, 0,10000);
		if ($vSelect === false) {
            echo "error\n";
            $still=false;
            break;
        } elseif ($vSelect==0)	{
            $still=false;
            break;
        } else {
            $buf=socket_read($handle,4096,PHP_NORMAL_READ);
            $data .= $buf;
        }

    }

    return($data);
}
//$in = fopen('php://stdin', 'r');
//$out = fopen('php://stdout', 'w');
//$request=null;
//
//// read the request

//
//fwrite($out,$request);
//exit;


?>