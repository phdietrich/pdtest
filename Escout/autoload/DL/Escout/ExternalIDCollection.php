<?php

namespace DL\Escout;

class ExternalIDCollection extends \DL\Collection {
	const MODEL = '\DL\Escout\ExternalID';
	public $UID = null;

	public function __construct (UID &$UID) {
		$this->UID =& $UID;
	}

	public function add ($model) {
		$model->UID =& $this->UID;
		return parent::add($model);
	}

	public function getByTypeID ($type, $id) {
		$id = "$type_$id";

		if (isset($this->collection[$id])) {
			return $this->collection[$id];
		} else {
			return null;
		}
	}

	public function getHash ($model) {
		return "{$model->type}_{$model->id}";
	}
}
