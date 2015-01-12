<?php

namespace DL;

class App {
	const MODE_PROD		= 1;
	const MODE_DEBUG	= 2;
	const MODE_DEV		= 4;

	protected static $mode = 1;

	public static function getMode () {
		return static::$mode;
	}

	public static function setMode ($mode) {
		static::$mode = $mode;
	}

	public static function isDebug () {
		return static::$mode & static::MODE_DEBUG;
	}

	public static function isDev () {
		return static::$mode & static::MODE_DEV;
	}

	public static function isProd () {
		return static::$mode & static::MODE_PROD;
	}


	const OUTPUT_HTML	= 1;
	const OUTPUT_JS		= 2;

	protected static $output = 1;

	public static function getOutput () {
		return static::$output;
	}

	public static function setOutput ($output) {
		static::$output = $output;
	}

	public static function isHTML () {
		return static::$output & static::OUTPUT_HTML;
	}

	public static function isJS () {
		return static::$output & static::OUTPUT_JS;
	}


	protected static $messages = array();

	public static function message ($message, $dump = null) {
		static::$messages[] = array(
			'message' => $message,
			'dump' => $dump,
		);
	}

	public static function printMessages () {
		if (static::isJS()): 
			echo "/*\n";
		else:
			echo "<pre>";
		endif;

		echo php_uname('n')."\n";

		foreach (static::$messages as $message): ?>
			----------------------------------------------
			<?= $message['message'] ?>
			<? print_r($message['dump']);
		endforeach;

		if (static::isJS()): 
			echo "\n*/";
		else:
			echo "</pre>";
		endif;
	}


	protected static $outputBuffered = false;

	public static function beforeOutput () {
		if (!static::isDebug()) {
			static::$outputBuffered = true;

			header('Connection: close', true);
			ignore_user_abort(true);

			@ob_end_clean();
			ob_start();
		}
	}

	public static function afterOutput () {
		if (static::isDebug()) {
			static::printMessages();
		}

		if (static::$outputBuffered) {
			header('Content-Length: '.ob_get_length(), true);

			ob_end_flush();
			flush();
			if (function_exists('fastcgi_finish_request')) {
				fastcgi_finish_request();
			}
		}
	}
}