<?php
/**
 * Mobile Layer invite
 */
header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');
header ('P3P: CP="ALL DSP COR PSAa PSDa OUR IND COM NAV INT LOC OTC"');
header ('Content-Type: text/javascript');

$survey_num = isset($_GET['survey_num']) ? $_GET['survey_num'] : "";
$site = isset($_GET['site']) ? $_GET['site'] : "";
$code = isset($_GET['code']) ? $_GET['code'] : "";
$from_node = isset($_GET['from_node']) ? $_GET['from_node'] : "";

if (!$survey_num || ( (!$site || !$code) && !$from_node)) {
    exit("/* p1 */;");
}

if ($from_node) {
    if (!is_file($_SERVER['DOCUMENT_ROOT']."/dt/s/$from_node/.params.php")) {
        exit("/* p2 */;");
    }
    include($_SERVER['DOCUMENT_ROOT']."/dt/s/$sid/.params.php");
} else {
    if (!is_file($_SERVER['DOCUMENT_ROOT']."/adsc/d$survey_num/$site/$code/params.ini")) {
        exit("/* p2 */;");
    }
    $params = parse_ini_file($_SERVER['DOCUMENT_ROOT']."/adsc/d$survey_num/$site/$code/params.ini");
}

$survey_server = (string)$params['survey_server'];
$server_name = (string)$params['server_name'];
$img_url = (string)$params['img_url'];
$img_width = (string)$params['img_width'];
$img_height = (string)$params['img_height'];
$speed = (string)$params['speed'];
$opacity = (string)$params['opacity'];
$start_pos = (string)$params['start_pos'];
$end_pos = (string)$params['end_pos'];
$close_coords = (string)$params['close_coords'];
$delay = (int)$params['delay'];	// ms before layer appears
$go_delay = (int)$params['go_delay'] + ($speed * 1000);	// ms that layer is stationary
$new_win = (isset($params['newwin'])) ? (int)$params['newwin'] : 0;

if($img_height < 1 || $img_width < 1) {
    exit('/* Image-file */;');
}

if ($from_node) {
    $survey_url = "http://$survey_server/surv/$survey_num/ai_start.php?from_node=$from_node&site=$site";
} else {
    $survey_url = "http://$survey_server/surv/$survey_num/ai_start.php?site=$site&from_aicode=$code";
}

$trans_params = "all ".$speed."s ease-out 0s";

$survey_coords = "0,0,$img_width,$img_height";

/* 
END IS EMPTY
	l - left:0; right empty
	r - right:0; left empty
	t - top:0; bottom empty
	b - bottom:0; top empty
	change - no change;
END = START
	l - left:-100%; right empty
	r - right:-100%; left empty
	t - top:-100%; bottom empty
	b - bottom:-100%; top empty
	change - left/right/top/bottom:0px;
END IS CENTER
	l - left:-100%; right 100%
	r - right:-100%; left 100%
	t - top:-100%; bottom 100%
	b - bottom:-100%; top 100%
	change - both:0px;
END IS LEFT
	left: 100%; right empty
	change - left:0px; right empty
END IS RIGHT
	right: 100%; left empty
	change - right:0px; left empty
END IS TOP
	top: 100%; bottom empty
	change - top:0px; bottom empty
END IS BOTTOM
	b - bottom:100%; top empty
	change - bottom:0px; top empty
*/

$lrtb = "";
$trans_js = "";
switch($end_pos) {
    case "":
        switch ($start_pos) {
            case "center":
                $lrtb = "left:0px; right:0px; top:0px; bottom:0px;";
                break;
            case "left":
                $lrtb = "left:0px; top:0px; bottom:0px;";
                break;
            case "right":
                $lrtb = "right:0px; top:0px; bottom:0px;";
                break;
            case "top":
                $lrtb = "left:0px; right:0px; top:0px;";
                break;
            case "bottom":
                $lrtb = "left:0px; right:0px; bottom:0px;";
                break;
        }
        break;

    case $start_pos:
        switch ($start_pos) {
            case "left":
                $lrtb = "left:-100%; top:0px; bottom:0px;";
                $trans_js = "DL_invite.style.left='0px';\n";
                break;
            case "right":
                $lrtb = "right:-100%; top:0px; bottom:0px;";
                $trans_js = "DL_invite.style.right='0px';\n";
                break;
            case "top":
                $lrtb = "left:0px; right:0px; top:-100%;";
                $trans_js = "DL_invite.style.top='0px';\n";
                break;
            case "bottom":
                $lrtb = "left:0px; right:0px; bottom:-100%;";
                $trans_js = "DL_invite.style.bottom='0px';\n";
                break;
        }
        break;

    case "center":
        switch ($start_pos) {
            case "left":
                $lrtb = "left:-100%; right:100%; top:0px; bottom:0px;";
                $trans_js = "DL_invite.style.left='0px';\n" .
                    "DL_invite.style.right='0px';\n";
                break;
            case "right":
                $lrtb = "left:100%; right:-100%; top:0px; bottom:0px;";
                $trans_js = "DL_invite.style.left='0px';\n" .
                    "DL_invite.style.right='0px';\n";
                break;
            case "top":
                $lrtb = "left:0px; right:0px; top:-100%; bottom:100%;";
                $trans_js = "DL_invite.style.top='0px';\n" .
                    "DL_invite.style.bottom='0px';\n";
                break;
            case "bottom":
                $lrtb = "left:0px; right:0px; top:100%; bottom:-100%;";
                $trans_js = "DL_invite.style.top='0px';\n" .
                    "DL_invite.style.bottom='0px';\n";
                break;
        }
        break;

    case "left":
        // Start pos must be right
        $lrtb = "left:100%; top:0px; bottom:0px;";
        $trans_js = "DL_invite.style.left='0px';\n";

        break;

    case "right":
        // Start pos must be left
        $lrtb = "right:100%; top:0px; bottom:0px;";
        $trans_js = "DL_invite.style.right='0px';\n";
        break;

    case "top":
        // Start pos must be bottom
        $lrtb = "left:0px; right:0px; top:100%;";
        $trans_js = "DL_invite.style.top='0px';\n";
        break;
    case "bottom":
        // Start pos must be top
        $lrtb = "left:0px; right:0px; bottom:100%;";
        $trans_js = "DL_invite.style.bottom='0px';\n";
        break;
}

?>
var DL_invite, DL_olay;
DL_InvW = <?=$img_width?>;
DL_InvH = <?=$img_height?>;

function DL_GotoSurvey() {
	DL_Close();
	window.top.location.href='<?=$survey_url?>';
}

function DL_Close() {
	DL_wrapper.style.display='none';
	if (1 == <?=$opacity?>) {
		try{
			DL_olay.style.display='none';
		} catch (err) { }
	}
}

function DL_AddInvite() {
// Create wrapper div to make sure that if somebody clicks outside the add it closes it.
DL_wrapper = document.createElement('div');
DL_wrapper.id = 'DL_wrapper';
DL_wrapper.style.cssText = 'position: fixed; left: 0px; right: 0px; top: 0px; bottom : 0px; width: 100%; height: 100%;'
DL_wrapper.setAttribute("onClick","DL_Close()");


// Create Invite div
DL_invite = document.createElement('div');
DL_invite.id = 'DL_invite';

DL_invite.style.cssText = 'position: fixed; <?=$lrtb?> margin: auto; z-index: 10000001; background: #FFF; background-repeat: no-repeat;' +
'-webkit-box-shadow: 0 4px 23px 5px rgba(0, 0, 0, 0.2), 0 2px 6px rgba(0, 0, 0, 0.15); -moz-box-shadow: 0 4px 23px 5px rgba(0, 0, 0, 0.2), 0 2px 6px rgba(0, 0, 0, 0.15); ' +
'box-shadow: 0 4px 23px 5px rgba(0, 0, 0, 0.2), 0 2px 6px rgba(0, 0, 0, 0.15);' +
'height: ' + DL_InvH + 'px; width: ' + DL_InvW + 'px; overflow: hidden;' +
'-webkit-transition: <?=$trans_params?>; -moz-transition: <?=$trans_params?>; transition: <?=$trans_params?>;';

DL_invite.innerHTML = '<map name="DL_btns"><area coords="<?=$close_coords?>" href="#" onClick="DL_Close();return false;">' +
<?php
if ($new_win) {
	echo "'<area coords=\"$survey_coords\" href=\"$survey_url\" target=\"_blank\" onClick=\"DL_Close();\">' +";
} else {
	echo "'<area coords=\"$survey_coords\" href=\"#\" onClick=\"DL_GotoSurvey();return false;\">' +";
}
?>
'</map><img src="<?=$img_url?>" usemap="#DL_btns" border="0" style="width:100%; height:100%; padding:0px; margin:0px;"/>';


DL_wrapper.appendChild(DL_invite);

// If opacity, create overlay to contain Invite div
<?php
if ($opacity) {
    echo <<<PRINT_HTML
	// Create overlay div
	DL_olay = document.createElement('div');
	DL_olay.id = 'DL_olay';
	DL_olay.style.cssText = 'position: fixed; background: rgba(0,0,0,0.5); opacity: $opacity; z-index: 1001; bottom: 0; left: 0; right: 0; top: 0;';
	document.getElementsByTagName("body")[0].appendChild(DL_olay);
	DL_olay.appendChild(DL_invite);
	
PRINT_HTML;
} else {
    echo '	document.getElementsByTagName("body")[0].appendChild(DL_wrapper);'."\n";
}
?>

	// redraw window to allow transitions to work
	(document.documentElement || 0).clientHeight;

	<?=$trans_js;?>

	setTimeout(DL_Close, <?=$go_delay?>);
}

if (!window.DL_already_ran) {
	DL_already_ran = 1;
	setTimeout(DL_AddInvite, <?=$delay?>);
}
