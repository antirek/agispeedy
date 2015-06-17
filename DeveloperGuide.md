# DeveloperGuide #

## Installation ##

agispeedy compitable with linux system and asterisk.

agispeedy is an application server listen on 4573.

**your need install PHP >= 5.3.0 because older version doesn't support 'pcntl\_sigprocmask'.**

now!

frist download agispeedy source tarball.

copying files:
```
> tar zxvf agispeedy.X.X.tar.gz
> cp -rf agispeedy/ /
> cd /agispeedy/
> chmod +x bin/*
```

## Setup ##

### Frist\_time ###
folder struction:
```
agiscripts/	<--- save your agi scripts for agispeedy
bin/	<--- agispeedy main server
contrib/	<-- init script for centos
etc/	<--- agispeedy.conf here
```

try to startup agispeedy by manual:
```
cd /agispeedy/bin/
./agispeedy.php --verbose

output:
[INFO][1330655809,1882]: Agispeedy - AGI ApplicationServer 1.0 starting...
[INFO][1330655809,1882][socket_open]: Services on 0.0.0.0:4573
[DEBUG][1330655809,1882][server_loop]: children 1883 created!
[DEBUG][1330655809,1882][server_loop]: children 1884 created!
[DEBUG][1330655809,1882][server_loop]: children 1885 created!
[DEBUG][1330655809,1882][server_loop]: children 1886 created!
[DEBUG][1330655809,1882][server_loop]: children 1887 created!
[DEBUG][1330655809,1882][server_loop]: children 1888 created!
[DEBUG][1330655809,1882][server_loop]: children 1889 created!
[DEBUG][1330655809,1882][server_loop]: children 1890 created!
```

### Configure ###

#### agispeedy.conf ####
edit /agispeedy/etc/agispeedy.conf to change configure:
```

[general]
agiscripts_path=/agispeedy/agiscripts/     <--- where is your agiscripts to change
pidfile_path=/var/run/    <--- pid file path, need write and read permission

// daemon settings
[daemon]
host=0.0.0.0    <--- accept ip 0.0.0.0 from all, 127.0.0.1 only local
port=4573       <--- FastAGI is 4573
max_connections=2048    <--- max connections limits, 8 to 4096
max_idle_servers=8    <--- max perfork idle clone, 2 to 64

```

#### open files ####
enable change your need restart agispeedy services.
in most linux 'open files' is limited in poor value, "ulimit -a" to see,
to change this value, edit: /etc/security/limits.conf

append end of :
```
* soft nofile 32768
* hard nofile 65536
```

save and reboot your system.

#### database ####
agispeedy not support mysql\_pconnect, agi connect database in each request.
if your need fast database connect you can use hook functions to make connect at children creating.

and don't forget add your database max connection limits.


#### commandline ####
run agispeedy in command:
```

> /agispeedy/bin/agispeedy.php

AGISPEEDY-PHP version 1.0
Sun bing <hoowa.sun@gmail.com>
This is free software, and you are welcome to modify and redistribute it
under the GPL version 2 license.
This software comes with ABSOLUTELY NO WARRANTY.

Usage: /agispeedy/bin/agispeedy.php [--verbose|--quiet] [msgopt]
  --verbose     Service in front and default messages level 4.
  --quiet       Service as background default no messages, enable 'msgopt'
                for save message into '/tmp/agispeedy.log'.
msgopt:
  --debug       Message Level 4.
  --info        Message Level 3.
  --notice      Message Level 2.
  --warning     Message Level 1.
  --error       Message Level 0.
```

runtime:
  * --verbose output any thing
  * --quiet slience

#### services ####
init.d scripts in /agispeedy/contrib/ support centos/redhat system.

```
> cp /agispeedy/contrib/agispeedy.init.centos /etc/init.d/agispeedy/
> chkconfig --add agispeedy
> chkconfig --list|grep agispeedy
agispeedy       0:off   1:off   2:on    3:on    4:on    5:on    6:off
```

just start services as:
```
/etc/init.d/agispeedy start
```

stop services as:
```
/etc/init.d/agispeedy stop
```

restart services as:
```
/etc/init.d/agispeedy restart
```

service under safe\_agispeedy mode.

## Works ##

### Asterisk ###
Yes, Asterisk extension syntax little different with normal AGI.

for example, your before extension:
```

[demo]
exten => 8888,1,AGI(demo.php,digits=123456)
exten => 8888,n,Hangup()

```

now new extension is:
```

[demo]
exten => 8888,1,AGI(agi://127.0.0.1/demo,digits=123456)
exten => 8888,n,Hangup()

```

and if your agispeedy in remote server, change 127.0.0.1 to that server ip.

### Agiscripts ###

  * each agispeedy scripts must put in /agispeedy/agiscripts/

  * each scripts must name prefix agi_+scriptname+.php, example:
```
exten => 8888,1,AGI(agi://127.0.0.1/demo,digits=123456)
agispeedy call agi_demo.php in /agispeedy/agiscripts/.

exten => 8888,1,AGI(agi://127.0.0.1/router_internal,digits=123456)
agispeedy call agi_router_internal.php in /agispeedy/agiscripts/.

```_

  * each scripts has same entry function named agi\_main().
```
agi_demo.php:

<?php
function agi_main(&$agi)
{
    $agi->answer();
    $agi->hangup();

return(true);
}
?>

```

  * you can change agiscripts in anytime without restart services.

so now, did you run your frist agispeedy scripts?

## Functions ##

### HOOKS ###

agispeedy provides a number of "hooks" allowing for run your code at different levels of execution.
The placement of the hooks can be seen in the PROCESS FLOW section.

edit /agispeedy/bin/agispeedy\_hooks.php to write hooks code.

each hook function can reference main variables:

  * $CONF = &$GLOBALS['CONF'];  //config files
  * $SERVER = &$GLOBALS['SERVER'];  //server variables
  * $CLIENT = &$GLOBALS['CLIENT'];  //after hooks\_asterisk\_connection system set this


#### hooks\_configure ####

in server process,take to run immediately after server configure loaded.

```
function hooks_configure()
{
}
```

#### hooks\_socket\_blind ####

in server process,take to run immediately after server socket bind and before server loop.

```
function hooks_configure()
{
}
```

#### hooks\_server\_close ####

in server process,take to run immediately before server close.

```
// notice: 
function hooks_server_close()
{
}
```


#### hooks\_fork\_children ####

in each children, no effect of server main process.
take to run immediately after server forked children.
you can write database connect code in this function.

```
function hooks_fork_children()
{
}
```

#### hooks\_connection\_accept ####

in each children, no effect of server main process.
take to run immediately after new connectio incoming.
```
function hooks_connection_accept()
{
}
```

#### hooks\_asterisk\_connection ####

in each children, no effect of server main process.
take to run immediately after asterisk new request connected.
```
function hooks_asterisk_connection()
{
}
```

#### hooks\_connection\_close ####

in each children, no effect of server main process.
take to run immediately after an asterisk connection close.
```
function hooks_connection_close()
{
}
```

### AGIClass ###

Simple Asterisk Gateway Interface Object.

agispeedy create an $agi object of AGI class and provide follow methods.

```
function agi_main(&$agi)
{
    $agi->answer();
    $agi->hangup();

return(true);
}
```

#### $agi->evaluate ####

send an agi send and waitting for receive.

AGI commands url : http://www.voip-info.org/wiki/view/Asterisk+AGI

```
$result = $agi->evaluate("SAY DIGITS $digits \"$escape_digits\"");
print_r($result);
```


#### $agi->answer ####

  * Answer channel if not already in answer state.
  * @link http://www.voip-info.org/wiki-answer
  * @return array, see evaluate for return information.  ['result'] is 0 on success, -1 on failure.
```
function $agi->answer()
```

#### $agi->channel\_status ####

Get the status of the specified channel. If no channel name is specified, return the status of the current channel.

  * @link http://www.voip-info.org/wiki-channel+status
  * @param string $channel
  * @return array, see evaluate for return information. ['data'] contains description.

```
function $agi->channel_status($channel='')
```


#### $agi->database\_del ####

Deletes an entry in the Asterisk database for a given family and key.

  * @link http://www.voip-info.org/wiki-database+del
  * @param string $family
  * @param string $key
  * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise.
```
function $agi->database_del($family, $key)
```


#### $agi->database\_deltree ####

Deletes a family or specific keytree within a family in the Asterisk database.

  * @link http://www.voip-info.org/wiki-database+deltree
  * @param string $family
  * @param string $keytree
  * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise.
```
function $agi->database_deltree($family, $keytree='')
```


#### $agi->database\_get ####

Retrieves an entry in the Asterisk database for a given family and key.

  * @link http://www.voip-info.org/wiki-database+get
  * @param string $family
  * @param string $key
  * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 failure. ['data'] holds the value
```
function $agi->database_get($family, $key)
```


#### $agi->database\_put ####

Adds or updates an entry in the Asterisk database for a given family, key, and value.

  * @param string $family
  * @param string $key
  * @param string $value
  * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise
```
function $agi->database_put($family, $key, $value)
```


#### $agi->set\_var ####
Sets a variable, using Asterisk 1.6 syntax.

  * @link http://www.voip-info.org/wiki/view/set+variable
  * @param string $pVariable
  * @param string|int|float $pValue
  * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 otherwise
```
function $agi->set_var($pVariable, $pValue)
```


#### $agi->exec ####

Executes the specified Asterisk application with given options.

  * @link http://www.voip-info.org/wiki-exec
  * @link http://www.voip-info.org/wiki-Asterisk+-+documentation+of+application+commands
  * @param string $application
  * @param mixed $options
  * @return array, see evaluate for return information. ['result'] is whatever the application returns, or -2 on failure to find application
```
function $agi->exec($application, $options)
```


#### $agi->get\_data ####

Plays the given file and receives DTMF data.

This is similar to STREAM FILE, but this command can accept and return many DTMF digits,
while STREAM FILE returns immediately after the first DTMF digit is detected.

Asterisk looks for the file to play in /var/lib/asterisk/sounds by default.

If the user doesn't press any keys when the message plays, there is $timeout milliseconds
of silence then the command ends.

The user has the opportunity to press a key at any time during the message or the
post-message silence. If the user presses a key while the message is playing, the
message stops playing. When the first key is pressed a timer starts counting for
$timeout milliseconds. Every time the user presses another key the timer is restarted.
The command ends when the counter goes to zero or the maximum number of digits is entered,
whichever happens first.

If you don't specify a time out then a default timeout of 2000 is used following a pressed
digit. If no digits are pressed then 6 seconds of silence follow the message.

If you don't specify $max\_digits then the user can enter as many digits as they want.

Pressing the # key has the same effect as the timer running out: the command ends and
any previously keyed digits are returned. A side effect of this is that there is no
way to read a # key using this command.

  * @link http://www.voip-info.org/wiki-get+data
  * @param string $filename file to play. Do not include file extension.
  * @param integer $timeout milliseconds
  * @param integer $max\_digits
  * @return array, see evaluate for return information. ['result'] holds the digits and ['data'] holds the timeout if present.
  * This differs from other commands with return DTMF as numbers representing ASCII characters.
```
function $agi->get_data($filename, $timeout=NULL, $max_digits=NULL)
```

#### $agi->get\_variable ####

Fetch the value of a variable.
Does not work with global variables. Does not work with some variables that are generated by modules.

  * @link http://www.voip-info.org/wiki-get+variable
  * @link http://www.voip-info.org/wiki-Asterisk+variables
  * @param string $variable name
  * @param boolean $getvalue return the value only
  * @return array, see evaluate for return information. ['result'] is 0 if variable hasn't been set, 1 if it has. ['data'] holds the value. returns value if $getvalue is TRUE
```
function $agi->get_variable($variable,$getvalue=FALSE)
```

#### $agi->get\_fullvariable ####

Fetch the value of a full variable.
  * @link http://www.voip-info.org/wiki/view/get+full+variable
  * @link http://www.voip-info.org/wiki-Asterisk+variables
  * @param string $variable name
  * @param string $channel channel
  * @param boolean $getvalue return the value only
  * @return array, see evaluate for return information. ['result'] is 0 if variable hasn't been set, 1 if it has. ['data'] holds the value.  returns value if $getvalue is TRUE
```
function $agi->get_fullvariable($variable,$channel=FALSE,$getvalue=FALSE)
```

#### $agi->hangup ####

Hangup the specified channel. If no channel name is given, hang up the current channel.

With power comes responsibility. Hanging up channels other than your own isn't something
that is done routinely. If you are not sure why you are doing so, then don't.

  * @link http://www.voip-info.org/wiki-hangup
  * @param string $channel
  * @return array, see evaluate for return information. ['result'] is 1 on success, -1 on failure.
```
function $agi->hangup($channel='')
```

#### $agi->noop ####

Does nothing.

  * @link http://www.voip-info.org/wiki-noop
  * @return array, see evaluate for return information.
```
function $agi->noop($string="")
```

#### $agi->receive\_char ####

Receive a character of text from a connected channel. Waits up to $timeout milliseconds for
a character to arrive, or infinitely if $timeout is zero.

  * @link http://www.voip-info.org/wiki-receive+char
  * @param integer $timeout milliseconds
  * @return array, see evaluate for return information. ['result'] is 0 on timeout or not supported, -1 on failure. Otherwise
  * it is the decimal value of the DTMF tone. Use chr() to convert to ASCII.
```
function $agi->receive_char($timeout=-1)
```


#### $agi->record\_file ####

Record sound to a file until an acceptable DTMF digit is received or a specified amount of
time has passed. Optionally the file BEEP is played before recording begins.

  * @link http://www.voip-info.org/wiki-record+file
  * @param string $file to record, without extension, often created in /var/lib/asterisk/sounds
  * @param string $format of the file. GSM and WAV are commonly used formats. MP3 is read-only and thus cannot be used.
  * @param string $escape\_digits
  * @param integer $timeout is the maximum record time in milliseconds, or -1 for no timeout.
  * @param integer $offset to seek to without exceeding the end of the file.
  * @param boolean $beep
  * @param integer $silence number of seconds of silence allowed before the function returns despite the
  * lack of dtmf digits or reaching timeout.
  * @return array, see evaluate for return information. ['result'] is -1 on error, 0 on hangup, otherwise a decimal value of the
  * DTMF tone. Use chr() to convert to ASCII.
```
function $agi->record_file($file, $format, $escape_digits='', $timeout=-1, $offset=NULL, $beep=false, $silence=NULL)
```


#### $agi->say\_digits ####

Say the given digit string, returning early if any of the given DTMF escape digits are received on the channel.

  * @link http://www.voip-info.org/wiki-say+digits
  * @param integer $digits
  * @param string $escape\_digits
  * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
  * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
```
function $agi->say_digits($digits, $escape_digits='')
```

#### $agi->say\_number ####

Say the given number, returning early if any of the given DTMF escape digits are received on the channel.

  * @link http://www.voip-info.org/wiki-say+number
  * @param integer $number
  * @param string $escape\_digits
  * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
  * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
```
function $agi->say_number($number, $escape_digits='')
```

#### $agi->say\_phonetic ####

Say the given character string, returning early if any of the given DTMF escape digits are received on the channel.

  * @link http://www.voip-info.org/wiki-say+phonetic
  * @param string $text
  * @param string $escape\_digits
  * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
  * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
```
function $agi->say_phonetic($text, $escape_digits='')
```

#### $agi->say\_time ####

Say a given time, returning early if any of the given DTMF escape digits are received on the channel.

  * @link http://www.voip-info.org/wiki-say+time
  * @param integer $time number of seconds elapsed since 00:00:00 on January 1, 1970, Coordinated Universal Time (UTC).
  * @param string $escape\_digits
  * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
  * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
```
function $agi->say_time($time=NULL, $escape_digits='')
```

#### $agi->send\_image ####

Send the specified image on a channel.
Most channels do not support the transmission of images.

  * @link http://www.voip-info.org/wiki-send+image
  * @param string $image without extension, often in /var/lib/asterisk/images
  * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if the image is sent or
  * channel does not support image transmission.
```
function $agi->send_image($image)
```


#### $agi->send\_text ####

Send the given text to the connected channel.
Most channels do not support transmission of text.

  * @link http://www.voip-info.org/wiki-send+text
  * @param $text
  * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if the text is sent or
  * channel does not support text transmission.
```
function $agi->send_text($text)
```


#### $agi->set\_autohangup ####

Cause the channel to automatically hangup at $time seconds in the future.
If $time is 0 then the autohangup feature is disabled on this channel.
If the channel is hungup prior to $time seconds, this setting has no effect.

  * @link http://www.voip-info.org/wiki-set+autohangup
  * @param integer $time until automatic hangup
  * @return array, see evaluate for return information.
```
function $agi->set_autohangup($time=0)
```


#### $agi->set\_context ####

Sets the context for continuation upon exiting the application.

Setting the context does NOT automatically reset the extension and the priority; if you want to start at the top of the new
context you should set extension and priority yourself.

If you specify a non-existent context you receive no error indication (['result'] is still 0) but you do get a
warning message on the Asterisk console.

  * @link http://www.voip-info.org/wiki-set+context
  * @param string $context
  * @return array, see evaluate for return information.
```
function $agi->set_context($context)
```

#### $agi->set\_extension ####

Set the extension to be used for continuation upon exiting the application.

Setting the extension does NOT automatically reset the priority. If you want to start with the first priority of the
extension you should set the priority yourself.

If you specify a non-existent extension you receive no error indication (['result'] is still 0) but you do
get a warning message on the Asterisk console.

  * @link http://www.voip-info.org/wiki-set+extension
  * @param string $extension
  * @return array, see evaluate for return information.
```
function $agi->set_extension($extension)
```

#### $agi->set\_music ####

Enable/Disable Music on hold generator.

  * @link http://www.voip-info.org/wiki-set+music
  * @param boolean $enabled
  * @param string $class
  * @return array, see evaluate for return information.
```
function $agi->set_music($enabled=true, $class='')
```

#### $agi->set\_priority ####

Set the priority to be used for continuation upon exiting the application.

If you specify a non-existent priority you receive no error indication (['result'] is still 0)
and no warning is issued on the Asterisk console.

  * @link http://www.voip-info.org/wiki-set+priority
  * @param integer $priority
  * @return array, see evaluate for return information.
```
function $agi->set_priority($priority)
```


#### $agi->set\_variable ####

Sets a variable to the specified value. The variables so created can later be used by later using ${< variablename >}
in the dialplan.

These variables live in the channel Asterisk creates when you pickup a phone and as such they are both local and temporary.
Variables created in one channel can not be accessed by another channel. When you hang up the phone, the channel is deleted
and any variables in that channel are deleted as well.

  * @link http://www.voip-info.org/wiki-set+variable
  * @param string $variable is case sensitive
  * @param string $value
  * @return array, see evaluate for return information.
```
function $agi->set_variable($variable, $value)
```


#### $agi->stream\_file ####

Play the given audio file, allowing playback to be interrupted by a DTMF digit. This command is similar to the GET DATA
command but this command returns after the first DTMF digit has been pressed while GET DATA can accumulated any number of
digits before returning.

  * @link http://www.voip-info.org/wiki-stream+file
  * @param string $filename without extension, often in /var/lib/asterisk/sounds
  * @param string $escape\_digits
  * @param integer $offset
  * @return array, see evaluate for return information. ['result'] is -1 on hangup or error, 0 if playback completes with no
  * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
```
function $agi->stream_file($filename, $escape_digits='', $offset=0)
```


#### $agi->tdd\_mode ####

Enable or disable TDD transmission/reception on the current channel.

  * @link http://www.voip-info.org/wiki-tdd+mode
  * @param string $setting can be on, off or mate
  * @return array, see evaluate for return information. ['result'] is 1 on sucess, 0 if the channel is not TDD capable.
```
function $agi->tdd_mode($setting)
```


#### $agi->verbose ####

Sends $message to the Asterisk console via the 'verbose' message system.

If the Asterisk verbosity level is $level or greater, send $message to the console.

The Asterisk verbosity system works as follows. The Asterisk user gets to set the desired verbosity at startup time or later
using the console 'set verbose' command. Messages are displayed on the console if their verbose level is less than or equal
to desired verbosity set by the user. More important messages should have a low verbose level; less important messages
should have a high verbose level.

  * @link http://www.voip-info.org/wiki-verbose
  * @param string $message
  * @param integer $level from 1 to 4
  * @return array, see evaluate for return information.
```
function $agi->verbose($message, $level=1)
```

#### $agi->wait\_for\_digit ####

Waits up to $timeout milliseconds for channel to receive a DTMF digit.

  * @link http://www.voip-info.org/wiki-wait+for+digit
  * @param integer $timeout in millisecons. Use -1 for the timeout value if you want the call to wait indefinitely.
  * @return array, see evaluate for return information. ['result'] is 0 if wait completes with no
  * digit received, otherwise a decimal value of the DTMF tone.  Use chr() to convert to ASCII.
```
function $agi->wait_for_digit($timeout=-1)
```

#### $agi->exec\_absolutetimeout ####

Set absolute maximum time of call.

Note that the timeout is set from the current time forward, not counting the number of seconds the call has already been up.
Each time you call AbsoluteTimeout(), all previous absolute timeouts are cancelled.
Will return the call to the T extension so that you can playback an explanatory note to the calling party (the called party
will not hear that)

  * @link http://www.voip-info.org/wiki-Asterisk+-+documentation+of+application+commands
  * @link http://www.dynx.net/ASTERISK/AGI/ccard/agi-ccard.agi
  * @param $seconds allowed, 0 disables timeout
  * @return array, see evaluate for return information.
```
function $agi->exec_absolutetimeout($seconds=0)
```

#### $agi->exec\_agi ####

Executes an AGI compliant application.

  * @param string $command
  * @return array, see evaluate for return information. ['result'] is -1 on hangup or if application requested hangup, or 0 on non-hangup exit.
  * @param string $args
```
function $agi->exec_agi($command, $args)
```

#### $agi->exec\_setlanguage ####

Set Language.

  * @param string $language code
  * @return array, see evaluate for return information.
```
function $agi->exec_setlanguage($language='en')
```

#### $agi->exec\_enumlookup ####

Do ENUM Lookup.

Note: to retrieve the result, use get\_variable('ENUM');

  * @param $exten
  * @return array, see evaluate for return information.
```
function $agi->exec_enumlookup($exten)
```