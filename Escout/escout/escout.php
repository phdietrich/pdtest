<?php

require 'autoload.php';

use \DL\App;
use \DL\AdScout;
use \DL\Collection;
use \DL\Escout;
use \DL\Escout\ExternalID;
use \DL\Escout\UID;
use \DL\Escout\Storage;

$optout = Escout::getCookieOptout();
$storage = new Storage;
$escout = null;

// There's a chance we get more than a single UID
$UIDs = array(
	UID::getFromEtag(),
	UID::getFromCookie(),
	UID::getFromHeader(),
);

$externalIDs =  json_decode(urldecode(filter_input(INPUT_SERVER, 'HTTP_X_DL_EXTIDS') ?:
				filter_input(INPUT_GET, 'extids')), true) ?:
				array();
$externalIDCollection = new Collection;
foreach ($externalIDs as $type => $id)
{ 
	if (!is_string($type))
	{
		list($type, $id) = explode(':', $id);
	}

	$externalIDCollection->add(new ExternalID($type, $id));
}

// Only create new UID and Escout, if not opted out
if (!$optout && !($UIDs && $escout = $storage->getEscout($UIDs, $externalIDCollection)))
{
	$UID = UID::create($storage);
	$escout = Escout::create($UID);
}

App::beforeOutput();

header('Content-Type: text/javascript');
header('Last-Modified: '.gmdate("l, d-M-y H:i:s T", time()));

if ($optout || $escout->isOptout())
{
	Escout::setCookieOptout();
	echo "DL.UID.set('OPTOUT');";
}
else
{
	header('Cache-Control: max-age=0, must-revalidate, private');
	header('ETag: "'.$escout->UID.'"'); // It's important to keep quotes around ETag to prevent browsers from interpreting value
	$escout->UID->setCookie();
	echo "DL.UID.set('{$escout->UID}');";
}

App::afterOutput();

if ($escout)
{
	$escout->setOptout($optout);
	$escout->setLastSeen();

	if (!$optout)
	{
		$survey_num	= filter_input(INPUT_SERVER, 'HTTP_X_DL_SURVEY_NUM');
		$site_num	= filter_input(INPUT_SERVER, 'HTTP_X_DL_SITE_NUM');
		$aicode		= filter_input(INPUT_SERVER, 'HTTP_X_DL_AICODE');
		$escout->recordExposure($survey_num, $site_num, $aicode);

		foreach ($externalIDCollection as $externalID) {
			$escout->external->add($externalID);
		}
	}

	$storage->setEscout($escout);	
}
