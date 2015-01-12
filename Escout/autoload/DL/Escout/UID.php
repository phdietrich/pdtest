<?php

namespace DL\Escout;

class UID {
	const PREFIX	= 'UID';
	const SEPARATOR	= ':';

	public $UID		= null;
	public $new		= null;

	public $escout	= null;


	public static function __set_state ($state) {
		$self = new self($state['UID']);

		foreach ($state as $key => $value) {
			$self->{$key} = $value;
		}

		return $self;
	}


	public static function create (Storage $storage) {
		return $storage->createUID();
	}


	public static function validate ($UID) {
		$UID = trim($UID, '"');
		return preg_match('/^[A-T0][0-9A-Z]{4,}$/i', $UID) === 1 ? new self(strtoupper($UID)) : false;
	}


	public static function getFromCookie () {
		return self::validate(filter_input(INPUT_COOKIE, 'DL_UID'));
	}

	public static function getFromEtag () {
		return self::validate(filter_input(INPUT_SERVER, 'HTTP_IF_NONE_MATCH'));
	}

	public static function getFromHeader () {
		return self::validate(filter_input(INPUT_SERVER, 'HTTP_X_DL_UID'));
	}


	public function __construct ($UID, $new = false) {
		$this->UID = $UID;
		$this->new = $new;
	}

	public function isNew () {
		return $this->new == true;
	}

	public function __toString () {
		return (string) $this->UID;
	}

	public function toKey () {
		return self::PREFIX . self::SEPARATOR . (string) $this->UID;
	}

	public function setCookie () {
		setcookie('DL_UID', $this->UID, time()+3e7, '/', '.questionmarket.com', false);
	}
}
