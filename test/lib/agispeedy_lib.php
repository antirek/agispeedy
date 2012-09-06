<?php
///*-------------------------------------------------------------------------
//  AGI Class
//-------------------------------------------------------------------------*/
//class agispeedy_agi {
//    
//    var $sock = null;
//    var $scriptname = null;
//    var $input = array();
//    var $param = array();
//
//    function agispeedy_agi($in_sock)
//    {
//        $this->sock = $in_sock;
//    }
//
//    // load enviroments from agi header
//    function loadenviromentvars()
//    {
//        $szSocketRead=socket_read_response($this->sock,"\012\012");  //ENVIROMENT is \n\n end of
//        $agienv = $this->envresult2array($szSocketRead);
//        $this->input = $agienv[0];
//        $this->param = $agienv[1];
//
//        if (isset($this->input['agi_network_script'])==false)
//            return(true);
//
//        // fix scriptname if end with ?
//        // check params in url mode like asterisk 1.4
//        $agi_request = $this->input['agi_network_script'];
//        if (strpos($agi_request,'?')!==false) {
//            $fullname = explode("?",$agi_request);
//            $this->scriptname = $fullname[0];
//            //have params
//            if (isset($fullname[1])) {
//                foreach (explode("&",$fullname[1]) as $each) {
//                    $kv = explode("=",$each);
//                    if (count($kv) < 1)
//                        continue;
//                    $kv[0] = trim($kv[0]);
//                    if (isset($kv[1])) {
//                        $kv[1] = trim($kv[1]);
//                        $this->param[$kv[0]]=$kv[1];
//                    } else {
//                        $this->param[$kv[0]]=null;
//                    }
//                }
//            }
//        } else {
//      $this->scriptname = $agi_request;
//  }
//
//        return(true);
//    }
//
//    // AGI ENV VARIABLES TO ARRAY
//    function envresult2array($szResultIn)
//    {
//        $aResultOut=Array();
//        $aParams=array();
//
//        $szResultIn   = trim($szResultIn,"\r\n");
//        $aResultIn    = explode("\n",$szResultIn);
//
//        foreach($aResultIn as $key => $value)
//        {
//            $name = substr($value, 0, strpos($value, ':'));
//            if (preg_match("/^agi_arg_([0-9]+)/",$name)) {
//                $kv = explode('=',trim(substr($value, strpos($value, ':') + 1)));
//                $kv[0]=trim($kv[0]);
//                if (isset($kv[1])) {
//                    $aParams[$kv[0]]=$kv[1];
//                } else {
//                    $aParams[$kv[0]]=null;
//                }
//            } else {
//                $aResultOut[$name] = trim(substr($value, strpos($value, ':') + 1));
//            }
//        }
//
//    return (array($aResultOut,$aParams));
//    }
//
//    // send an agi send and waitting for receive
//    function evaluate($command)
//    {
//
//        $response = socket_send_command($command,$this->sock);
//        $response = $this->utils_agiresult2array($response);
//        return($response);
//    }
//
//    // AGI COMMAND RESULT DATA TO ARRAY
//    function utils_agiresult2array($szResultIn)
//    {
//        $code = substr($szResultIn,0,3);
//        $chunk = substr($szResultIn,4);
//        $result = null;
//        $data = null;
//        if ($code=='200') {
//
//            $fristspeace = strpos($chunk, " ");
//            if ($fristspeace==false) {
//                $kv = explode('=',$chunk);
//                $kv[1] = trim($kv[1]);
//                $result = $kv[1];
//            } else {
//                $result = substr($chunk,0,$fristspeace);
//                $kv = explode('=',$result);
//                $kv[1] = trim($kv[1]);
//                $result = $kv[1];
//                $data = substr($chunk,$fristspeace);
//                $data = trim($data);
//                $data = ltrim($data,"(");
//                $data = rtrim($data,")");
//            }
//
//        } else {
//
//            if (isset($string)){
//                $result = substr($string,4);
//            }
//
//        }
//
//        return(array('code'=>$code, 'result'=>$result, 'data'=>$data));
//    }
//
//
//    /* Answer channel if not already in answer state.
//    * @link http://www.voip-info.org/wiki-answer
//    * @return array, see evaluate for return information.  ['result'] is 0 on success, -1 on failure.
//    */
//    function answer()
//    {
//        return $this->evaluate('ANSWER');
//    }
//
//
//    /* Get the status of the specified channel. If no channel name is specified, return the status of the current channel.
//    *
//    * @link http://www.voip-info.org/wiki-channel+status
//    * @param string $channel
//    * @return array, see evaluate for return information. ['data'] contains description.
//    */
//    function channel_status($channel='')
//    {
//        $ret = $this->evaluate("CHANNEL STATUS $channel");
//        switch($ret['result'])
//        {
//            case -1: $ret['data'] = trim("There is no channel that matches $channel"); break;
//            case AST_STATE_DOWN: $ret['data'] = 'Channel is down and available'; break;
//            case AST_STATE_RESERVED: $ret['data'] = 'Channel is down, but reserved'; break;
//            case AST_STATE_OFFHOOK: $ret['data'] = 'Channel is off hook'; break;
//            case AST_STATE_DIALING: $ret['data'] = 'Digits (or equivalent) have been dialed'; break;
//            case AST_STATE_RING: $ret['data'] = 'Line is ringing'; break;
//            case AST_STATE_RINGING: $ret['data'] = 'Remote end is ringing'; break;
//            case AST_STATE_UP: $ret['data'] = 'Line is up'; break;
//            case AST_STATE_BUSY: $ret['data'] = 'Line is busy'; break;
//            case AST_STATE_DIALING_OFFHOOK: $ret['data'] = 'Digits (or equivalent) have been dialed while offhook'; break;
//            case AST_STATE_PRERING: $ret['data'] = 'Channel has detected an incoming call and is waiting for ring'; break;
//            default: $ret['data'] = "Unknown ({$ret['result']})"; break;
//        }
//        return $ret;
//    }
//
//    /**
//    * Deletes an entry in the Asterisk database for a given family and key.
//    *
//    * @link http://www.voip-info.org/wiki-database+del
//    * @param string $family
//    * @param string $key
//    * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise.
//    */
//    function database_del($family, $key)
//    {
//        return $this->evaluate("DATABASE DEL \"$family\" \"$key\"");
//    }
//
//    /**
//    * Deletes a family or specific keytree within a family in the Asterisk database.
//    *
//    * @link http://www.voip-info.org/wiki-database+deltree
//    * @param string $family
//    * @param string $keytree
//    * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise.
//    */
//    function database_deltree($family, $keytree='')
//    {
//        $cmd = "DATABASE DELTREE \"$family\"";
//        if($keytree != '') $cmd .= " \"$keytree\"";
//        return $this->evaluate($cmd);
//    }
//
//    /**
//    * Retrieves an entry in the Asterisk database for a given family and key.
//    *
//    * @link http://www.voip-info.org/wiki-database+get
//    * @param string $family
//    * @param string $key
//    * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 failure. ['data'] holds the value
//    */
//    function database_get($family, $key)
//    {
//        return $this->evaluate("DATABASE GET \"$family\" \"$key\"");
//    }
//
//    /**
//    * Adds or updates an entry in the Asterisk database for a given family, key, and value.
//    *
//    * @param string $family
//    * @param string $key
//    * @param string $value
//    * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise
//    */
//    function database_put($family, $key, $value)
//    {
//        $value = str_replace("\n", '\n', addslashes($value));
//        return $this->evaluate("DATABASE PUT \"$family\" \"$key\" \"$value\"");
//    }
//
//
////    /**
////    * Sets a global variable, using Asterisk 1.6 syntax.
////    *
////    * @link http://www.voip-info.org/wiki/view/Asterisk+cmd+Set
////    *
////    * @param string $pVariable
////    * @param string|int|float $pValue
////    * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise
////    */
////    function set_global_var($pVariable, $pValue)
////    {
////        if (is_numeric($pValue))
////            return $this->agi_exec("Set","{$pVariable}={$pValue},g");
//////            return $this->evaluate("Set({$pVariable}={$pValue},g);");
////        else
////            return $this->agi_exec("Set","{$pVariable}=\"{$pValue}\",g");
//////            return $this->evaluate("Set({$pVariable}=\"{$pValue}\",g);");
////    }
//
//
//    /**
//    * Sets a variable
//    *
//    * @link http://www.voip-info.org/wiki/view/set+variable
//    *
//    * @param string $pVariable
//    * @param string|int|float $pValue
//    * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise
//    */
//    function set_var($pVariable, $pValue)
//    {
//        if (is_numeric($pValue))
//            return $this->agi_exec("Set","{$pVariable}={$pValue}");
////            return $this->evaluate("Set({$pVariable}={$pValue});");
//        else
//            return $this->agi_exec("Set","{$pVariable}=\"{$pValue}\"");
////            return $this->evaluate("Set({$pVariable}=\"{$pValue}\");");
//    }
//
//
//    /**
//    * Executes the specified Asterisk application with given options.
//    *
//    * @link http://www.voip-info.org/wiki-exec
//    * @link http://www.voip-info.org/wiki-Asterisk+-+documentation+of+application+commands
//    * @param string $application
//    * @param mixed $options
//    * @return array, see evaluate for return information. ['result'] is whatever the application returns, or -2 on failure to find application
//    */
//    function agi_exec($application, $options=null)
//    {
//        if(is_array($options)) $options = join('|', $options);
//        return $this->evaluate("EXEC $application $options");
//    }
//
//    /**
//    * Plays the given file and receives DTMF data.
//    *
//    * This is similar to STREAM FILE, but this command can accept and return many DTMF digits,
//    * while STREAM FILE returns immediately after the first DTMF digit is detected.
//    *
//    * Asterisk looks for the file to play in /var/lib/asterisk/sounds by default.
//    *
//    * If the user doesn't press any keys when the message plays, there is $timeout milliseconds
//    * of silence then the command ends. 
//    *
//    * The user has the opportunity to press a key at any time during the message or the
//    * post-message silence. If the user presses a key while the message is playing, the
//    * message stops playing. When the first key is pressed a timer starts counting for
//    * $timeout milliseconds. Every time the user presses another key the timer is restarted.
//    * The command ends when the counter goes to zero or the maximum number of digits is entered,
//    * whichever happens first. 
//    *
//    * If you don't specify a time out then a default timeout of 2000 is used following a pressed
//    * digit. If no digits are pressed then 6 seconds of silence follow the message. 
//    *
//    * If you don't specify $max_digits then the user can enter as many digits as they want. 
//    *
//    * Pressing the # key has the same effect as the timer running out: the command ends and
//    * any previously keyed digits are returned. A side effect of this is that there is no
//    * way to read a # key using this command.
//    *
//    * @example examples/ping.php Ping an IP address
//    *
//    * @link http://www.voip-info.org/wiki-get+data
//    * @param string $filename file to play. Do not include file extension.
//    * @param integer $timeout milliseconds
//    * @param integer $max_digits
//    * @return array, see evaluate for return information. ['result'] holds the digits and ['data'] holds the timeout if present.
//    *
//    * This differs from other commands with return DTMF as numbers representing ASCII characters.
//    */
//    function get_data($filename, $timeout=NULL, $max_digits=NULL)
//    {
//        return $this->evaluate(rtrim("GET DATA $filename $timeout $max_digits"));
//    }
//
//    /**
//    * Fetch the value of a variable.
//    *
//    * Does not work with global variables. Does not work with some variables that are generated by modules.
//    *
//    * @link http://www.voip-info.org/wiki-get+variable
//    * @link http://www.voip-info.org/wiki-Asterisk+variables
//    * @param string $variable name
//    * @param boolean $getvalue return the value only
//    * @return array, see evaluate for return information. ['result'] is 0 if variable hasn't been set, 1 if it has. ['data'] holds the value. returns value if $getvalue is TRUE
//    */
//    function get_variable($variable,$getvalue=FALSE)
//    {
//        $res=$this->evaluate("GET VARIABLE $variable");
//
//        if($getvalue==FALSE)
//          return($res);
//
//        return($res['data']);
//    }
//
//
//    /**
//    * Fetch the value of a full variable.
//    *
//    *
//    * @link http://www.voip-info.org/wiki/view/get+full+variable
//    * @link http://www.voip-info.org/wiki-Asterisk+variables
//    * @param string $variable name
//    * @param string $channel channel
//    * @param boolean $getvalue return the value only 
//    * @return array, see evaluate for return information. ['result'] is 0 if variable hasn't been set, 1 if it has. ['data'] holds the value.  returns value if $getvalue is TRUE
//    */
//    function get_fullvariable($variable,$channel=FALSE,$getvalue=FALSE)
//    {
//      if($channel==FALSE){
//        $req = $variable;
//      } else {
//        $req = $variable.' '.$channel;
//      }
//      
//      $res=$this->evaluate('GET VARIABLE FULL '.$req);
//      
//      if($getvalue==FALSE)
//        return($res);
//      
//      return($res['data']);
//      
//    }
//
//    /**
//    * Hangup the specified channel. If no channel name is given, hang up the current channel.
//    *
//    * With power comes responsibility. Hanging up channels other than your own isn't something
//    * that is done routinely. If you are not sure why you are doing so, then don't.
//    *
//    * @link http://www.voip-info.org/wiki-hangup
//    * @example examples/dtmf.php Get DTMF tones from the user and say the digits
//    * @example examples/input.php Get text input from the user and say it back
//    * @example examples/ping.php Ping an IP address
//    *
//    * @param string $channel
//    * @return array, see evaluate for return information. ['result'] is 1 on success, -1 on failure.
//    */
//    function hangup($channel=null)
//    {
//        if ($channel==null) {
//            return $this->evaluate("HANGUP");
//        } else {
//            return $this->evaluate("HANGUP $channel");
//        }
//    }
//
//    /**
//    * Does nothing.
//    *
//    * @link http://www.voip-info.org/wiki-noop
//    * @return array, see evaluate for return information.
//    */
//    function noop($string="")
//    {
//        return $this->evaluate("NOOP \"$string\"");
//    }
//
//    /**
//    * Receive a character of text from a connected channel. Waits up to $timeout milliseconds for
//    * a character to arrive, or infinitely if $timeout is zero.
//    *
//    * @link http://www.voip-info.org/wiki-receive+char
//    * @param integer $timeout milliseconds
//    * @return array, see evaluate for return information. ['result'] is 0 on timeout or not supported, -1 on failure. Otherwise 
//    * it is the decimal value of the DTMF tone. Use chr() to convert to ASCII.
//    */
//    function receive_char($timeout=-1)
//    {
//        return $this->evaluate("RECEIVE CHAR $timeout");
//    }
//
//    /**
//    * Record sound to a file until an acceptable DTMF digit is received or a specified amount of
//    * time has passed. Optionally the file BEEP is played before recording begins.
//    *
//    * @link http://www.voip-info.org/wiki-record+file
//    * @param string $file to record, without extension, often created in /var/lib/asterisk/sounds
//    * @param string $format of the file. GSM and WAV are commonly used formats. MP3 is read-only and thus cannot be used.
//    * @param string $escape_digits
//    * @param integer $timeout is the maximum record time in milliseconds, or -1 for no timeout.
//    * @param integer $offset to seek to without exceeding the end of the file.
//    * @param boolean $beep
//    * @param integer $silence number of seconds of silence allowed before the function returns despite the 
//    * lack of dtmf digits or reaching timeout.
//    * @return array, see evaluate for return information. ['result'] is -1 on error, 0 on hangup, otherwise a decimal value of the 
//    * DTMF tone. Use chr() to convert to ASCII.
//    */
//    function record_file($file, $format, $escape_digits='', $timeout=-1, $offset=NULL, $beep=false, $silence=NULL)
//    {
//        $cmd = trim("RECORD FILE $file $format \"$escape_digits\" $timeout $offset");
//        if($beep) $cmd .= ' BEEP';
//        if(!is_null($silence)) $cmd .= " s=$silence";
//        return $this->evaluate($cmd);
//    }
//
//    /**
//    * Say the given digit string, returning early if any of the given DTMF escape digits are received on the channel.
//    *
//    * @link http://www.voip-info.org/wiki-say+digits
//    * @param integer $digits
//    * @param string $escape_digits
//    * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no 
//    * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
//    */
//    function say_digits($digits, $escape_digits='')
//    {
//        return $this->evaluate("SAY DIGITS $digits \"$escape_digits\"");
//    }
//
//    /**
//    * Say the given number, returning early if any of the given DTMF escape digits are received on the channel.
//    *
//    * @link http://www.voip-info.org/wiki-say+number
//    * @param integer $number
//    * @param string $escape_digits
//    * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no 
//    * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
//    */
//    function say_number($number, $escape_digits='')
//    {
//        return $this->evaluate("SAY NUMBER $number \"$escape_digits\"");
//    }
//
//    /**
//    * Say the given character string, returning early if any of the given DTMF escape digits are received on the channel.
//    *
//    * @link http://www.voip-info.org/wiki-say+phonetic
//    * @param string $text
//    * @param string $escape_digits
//    * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no 
//    * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
//    */
//    function say_phonetic($text, $escape_digits='')
//    {
//        return $this->evaluate("SAY PHONETIC $text \"$escape_digits\"");
//    }
//
//    /**
//    * Say a given time, returning early if any of the given DTMF escape digits are received on the channel.
//    *
//    * @link http://www.voip-info.org/wiki-say+time
//    * @param integer $time number of seconds elapsed since 00:00:00 on January 1, 1970, Coordinated Universal Time (UTC).
//    * @param string $escape_digits
//    * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no 
//    * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
//    */
//    function say_time($time=NULL, $escape_digits='')
//    {
//        if(is_null($time)) $time = time();
//        return $this->evaluate("SAY TIME $time \"$escape_digits\"");
//    }
//
//    /**
//    * Send the specified image on a channel.
//    *
//    * Most channels do not support the transmission of images.
//    *
//    * @link http://www.voip-info.org/wiki-send+image
//    * @param string $image without extension, often in /var/lib/asterisk/images
//    * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if the image is sent or 
//    * channel does not support image transmission.
//    */
//    function send_image($image)
//    {
//        return $this->evaluate("SEND IMAGE $image");
//    }
//
//    /**
//    * Send the given text to the connected channel.
//    *
//    * Most channels do not support transmission of text.
//    *
//    * @link http://www.voip-info.org/wiki-send+text
//    * @param $text
//    * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if the text is sent or 
//    * channel does not support text transmission.
//    */
//    function send_text($text)
//    {
//        return $this->evaluate("SEND TEXT \"$text\"");
//    }
//
//    /**
//    * Cause the channel to automatically hangup at $time seconds in the future.
//    * If $time is 0 then the autohangup feature is disabled on this channel.
//    *
//    * If the channel is hungup prior to $time seconds, this setting has no effect.
//    *
//    * @link http://www.voip-info.org/wiki-set+autohangup
//    * @param integer $time until automatic hangup
//    * @return array, see evaluate for return information.
//    */
//    function set_autohangup($time=0)
//    {
//        return $this->evaluate("SET AUTOHANGUP $time");
//    }
//
//    /**
//    * Changes the caller ID of the current channel.
//    *
//    * @link http://www.voip-info.org/wiki-set+callerid
//    * @param string $cid example: "John Smith"<1234567>
//    * This command will let you take liberties with the <caller ID specification> but the format shown in the example above works 
//    * well: the name enclosed in double quotes followed immediately by the number inside angle brackets. If there is no name then
//    * you can omit it. If the name contains no spaces you can omit the double quotes around it. The number must follow the name
//    * immediately; don't put a space between them. The angle brackets around the number are necessary; if you omit them the
//    * number will be considered to be part of the name.
//    * @return array, see evaluate for return information.
//    */
//    //function set_callerid($cid)
//    //{
//    //    return $this->evaluate("SET CALLERID $cid");
//    //}
//
//    /**
//    * Sets the context for continuation upon exiting the application.
//    *
//    * Setting the context does NOT automatically reset the extension and the priority; if you want to start at the top of the new 
//    * context you should set extension and priority yourself. 
//    *
//    * If you specify a non-existent context you receive no error indication (['result'] is still 0) but you do get a 
//    * warning message on the Asterisk console.
//    *
//    * @link http://www.voip-info.org/wiki-set+context
//    * @param string $context 
//    * @return array, see evaluate for return information.
//    */
//    function set_context($context)
//    {
//        return $this->evaluate("SET CONTEXT $context");
//    }
//
//    /**
//    * Set the extension to be used for continuation upon exiting the application.
//    *
//    * Setting the extension does NOT automatically reset the priority. If you want to start with the first priority of the 
//    * extension you should set the priority yourself. 
//    *
//    * If you specify a non-existent extension you receive no error indication (['result'] is still 0) but you do 
//    * get a warning message on the Asterisk console.
//    *
//    * @link http://www.voip-info.org/wiki-set+extension
//    * @param string $extension
//    * @return array, see evaluate for return information.
//    */
//    function set_extension($extension)
//    {
//        return $this->evaluate("SET EXTENSION $extension");
//    }
//
//    /**
//    * Enable/Disable Music on hold generator.
//    *
//    * @link http://www.voip-info.org/wiki-set+music
//    * @param boolean $enabled
//    * @param string $class
//    * @return array, see evaluate for return information.
//    */
//    function set_music($enabled=true, $class='')
//    {
//        $enabled = ($enabled) ? 'ON' : 'OFF';
//        return $this->evaluate("SET MUSIC $enabled $class");
//    }
//
//    /**
//    * Set the priority to be used for continuation upon exiting the application.
//    *
//    * If you specify a non-existent priority you receive no error indication (['result'] is still 0)
//    * and no warning is issued on the Asterisk console.
//    *
//    * @link http://www.voip-info.org/wiki-set+priority
//    * @param integer $priority
//    * @return array, see evaluate for return information.
//    */
//    function set_priority($priority)
//    {
//        return $this->evaluate("SET PRIORITY $priority");
//    }
//
//    /**
//    * Sets a variable to the specified value. The variables so created can later be used by later using ${<variablename>}
//    * in the dialplan.
//    *
//    * These variables live in the channel Asterisk creates when you pickup a phone and as such they are both local and temporary. 
//    * Variables created in one channel can not be accessed by another channel. When you hang up the phone, the channel is deleted 
//    * and any variables in that channel are deleted as well.
//    *
//    * @link http://www.voip-info.org/wiki-set+variable
//    * @param string $variable is case sensitive
//    * @param string $value
//    * @return array, see evaluate for return information.
//    */
//    function set_variable($variable, $value)
//    {
//        $value = str_replace("\n", '\n', addslashes($value));
//        return $this->evaluate("SET VARIABLE $variable \"$value\"");
//    }
//
//    /**
//    * Play the given audio file, allowing playback to be interrupted by a DTMF digit. This command is similar to the GET DATA 
//    * command but this command returns after the first DTMF digit has been pressed while GET DATA can accumulated any number of 
//    * digits before returning.
//    *
//    * @example examples/ping.php Ping an IP address
//    *
//    * @link http://www.voip-info.org/wiki-stream+file
//    * @param string $filename without extension, often in /var/lib/asterisk/sounds
//    * @param string $escape_digits
//    * @param integer $offset
//    * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no 
//    * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
//    */
//    function stream_file($filename, $escape_digits='', $offset=0)
//    {
//        return $this->evaluate("STREAM FILE $filename \"$escape_digits\" $offset");
//    }
//
//    /**
//    * Enable or disable TDD transmission/reception on the current channel.
//    *
//    * @link http://www.voip-info.org/wiki-tdd+mode
//    * @param string $setting can be on, off or mate
//    * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 if the channel is not TDD capable.
//    */
//    function tdd_mode($setting)
//    {
//        return $this->evaluate("TDD MODE $setting");
//    }
//
//    /**
//    * Sends $message to the Asterisk console via the 'verbose' message system.
//    *
//    * If the Asterisk verbosity level is $level or greater, send $message to the console.
//    *
//    * The Asterisk verbosity system works as follows. The Asterisk user gets to set the desired verbosity at startup time or later 
//    * using the console 'set verbose' command. Messages are displayed on the console if their verbose level is less than or equal 
//    * to desired verbosity set by the user. More important messages should have a low verbose level; less important messages 
//    * should have a high verbose level.
//    *
//    * @link http://www.voip-info.org/wiki-verbose
//    * @param string $message
//    * @param integer $level from 1 to 4
//    * @return array, see evaluate for return information.
//    */
//    function verbose($message, $level=1)
//    {
//        foreach(explode("\n", str_replace("\r\n", "\n", print_r($message, true))) as $msg)
//        {
//          @syslog(LOG_WARNING, $msg);
//          $ret = $this->evaluate("VERBOSE \"$msg\" $level");
//        }
//        return $ret;
//    }
//
//    /**
//    * Waits up to $timeout milliseconds for channel to receive a DTMF digit.
//    *
//    * @link http://www.voip-info.org/wiki-wait+for+digit
//    * @param integer $timeout in millisecons. Use -1 for the timeout value if you want the call to wait indefinitely.
//    * @return array, see evaluate for return information. ['result'] is 0 if wait completes with no 
//    * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
//    */
//    function wait_for_digit($timeout=-1)
//    {
//        return $this->evaluate("WAIT FOR DIGIT $timeout");
//    }
//
//
//    // AGI to run application
//
//    /**
//    * Set absolute maximum time of call.
//    *
//    * Note that the timeout is set from the current time forward, not counting the number of seconds the call has already been up. 
//    * Each time you call AbsoluteTimeout(), all previous absolute timeouts are cancelled. 
//    * Will return the call to the T extension so that you can playback an explanatory note to the calling party (the called party 
//    * will not hear that)
//    *
//    * @link http://www.voip-info.org/wiki-Asterisk+-+documentation+of+application+commands
//    * @link http://www.dynx.net/ASTERISK/AGI/ccard/agi-ccard.agi
//    * @param $seconds allowed, 0 disables timeout
//    * @return array, see evaluate for return information.
//    */
//    function exec_absolutetimeout($seconds=0)
//    {
//        return $this->agi_exec('AbsoluteTimeout', $seconds);
//    }
//
//    /**
//    * Executes an AGI compliant application.
//    *
//    * @param string $command
//    * @return array, see evaluate for return information. ['result'] is -1 on hangup or if application requested hangup, or 0 on non-hangup exit.
//    * @param string $args
//    */
//    function exec_agi($command, $args)
//    {
//        return $this->agi_exec("AGI $command", $args);
//    }
//
//    /**
//    * Set Language.
//    *
//    * @param string $language code
//    * @return array, see evaluate for return information.
//    */
//    function exec_setlanguage($language='en')
//    {
//        return $this->agi_exec('Set', 'CHANNEL(language)='. $language);
//    }
//
//    /**
//    * Do ENUM Lookup.
//    *
//    * Note: to retrieve the result, use
//    *   get_variable('ENUM');
//    *
//    * @param $exten
//    * @return array, see evaluate for return information.
//    */
//    function exec_enumlookup($exten)
//    {
//        return $this->agi_exec('EnumLookup', $exten);
//    }
//
//}
//
?>