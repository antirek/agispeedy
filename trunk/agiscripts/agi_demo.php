<?php
/*
    agi_demo.php:
    agi scripts demo, asterisk request agi://127.0.0.1/demo,key=value
    agispeedy call function agi_main() in agi_demo.php in agiscripts
    path.

    agi_main() is entry function and your agiscripts must have this.

    $CONF = &$GLOBALS['CONF'];  //config files
    $SERVER = &$GLOBALS['SERVER'];  //server variables
    $CLIENT = &$GLOBALS['CLIENT'];  //current session variables

*/

function agi_main()
{
    $CLIENT = &$GLOBALS['CLIENT'];  //current session variables
    $AGI_INPUT = $CLIENT['agi']['input'];   //agi enviroment variable
    $AGI_PARAMS = $CLIENT['agi']['params']; //agi params variable

    agi_answer();
    $rt = agi_channel_status();

    agi_say_digits($AGI_PARAMS['digits']);
    $input = agi_stream_file('demo-enterkeywords','0123456789');
    utils_message('[DEBUG]['.__FUNCTION__.']: receive input is "'.chr($input['result']).'"',4);

    agi_hangup();

return(true);
}
?>