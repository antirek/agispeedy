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
        fwrite($stderr,$request);

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

function read($handle,$breakchar="\012")
{
    $data=null;
    $still=true;
    while ($still) {
        $buf=socket_read($handle,1);
        $data .= $buf;
        if (preg_match("/".$breakchar."$/",$data)) {
            $still=false;
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