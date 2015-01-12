<?php

namespace DL;

use \DL\AdScoutCollection;
use \DL\Escout\ExternalIDCollection;
use \DL\Escout\UID;
use \DL\Escout\ExternalID;

class Escout extends AbstractScout implements \JsonSerializable {
	public $UID			= null;

	public $first_seen	= 0;
	public $last_seen	= 0;

	public $optout		= false;

	public $adscouts	= null;
	public $external	= null;
	public $surveys		= null;

	public static function __set_state($state, UID $UID = null) {
		if (!$UID) {
			$UID = new UID($state['UID']);
		}

		$self = new static($UID);

		$self->adscouts = AdScoutCollection::__set_state($state['adscouts']);

		// This is shortsighted and ugly
		$external = $self->external;
		$ExternalIDclass = $external::MODEL;
		foreach ($state['external'] as $externalID) {
			$externalID[] = $UID;
			$self->external->add($ExternalIDclass::__set_state($externalID));
		}

		unset($state['UID'], $state['adscouts'], $state['external']);

		foreach ($state as $key => $value) {
			$self->{$key} = $value;
		}

		return $self;
	}

	public static function create (UID $UID) {
		$escout = new static($UID);

		$escout->first_seen	= static::now();

		return $escout;
	}

	public function __construct (UID $UID) {
		$this->UID = $UID;
		$this->UID->escout = $this;

		$this->adscouts	= new AdScoutCollection;
		$this->external	= new ExternalIDCollection($this->UID);
		$this->surveys	= array();
	}

	public function getFirstSeen () {
		return $this->first_seen;
	}

	public function getLastSeen () {
		return $this->first_seen + $this->last_seen;
	}

	public function setLastSeen () {
		return $this->last_seen = static::timeSince($this->first_seen);
	}

	public function isOptout () {
		return (bool)$this->optout;
	}

	public function setOptout ($optout = false) {
		return $this->optout = $this->optout ?: (bool)$optout;
	}

	public function addExternalID (ExternalID $externalID) {
		if ($externalID->isValid() && !$this->external->exists($externalID)) {
			$this->external->add($externalID);
		}
	}

	public function recordExposure ($survey_num, $site_num, $aicode) {
		if ($survey_num && $site_num && $aicode) {
			if (!$adscout = $this->adscouts->getBySurveySiteAicode($survey_num, $site_num, $aicode)) {
				$adscout = new AdScout($survey_num, $site_num, $aicode, array());
				$this->adscouts->add($adscout);
			}
			$adscout->recordExposure();
		}
	}


	public function merge (Escout $escout) {
		$this->first_seen = min($this->first_seen, $escout->first_seen);
		$this->last_seen = max($this->getLastSeen(), $escout->getLastSeen()) - $this->first_seen;

		$this->optout = (bool) ($this->optout || $escout->optout);

		foreach ($escout->adscouts as $adscout2) {
			if ($adscout1 =& $this->adscouts->getBySurveySiteAicode($adscout2->survey_num, $adscout2->site_num, $adscout2->aicode)) {
				$adscout1->exposures = array_merge($adscout1->exposures, $adscout2->exposures);
			} else {
				$this->adscouts->add($adscout2);
			}
		}

		foreach ($escout->external as $externalID) {
			$this->external->add($externalID);
		}
		$this->external->add(new ExternalID('UID', (string) $escout->UID));

		$this->surveys = array_merge($this->surveys, $escout->surveys);
	}


	// JsonSerializable
	public function jsonSerialize () {
		$json = (array) clone $this;
		unset($json['UID']);
		$json['UID'] = (string) $this->UID;
		$json['adscouts'] = $json['adscouts']->jsonSerialize(true);
		$json['external'] = $json['external']->jsonSerialize();

		return $json;
	}
}
