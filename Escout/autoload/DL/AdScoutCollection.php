<?php

namespace DL;

class AdScoutCollection extends Collection {
	const MODEL = '\DL\AdScout';
	public function __construct () {}

	public function getBySurveySiteAicode ($survey_num, $site_num, $aicode) {
		$id = "{$aicode}_{$site_num}_{$survey_num}";

		if (isset($this->collection[$id])) {
			return $this->collection[$id];
		} else {
			return null;
		}
	}

	public function getHash ($model) {
		return "{$model->aicode}_{$model->site_num}_{$model->survey_num}";
	}
}
