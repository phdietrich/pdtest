<?php

namespace DL\Escout;

use \DL\Escout;
use \DL\Collection;
use \DL\Escout\UID;
use \DL\Escout\ExternalID;

require_once 'common/config/couchbase.php';

class Storage {
	const COUCH_BUCKET	= 'escout';
	const SEPARATOR		= ':';

	protected $storage = null;

	public function __construct () {}
	
	public function storage () {
		if (!$this->storage)
		{
			$this->storage = new \Couchbase(COUCH_HOSTS, self::COUCH_BUCKET, COUCH_PASS, self::COUCH_BUCKET);
		}

		return $this->storage;
	}

	public function createUID () {
		$storage = $this->storage();

		$next = $storage->increment('ID.next.'.COUCH_ZONE, 1, true);
		return new UID(strtoupper(COUCH_ZONE.base_convert($next, 10, 35).base_convert(bin2hex(openssl_random_pseudo_bytes(2)), 16, 35)), true);
	}

	public function getEscoutByUID (UID $UID) {
		$storage = $this->storage();

		if (!$UID->isNew() && $data = $storage->get($UID->toKey()))
		{
			return Escout::__set_state($data);
		}

		return null;
	}

	public function getEscout (array $UIDs, Collection $externalIDs = null) {
		$escout = null;
		$storage = $this->storage();

		$UIDs = array_unique(array_filter($UIDs, function ($UID) {
			return $UID instanceof UID && !$UID->isNew();
		}));

		// This is the most common case - we know only one UID, there are no external IDs
		if (count($UIDs) === 1 && !count($externalIDs))
		{
			// Get first element off of array without modifying it
			$UID = reset($UIDs);

			if ($escout = $storage->get($UID->toKey()))
			{
				return Escout::__set_state($escout);
			}
		}

		/* ======================== */


		// Check if UIDs point to any other UIDs (symlinks)
		foreach ($UIDs as $UID)
		{
			$externalID = new ExternalID('UID', (string) $UID);
			$externalID->UID = $UID;
			$externalIDs->add($externalID);
		}

		$this->validateExternalIDs($externalIDs);

		// Collect any new UIDs we've found - we will look up by them as well
		$keys = array();
		foreach ($externalIDs as $externalID)
		{
			if ($UID = $externalID->UID)
			{
				$keys[] = $UID->toKey();
			}
		}
		$keys = array_unique($keys);

		// Get all escouts we can find
		if ($escouts = $storage->getMulti($keys))
		{
			$escout = Escout::__set_state(array_shift($escouts));
			$escoutUIDkey = $escout->UID->toKey();

			while ($escouts)
			{
				$escout->merge(Escout::__set_state(array_shift($escouts)));
			}

			foreach ($externalIDs as $externalID)
			{
				if ($externalID->type != 'UID' || $externalID->UID->toKey() != $escoutUIDkey)
				{
					$escout->external->add($externalID);
				}
			}
		}
		return $escout;
	}

	public function setEscout (Escout $escout) {
		$storage = $this->storage();

		if (!$storage->set($escout->UID->toKey(), $escout->jsonSerialize()))
		{
			throw new ErrorException("Failed to store escout {$escout->UID}");
		}

		// Delete only if new escout is saved
		foreach ($escout->external as $externalID)
		{
			if ($externalID->type == 'UID' && $externalID->isNew())
			{
				$UID = new UID($externalID->id);
				$storage->delete($UID->toKey());
			}
		}

		return true;
	}


	protected function validateExternalIDs (Collection &$externalIDs) {
		$storage = $this->storage();

		// Fail fast, don't waste time
		if (!count($externalIDs))
		{
			return;
		}

		$pairs = $storage->view('escout.php', 'external_ids', array(
			'connection_timeout' => 500,
			'keys' => $externalIDs->jsonSerialize(),
			'on_error' => 'continue',
			'reduce' => false,
			'stale' => 'update_after',
		));

		foreach ($pairs['rows'] as $pair)
		{
			$externalID = new ExternalID($pair['key'][0], $pair['key'][1], true);
			$externalID->UID = new UID(str_replace(UID::PREFIX.self::SEPARATOR, '', $pair['id']), false);
			$externalIDs->add($externalID);
		}
	}
}
