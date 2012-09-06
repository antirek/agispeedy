<?php
$CONF_SET=array();
$CONF_SET['release']=null;
$CONF_SET['license']=array();
$CONF_SET['license']['max_extensions']=24;
$CONF_SET['path_base']='/pbxserver';
$CONF_SET['path_etc']=$CONF_SET['path_base'].'/etc';
$CONF_SET['path_bin']=$CONF_SET['path_base'].'/bin';
$CONF_SET['path_lib']=$CONF_SET['path_base'].'/lib';
$CONF_SET['path_moh']=$CONF_SET['path_base'].'/moh';
$CONF_SET['path_sound']=$CONF_SET['path_base'].'/sounds';
$CONF_SET['path_web']=$CONF_SET['path_base'].'/pbxweb';
$CONF_SET['path_record']='/tmp/pbxrecords';
$CONF_SET['agispeedy_maxconn']=64;

$CONF_SET['pbxkeeper']=array();
$CONF_SET['pbxkeeper']['asterisk']=array();
$CONF_SET['pbxkeeper']['asterisk']['enable']=true;
$CONF_SET['pbxkeeper']['asterisk']['mode']='proc';
$CONF_SET['pbxkeeper']['asterisk']['roundsec']=5;
$CONF_SET['pbxkeeper']['asterisk']['run']='/usr/sbin/asterisk';
$CONF_SET['pbxkeeper']['asterisk']['run_args']=array('-C','/pbxserver/etc/pbx/pbx.conf','-q');
$CONF_SET['pbxkeeper']['asterisk']['daemon']='asterisk';
$CONF_SET['pbxkeeper']['asterisk']['pidfile']='/var/run/asterisk/asterisk.pid';

?>