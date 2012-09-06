<?php
/*
    Freeiris WRT, PBXSERVER
    Copyright (C) Sun bing <hoowa.sun@gmail.com>
*/

// init and default set
const CONF_MAIN_VERSION = '5.5';
const CONF_MAIN_BUILDVER = '0';
const CONF_MAIN_REVISION = '0';

const PIF_ISSET = 10;       // checktype is set default in all
const PIF_ISNOTNULL = 11;   // checktype is set & is not null
const PIF_ISDIGINNUL = 12;  // is set & is not null & is [0-9]
const PIF_ISNOEXTEN = 13;   // is set & is not null & is [0-9a-zA-Z\+]
const PIF_ISARRAY = 14;     // is set & is not null & in array
const PIF_ISPHONENUM = 15;  // is set & is not null & is [0-9\*]
const PIF_ISPUREDIGI = 16;   // is set & is [0-9]
const PIF_ISFULLPNUM = 17;  // is set & is not null & is [0-9\*\#]

require_once(dirname(__FILE__).'/../etc/pbxserver_conf.php');
$CONF_MAIN=func_init();
unset($GLOBALS['CONF_SET']);

function func_init()
{
    if (!isset($GLOBALS['CONF_SET'])) {
        func_message('SYSTEM ERROR, NOT FOUND CONF_SET.',true);
        exit;
    }
    $myconf = &$GLOBALS['CONF_SET'];
    $myconf['timezone']=trim(file_get_contents('/tmp/TZ'));
    $myconf['database_engine']='sqlite3';
    $myconf['database_sqlite_key']='18600061138';
    $myconf['database_sqlite_timeout']=1000;
    $myconf['database_sqlite_init']=array();
    $myconf['database_sqlite_init'][]='PRAGMA cache_size=3000';
    $myconf['database_sqlite_init'][]='PRAGMA encoding="UTF-8"';
    $myconf['database_sqlite_init'][]='PRAGMA synchronous = NORMAL';
    $myconf['database_sqlite_init'][]='PRAGMA count_changes = 1';
    $myconf['error']=array();
    $myconf['error']['code']=null;
    $myconf['error']['message']=null;

    date_default_timezone_set($myconf['timezone']);

    return($myconf);
}


function func_message($msg,$display=false)
{
    if ($display === true) {
        $HANDLE_STDERR = fopen("php://stderr","w");    //stderr handle
        fwrite($HANDLE_STDERR,"[".time()."] ".$msg."\n");
    }
}


function func_getportkey(){return('AK47FUSE');}


function func_argsfilter($def,$args)
{
    foreach ($def as $key=>$type) {
        // all need check PIF_ISSET
        if (!array_key_exists($key,$args)) {
            return(false);
        }
        // standard
        if ($type == PIF_ISNOTNULL && $args[$key] == null) {
            return(false);

        } elseif ($type == PIF_ISDIGINNUL) {
            if($args[$key] == null || preg_match('/[^0-9]/',$args[$key]))
                return(false);
        } elseif ($type == PIF_ISNOEXTEN) {
            if($args[$key] == null || preg_match("/[^0-9a-zA-Z\+]/",$args[$key]))
                return(false);
        } elseif ($type == PIF_ISARRAY) {
            if($args[$key] == null || is_array($args[$key]) == false)
                return(false);
        } elseif ($type == PIF_ISPHONENUM) {
            if($args[$key] == null || preg_match("/[^0-9\*]/",$args[$key]))
                return(false);
        } elseif ($type == PIF_ISPUREDIGI) {
            if($args[$key] != null && preg_match('/[^0-9]/',$args[$key]))
                return(false);
        } elseif ($type == PIF_ISFULLPNUM) {
            if($args[$key] == null || preg_match("/[^0-9\*\#]/",$args[$key]))
                return(false);
        }

    }

    return(true);
}


function func_seturgency($level=0)
{
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];
    if (is_file($CONF_MAIN['path_etc'].'/urgency')) {
        file_put_contents($CONF_MAIN['path_etc'].'/urgency',3);
    } else {
        file_put_contents($CONF_MAIN['path_etc'].'/urgency',$level);
    }
    return(true);
}

function func_geturgency()
{
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];
    if (is_file($CONF_MAIN['path_etc'].'/urgency')) {
        return(trim(file_get_contents($CONF_MAIN['path_etc'].'/urgency')));
    } else {
        return(0);
    }
}


/*-------------------------------------------------------------------------
  database handle
-------------------------------------------------------------------------*/
// this will connect to database and try to reconnect
// sqlite3: file=>,flags=>,encryption_key=>
function func_dbcon($dbopts)
{
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];

    if (!is_array($dbopts)) {
        return(false);
    }

    if (!isset($dbopts['file']) || $dbopts['file'] == null)
        return(false);
    if (!isset($dbopts['flags']) || $dbopts['flags'] == null)
        $dbopts['flags']=SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE;
    if (!isset($dbopts['encryption_key']) || $dbopts['encryption_key'] == null)
        $dbopts['encryption_key']=null;

    $dblink = new SQLite3($dbopts['file'],$dbopts['flags'],$dbopts['encryption_key']);
    $dblink->busyTimeout($CONF_MAIN['database_sqlite_timeout']);
    foreach ($CONF_MAIN['database_sqlite_init'] as $each) {
        $dblink->query($each);
    }

    return($dblink);
}

// disconnect database
function func_dbdiscon($dblink)
{
    if (is_object($dblink)) {
        $dblink->close();
        return(true);
    }
}

function func_dbfmtsql($dbcon,$template,$array,$select_keyword=null)
{
    $keywords=null;
    $values=null;

    if ($select_keyword != null) {
        foreach ($select_keyword as $keyname) {
            $keywords .= $keyname.',';
            if (isset($array[$keyname])) {
                $values .= "'".$dbcon->escapeString($array[$keyname])."',";
            } else {
                $values .= "'',";
            }
        }

    } else {
        foreach ($array as $keyname => $value) {
            $keywords .= $keyname.',';
            $values .= "'".$dbcon->escapeString($array[$keyname])."',";
        }
    }
    $keywords = rtrim($keywords,',');
    $values = rtrim($values,',');

    return(sprintf($template,$keywords,$values));
}

function func_dbexecarray($dbcon,$sql_array)
{

    @$dbcon->query('BEGIN TRANSACTION;');
    if ($dbcon->lastErrorCode() !== 0) {
//        $PBXCTL['error']['code'] = PE_DBQSER;
//        $PBXCTL['error']['messsage'] = $dbcon->lastErrorMsg();
        return(false);
    }
    foreach ($sql_array as $each) {
        @$dbcon->query($each);
        if ($dbcon->lastErrorCode() !== 0) {
//            $PBXCTL['error']['code'] = PE_DBQSER;
//            $PBXCTL['error']['messsage'] = $dbcon->lastErrorMsg();
            return(false);
        }
    }
    @$dbcon->query('END TRANSACTION;');
    if ($dbcon->lastErrorCode() !== 0) {
//        $PBXCTL['error']['code'] = PE_DBQSER;
//        $PBXCTL['error']['messsage'] = $dbcon->lastErrorMsg();
        return(false);
    }

    return(true);
}

///*
//    $dblink is obj
//    $tuneall: true/false
//*/
//function func_aql_dbsync_files($dbcon,$tuneall=false)
//{
//    $PSFUNC = &$GLOBALS['PSFUNC'];
//
//    $sql=null;
//    if ($tuneall == true) {
//        $sql = 'select filename,section,rawdata,level from pbx_astconf';
//    } else {
//        $sql = 'select filename,section,rawdata,level from pbx_astconf where tunedo != 0';
//    }
//
//    //get all need sync datas
//    $allfiles=array();
//    $buffer=null;
//    $lastname=null;
//    $unsection=null;
//    $result = @$dbcon->query($sql);
//    if ($dbcon->lastErrorCode() !== 0) {
//        print "database error: ".$dbcon->lastErrorMsg()."\n";
//        return(false);
//    }
//    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
//
//        if ($lastname != $row['filename']) {
//            if ($buffer != null || $unsection != null) {
//                $allfiles[$lastname] = $unsection."\n".$buffer;
//            }
//            $buffer = null;
//            $lastname = $row['filename'];
//        }
//
//        if ($row['section'] == null) {
//            $unsection = $row['rawdata'];
//        } else {
//            $buffer .= "[".$row['section']."]\n".$row['rawdata']."\n";
//        }
//    }
//    if ($buffer != null || $unsection != null) {
//        $allfiles[$lastname] = $unsection."\n".$buffer;
//    }
//    $result->finalize();
//
//    @$dbcon->query('BEGIN TRANSACTION;');
//    if ($dbcon->lastErrorCode() !== 0) {
//        print "database error: ".$dbcon->lastErrorMsg()."\n";
//        return(false);
//    }
//    foreach ($allfiles as $filename=>$rawdata) {
//        $fullpath = $PSFUNC['basedir'].'/../etc/pbx/'.$filename;
//        file_put_contents($fullpath,$rawdata);
//        @$dbcon->query('update pbx_astconf set tunedo = 0 where filename = "'.$dbcon->escapeString($filename).'"');
//        if ($dbcon->lastErrorCode() !== 0) {
//            print "database error: ".$dbcon->lastErrorMsg()."\n";
//            return(false);
//        }
//    }
//    @$dbcon->query('END TRANSACTION;');
//    if ($dbcon->lastErrorCode() !== 0) {
//        print "database error: ".$dbcon->lastErrorMsg()."\n";
//        return(false);
//    }
//
//    if (is_file($PSFUNC['basedir'].'/../etc/urgency'))
//        unlink($PSFUNC['basedir'].'/../etc/urgency');
//
//    return($allfiles);
//}


/*-------------------------------------------------------------------------
  MiniAQL is simple and fast asterisk conf control functions
-------------------------------------------------------------------------*/
// simple and direct to append string to file
function func_maql_file_append($filename,$string)
{
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];

    if (!is_file($filename)) {
        $CONF_MAIN['error']['message']='file not found';
        return(false);
    }
    $handle = fopen($filename,'a+');
    if (!$handle) {
        $CONF_MAIN['error']['message']='file open failure';
        return(false);
    }
    if (flock($handle, LOCK_EX)) {  // acquire an exclusive lock
        fwrite($handle,$string);
        fflush($handle);            // flush output before releasing the lock
        flock($handle, LOCK_UN);    // release the lock
        fclose($handle);
        return(true);
    } else {
        fclose($handle);
        $CONF_MAIN['error']['message']='file open lock failure';
        return(false);
    }

}

function func_maql_init($filename)
{
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];
    if (!is_file($filename)) {
        $CONF_MAIN['error']['message']='file not found';
        return(false);
    }
    $conffile=array();
    $conffile['filename']=$filename;
    $conffile['stream']=file_get_contents($filename);

    return($conffile);
}

function func_maql_intl_keywordpos(&$stream,$keyword,$offset=0)
{
    // find the keyword
    $start=null;
    for ($i=0;$i<=10;$i++) {
        $start = strpos($stream,$keyword,$offset);
        if ($start === false) {
            break;
        }
        if ($start == 0) {
            break;
        }

        $isok=true;
        for ($q=1;$q<=$start;$q++) {
            $char = $stream[($start-$q)];

            if ($char == ';') {
                $isok=false;
                $offset=($start+1);
                break 1;
            } elseif ($char == "\n") {
                break;
            }
        }

        if ($isok==true) {
            break;
        }

    }
    return($start);
}

// delete key from file
function func_maql_file_rawdel(&$res,$line,$savefile=true)
{
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];
    if (!isset($res['stream'])) {
        $CONF_MAIN['error']['message']='no stream in mares';
        return(false);
    }

    $start = func_maql_intl_keywordpos($res['stream'],$line);
    if ($start === false || $start === null)
        return(null);

    $res['stream'] = substr_replace($res['stream'],'',$start,strlen($line));

    if ($savefile==true) {
        file_put_contents($res['filename'],$res['stream']);
    }

    return(true);
}

// note: this function not support section name
// $res must is reference of func_maql_init
function func_maql_key_get(&$res,$keyname)
{
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];
    if (!isset($res['stream'])) {
        $CONF_MAIN['error']['message']='no stream in mares';
        return(false);
    }
    $start = func_maql_intl_keywordpos($res['stream'],$keyname);
    if ($start === false || $start === null)
        return(null);

    // find value end
    $buffer=null;
    for ($i=$start;$i<=(strlen($res['stream'])-1);$i++) {
        $data = $res['stream'][$i];
        if ($data == "\n" || $data == ';') {
            break;
        } else {
            $buffer .= $data;
        }
    }
    if ($buffer == null)
        return(null);
    $kv = explode("=",$buffer);
    if (!isset($kv[1])) {
        return(null);
    }
    $value = trim($kv[1]);

    return($value);
}

// return as raw of section
function func_maql_section_asraw(&$res,$section,$trimsection=true)
{
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];
    if (!isset($res['stream'])) {
        $CONF_MAIN['error']['message']='no stream in mares';
        return(false);
    }

    $section_keyword = "[".$section."]";

    $start = func_maql_intl_keywordpos($res['stream'],$section_keyword);
    if ($start === false || $start === null)
        return(null);

    $end = func_maql_intl_keywordpos($res['stream'],'[',($start+strlen($section_keyword)));
    if ($end === false || $end === null)
        $end = strlen($res['stream']);

    if ($trimsection != true) {
        return(trim(substr($res['stream'],$start,$end)));
    } else {
        return(trim(substr($res['stream'],($start+strlen($section_keyword)),$end)));
    }
}

// delete section from file
function func_maql_section_del(&$res,$section,$savefile=true)
{
    $CONF_MAIN = &$GLOBALS['CONF_MAIN'];
    if (!isset($res['stream'])) {
        $CONF_MAIN['error']['message']='no stream in mares';
        return(false);
    }

    $section_keyword = "[".$section."]";

    $start = func_maql_intl_keywordpos($res['stream'],$section_keyword);
    if ($start === false || $start === null) {
        $CONF_MAIN['error']['message']='no section found';
        return(false);
    }

    $end = func_maql_intl_keywordpos($res['stream'],'[',($start+strlen($section_keyword)));
    if ($end === false || $end === null)
        $end = strlen($res['stream']);
    $res['stream'] = substr_replace($res['stream'],'',$start,($end-$start));
    if ($savefile==true) {
        file_put_contents($res['filename'],$res['stream']);
    }

    return(true);
}

/*-------------------------------------------------------------------------
  Processor Opeartor Functions
-------------------------------------------------------------------------*/
// for all single
function func_sig_all($sig)
{
    switch($sig) {
        case SIGTERM	:   exit();break;
        case SIGINT	    :	exit();break;
        case SIGHUP 	:   exit();break;
    }
}
// for main server sig chld only
function func_sig_chld()
{
    while (($pid = pcntl_waitpid(-1, $status,WNOHANG)) > 0)
    {
    }
}

// check pid is exists
function func_checkpid($pid,$thisname,$pidnum_string=null)
{
	$exists = false;

    $pid_number = null;
    if ($pidnum_string != null) {
        $pid_number = $pidnum_string;
    } elseif (is_file($pid)==true) {
		$pid_number = trim(file_get_contents($pid));
	}

    // if this process is exists?
    if ($pid_number != null && is_file("/proc/".$pid_number."/cmdline")) {
        $pid_cmdline = trim(file_get_contents('/proc/'.$pid_number.'/cmdline'));

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

return($exists);
}


?>