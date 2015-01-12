<?php
/**
 * Weekly Caps
 *
 * @version $Id: weekly_caps.php $
 */
require("adindex3c/constants.php");
require_once("common/db/db.php");
require_once("adindex3c/weekly_caps_functions.php");
include 'layout.php';

$survey_num = (isset($_REQUEST['survey_num'])) ? (int)$_REQUEST['survey_num'] : '';
$ainame = (isset($_REQUEST['ainame'])) ? $_REQUEST['ainame'] : '';
$site_num = (isset($_REQUEST['site_num'])) ? $_REQUEST['site_num'] : '';
$confirmed = (isset($_REQUEST['confirm']) && "y" == $_REQUEST['confirm']) ? "y" : "";
$linked_site_list = (isset($_POST['linked_sites'])) ? $_POST['linked_sites'] : "";

if (!$survey_num || !$ainame) {
	echo "Required parameters missing!";
	exit;
}

$params = array(
	'title' => "Weekly Caps",
	'subtitle' => "$ainame ($survey_num)",
	'css' => array(
		'/css/dl_basic.css',
		'/css/dl_forms.css',
		'/css/colours.css',
		'/scripts/jquery/jscrollpane/jquery.jscrollpane.css',
		'/adindex3c/css/weekly_caps.css',
	),
	'js'=>array(
		'http://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js',
		'/scripts/jquery/jscrollpane/jquery.jscrollpane.min.js',
		'/scripts/jquery/jscrollpane/jquery.mousewheel.js',
	),
);

$layout->view('header', $params);

if (!$site_num) {
	$site_num = $db->getOne("SELECT site_num FROM ai_sites WHERE survey_num = ? and site_num > 1 and rownum = 1",array($survey_num));
	if (!$site_num) {
		echo '<div class="message error"><span class="type">Error</span><li class="msg">You must define sites for this campaign to set caps.</div>';
		$layout->view('footer');
		exit;
	}
}

/**
 * Remove site_num from querystring so we can use the select list form field
 */
$params = explode("&", $_SERVER['QUERY_STRING']);
for ($i=0; $i<count($params); $i++) {
	$param_vals = explode("=",$params[$i]);
	if ("site_num" == $param_vals[0]) {
		unset($params[$i]);
		break;
	}
}
$qs = implode("&", $params);

$msg = "";

/**
 * Get list of sites
 */
$site_name = "";
$ckd = "";
$all_sites = array();
$site_sel = '<select name="site_num" class="site_sel">';
$choose_recalc_sites = "Choose Sites to Re-Calculate:<br/>";
$res = $db->query("SELECT site_num, site_name FROM ai_sites WHERE survey_num = ? AND site_num > 1 ORDER BY site_num", array($survey_num));

while (list($site_sel_num, $site_sel_name) = $res->fetchRow(DB_FETCHMODE_ORDERED)) {
	if ($db->getOne("SELECT count(*) FROM weekly_caps_linked_sites WHERE survey_num = ? AND master_site_num = ?", array($survey_num, $site_sel_num)) > 0) {
		$link_text = " - Shared Caps Host";
	} else {
		$link_text = "";
	}

	$seld = ($site_sel_num == $site_num) ? " selected" : "";
	$site_sel .= "<option value=\"$site_sel_num\" $seld>$site_sel_name ($site_sel_num) $link_text</option>";
	$choose_recalc_sites .= "<div class='sml'><input type='checkbox' name='recalc_site_{$site_sel_num}' value='1'/> $site_sel_num - $site_sel_name</div>";

	$all_sites[$site_sel_num] = $site_sel_name;
}
$site_sel .= "</select>\n";
$choose_recalc_sites .= '<input class="btn sml" type="button" value="Submit Select Sites Recalc" name="recalc_select_submit" onclick="remove_recalc(\'select\', \'recalc\', 1)"/>';

$site_caps_msg = (!check_site_caps($survey_num, $site_num)) ? "<span class=errmsg>Site Caps for this Site</a> must be entered to set weekly caps.</span>" : "";

if (isset($_POST['action']) && $_POST['action']) {

	switch ($_POST['action']) {
	case "updt":
		$err = validate_input();
		if (!empty($err)) {
			$msg = '<div class="message error"><span class="type">Error</span><li class="msg">'.implode("<li class=\"msg\">", $err).'</div>';
		} else {
			do_update($survey_num, $site_num);
			$msg = '<div class="message success"><span class="type">Success</span>Weekly Cap settings were saved.</div>';
		}
		break;

	case "recalc_caps":
		if (!$confirmed) {
			if ($_POST['site_or_all'] == "all") {
				echo  <<<PRINT_HTML
<div class="message warning"><span class="type">Note</span>You are about to recalc Weekly Caps for All Sites. <br/>
<span class="pseudolink" onclick="remove_recalc('all', 'recalc', 1)">Click to proceed</span> or <a href="/adindex3c/weekly_caps.php?$qs&site_num=$site_num">Go Back to cancel</a>.</div>

PRINT_HTML;
			} else {
				echo  <<<PRINT_HTML
<div class="message warning"><span class="type">Note</span>You are about to recalc Weekly Caps for Site $site_num. <br/>
<span class="pseudolink" onclick="remove_recalc('site', 'recalc', 1)">Click to proceed</span> or <a href="/adindex3c/weekly_caps.php?$qs&site_num=$site_num">Go Back to cancel</a>.</div>

PRINT_HTML;
			}
		} else {

			$from_pp = 1;
			if ("all" == $_POST['site_or_all']) {
				$err = recalc_survey($survey_num);
				if ($err) {
					$msg = '<div class="message error"><span class="type">Error</span>'.$err.'</div>';
				} else {
					$msg = '<div class="message success"><span class="type">Success</span>Weekly Caps for All Sites were Re-Calculated.</div>';
				}
			} elseif ($_POST['site_or_all'] == "select") {
				$err = "";
				$site_list = array();
				foreach ($_POST as $fld => $val) {
					if (substr($fld, 0, 12) == "recalc_site_") {
						$site_num = substr($fld, 12, strlen($fld));
						if (!is_numeric($site_num)) {
							$msg = '<div class="message error"><span class="type">Error</span>There was a problem recalculating Select Sites.</div>';
							break;
						}
						$site_list[] = $site_num;
						$err = recalc_site($survey_num, $site_num, 'manual');
						if ($err) {
							$msg = '<div class="message error"><span class="type">Error</span>'.$err.'</div>';
							break;
						}
					}
					if (!$err) {
						$msg = '<div class="message success"><span class="type">Success</span>Weekly Caps for Sites '.implode(", ", $site_list).' were Re-Calculated.</div>';
					}
				}
			} else {
				$err = recalc_site($survey_num, $site_num, 'manual');
				if ($err) {
					$msg = '<div class="message error"><span class="type">Error</span>'.$err.'</div>';
				} else {
					$msg = '<div class="message success"><span class="type">Success</span>Weekly Caps for Site '.$site_num.' were Re-Calculated.</div>';
				}
			}
		}

		break;

	case "remove_caps":
		if ("all" == $_POST['site_or_all']) {
			$caps_msg = "All Sites";
			$site_all = 'all';
		} else {
			$caps_msg = "Site $site_num";
			$site_all = $site_num;
		}

		if ($confirmed) {
			remove_caps($survey_num, $site_all);
			if ("all" == $_POST['site_or_all']) {
				$msg = '<div class="message success"><span class="type">Success</span>Weekly Caps for All Sites were Removed.</div>';
			} else {
				$msg = '<div class="message success"><span class="type">Success</span>Weekly Caps for Site '.$site_num.' were Removed.</div>';
			}
		} else {
			echo  <<<PRINT_HTML
<div class="message warning"><span class="type">Note</span>You are about to remove Weekly Caps for $caps_msg. <br/>
<span class="pseudolink" onclick="remove_recalc('{$_POST['site_or_all']}', 'remove', 1)">Click to proceed</span> or <a href="/adindex3c/weekly_caps.php?$qs&site_num=$site_num">Go Back to cancel</a>.</div>

PRINT_HTML;
		}
		break;
	case "ls_add":
		$err = add_updt_validation($survey_num, $site_num, $linked_site_list, $all_sites);

		if ($err) {
			$msg = '<div class="message error"><span class="type">Error</span>'.$err.'</div>';
			break;
		}

		if (!$confirmed) {
			$qs = $_SERVER['QUERY_STRING'];
			echo  <<<PRINT_HTML
<div class="message warning"><span class="type">Note</span>You are about to set up Shared Weekly Caps. This will remove all caps and freeze all weeks in your Linked Sites.<br/>
<span class="pseudolink" onclick="do_linked_site('ls_add', 1)">Click to proceed.</span></div>

PRINT_HTML;
			break;
		} else {
			$err = add_updt_sites($survey_num, $site_num, $linked_site_list);

			if ($err) {
				$msg = '<div class="message error"><span class="type">Error</span>'.$err.'</div>';
			} else {
				$msg = '<div class="message success"><span class="type">Success</span>Shared weekly caps were successfully setup.</div>';
			}
		}
		break;

	case "ls_del":
		if (!$linked_site_list) {
			$msg = '<div class="message error"><span class="type">Error</span>There are no Linked Sites to Delete</div>';
			break;
		}
		if (!$confirmed) {
			$qs = $_SERVER['QUERY_STRING'];
			echo  <<<PRINT_HTML
<div class="message warning"><span class="type">Note</span>You are about to remove all Shared Caps for this site.<br/>
<span class="pseudolink" onclick="do_linked_site('ls_del', 1)">Click to proceed.</span></div>

PRINT_HTML;
			break;
		}
		$resUpdt = $db->query("DELETE FROM weekly_caps_linked_sites WHERE survey_num = ? AND master_site_num = ?", array($survey_num, $site_num));
		if ($resUpdt !== DB_OK || $db->affectedRows() < 1) {
			$msg = '<div class="message error"><span class="type">Error</span>There was a database problem removing Shared weekly caps</div>';
		} else {
			$msg = '<div class="message success"><span class="type">Success</span>Shared weekly caps were successfully deleted.</div>';
		}
		break;
	}
}

echo $msg;

$timezone = $db->getOne("SELECT tzname FROM ai_sites ais, site_rules sr WHERE ais.site_rules_id=sr.site_rules_id AND survey_num = ? AND site_num = ?",array($survey_num, $site_num));

/**
 * Define weeks in campaign
 */
$wks_from_dates = get_weeks($survey_num, $site_num);

if (isset($wks_from_dates['err'])) {
	$err = "You must <a href=\"/adindex3c/siteloop_admin.php?ainame=$ainame&survey_num=$survey_num&edit=$site_num\">define a Start and End date for this Site</a> to set caps.<br/>";
	echo '<div class="message error"><span class="type">Error</span>'.$err.'</div>';
	$layout->view('footer');
	exit;
}

$wks = get_start_of_week_dates($wks_from_dates['start_date'], $wks_from_dates['end_date']);

$sql = "SELECT min(to_char(week_start_date, 'yyyymmdd')), max(to_char(week_start_date, 'yyyymmdd')) FROM weekly_caps WHERE survey_num = ? AND site_num = ?";
list($wc_start, $wc_end) = $db->getRow($sql, array($survey_num, $site_num), DB_FETCHMODE_ORDERED);

/**
 * delete any future weekly_caps record that are out-of-bounds for calculated start-end dates
 * DO NOT delete historical cap records.
 */
if ( ($wc_start && $wks[0] > $wc_start) || ($wc_end && end($wks) < $wc_end)) {
	$sql = <<<SQL_STMT
DELETE FROM weekly_caps
	WHERE survey_num = ? AND site_num = ?
	AND ctrl_cap < 0
	AND exp_cap < 0
	AND any_cap < 0
	AND (to_char(week_start_date, 'yyyymmdd') < ?
	OR to_char(week_start_date, 'yyyymmdd') > ?)
SQL_STMT;

	$db->query($sql, array($survey_num, $site_num, $wks[0], end($wks)));
}

if ($db->getOne("SELECT weekly_caps_rollover FROM ai_sites WHERE survey_num = ? AND site_num = ?", array($survey_num, $site_num)) > 0) {
	$rollover_checked = "checked";
} else {
	$rollover_checked = "";
}

/**
 * Get max values for site
 */
$max_values = get_max_vals($survey_num, $site_num);

$max_control = $max_values['max_control'];
$max_exposed = $max_values['max_exposed'];
$max_any = $max_values['max_any'];

$ctrl_no_caps_msg = "";
$exp_no_caps_msg = "";
if ($max_any == 'n/a') {
	if ($max_control == 'n/a' && $max_exposed != 'n/a') {
		$ctrl_no_caps_msg = '<tr><td colspan=2><span class="errmsg">Site Control Caps have not been set.</span></td></tr>';
	} elseif ($max_control == 'n/a' && $max_exposed != 'n/a') {
		$exp_no_caps_msg = '<tr><td colspan=2><span class="errmsg">Site Exposed Caps have not been set.</span></td></tr>';
	}
}


$either =  ( $max_control != "n/a" || $max_exposed != "n/a" || "n/a" == $max_any) ? 0 : 1;
$either_start_show = fmt_date_to_display($wks_from_dates['start_date']);
$either_end_show = fmt_date_to_display($wks_from_dates['end_date']);

$exp_start_show = fmt_date_to_display($wks_from_dates['exp_start_date']);
$exp_end_show = fmt_date_to_display($wks_from_dates['exp_end_date']);
$ctrl_start_show = fmt_date_to_display($wks_from_dates['ctrl_start_date']);
$ctrl_end_show = fmt_date_to_display($wks_from_dates['ctrl_end_date']);

$weekly_counts = get_counts($survey_num, $site_num, $wks);

$ctrl_total = 0;
$exp_total = 0;
foreach($weekly_counts as $wc_dt => $wc_vals) {
	$ctrl_total += $wc_vals['ctrl'];
	$exp_total += $wc_vals['exp'];
}

$linked_sites = array();
$sql = "SELECT linked_site_num FROM weekly_caps_linked_sites WHERE survey_num = ? AND master_site_num = ?";
$res = $db->query($sql, array($survey_num, $site_num));
while (list($l_site) = $res->fetchRow(DB_FETCHMODE_ORDERED)) {
	$linked_sites[] = $l_site;
}
?>

<script type="text/javascript">
var d=document, w=window;

var toggle_on_off = false;

$(function() {
	$('.scroll-pane').jScrollPane({showArrows: true});
});

function new_site() {
	c_form.submit();
}

function updt() {
	c_form.elements['action'].value = "updt";
	c_form.submit();
}

function remove_recalc(site_or_all, mode, confirm) {
	if ("remove" == mode) {
		c_form.elements['action'].value = "remove_caps";
	} else {
		c_form.elements['action'].value = "recalc_caps";
	}
	c_form.elements['site_or_all'].value = site_or_all;
	if (confirm) {
		c_form.elements['confirm'].value = "y";
	}
	c_form.submit();
}

function do_linked_site(mode, confirm) {
	c_form.elements['action'].value = mode;
	if (confirm) {
		c_form.elements['confirm'].value = "y";
	}
	c_form.submit();
}

function showSelRecalc() {
	if (d.getElementById('choose_site_recalc').style.display == "none") {
		d.getElementById('choose_site_recalc').innerHTML = d.getElementById('choose_site_ph').innerHTML;
		d.getElementById('choose_site_recalc').style.display = '';
	} else {
		d.getElementById('choose_site_recalc').style.display = 'none';
	}
}

function freeze() {
	var elems = d.getElementsByTagName('input');

	toggle_on_off = (toggle_on_off) ? false : true;

	// Check if all freeze boxes are checked
	var all_ckd = true;
	for (var i = 0; i < elems.length; i++) {
		if ("freeze" == elems[i].name.substr(0, 6) && "checkbox" == elems[i].type && c_form.elements[elems[i].name].checked == false) {
			all_ckd = false;
			break;
		}
	}

	if (toggle_on_off && all_ckd) {
		toggle_on_off = false;
	}

	for (var i = 0; i < elems.length; i++) {
		if ("freeze" == elems[i].name.substr(0, 6) && "checkbox" == elems[i].type) {
			c_form.elements[elems[i].name].checked = (toggle_on_off == true) ? true : false;
		}
	}
}

// Handler for any changes in caps
$(document).ready(function() {
	$('input.caps:text').change( function() {
		var ctrlTot = 0;
		var expTot = 0;
		var anyTot = 0;
		var val;
		var caps_name;
		var this_week = $("input[id=this_week]").val();

		// get caps from open weeks
		$('input.caps:text').each(function() {
			val = parseInt($(this).val());
			if (val >= 0 ) {
				if ($(this).attr("name").indexOf("ctrl") >= 0) {
					ctrlTot = ctrlTot + val;
				} else if ($(this).attr("name").indexOf("exp") >= 0) {
					expTot = expTot + val;
				} else if ($(this).attr("name").indexOf("any") >= 0) {
					anyTot = anyTot + val;
				}

			}
		});

		// get caps from frozen weeks
		$('input.caps:hidden').each(function() {
			val = parseInt($(this).val());
			caps_name = $(this).attr("name").split("-");
			if (caps_name[1] >= this_week &&val >= 0 ) {
				if ($(this).attr("name").indexOf("ctrl") >= 0) {
					ctrlTot = ctrlTot + val;
				} else if ($(this).attr("name").indexOf("exp") >= 0) {
					expTot = expTot + val;
				} else if ($(this).attr("name").indexOf("any") >= 0) {
					anyTot = anyTot + val;
				}

			}
		});

		//get actuals
		$('input.actuals:hidden').each(function() {
			val = parseInt($(this).val());
			if ($(this).attr("name").indexOf(this_week) < 0 && val >= 0) {	// do not count actuals for current week
				if ($(this).attr("name").indexOf("ctrl") >= 0) {
					ctrlTot = ctrlTot + val;
				} else if ($(this).attr("name").indexOf("exp") >= 0) {
					expTot = expTot + val;
				} else if ($(this).attr("name").indexOf("any") >= 0) {
					anyTot = anyTot + val;
				}

			}
		});

		if ( $("#ctrlTot").length > 0) {
			d.getElementById("ctrlTot").innerHTML = ctrlTot;
		}
		if ( $("#expTot").length > 0) {
			d.getElementById("expTot").innerHTML = expTot;
		}
		if ( $("#anyTot").length > 0) {
			d.getElementById("anyTot").innerHTML = anyTot;
		}
	});

	$('.caps').change();
});

</script>

<form name="caps_form" method="post" action="/adindex3c/weekly_caps.php?<?=$qs?>">
<input type="hidden" name="action" value=""/>
<input type="hidden" name="survey_num" value="<?=$survey_num?>"/>
<input type="hidden" name="start_date" value="<?=$wks_from_dates['start_date']?>"/>
<input type="hidden" name="end_date" value="<?=$wks_from_dates['end_date']?>"/>
<input type="hidden" name="freeze_all" value=""/>
<input type="hidden" name="confirm" value=""/>
<input type="hidden" name="site_or_all" value=""/>
<input type="hidden" name="this_week" id="this_week" value="<?=get_this_week()?>"/>
<input type="hidden" name="linked_site_conf" value="<?=$linked_site_list?>"/>

<!-- This input field will cause the caps selector event handler to fire even if all weeks are frozen (which means there would be no input.caps selectors -->
<input type="text" name="event_initiator" class="caps" value="0" style="display:none"/>

<table class="top_sect">
	<tr>
		<td class="accent">
			<span class="lrg"><b>Site:</b></span> <?=$site_sel?>
			<input class="btn sml" type="button" value="Go" name="new_site_submit" onclick="new_site()"/><br/>
			<?=$site_caps_msg?>
		</td>
		<td class="accent">Timezone: <span class="med"><?=$timezone?></span></td>
	</tr>
		<?php
		if ($either) {
			$any_total = $ctrl_total + $exp_total;

			echo <<<PRINT_HTML
<tr>
<th>Either</th>
<th>&nbsp;</th>
</tr>
<tr>
<td class="fixed_50"><table class="top_sml">
	<tr>
	<td>Starts: <b>$either_start_show</b></td>
	<td>To Date: <b>$any_total</b></td>
	</tr>
	<tr>
	<td>Ends: <b>$either_end_show</b></td>
	<td>Max: <b>$max_any</b></td>
	</tr>
	<tr>
	<td>Caps Total: <b><span id="anyTot"></span></b></td>
	<td>&nbsp;</td>
	</tr>
	</table>
</td>
<td class="fixed_50"><table class="top_sml">
	<tr>
	<td>&nbsp;</td>
	<td>&nbsp;</td>
	</tr>
	<tr>
	<td>&nbsp;</td>
	<td>&nbsp;</td>
	</tr>
	<tr>
	<td>&nbsp;</td>
	<td>&nbsp;</td>
	</tr>
	</table>
</td>
</tr>

PRINT_HTML;
		} else {
			echo <<<PRINT_HTML
<tr>
<th>Control</th>
<th>Exposed</th>
</tr>
<tr>
<td class="fixed_50"><table class="top_sml">
	<tr>
	<td>Starts: <b>$ctrl_start_show</b></td>
	<td>To Date: <b>$ctrl_total</b></td>
	</tr>
	<tr>
	<td>Ends: <b>$ctrl_end_show</b></td>
	<td>Max: <b>$max_control</b></td>
	</tr>
	<tr>
	<td>Caps Total: <b><span id="ctrlTot"></span></b></td>
	<td>&nbsp;</td>
	</tr>
	$ctrl_no_caps_msg
	</table>
</td>
<td class="fixed_50"><table class="top_sml">
	<tr>
	<td>Starts: <b>$exp_start_show</b></td>
	<td>To Date: <b>$exp_total</b></td>
	</tr>
	<tr>
	<td>Ends: <b>$exp_end_show</b></td>
	<td>Max: <b>$max_exposed</b></td>
	</tr>
	<tr>
	<td>Caps Total: <b><span id="expTot"></span></b></td>
	<td>&nbsp;</td>
	</tr>
	$exp_no_caps_msg
	</table>
</td>
</tr>

PRINT_HTML;
		}
		?>
	<tr>
		<td class="accent"><input class="btn sml" type="button" value="Re-Calculate Site <?=$site_num?>" name="recalc_site_submit" onclick="remove_recalc('site', 'recalc', 0)" /></td>
		<td class="accent"><input class="btn sml" type="button" value="Remove Caps Site <?=$site_num?>" name="remove_caps_site_submit" onclick="remove_recalc('site', 'remove', 0)" /></td>
	</tr>
	<tr>
		<td class="accent">
			<div>
				<div class="div_splt">
					<input class="btn sml" type="button" value="Re-Calculate All Sites" name="recalc_all_submit" onclick="remove_recalc('all', 'recalc', 0)"/>
				</div>
				<input class="btn sml" type="button" value="Re-Calculate Select Sites" name="recalc_select_submit" onclick="showSelRecalc();"/>
				<div class="clearfloat"></div>

				<div id="choose_site_recalc"></div>
			</div>
		</td>
		<td class="accent"><input class="btn sml" type="button" value="Remove Caps All Sites" name="remove_caps_all_submit" onclick="remove_recalc('all', 'remove', 0)"/></td>
	</tr>
	<tr>
</table>

<div id="choose_site_ph"><?=$choose_recalc_sites?></div>
<br/>

<table class="top_sect">
	<tr>
		<td class="accent" colspan="2">
			<div class="indent"><b>Setup shared caps using the current site as the Host Site.</b><br/>
				Linked Sites will have all caps set to -1 and all weeks frozen, as they will use cap settings for the Host Site.</div>
		</td>
	</tr>
<?php
$html = linked_sites_section($survey_num, $site_num, $all_sites, $linked_sites, $linked_site_list);
echo $html;
?>

</table>

<br/><br/>
<div>
	<div class="sml">Use scrollbar or mousewheel to view more weeks.</div>
	<table class="wks_hdr">
		<tr>
			<td class="fixed_40"><input class="btn sml" type="button" value="Update" onclick="updt()"/></td>
			<td class="fixed_40"><input class="btn sml" type="reset" value="Clear"/></td>
			<td class="fixed_20"><input class="btn sml" type="button" value="Freeze All" onclick="freeze()" /></td>
		</tr>
		<tr>
			<td colspan="3" class="spec_instr">
				<input type="checkbox" value="1" name="rollover" <?=$rollover_checked?>/>
				<span class="greentxt">Roll unmet caps from last week into the next week.</span> Check this box to add unmet caps from the previous week to next week's bucket during Sunday's automated recalc. This will happen each Sunday automatically until the box is unchecked. If you do a manual recalc, all caps will be distributed evenly over the remaining weeks until the next Sunday automated recalc.
			</td>
		</tr>
	</table>
	<div class="scroll-pane">
		<table class="wks">
				<?php

				$ctr = count($wks);

				for ($i=0; $i < $ctr; $i++) {
					$wk_nbr = $i + 1;
					$dt = fmt_date_to_display($wks[$i]);
					$last_day = date('m/d/y', (strtotime($dt) + 86400 * 6 + 3601));

					$mini_cal = get_wk_mini_cal($wks[$i], $wks_from_dates['start_date'], $wks_from_dates['end_date']);

					$alt_row_style = (($wk_nbr % 2) == 0) ? 'even_row' : "odd_row";

					$sql = <<<SQL_STMT
SELECT ctrl_cap, exp_cap, any_cap, frozen FROM weekly_caps WHERE survey_num = ? AND site_num = ? AND to_char(week_start_date, 'yyyymmdd') = ?
SQL_STMT;
					list($ctrl_cap, $exp_cap, $either_cap, $freeze) = $db->getRow($sql, array($survey_num, $site_num, $wks[$i]), DB_FETCHMODE_ORDERED);

					if (!isset($ctrl_cap)) {
						$ctrl_cap = -1;
						$exp_cap = -1;
						$either_cap = -1;
						$freeze = 0;
					}

					$freeze_ckd = ($freeze) ? " checked" : "";

					$ctrl_disable = ($ctrl_no_caps_msg) ? " readonly" : "";
					$exp_disable = ($exp_no_caps_msg) ? " readonly" : "";

					if (mktime(0, 0, 0, substr($last_day,0, 2), substr($last_day,3, 2), substr($last_day,6, 4)) < time()) {
						$ctrl_display = "$ctrl_cap<input class=\"caps\" type=\"hidden\" name=\"ctrl_cap-$wks[$i]\" id=\"ctrl_cap-$wks[$i]\" value=\"$ctrl_cap\"/>";
						$exp_display = "$exp_cap<input class=\"caps\" type=\"hidden\" name=\"exp_cap-$wks[$i]\" id=\"exp_cap-$wks[$i]\" value=\"$exp_cap\"/>";
						$either_display = "$either_cap<input class=\"caps\" type=\"hidden\" name=\"any_cap-$wks[$i]\" id=\"any_cap-$wks[$i]\" value=\"$either_cap\"/>";
						$freeze_display = "";
					} elseif ($freeze) {
						$ctrl_display = "$ctrl_cap<input class=\"caps\" type=\"hidden\" name=\"ctrl_cap-$wks[$i]\" id=\"ctrl_cap-$wks[$i]\" value=\"$ctrl_cap\"/>";
						$exp_display = "$exp_cap<input class=\"caps\" type=\"hidden\" name=\"exp_cap-$wks[$i]\" id=\"exp_cap-$wks[$i]\" value=\"$exp_cap\"/>";
						$either_display = "$either_cap<input class=\"caps\" type=\"hidden\" name=\"any_cap-$wks[$i]\" id=\"any_cap-$wks[$i]\" value=\"$either_cap\"/>";
						$freeze_display = "Freeze: <input type=\"checkbox\" name=\"freeze-$wks[$i]\" value=\"1\" $freeze_ckd/>";
					} else {
						$ctrl_display = "<input class=\"caps\" type=\"text\" name=\"ctrl_cap-$wks[$i]\" id=\"ctrl_cap-$wks[$i]\" value=\"$ctrl_cap\" size=\"3\" $ctrl_disable/>";
						$exp_display = "<input class=\"caps\" type=\"text\" name=\"exp_cap-$wks[$i]\" id=\"exp_cap-$wks[$i]\" value=\"$exp_cap\" size=\"3\" $exp_disable/>";
						$either_display = "<input class=\"caps\" type=\"text\" name=\"any_cap-$wks[$i]\" id=\"any_cap-$wks[$i]\" value=\"$either_cap\" size=\"3\"/>";
						$freeze_display = "Freeze: <input type=\"checkbox\" name=\"freeze-$wks[$i]\" value=\"1\" $freeze_ckd/>";
					}

					$ls_item_ctrl = "";
					$ls_item_exp = "";
					$ls_item_any = "";
					if (isset($weekly_counts_ls_sum)) {
						if (!isset($weekly_counts_ls_sum[$wks[$i]])) {
							$ls_item_ctrl = "<br/>Linked Sites Act: <b>0</b>";
							$ls_item_exp = "<br/>Linked Sites Act: <b>0</b>";
							$ls_item_any = "<br/>Linked Sites Act: <b>0</b>";
						}
						if ($either) {
							$ls_item_any = "<br/>Linked Sites Act: <b>" . ($weekly_counts_ls_sum[$wks[$i]]['ctrl'] + $weekly_counts_ls_sum[$wks[$i]]['exp']) . "</b>";
						} else {
							$ls_item_ctrl = "<br/>Linked Sites Act: <b>" . $weekly_counts_ls_sum[$wks[$i]]['ctrl'] . "</b>";
							$ls_item_exp = "<br/>Linked Sites Act: <b>" . $weekly_counts_ls_sum[$wks[$i]]['exp'] . "</b>";
						}
					}

					if ($either) {
						$any_actual = 0;
						if (isset($weekly_counts[$wks[$i]]['ctrl'])) {
							$any_actual = $weekly_counts[$wks[$i]]['ctrl'];
						}
						if (isset($weekly_counts[$wks[$i]]['exp'])) {
							$any_actual += $weekly_counts[$wks[$i]]['exp'];
						}

						echo <<<PRINT_HTML
<tr>
	<td class="fixed_40 $alt_row_style">
	<div>
	<div class="div_splt">
		<b>WK $wk_nbr</b><br/>
		$dt - $last_day
		<input type="hidden" name="wk_nbr-$wks[$i]" value="$wk_nbr"/>
	</div>
	$mini_cal
	<div class="clearfloat"></div>
	</div>
	</td>
	<td class="fixed_40 $alt_row_style">
	<div class="div_splt">
		Either Cap: $either_display<br/>
		Act: <b>$any_actual</b>
		<input class="actuals" type="hidden" name="any_act-$wk_nbr" id="any_act-$wk_nbr" value="$any_actual"/>
		$ls_item_any
	</div>
	</td>
	<td class="fixed_20 $alt_row_style">$freeze_display</td>
</tr>

PRINT_HTML;

					} else {
						$ctrl_actual = (isset($weekly_counts[$wks[$i]]['ctrl'])) ? $weekly_counts[$wks[$i]]['ctrl'] : 0;
						$exp_actual = (isset($weekly_counts[$wks[$i]]['exp'])) ? $weekly_counts[$wks[$i]]['exp'] : 0;

						echo <<<PRINT_HTML
<tr>
	<td class="fixed_40 $alt_row_style">
	<div>
	<div class="div_splt">
		<b>WK $wk_nbr</b><br/>
		$dt - $last_day
		<input type="hidden" name="wk_nbr-$wks[$i]" value="$wk_nbr"/>
	</div>
	$mini_cal
	<div class="clearfloat"></div>
	</div>
	</td>
	<td class="fixed_40 $alt_row_style">
	<div>
	<div class="div_splt">
		CTL Cap: $ctrl_display<br/>
		Act: <b>$ctrl_actual</b>
		<input class="actuals" type="hidden" name="ctrl_act-{$wks[$i]}" id="ctrl_act-{$wks[$i]}" value="$ctrl_actual"/>
		$ls_item_ctrl
	</div>
		EXP Cap: $exp_display<br/>
		Act: <b>$exp_actual</b>
		<input class="actuals" type="hidden" name="exp_act-{$wks[$i]}" id="exp_act-{$wks[$i]}" value="$exp_actual"/>
		$ls_item_exp
	<div class="clearfloat"></div>
	</div>
	</td>
	<td class="fixed_20 $alt_row_style">$freeze_display</td>
</tr>

PRINT_HTML;
					}
				}
				?>

		</table>
	</div>

</div>
</form>

<script type="text/javascript">
	var c_form = d.forms['caps_form'];
	d.getElementById("choose_site_recalc").style.display='none';
</script>
<?php
$layout->view('footer');

/**
 * Validate form fields
 */
function validate_input() {
	$err = array();

	$updt = array();
	foreach ($_POST as $key => $val) {
		$fld = explode("-",$key);
		if (!isset($fld[1])) {
			continue;
		}

		if (!is_numeric($val)) {
			if ("ctrl_cap" == $fld[0]) {
				$fld_name = "Control Caps";
			} elseif ("exp_cap" == $fld[0]) {
				$fld_name = "Exposed Caps";
			} elseif ("any_cap" == $fld[0]) {
				$fld_name = "Either Caps";
			} else {
				continue;
			}
			$show_dt = 	substr($fld[1], 4, 2) . "/". substr($fld[1], 6, 2) . "/" . substr($fld[1], 0, 4);
			$err[] = "The $fld_name for Week of $show_dt is not numeric.<br/>";
		}
	}

	return $err;
}

function do_update($survey_num, $site_num) {
	global $db;

	$this_week = get_this_week();

	/**
	 * Build array of values for each week from $_POST
	 */
	$updt = array();
	foreach ($_POST as $key => $val) {
		$fld = explode("-",$key);
		if (!isset($fld[1])) {
			continue;
		}

		/**
		 * Don't bother with prior week data
		 */
		if ($fld[1] < $this_week) {
			continue;
		}

		switch($fld[0]) {
		case "ctrl_cap":
			$updt[$fld[1]]["ctrl_cap"] = $val;
			break;
		case "exp_cap":
			$updt[$fld[1]]["exp_cap"] = $val;
			break;
		case "any_cap":
			$updt[$fld[1]]["any_cap"] = $val;
			break;
		case "freeze":
			$updt[$fld[1]]["freeze"] = $val;
			break;
		case "wk_nbr":
			$updt[$fld[1]]["wk_nbr"] = $val;
			break;
		}
	}

	// Start-End Dates
	$wks_from_dates = get_weeks($survey_num, $site_num);

	$total_wks = count($updt);
	foreach ($updt as $dt => $val) {
		/**
		 * Caps should not be blank and should not be less than -1
		 */
		$ctrl_cap = (!isset($val['ctrl_cap']) || $val['ctrl_cap'] < -1) ?  -1 : $val['ctrl_cap'];
		$exp_cap = (!isset($val['exp_cap']) || $val['exp_cap'] < -1) ?  -1 : $val['exp_cap'];
		$any_cap = (!isset($val['any_cap']) || $val['any_cap'] < -1) ?  -1 : $val['any_cap'];
		$frozen = (isset($val['freeze'])) ? 1 : 0;

		$days_in_week = get_days_in_week($dt, $wks_from_dates['start_date'], $wks_from_dates['end_date']);

		if ($db->getOne("SELECT count(*) FROM weekly_caps WHERE survey_num = ? AND site_num = ? AND to_char(week_start_date, 'yyyymmdd') = ?", array($survey_num, $site_num, $dt)) > 0) {
			// update
			$sql = <<<SQL_STMT
UPDATE weekly_caps SET ctrl_cap = ?, exp_cap = ?, any_cap = ?, frozen = ?, days_in_week = ?
	WHERE survey_num = ? AND site_num = ? AND to_char(week_start_date, 'yyyymmdd') = ?
SQL_STMT;
			$res = $db->query($sql, array($ctrl_cap, $exp_cap, $any_cap, $frozen, $days_in_week, $survey_num, $site_num, $dt));
		} else {
			// insert

			$ts = mktime(0, 0, 0, substr($dt, 4, 2), substr($dt, 6, 2), substr($dt, 0, 4));
			$dt_ora = strtoupper(date('d-M-y', $ts));

			$sql = <<<SQL_STMT
INSERT INTO weekly_caps
	(survey_num, site_num, week_start_date, ctrl_cap, exp_cap, any_cap, frozen, days_in_week)
	VALUES (?, ?, ?, ?, ?, ?, ?, ?)
SQL_STMT;
			$res = $db->query($sql, array($survey_num, $site_num, $dt_ora, $ctrl_cap, $exp_cap, $any_cap, $frozen, $days_in_week));
		}
	}

	/**
	 * handle Rollover checkbox
	 */
	$ro = (isset($_POST['rollover']) && $_POST['rollover'] == 1) ? 1 : 0;
	$res = $db->query("UPDATE ai_sites SET weekly_caps_rollover = ? WHERE survey_num = ? AND site_num = ?", array($ro, $survey_num, $site_num));
}

/**
 * Remove caps for 1 site or all sites
 */
function remove_caps($survey_num, $site) {
	global $db;

	$tdy = date('Ymd');
	$dow = get_dow($tdy);
	if ($dow > 0) {
		$ts = time() - (86400 * $dow) + 3601;
		$cutoff = date('Ymd', $ts);
	} else {
		$cutoff = $tdy;
	}

	$site_all = ("all" == $site) ? "" : " AND site_num=$site";
	$sql = "DELETE FROM weekly_caps WHERE survey_num = ? $site_all AND to_char(week_start_date, 'yyyymmdd') >= ?";

	$db->query($sql, array($survey_num, $cutoff));
}

/**
 * Show Linked Sites section
 */
function linked_sites_section($survey_num, $site_num, $all_sites, $linked_sites, $linked_site_sel) {
	global $db, $weekly_counts_ls_sum;

	$html = "<tr><td>";

	/**
	 * Check if current site is already a linked site
	 */
	$lsites = $db->getCol("SELECT DISTINCT master_site_num FROM weekly_caps_linked_sites WHERE survey_num = ? AND linked_site_num = ?", 0, array($survey_num, $site_num));
	if (isset($lsites[0])) {
		$html .= "<div class='indent'>This site is a Linked Site to Host Site <b>{$all_sites[$lsites[0]]} ({$lsites[0]})</b></div></td></tr>";
		return $html;
	}

	$selsize = (count($all_sites) > 26) ? 25 : (count($all_sites) - 1);
	$html .= <<<PRINT_HTML
<div class="indent">
	Choose Linked sites to share caps:<br/>
	<select name="linked_sites[]" id="linked_sites" multiple="multiple" size="$selsize">

PRINT_HTML;

	/**
	 * Was this a delete of all linked sites? Then don't "select" sites from POST list.Fvar_
	 */
	$del = (isset($_POST['action']) && isset($_REQUEST['confirm']) && $_POST['action'] == 'ls_del' && $_REQUEST['confirm'] == 'y') ? 1 : 0;

	foreach ($all_sites as $asnum => $asname) {
		if ($site_num == $asnum) {
			continue;
		}

		if ( (!empty($linked_sites) && in_array($asnum, $linked_sites)) || (!$del && is_array($linked_site_sel) && in_array($asnum, $linked_site_sel))) {
			$sel = "selected";
		} else {
			$sel = "";
		}

		$html .= "<option value='$asnum' $sel>$asnum - $asname</option>\n";
	}


	$html .= <<<PRINT_HTML
	</select>
	<br/><br/>
	<div>
		<div class="div_splt">
			<input class="btn sml" type="button" value="Add/Update Linked Sites" onclick="do_linked_site('ls_add', 0)"/>
		</div>
		<input class="btn sml" type="button" value="Remove All Shared Caps" onclick="do_linked_site('ls_del', 0)"/>
		<div class="clearfloat"></div>
	</div>
</div>
</td>

<td>
	<table class="top_sml">
PRINT_HTML;

		if (!empty($linked_sites)) {
			$html .= "<tr><th class=ls>Linked Site Totals</th></tr>";
			$weekly_counts_ls_sum = array();
			foreach ($linked_sites as $lsnum) {
				$lsname = $all_sites[$lsnum];
				$max_values_ls = get_max_vals($survey_num, $lsnum);
				$either_ls =  ( $max_values_ls['max_control'] != "n/a" || $max_values_ls['max_exposed'] != "n/a" || "n/a" == $max_values_ls['max_any']) ? 0 : 1;
				$wks_from_dates_ls = get_weeks($survey_num, $lsnum);
				$wks_ls = get_start_of_week_dates($wks_from_dates_ls['start_date'], $wks_from_dates_ls['end_date']);
				$weekly_counts_ls = get_counts($survey_num, $lsnum, $wks_ls);

				$ctrl_total_ls = 0;
				$exp_total_ls = 0;

				foreach($weekly_counts_ls as $wc_dt => $wc_vals) {
					$ctrl_total_ls += $wc_vals['ctrl'];
					$exp_total_ls += $wc_vals['exp'];

					if (isset($weekly_counts_ls_sum[$wc_dt]['ctrl'])) {
						$weekly_counts_ls_sum[$wc_dt]['ctrl'] += $wc_vals['ctrl'];
					} else {
						$weekly_counts_ls_sum[$wc_dt]['ctrl'] = $wc_vals['ctrl'];
					}
					if (isset($weekly_counts_ls_sum[$wc_dt]['exp'])) {
						$weekly_counts_ls_sum[$wc_dt]['exp'] += $wc_vals['exp'];
					} else {
						$weekly_counts_ls_sum[$wc_dt]['exp'] = $wc_vals['exp'];
					}
				}

				if ($either_ls) {
					$any_total_ls = $ctrl_total_ls + $exp_total_ls;
					$ls_ttl =  "Any To-Date: $any_total_ls";
				} else {
					$ls_ttl = <<<PRINT_HTML
		<div class="div_splt">Ctrl To-Date: $ctrl_total_ls</div>
		Exp To-Date: $exp_total_ls
		<div class="clearfloat"></div>

PRINT_HTML;
				}

				$html .= <<<PRINT_HTML
	<tr><td class=ls>
		<div><b>$lsname ($lsnum)</b></div>
		<div>$ls_ttl</div>
	</td></tr>

PRINT_HTML;

			} // end foreach site
		}
	$html .= "</table></td></tr>";

	return $html;
}

function check_site_caps($survey_num, $site_num) {
	$max_values = get_max_vals($survey_num, $site_num);

	if ($max_values['max_control'] == 'n/a' && $max_values['max_exposed'] == 'n/a' && $max_values['max_any'] == 'n/a') {
		return false;
	}

	return true;
}

