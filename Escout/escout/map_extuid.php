<?php
require 'autoload.php';

use \DL\BeforeAfterApp;
use \DL\Escout;
use \DL\Escout\ExternalID;
use \DL\Escout\Storage;
use \DL\Escout\UID;

$app = new BeforeAfterApp;
$optout = Escout::getCookieOptout();
$storage = new Storage;
$UID = null;

$app->before(function () use ($app, $optout, $storage, &$UID) {
	$action = base64_decode(filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING));

	$app->ExternalIDs = filter_input(INPUT_GET, 'uids', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY);

	if ($optout) {
		Escout::setCookieOptout();
	} elseif (!$app->ExternalIDs) {
		exit(';// Q');
	} else {
		array_walk($app->ExternalIDs, function (&$id, $type) {
			$id = is_string($type) ? new ExternalID($type, $id) : ExternalID::createFromString($id);
		});

		$UID = UID::getFromCookie() ?: $storage->getUIDbyExternalIDs($app->ExternalIDs) ?: $storage->createUID();

		$UID->setCookie();
		header ('X-DL-GP: '.php_uname('n').'/'.(int)$UID->isNew().'/'.(int)$optout);
	}

	switch (true) {
		case $action == 'js':
			header('Content-Type: text/javascript');
			echo ';';
		break;

		case $action == 'gif':
			header('Content-Type: image/gif');
			echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
		break;

		case $action == 'png':
			header('Content-Type: image/png');
			echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAFElEQVR4XgXAAQ0AAABAMP1L30IDCPwC/o5WcS4AAAAASUVORK5CYII=');
		break;

		// For creative URL or redirect
		case filter_var($action, FILTER_VALIDATE_URL):
			header("Location: $action");
		break;

		default:
			echo ';// A';
	}
});

$app->after(function () use ($app, $optout, $storage, &$UID) {
	$escout = $UID->isNew() ? Escout::create($UID) : $storage->getEscoutByUID($UID);
	$escout->setOptout($optout);
	$escout->setLastSeen();

	foreach ($app->ExternalIDs as $ExternalID) {
		$escout->addExternalID($ExternalID);
	}

	if (!$optout) {
		$escout->recordExposure(
			filter_input(INPUT_GET, 'survey_num', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
			filter_input(INPUT_GET, 'site_num', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
			filter_input(INPUT_GET, 'aicode', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
		);
	}

	$storage->updateEscout($escout);
});

$app->run();
