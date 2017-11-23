#!/usr/bin/env php
<?php


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
try 
{
    $qdb = $db->exec("UPDATE channels SET duration = 0 WHERE  groupname = '{$argv[1]}'");
}
catch(PDOException $e) 
{
    print("DB ALERT: ".$e->getMessage());
    die();
}
		

unset($qdb);
unset($db);

