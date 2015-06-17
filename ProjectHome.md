The Agispeedy is an AGI Application Server implemention in asterisk and written by Pure PHP

### DESCRIPTION ###

The Agispeedy is robust implemention of AGI in asterisk. Agispeedy is inconceivable faster than AGI. The result of test shows that by using Agispeedy in asterisk, the performance of system would be improved more than 10 times comparing with AGI.

![http://agispeedy.googlecode.com/files/agispeedy_flow.png](http://agispeedy.googlecode.com/files/agispeedy_flow.png)

Agispeedy Features:

  * compatible with asterisk 1.0/1.2/1.4/1.6/1.8/1.10 or higher.
  * 100% compatible AGI commands.
  * implemention of FastAGI over TCP Sockets.
  * Work in Stabilize Prefork Mode and Written by Pure PHP.
  * Automatic load your agi scripts when asterisk send request.
  * Providing possibility of running AGI on a remote server.
  * Fast Database Connect(hook functions in each children server).
  * **Perl Version (discard) in subversion : branches/agispeedy-perl**

### SYNOPSIS ###
agi\_demo.php
```
<?php
function agi_main(&$agi)
{
    $agi->answer();
    $agi->hangup();

return(true);
}
?>
```