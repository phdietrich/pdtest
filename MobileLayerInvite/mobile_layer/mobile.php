<?php
/**
 * Mobile Layer decide.php
 */
header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');
header ('P3P: CP="ALL DSP COR PSAa PSDa OUR IND COM NAV INT LOC OTC"');
header ('Content-Type: text/html');

$survey_num = SURVEY_NUM;
$site = SITE;
$code = AICODE;
$type = TAG_CODE;
$control_exposed = CONTROL_EXPOSED;
$perc = PERC;
$numsecs = NUMSECS;
$dl_domain = 'DYNAMICLINK_DOMAIN';

if (isset($_COOKIE['ST']) && strpos($_COOKIE['ST'], 'OPTOUT') !== FALSE) {
	call_tracking();
	exit('<!-- optout -->');
}

/**
 * Drop an Adscout Cookie for anything BUT type 4
 */
if ($type != 4) {
	include("/var/www/html/adscgen/adscout_incn_param.php");
	do_adscout_param($site, $code, 2);
} else {
	// For control, check if they were exposed to this survey
	if ($control_exposed) {
		/*
		 * Parse the ES cookie.
		 */
		if (isset($_COOKIE['ES'])) {
			$es = preg_split('/((-[^_]*)?_)|((-[^_]*))/', $_COOKIE['ES'], -1, PREG_SPLIT_NO_EMPTY);
		} else {
			$es = array();
		}

		if ($control_exposed == 1) { /* need exposed */
			if (!in_array($survey_num, $es)) {
				call_tracking();
				exit('<!-- exp/ctrl -->');
			}
		} else { /* need control */
			if (in_array($survey_num, $es)) {
				call_tracking();
				exit('<!-- exp/ctrl-->');
			}
		}
	}
}

/**
 * Check Frequency
 */
if ($perc == 0) {
	call_tracking();
	exit('<!-- freq -->');
} elseif ($perc < 100 && mt_rand(0, 10000) >= ($perc*100)) {
	call_tracking();
	exit('<!-- freq -->');
}

/**
 *  Check if ST cookie set for this campaign
 */
if ( isset($_COOKIE['ST']) && strstr($_COOKIE['ST'], (string)$survey_num) ) {
	call_tracking();
	exit('<!-- ST Cookie -->');
}

$ts = time();
/**
 * Check Time-Between
 */
if (isset($_COOKIE['LP'])) {
	if (($ts - (int) $_COOKIE['LP']) < $numsecs) {
		call_tracking();
		exit('<!-- LP Cookie -->');
	}
}

/**
 * We are doing an invite (assuming they accept cookies) so set LP Cookie
 */
setcookie('LP', $ts, $ts + 360000, '/', '.DL_DOMAIN', 0);

function call_tracking() {
	echo <<<PRINT_HTML
<!DOCTYPE HTML><html><head><title></title></head><body>
<script>(new Image).src="http://SERVER_NAME/adsc/dSURVEY_NUM/SITE/AICODE/adscout.php?ord="+Math.floor((new Date()).getTime()/1000);</script>

PRINT_HTML;

}

?>
<!DOCTYPE HTML>
<html><head><title></title></head><body>
<script type="text/javascript">
	(function() {
		var d=document, w=window;

// check we can set cookies and launch invite if so.
		var expires = new Date((new Date()).getTime() + 10000);
		expires = expires.toGMTString();
		d.cookie = "dl_ck_test=test; path=/; domain=.DL_DOMAIN; expires="+expires;
		if (getCookie('dl_ck_test')) {
			// erase test cookie
			d.cookie = "dl_ck_test=0; path=/; domain=.DL_DOMAIN; expires=Thu, 01-Jan-1970 00:00:01 GMT";	// delete it now
			(new Image).src="http://SERVER_NAME/adsc/dSURVEY_NUM/SITE/AICODE/adscout.php?ord="+Math.floor((new Date()).getTime()/1000);
			callInvite();
			return;
		}

// Use escout to check LP and quit if not expired
		var lpReq = new XMLHttpRequest();
		lpReq.onreadystatechange = function(){
			if (lpReq.readyState==4 && lpReq.status==200){
				var lp = lpReq.responseText;
				if (lp != "") {
					var tdiff = <?=$ts?> - lp;
					if ( (tdiff > 5) && tdiff < <?=$numsecs?>) {	// less than 5 second window for etag response; otherwise, time has been cached
						return;
					}

					// Now read UID
					var uidReq = new XMLHttpRequest();
					uidReq.onreadystatechange = function(){
						if (uidReq.readyState==4 && uidReq.status==200){
							var udata = uidReq.responseText;
							if (udata == "") {
								udata = "UNAVAIL-"+Math.floor((Math.random()*100000000000)+1000000);
								callTracking(udata);
								return;
							}
							callTracking(udata);
							callInvite();
							return;
						}
					}
					uidReq.open("GET", "http://SERVER_NAME/dynamicookie/dc_etag.php", true);
					uidReq.send();
				}
			}
		}
		lpReq.open("GET", "http://SERVER_NAME/dynamicookie/dc_etag_LP.php", true);
		lpReq.send();

		function callTracking(udata) {
			var ts = Math.round(new Date().getTime() / 1000);
			var img = new Image();
			img.style.visibility = 'hidden';
			img.style.position = 'absolute';
			img.src="http://SERVER_NAME/adsc/dSURVEY_NUM/SITE/AICODE/mobile_uid.php?mobile_uid="+udata+"&ts="+ts+"&ord="+Math.random()*100;
		}

		function getCookie(cknm) {
			var res = d.cookie.match('(^|;) ?' + cknm + '=([^;]*)(;|$)');
			if (res) return res[2];
			return false;
		}

		function callInvite() {
			w.parent.postMessage("invite", d.referrer);
		}

	})();
</script>
</body></html>
