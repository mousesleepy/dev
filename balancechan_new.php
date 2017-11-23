#!/usr/bin/env php
<?php
//init
date_default_timezone_set('Etc/GMT-11');
require_once('astapilib/agi.php');
set_time_limit(0);
declare(ticks = 1);
function on_hangup($signo)
{
    global $AGI;
    $AGI->Verbose('Originate channel hanguped');
    store_to_db();
    die();
}

function store_to_db()
{
    global $AGI, $db, $usedchan;
    $dur =(int) $AGI->GetVariable('CDR(billsec)');
    $AGI->Verbose("STORE DATA: channel {$usedchan} duration {$dur} secs.",3);
    if ($dur == 0)
    {
	return;
    }
    try {
	$qdb = $db->exec("UPDATE channels SET duration = duration + {$dur} WHERE channel = '{$usedchan}'");
    }
    catch(PDOException $e) {
	    $AGI->Verboseerbose("DB ALERT: ".$e->getMessage(),3);
	    die();
    }
    if ($qdb == 0){
	    $AGI->Verbose("DATABASE NOT UPDATED!",3);
    }
    
}

pcntl_signal(SIGHUP,  "on_hangup");
$AGI = new AGI();

$timeout = 90;
$opts = '';

// Удаляется имя звонящего из CALLERID на всякий случай, поскольку на шлюзах может только проблемы создавать и не используется
$AGI->SetVariable('CALLERID(name)', '');
$dialnumber = $argv[2];

$db = new PDO('sqlite:'.dirname(__FILE__).'/extastcfg.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try 
{
    $db->exec('CREATE TABLE IF NOT EXISTS channels (
    id INTEGER  NOT NULL PRIMARY KEY AUTOINCREMENT,
    channel VARCHAR(255)  NOT NULL UNIQUE,
    groupname VARCHAR(255)  NOT NULL,
    duration INTEGER,
    is_active INTEGER
    )');
}
catch(PDOException $e) 
{
    $AGI->Verboseerbose("DB ALERT: ".$e->getMessage(),3);
    die();
}

// Получаю массив с информацией о группе каналов
try
{
    $qdb = $db->query("SELECT * FROM channels WHERE groupname = '{$argv[1]}' and is_active = 1 order by duration");
}
catch(PDOException $e) 
{
    $AGI->Verboseerbose("DB ALERT: ".$e->getMessage(),3);
    die();
}
$qdb->setFetchMode(PDO::FETCH_ASSOC);
$rows = $qdb->fetchAll();
//перебор каналов, совершение вызова
foreach ($rows as $row)
{
    $usedchan = $row['channel'];
    $AGI->Exec('Dial',$row['channel'].$dialnumber.','.$timeout.',Ttg'.$opts);
    $CAUSECODE = $AGI->GetVariable('HANGUPCAUSE');
    $SIP_CAUSE = $AGI->GetVariable('SIP_CAUSE');
    $AGI->Verbose("CAUSECODE = ".$CAUSECODE);
    // 17 - channel is used
    if ($CAUSECODE == '16') //NORMAL
    {
	break;
	
    } 
    if ($CAUSECODE == '0') //CANCEL
    {
	break;
    } 
    if ($CAUSECODE == '19') //cause translate from 19 (addpack busy) 17 (isdn busy)
    {
	$AGI->Exec('Playtones','busy');
	$AGI->Exec('Busy');
	break;
    } 
}
if ($CAUSECODE == '17' || $CAUSECODE == '20') //last cause code 
{
    $logdata = date("Y-m-d H:i:s")." All channels are busy S:{$SIP_CAUSE} I:{$CAUSECODE}\n";
    file_put_contents(dirname(__FILE__).'/balancechan.log',$logdata,FILE_APPEND);
    $AGI->Exec('Playtones','info');
    sleep(2);
    $AGI->Exec('Playback','all-circuits-busy-now&pls-try-call-later,noanswer');
    $AGI->Exec('Playtones','congestion');
    $AGI->Exec('Congestion');
}
store_to_db();
unset($qdb);
unset($db);

/*    case 'reset':
		try {
		    $qdb = $db->exec("UPDATE channels SET duration = 0 WHERE  groupname = '{$argv[2]}'");
		}
    		catch(PDOException $e) {
			print("DB ALERT: ".$e->getMessage());
			die();
		}
		
		break;
    default:
		die("Illegal command\n");

*/
