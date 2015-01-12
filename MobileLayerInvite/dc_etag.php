<?php
header ('Content-Type: text/javascript');
header ('P3P: CP="NOI DSP COR PSAa PSDa OUR NOR LOC OTC"');
header ('DL_S: '.php_uname('n'));

$UID = null;
$etag = null;
$cookie = null;

if (isset($_COOKIE['dl_muid']))
{
	$cookie = validateUID ($_COOKIE['dl_muid']);
}

if (isset($_SERVER['HTTP_IF_NONE_MATCH']))
{
	$etag = validateUID (trim($_SERVER['HTTP_IF_NONE_MATCH'], '"'));
}

/** 
 * IF Cookie AND ETag // returning user
 * Return "304 Not Modified"; Record an impression	
 */
if ($cookie && $etag)
{
	header('Not Modified', true, 304);
}
else {
	/**
	 * IF Cookie AND no ETag // cache cleared
	 * Return "200 Success" with UID from Cookie in ETag, Cookie and Body; Record an impression
	 */
	if ($cookie)
	{
		$UID = $cookie;
	}
	/**
	 * IF no Cookie AND ETag // can't set cookies or cleared them
	 * Return "200 Success" with UID from ETag in ETag, Cookie and Body; Record an impression
	 */
	elseif ($etag)
	{
		$UID = $etag;
	}
	/**
	 * IF no Cookie AND no ETag // new user
	 * Assume this is a new user, return UID in ETag, Cookie and Body; Record an impression
	 */
	else
	{
		$UID = newUID();
	}
	
	header("Cache-Control: private");
	header('ETag: "'.$UID.'"');
	header("Last-Modified: ".gmdate("D, d M Y H:i:s",time())." GMT");
	setcookie('dl_muid', $UID, time()+3600*24*365, '/', '.questionmarket.com', false);
	echo $UID;
}

function newUID () {
	/**
	 * Build randmon 36 character hex-flavored UID
	 */
	$chars = array('0','1','2','3','4','5','6','7','8','9','A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');

	$m_str = $chars; 
	$rnd=0;
	$r = '';
	$uid = array();
	for ($i=0; $i<36; $i++) {
		if ($i==8 || $i==13 || $i==18 || $i==23) {
			$uid[$i] = '-';
		} elseif ($i==14) {
			$uid[$i] = '4';
		} else {
			if ($rnd <= 0x02) {
				$rnd = 0x2000000 + ((mt_rand(100000,9999999)*.0000001*0x1000000)|0);
			}
			$r = $rnd & 0xf;
			$rnd = $rnd >> 4;
			$uid[$i] = $m_str[($i == 19) ? ($r & 0x3) | 0x8 : $r];
		}
	}
	return implode("",$uid);
}


function validateUID ($UID) {
    return preg_match('/^[a-zA-Z0-9]{8}\-[a-zA-Z0-9]{4}\-[a-zA-Z0-9]{4}\-[a-zA-Z0-9]{4}\-[a-zA-Z0-9]{12}$/i', $UID) === 1 ? $UID : false;
}
