<?php
require_once('../config.php');
require_once('../DB.php');
require_once('../d_Mailschedule.php');
require_once('../BrachioMailer.php');

if (DB::CreateDBConnection()) {
    d_Mailschedule::getInstance()->Create();
} else {
    exit('Cannot connect to database.<br />');
}

$messagehtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=UTF-8" />
		<title>Test10</title>
	</head>
	<body>
		<h1>test message10</h1>
		<p>This is the <i>tenth</i> <em>scheduled</em> test message.<br />
		With use of HTML with <a href="http://localhost.local/">plaintext</a> fallback.</p>
	</body>
</html>
';
$brachioMailer = new BrachioMailer(true);
$brachioMailer->setMessagecontenttype('text/html');
$brachioMailer->setOrganization('Testers N.V.');
$brachioMailer->setImportance('low');
$dtMsgCreate = new DateTime();
$iso8601CreateDateTime = $dtMsgCreate->format('c');
$dtNowPlus15Min = new DateTime();
// Schedule for over 15 minutes
$dtNowPlus15Min->add(new DateInterval('PT15M'));
$brachioMailer->ScheduleMailFor($dtNowPlus15Min);

if ($brachioMailer->Send('tester2@localhost.local',
                         'tester@localhost.local', 
                         'test10, created on '.$iso8601CreateDateTime,
                         $messagehtml)) {
    echo 'Scheduled mail for '.$dtNowPlus15Min->format('Y-m-d H:i:s').' UTC';
} else {
    echo 'Error scheduling mail<br />';
}

