<?php

namespace DL;

class AdScout extends AbstractScout implements \JsonSerializable {
	public $survey_num	= null;
	public $site_num	= null;
	public $aicode		= null;
	public $exposures = array();

	protected $new = null;

	public static function __set_state ($state) {
		$state[3][0] += static::EPOCH_START;
		for ($i = 1; $i < count($state[3]); $i++) {
			$state[3][$i] += $state[3][$i-1];
		}

		return new static($state[0], $state[1], $state[2], $state[3], false);
	}

	public function __construct ($survey_num, $site_num, $aicode, array $exposures = array(), $new = true) {
		$this->survey_num = $survey_num;
		$this->site_num = $site_num;
		$this->aicode = $aicode;
		$this->exposures = $exposures;
		$this->new = $new;
	}

	public function recordExposure ($time = null) {
		$this->exposures[] = $time ?: time();
	}

	public function jsonSerialize ($values_only = false) {
		$exposures = array_unique(array_values($this->exposures));
		sort($exposures);
		for ($i = count($exposures)-1; $i > 0; $i--) {
			$exposures[$i] -= $exposures[$i-1];
		}
		$exposures[0] -= static::EPOCH_START;

		$return = array (
			'survey_num' => $this->survey_num,
			'site_num' => $this->site_num,
			'aicode' => $this->aicode,
			'exposures' => $exposures,
		);

		if ($values_only) {
			return array_values($return);
		} else {
			return $return;
		}
	}
}
