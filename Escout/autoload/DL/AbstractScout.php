<?php

namespace DL;

abstract class AbstractScout {
	const EPOCH_START	= 1357000000;

	public static function now ($now = null) {
		return $now ?: (gmmktime() - self::EPOCH_START);
	}

	protected static $optoutCookie = null;
	public static function getCookieOptout () {
		if (static::$optoutCookie === null)
		{
			static::$optoutCookie = (bool)filter_input(INPUT_COOKIE, 'ST', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/OPTOUT/i')));
		}
		return static::$optoutCookie;
	}

	public static function setCookieOptout () {
		$cookie = explode('_', filter_input(INPUT_COOKIE, 'ST', FILTER_SANITIZE_STRING));
		$cookie[] = 'OPTOUT';
		$cookie = implode('_', $cookie).'_';
		setcookie('ST', $cookie, strtotime('+1 year'), '/', '.questionmarket.com', false);
	}

	public static function timeDiff ($time, $now = null) {
		return abs(static::now($now) - $time);
	}

	public static function timeSince($time) {
		return abs(static::now() - $time);
	}
}
