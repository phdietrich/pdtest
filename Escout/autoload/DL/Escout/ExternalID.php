<?php

namespace DL\Escout;

use DL\Escout\UID;

class ExternalID implements \JsonSerializable {
	const PREFIX	= 'EXT';
	const UNAVAIL	= 'UNAVAILABLE';

	public $type	= null;
	public $id		= null;
	public $UID		= null;

	protected $new	= null;

	public static function __set_state ($state) {
		return new static($state[0], $state[1], false);
	}

	public static function createFromString ($string) {
		list($type, $id) = explode(':', $string);
		return new static($type, $id);
	}

	public function __construct ($type, $id = null, $new = true) {
		$this->type	= $type;
		$this->id	= $id;
		$this->new	= $new;

		if ($this->id === null) {
			$this->id = self::UNAVAIL;
		}
	}

	public function isNew () {
		return $this->new === true;
	}

	public function isValid() {
		return $this->type && $this->id && $this->id != self::UNAVAIL;
	}

	public function __toString () {
		return $this->type.':'.$this->id;
	}

	public function jsonSerialize () {
		return array($this->type, $this->id);
	}

}
