<?php

//////////////////////////////////
// DATE FUNCTIONS
//////////////////////////////////

/**
 * Find overall/control/exposed start and end dates
 */
function get_weeks($survey_num, $site_num) {
	global $db;
	
	$sql = <<<SQL_STMT
SELECT to_char(exposed_rec_start, 'yyyymmdd'), to_char(exposed_rec_end, 'yyyymmdd'), 
	to_char(control_rec_start, 'yyyymmdd'), to_char(control_rec_end, 'yyyymmdd') 
	FROM ai_sites WHERE survey_num = ? AND site_num = ?
SQL_STMT;
	list($exposed_rec_start, $exposed_rec_end, $control_rec_start, $control_rec_end) = $db->getRow($sql, array($survey_num, $site_num), DB_FETCHMODE_ORDERED);
	if (!$exposed_rec_start || !$exposed_rec_end || !$control_rec_start || !$control_rec_end) {
		$sql = <<<SQL_STMT
SELECT to_char(campaign_start, 'yyyymmdd'), to_char(campaign_end, 'yyyymmdd'), 
	to_char(ctrl_start_date, 'yyyymmdd'), to_char(ctrl_end_date, 'yyyymmdd'),
	to_char(exp_start_date, 'yyyymmdd'), to_char(exp_end_date, 'yyyymmdd')
	FROM statement_of_work WHERE survey_num = ?
SQL_STMT;
		list($campaign_start, $campaign_end, $ctrl_start_date, $ctrl_end_date, $exp_start_date, $exp_end_date) = $db->getRow($sql, array($survey_num), DB_FETCHMODE_ORDERED);
	}

	if ( (!$exposed_rec_start && !$exp_start_date && !$control_rec_start && !$ctrl_start_date && !$campaign_start) ||
		(!$exposed_rec_end && !$exp_end_date && !$control_rec_end && !$ctrl_end_date && !$campaign_end)
	) {
		return array('err' => "No Start and/or End Dates defined for Survey $survey_num, Site $site_num.");
	}

	/**
	 * Get exposed start & end dates.
	 */
	if (!$exposed_rec_start) {
		if (!$exp_start_date) {
			$exp_start = $campaign_start;
		} else {
			$exp_start = $exp_start_date;
		}
	} else {
		$exp_start = $exposed_rec_start;
	}
	if (!$exposed_rec_end) {
		if (!$exp_end_date) {
			$exp_end = $campaign_end;
		} else {
			$exp_end = $exp_end_date;
		}
	} else {
		$exp_end = $exposed_rec_end;
	}
	
	/**
	 * Get control start & end dates.
	 */
	if (!$control_rec_start) {
		if (!$ctrl_start_date) {
			$ctrl_start = $campaign_start;
		} else {
			$ctrl_start = $ctrl_start_date;
		}
	} else {
		$ctrl_start = $control_rec_start;
	}
	if (!$control_rec_end) {
		if (!$ctrl_end_date) {
			$ctrl_end = $campaign_end;
		} else {
			$ctrl_end = $ctrl_end_date;
		}
	} else {
		$ctrl_end = $control_rec_end;
	}
	
	/**
	 * Get earliest start date & latest end date
	 */
	$start_date = ($exp_start <= $ctrl_start) ? $exp_start : $ctrl_start;
	$end_date = ($exp_end >= $ctrl_end) ? $exp_end : $ctrl_end;
	
	return array(
		'start_date' => $start_date,
		'end_date' => $end_date,
		'exp_start_date' => $exp_start,
		'exp_end_date' => $exp_end,
		'ctrl_start_date' => $ctrl_start,
		'ctrl_end_date' => $ctrl_end,
	);
}

/**
* Get start of current week (or next week for rollover)
*/
function get_this_week() {
	$s = date('Ymd');
	$s_dow = get_dow($s);
	if (!$s_dow) {
		return $s;
	} else {
		return date('Ymd', (strtotime($s) - ($s_dow * 86400) + 3601));
	}
}

/**
 * Get start of current week (or next week for rollover)
 */
function get_last_week() {
	$s = date('Ymd');
	$s_dow = get_dow($s);
	return date('Ymd', (strtotime($s) - ($s_dow * 86400) - (7 * 86400) + 3601));
}

/**
 * Get the first day of each week
 */
function get_start_of_week_dates($s, $e) {
	$wks = array();

	/**
	 * Determine if start date is a week boundary and enter into 1st element of $wks array
	 */
	$s_dow = get_dow($s);
	if (!$s_dow) {
		$wks[0] = $s;
	} else {
		$wks[0] = date('Ymd', (strtotime($s) - ($s_dow * 86400) + 3601));
	}
	
	/**
	 * Enter all start-of-week dates into $wks array
	 */
	$dt = $wks[0];
	while ($dt < $e) {
		 $next = strtotime($dt) + 86400 * 7 + 3601; // 3601 because we need the timestamp of 1 hour after our date at midnight to account for DST
		 $dt = date('Ymd', $next);
		 $wks[] = $dt;
		 
	}

	if (end($wks) > $e) {
		array_pop($wks);
	}
	
	return $wks;
}

/**
 * Get number of days in each week for ctrl/exp/any
 */
function get_days_per_week($wks_from_dates, $wks) {
	$ctr = count($wks);
	$days_per_week = array();
	
	for ($i=0; $i<$ctr; $i++) {
		$dt = $wks[$i];
		
		// ANY
		if ( ($wks_from_dates['end_date'] >= $dt) && (get_date_diff($dt, $wks_from_dates['start_date'])+1 < 8) ) {
			$days_per_week[$dt]['any'] = get_days_in_week($dt, $wks_from_dates['start_date'], $wks_from_dates['end_date']);
		} else {
			$days_per_week[$dt]['any'] = 0;
		}
		// CTRL
		if ( ($wks_from_dates['ctrl_end_date'] >= $dt) && (get_date_diff($dt, $wks_from_dates['ctrl_start_date'])+1 < 8) ) {
			$days_per_week[$dt]['ctrl'] = get_days_in_week($dt, $wks_from_dates['ctrl_start_date'], $wks_from_dates['ctrl_end_date']);
		} else {
			$days_per_week[$dt]['ctrl'] = 0;
		}
		// EXP
		if ( ($wks_from_dates['exp_end_date'] >= $dt) && (get_date_diff($dt, $wks_from_dates['exp_start_date'])+1 < 8) ) {
			$days_per_week[$dt]['exp'] = get_days_in_week($dt, $wks_from_dates['exp_start_date'], $wks_from_dates['exp_end_date']);
		} else {
			$days_per_week[$dt]['exp'] = 0;
		}
	}

	return $days_per_week;
}

/**
 * Find day of the week (numeric between 0 and 6)
 * Weekly caps begins on Monday so need to adjust result of jddayofweek function
 * Input date is yyyymmdd
 */
function get_dow($dt) {
	$y = substr($dt, 0, 4);
	$m = substr($dt, 4, 2);
	$d = substr($dt, 6, 2);

	$dow = jddayofweek(cal_to_jd(CAL_GREGORIAN, $m, $d, $y));

	if ($dow == 0) {
		$dow = 6;
	} else {
		$dow--;
	}
	
	return $dow; 
}

/**
 * Get number of days between 2 dates
 */

function get_date_diff($d1, $d2) {
	// dates must be YYYY-mm-dd
	$d1 = substr($d1, 0, 4) . "-" . substr($d1, 4, 2) . "-" . substr($d1, 6, 2);
	$d2 = substr($d2, 0, 4) . "-" . substr($d2, 4, 2) . "-" . substr($d2, 6, 2);
	
	$d1 = new DateTime($d1);
	$d2 = new DateTime($d2);
	$nbr_days = ceil(($d2->format('U') - $d1->format('U')) / (60*60*24));

	return $nbr_days;
}

/**
 * Build the mini-calendar
 */
function get_wk_mini_cal($dt, $s, $e) {
	$html_str = <<<PRINT_HTML
<table class="mini_cal"><tr><td>M</td><td>T</td><td>W</td><td>T</td><td>F</td><td>S</td><td>S</td></tr><tr>
	
PRINT_HTML;

	$td_class = ($dt < $s || $dt > $e) ? ' class="grayout"' : "";
	$html_str .= "<td $td_class>". substr($dt, 6,2) . "</td>\n";
	
	$next_dt = date('Ymd', strtotime($dt) + 86400 * 1 + 3601);
	$td_class = ($next_dt < $s || $next_dt > $e) ? ' class="grayout"' : "";
	$html_str .= "<td $td_class>". substr($next_dt,6,2) . "</td>\n";
	
	$next_dt = date('Ymd', strtotime($dt) + 86400 * 2 + 3601);
	$td_class = ($next_dt < $s || $next_dt > $e) ? ' class="grayout"' : "";
	$html_str .= "<td $td_class>". substr($next_dt,6,2) . "</td>\n";
	
	$next_dt = date('Ymd', strtotime($dt) + 86400 * 3 + 3601);
	$td_class = ($next_dt < $s || $next_dt > $e) ? ' class="grayout"' : "";
	$html_str .= "<td $td_class>". substr($next_dt,6,2) . "</td>\n";
	
	$next_dt = date('Ymd', strtotime($dt) + 86400 * 4 + 3601);
	$td_class = ($next_dt < $s || $next_dt > $e) ? ' class="grayout"' : "";
	$html_str .= "<td $td_class>". substr($next_dt,6,2) . "</td>\n";
	
	$next_dt = date('Ymd', strtotime($dt) + 86400 * 5 + 3601);
	$td_class = ($next_dt < $s || $next_dt > $e) ? ' class="grayout"' : "";
	$html_str .= "<td $td_class>". substr($next_dt,6,2) . "</td>\n";
	
	$next_dt = date('Ymd', strtotime($dt) + 86400 * 6 + 3601);
	$td_class = ($next_dt < $s || $next_dt > $e) ? ' class="grayout"' : "";
	$html_str .= "<td $td_class>". substr($next_dt,6,2) . "</td>\n";
	
	$html_str .= "</tr></table>\n";
	
	return $html_str;
}

/**
 * Convert date from yymmdd to mm/dd/yy
 */
function fmt_date_to_display($dt) {
	return substr($dt, 4, 2) . "/". substr($dt, 6, 2) . "/" . substr($dt, 2, 2);
}

/**
 * Get number of days in a week
 */
function get_days_in_week($dt, $start_date, $end_date) {
	$start_dow = get_dow($start_date);
	$end_dow = get_dow($end_date);
	$total_days = get_date_diff($start_date, $end_date)+1;
	
	/**
	 * Handle weekly caps for less than 1 week window
	 */
	if ($total_days < 7 && (($start_dow - $end_dow) <= 0)) {
		$days_in_week = $total_days;
	} elseif ($dt < $start_date) {
		$days_in_week = 7 - get_dow($start_date);
	} elseif (get_date_diff($dt, $end_date) < 7) {
		$days_in_week = get_dow($end_date) + 1;
	} else {
		$days_in_week = 7;
	}

	return $days_in_week;
	
}

//////////////////////////////////
// COUNTS FUNCTIONS
//////////////////////////////////

/**
 * Build an array of counts from survey_data table of actual exposed/control respondents.
 */
function get_counts($survey_num, $site_num, $wks) {
	global $db;
	
	if ($db->getOne("SELECT count(*) FROM user_tables WHERE table_name='SURVEY_DATA_$survey_num'") < 1) {
		return array();
	}
	
	$sql = <<<SQL_STMT
SELECT count(*) ctr, exposed_control, to_char(participation_date, 'yyyymmdd') 
	FROM survey_data_$survey_num 
	WHERE from_site = ?
		AND exposed_control IN (1,2)
	GROUP BY exposed_control, to_char(participation_date, 'yyyymmdd')
	ORDER BY to_char(participation_date, 'yyyymmdd'), exposed_control
SQL_STMT;

	$res = $db->query($sql, array($site_num));
	$ec_counts = array();
	while (list($ctr, $ec, $dt) = $res->fetchRow(DB_FETCHMODE_ORDERED)) {
		if (2 == $ec) {
			$ec_counts[$dt]['ctrl'] = $ctr;
		} else {
			$ec_counts[$dt]['exp'] = $ctr;
		}
	}
	
	/**
	 * Grab all dates as an assoc array with value zero.
	 * Accumulate survey data in weekly date buckets.
	 * Reverse the weeks order to descending so individual date comparisons are easy (survey dates are in ascending order)
	 */
	
	$week1 = $wks[0];
	
	$wks = array_reverse($wks);
	$t_arr = array_flip($wks);
	
	$s_dates = array();
	foreach ($t_arr as $k => $v) {
		$s_dates[$k]['ctrl'] = 0;
		$s_dates[$k]['exp'] = 0;
	}

	foreach ($ec_counts as $ec_dt => $ec_val) {
		foreach ($s_dates as $s_dt => $v) {
			if ($ec_dt >= $s_dt) {
				if (isset($ec_val['ctrl'])) {
					$s_dates[$s_dt]['ctrl'] += $ec_val['ctrl'];
				}
				if (isset($ec_val['exp'])) {
					$s_dates[$s_dt]['exp'] += $ec_val['exp'];
				}
				break;
			} elseif ($ec_dt < $week1) {
				if (isset($ec_val['ctrl'])) {
					$s_dates[0]['ctrl'] += $ec_val['ctrl'];
				}
				if (isset($ec_val['exp'])) {
					$s_dates[0]['exp'] += $ec_val['exp'];
				}
				break;
			}
		}
	}

	return $s_dates;
}

/**
* Get max exposed-control-any values
* first check sites, then get campaign values if none set up for sites
*/
function get_max_vals($survey_num, $site_num) {
	global $db;
	
	$sql = "SELECT max_exposed, max_control, max_either_ec FROM banners_in_sites WHERE survey_num = ? AND site_num = ? AND aicode=0";
	list($max_exposed, $max_control, $max_either_ec) = $db->getRow($sql, array($survey_num, $site_num), DB_FETCHMODE_ORDERED);
	if (!$max_exposed && $max_exposed != 0 && !$max_control && $max_control != 0 && !$max_either_ec && $max_either_ec != 0) {
		$sql = "SELECT max_exposed, max_control, max_either_ec FROM boom1 WHERE survey_num = ?";
		list($max_exposed, $max_control, $max_either_ec) = $db->getRow($sql, array($survey_num), DB_FETCHMODE_ORDERED);
	}

	$max_exposed = ($max_exposed || $max_exposed === 0) ? $max_exposed : 'n/a';
	$max_control = ($max_control || $max_control === 0) ? $max_control : 'n/a';
	$max_either_ec = ($max_either_ec || $max_either_ec === 0) ? $max_either_ec : 'n/a';

	return array(
		'max_control' => $max_control,
		'max_exposed' => $max_exposed,
		'max_any' => $max_either_ec,
	);
}

/**
 * Recalc by Site
 * 
 * Get start end dates for ctrl/exp/any
 * Calc weeks/days remaining, taking into account partial weeks
 * Get max control/exposed/either
 * Get how many respondents already for ctrl/exp/any
 * Calc ctrl/exp/any respondents still needed
 * Get relevent weeks with Freeze (and if any of them were from partial weeks
 * Subtract frozen week caps from needed respondents and subtract that week from weeks remaining
 * divide needed respondents by number weeks remaining and round up. That is the new cap.
 */
function recalc_site($survey_num, $site_num, $mode = "") {

	global $db, $from_pp;
	
	$err = "";
	
	/**
	 * Don't do recalc if no weekly caps records exist for this survey-site
	 */
	if (!isset($from_pp) && $db->getOne("SELECT count(*) FROM weekly_caps WHERE survey_num = ? AND site_num = ?", array($survey_num, $site_num)) < 1) {
		return;
	}

	// Start-End Dates
	$wks_from_dates = get_weeks($survey_num, $site_num);
	if (isset($wks_from_dates['err'])) {
		return $wks_from_dates['err'];
	}

	
	// All Week Starting Dates
	$wks = get_start_of_week_dates($wks_from_dates['start_date'], $wks_from_dates['end_date']);
	
	// Beginning of this week
	$this_week = get_this_week();

	/**
	 * Check if all weekly caps records exist and if not, create them.
	 */
	create_new_wc_records($survey_num, $site_num, $wks, $wks_from_dates['start_date'], $wks_from_dates['end_date']);
	
	// Get Frozen totals
	/**
	 * Get number frozen days and Frozen totals
	 * Number of days is always the same for Exp/Ctrl/Any
	 */
	$frozen_wks = array();
	$frozen_days = 0;
	$frozen_total = array('any' => 0, 'ctrl' => 0, 'exp' => 0);
	$this_week_frozen = 0;

	$sql = <<<SQL_STMT
SELECT to_char(week_start_date, 'yyyymmdd'), ctrl_cap, exp_cap, any_cap
	FROM weekly_caps
	WHERE survey_num = ? 
		AND site_num = ? 
		AND to_char(week_start_date, 'yyyymmdd') >= ? 
		AND frozen = 1
	ORDER BY week_start_date
SQL_STMT;

	$res = $db->query($sql, array($survey_num, $site_num, $this_week));
	while (list($week_start_date, $ctrl_cap, $exp_cap, $any_cap) = $res->fetchRow(DB_FETCHMODE_ORDERED)) {
		$frozen_wks[] = $week_start_date;
		// Look for partial Frozen weeks
		if ($week_start_date < $wks_from_dates['start_date']) {
			$frozen_days += (7 - get_dow($week_start_date));
		} elseif (get_date_diff($week_start_date, $wks_from_dates['end_date']) < 7) {
			$frozen_days += get_date_diff($week_start_date, $wks_from_dates['end_date']);
		} else {
			$frozen_days += 7;
		}

		if ($ctrl_cap > 0) {
			$frozen_total['ctrl'] += $ctrl_cap;
		}
		if ($exp_cap > 0) {
			$frozen_total['exp'] += $exp_cap;
		}
		if ($any_cap > 0) {
			$frozen_total['any'] += $any_cap;
		}
		if ($week_start_date == $this_week) {
			$this_week_frozen = 1;
		}
	}

	/**
	 * Get remaining nbr_days for Ctrl/Exp/Any
	 */
	$ctrl_start = (get_date_diff($this_week, $wks_from_dates['ctrl_start_date']) > 0) ? $wks_from_dates['ctrl_start_date'] :  $this_week;
	$exp_start = (get_date_diff($this_week, $wks_from_dates['exp_start_date']) > 0) ? $wks_from_dates['exp_start_date'] :  $this_week;
	$any_start = (get_date_diff($this_week, $wks_from_dates['start_date']) > 0) ? $wks_from_dates['start_date'] :  $this_week;

	$ctrl_days_remain = get_date_diff($ctrl_start, $wks_from_dates['ctrl_end_date']);
	if (get_date_diff($this_week, $wks_from_dates['ctrl_start_date']) <= 7) {
		$ctrl_days_remain = $ctrl_days_remain - $frozen_days + 1;
	}

	$exp_days_remain = get_date_diff($exp_start, $wks_from_dates['exp_end_date']);
	if (get_date_diff($this_week, $wks_from_dates['exp_start_date']) <= 7) {
		$exp_days_remain = $exp_days_remain - $frozen_days + 1;
	}

	$any_days_remain = get_date_diff($any_start, $wks_from_dates['end_date']);
	if (get_date_diff($this_week, $wks_from_dates['start_date']) <= 7) {
		$any_days_remain = $any_days_remain - $frozen_days + 1;
	}

	// Get Maximum Ctrl/Exp/Any
	$max_values = get_max_vals($survey_num, $site_num);
	
	// Total respondents to date
	$weekly_counts = get_counts($survey_num, $site_num, $wks);

	/**
	 * If there are linked sites for this host site, we need to add the counts for those sites
	 */
	$weekly_counts = get_linked_sites_counts($survey_num, $site_num, $weekly_counts);

	$ctrl_total = 0;
	$exp_total = 0;
	foreach($weekly_counts as $wc_dt => $wc_vals) {
		$ctrl_total += $wc_vals['ctrl'];
		$exp_total += $wc_vals['exp'];
	}
	$any_total = $ctrl_total + $exp_total;

	// Respondents Still Needed
	$ctrl_needed = ($max_values['max_control'] != 'n/a' && $max_values['max_control'] != 0) ? $max_values['max_control'] - $ctrl_total - $frozen_total['ctrl'] : 0;
	$exp_needed = ($max_values['max_exposed'] != 'n/a' && $max_values['max_exposed'] != 0) ? $max_values['max_exposed'] - $exp_total - $frozen_total['exp'] : 0;
	$any_needed = ($max_values['max_any'] != 'n/a' && $max_values['max_any'] != 0) ? $max_values['max_any'] - $any_total - $frozen_total['any'] : 0;

	/**
	 * Get rollover
	 */
//$mode=''; // set this to manually retest Rollover

	$rollover = ($mode != "manual") ? $db->getOne("SELECT weekly_caps_rollover FROM ai_sites WHERE survey_num = ? AND site_num = ?", array($survey_num, $site_num)) : 0;
	if ($rollover && !$this_week_frozen) {
		$last_week = get_last_week();

		// get count from last week
		$ctrl_count_last_week = 0;
		$exp_count_last_week = 0;
		$any_count_last_week = 0;

		if ($last_week && isset($weekly_counts[$last_week])) {
			$ctrl_count_last_week = $weekly_counts[$last_week]['ctrl'];
			$exp_count_last_week = $weekly_counts[$last_week]['exp'];
			$any_count_last_week = $ctrl_count_last_week + $exp_count_last_week;
		}

		// get cap from last week
		$sql = <<<SQL_STMT
SELECT ctrl_cap, exp_cap, any_cap, frozen
	FROM weekly_caps
	WHERE survey_num = ?
		AND site_num = ?
		AND to_char(week_start_date, 'yyyymmdd') = ?
SQL_STMT;

		/**
		 * Do not do rollover if last week was un-met or if frozen or if current week caps are frozen or zero
		 */
		$caps = $db->getRow($sql, array($survey_num, $site_num, $last_week));
		$ctrl_unmet = (!$caps['FROZEN'] && $caps['CTRL_CAP'] > 0 && ($caps['CTRL_CAP'] > $ctrl_count_last_week)) ? ($caps['CTRL_CAP'] - $ctrl_count_last_week) : 0;
		$exp_unmet = (!$caps['FROZEN'] && $caps['EXP_CAP'] > 0 && ($caps['EXP_CAP'] > $exp_count_last_week)) ? ($caps['EXP_CAP'] - $exp_count_last_week) : 0;
		$any_unmet = (!$caps['FROZEN'] && $caps['ANY_CAP'] > 0 && ($caps['ANY_CAP'] > $any_count_last_week)) ? ($caps['ANY_CAP'] - $any_count_last_week) : 0;

		// subtract un-met from amt_still_needed
		$ctrl_needed = $ctrl_needed - $ctrl_unmet;
		$exp_needed = $exp_needed - $exp_unmet;
		$any_needed = $any_needed - $any_unmet;
	}

	/**
	 * Get Caps per day (no rounding!)
	 */
	$ctrl_per_day = ($ctrl_needed > 0 && $ctrl_days_remain != 0) ? ($ctrl_needed / $ctrl_days_remain) : 0;
	$exp_per_day = ($exp_needed > 0 && $exp_days_remain != 0) ? ($exp_needed / $exp_days_remain) : 0;
	$any_per_day = ($any_needed > 0 && $any_days_remain != 0) ? ($any_needed / $any_days_remain) : 0;

	$days_per_week = get_days_per_week($wks_from_dates, $wks);

	foreach ($days_per_week as $dt => $days) {
		if ($dt < $this_week) {
			continue;
		}
		
		if (in_array($dt, $frozen_wks)) {
			continue;
		}

		$sql = "SELECT ctrl_cap, exp_cap, any_cap FROM weekly_caps	WHERE survey_num = ? AND site_num = ? AND to_char(week_start_date, 'yyyymmdd') = ?";
		list($ctrl_cap, $exp_cap, $any_cap) = $db->getRow($sql, array($survey_num, $site_num, $dt), DB_FETCHMODE_ORDERED);
		
		$ctrl_new_cap = ceil($ctrl_per_day * 7);
		if ($days['ctrl'] < 7) {
			$ctrl_new_cap = ceil((($ctrl_new_cap * $days['ctrl']) / 7));
		}
		if ($ctrl_new_cap == 0 && $ctrl_cap == -1) {
			if ($max_values['max_control'] === 0) {
				$ctrl_new_cap = 0;
			} else {
				$ctrl_new_cap = -1;
			}
		}
		
		$exp_new_cap = ceil($exp_per_day * 7);
		if ($days['exp'] < 7) {
			$exp_new_cap = ceil((($exp_new_cap * $days['exp']) / 7));
		}
		if ($exp_new_cap == 0 && $exp_cap == -1) {
			if ($max_values['max_exposed'] === 0) {
				$exp_new_cap = 0;
			} else {
				$exp_new_cap = -1;
			}
		}

		$any_new_cap = ceil($any_per_day * 7);
		if ($days['any'] < 7) {
			$any_new_cap = ceil((($any_new_cap * $days['any']) / 7));
		}
		if ($any_new_cap == 0 && $any_cap == -1) {
			if ($max_values['max_any'] === 0) {
				$any_new_cap = 0;
			} else {
				$any_new_cap = -1;
			}
		}

		if ($rollover) {
			if ($dt == $this_week) {
				$ctrl_new_cap = ($ctrl_new_cap > 0) ? ($ctrl_new_cap + $ctrl_unmet) : $ctrl_new_cap;
				$exp_new_cap = ($exp_new_cap > 0) ? ($exp_new_cap + $exp_unmet) : $exp_new_cap;
				$any_new_cap = ($any_new_cap > 0) ? ($any_new_cap + $any_unmet) : $any_new_cap;

				$sql = <<<SQL_STMT
UPDATE weekly_caps SET ctrl_cap = ?, exp_cap = ?, any_cap = ?
	WHERE survey_num = ? AND site_num = ? AND to_char(week_start_date, 'yyyymmdd') = ?
SQL_STMT;
			} else {
				$sql = <<<SQL_STMT
UPDATE weekly_caps SET ctrl_cap = ?, exp_cap = ?, any_cap = ?
	WHERE survey_num = ? AND site_num = ? AND to_char(week_start_date, 'yyyymmdd') = ?
SQL_STMT;
			}
		} else {
			$sql = <<<SQL_STMT
UPDATE weekly_caps SET ctrl_cap = ?, exp_cap = ?, any_cap = ?
	WHERE survey_num = ? AND site_num = ? AND to_char(week_start_date, 'yyyymmdd') = ? AND frozen <> 1
SQL_STMT;
		}

//echo $sql;
//var_dump(array($ctrl_new_cap, $exp_new_cap, $any_new_cap, $survey_num, $site_num, $dt));

		$db->query($sql, array($ctrl_new_cap, $exp_new_cap, $any_new_cap, $survey_num, $site_num, $dt));
	}
	
	return $err;
}

/**
 * Recalc for entire Survey
 */
function recalc_survey($survey_num) {
	global $db;
	
	$err = "";
	
	$res = $db->query("SELECT site_num FROM ai_sites WHERE survey_num = ? AND site_num > 1 ORDER BY site_num", array($survey_num));
	while (list($site_num) = $res->fetchRow(DB_FETCHMODE_ORDERED)) {
		$err = recalc_site($survey_num, $site_num);
		if ($err) {
			return $err;
		}
	}
	
	return $err;
}

/**
 * Create new weekly_caps records if they don't exist
 * They might not exist if the user loaded the screen and pressed "Recalc" before pressing Update.
 * They might not exist if the Dates have changed in the campaign since the last recalc.
 */
function create_new_wc_records($survey_num, $site_num, $wks, $start_date, $end_date) {
	global $db;
		
	$this_week = get_this_week();

	$ct = count($wks);
	for ($i=0; $i<$ct; $i++) {
		$dt = $wks[$i];
		
		if ($dt < $this_week) {
			continue;
		}
		
		$days_in_week = get_days_in_week($dt, $start_date, $end_date);

		if ($db->getOne("SELECT count(*) FROM weekly_caps WHERE survey_num = ? AND site_num = ? AND to_char(week_start_date, 'yyyymmdd') = ?", array($survey_num, $site_num, $dt)) < 1) {
			$ts = mktime(0, 0, 0, substr($dt, 4, 2), substr($dt, 6, 2), substr($dt, 0, 4));
			$dt_ora = strtoupper(date('d-M-y', $ts));
			
			$sql = <<<SQL_STMT
	INSERT INTO weekly_caps 
	(survey_num, site_num, week_start_date, ctrl_cap, exp_cap, any_cap, frozen, days_in_week)
	VALUES (?, ?, ?, -1, -1, -1, 0, ?)
SQL_STMT;
			
			$res = $db->query($sql, array($survey_num, $site_num, $dt_ora, $days_in_week));
		}
	}
}

/**
 * Linked sites validation - Each site in the survey can only be used ONCE as a master site or linked site.
 */
function add_updt_validation($survey_num, $master_site, $linked_sites, $all_sites) {
	global $db;

	if ($db->getOne("SELECT COUNT(*) FROM weekly_caps WHERE survey_num = ? AND site_num = ?", array($survey_num, $master_site)) < 1) {
		return "This site has not been set up for Weekly caps. Set up Weekly caps for before sharing caps.";
	}

	if (!$linked_sites) {
		return 'You must select Linked Sites to setup Shared weekly caps.';
	}

	$sql = "SELECT master_site_num, linked_site_num FROM weekly_caps_linked_sites WHERE survey_num = ?";
	$curr_linked_sites = $db->getAssoc($sql, false, array($survey_num), DB_FETCHMODE_ASSOC, true);

	$all_master_sites = array_keys($curr_linked_sites);

	foreach ($linked_sites as $site_num) {
		if ($db->getOne("SELECT COUNT(*) FROM weekly_caps WHERE survey_num = ? AND site_num = ?", array($survey_num, $site_num)) < 1) {
			create_site_weekly_caps($survey_num, $site_num);
		}

		foreach ($curr_linked_sites as $msite => $lsites) {
			if (in_array($site_num, $lsites) && $msite != $master_site) {
				return "Linked site {$all_sites[$site_num]} ($site_num) is already a Linked to another Host site.";
			}
			if (in_array($site_num, $all_master_sites)) {
				return "Linked site {$all_sites[$site_num]} ($site_num) is already being used as a Host site.";
			}
		}
	}

	return false;
}

/**
 * Linked Sites add/update
 * For update, delete all records then re-insert
 * Also, update weekly caps record for each linked site
 */
function add_updt_sites($survey_num, $master_site, $linked_sites) {
	global $db;

	$db->autoCommit(false);

	$this_week = get_this_week();

	$db->query("DELETE FROM weekly_caps_linked_sites WHERE survey_num = ? AND master_site_num = ?", array($survey_num, $master_site));

	foreach ($linked_sites as $site_num) {
		$sql = "INSERT INTO weekly_caps_linked_sites (survey_num, master_site_num, linked_site_num) VALUES (?, ?, ?)";
		$resI = $db->query($sql, array($survey_num, $master_site, $site_num));
		if ($resI !== DB_OK) {
			return "There was a database problem processing Shared weekly caps";
		}

		$sql = <<<SQL_STMT
SELECT COUNT(*) FROM weekly_caps
WHERE survey_num = ? AND
site_num = ? AND
to_char(week_start_date, 'yyyymmdd') >= ?
SQL_STMT;

		if ($db->getOne($sql, array($survey_num, $site_num, $this_week)) > 0) {
			$sql = <<<SQL_STMT
UPDATE weekly_caps
SET exp_cap=-1, ctrl_cap=-1, any_cap=-1, frozen=1
WHERE survey_num = ? AND
site_num = ? AND
to_char(week_start_date, 'yyyymmdd') >= ?
SQL_STMT;

			$resU = $db->query($sql, array($survey_num, $site_num, $this_week));
			if ($resU !== DB_OK || $db->affectedRows() < 1) {
				return "There was a database problem processing Shared weekly caps";
			}
		}
	}

	$db->commit();

	return false;
}

function create_site_weekly_caps($survey_num, $site_num) {
	global $db;

	$this_week = get_this_week();

	// Start-End Dates
	$wks_from_dates = get_weeks($survey_num, $site_num);

	// All weeks
	$wks = get_start_of_week_dates($wks_from_dates['start_date'], $wks_from_dates['end_date']);

	$ct = count($wks);
	for ($i=0; $i < $ct; $i++) {
		if ($wks[$i] < $this_week) {
			continue;
		}

		$days_in_week = get_days_in_week($wks[$i], $wks_from_dates['start_date'], $wks_from_dates['end_date']);

		$ts = mktime(0, 0, 0, substr($wks[$i], 4, 2), substr($wks[$i], 6, 2), substr($wks[$i], 0, 4));
		$dt_ora = strtoupper(date('d-M-y', $ts));

		$sql = <<<SQL_STMT
INSERT INTO weekly_caps
	(survey_num, site_num, week_start_date, ctrl_cap, exp_cap, any_cap, frozen, days_in_week)
	VALUES (?, ?, ?, -1, -1, -1, 0, ?)
SQL_STMT;
		$res = $db->query($sql, array($survey_num, $site_num, $dt_ora, $days_in_week));
	}
}

/**
 * Add linked site counts to host/master site counts
 */
function get_linked_sites_counts($survey_num, $site_num, $host_cts) {
	global $db;

	$weekly_counts_ls = array();
	$sql = "SELECT linked_site_num FROM weekly_caps_linked_sites WHERE survey_num = ? AND master_site_num = ?";
	$res = $db->query($sql, array($survey_num, $site_num));
	while (list($l_site) = $res->fetchRow(DB_FETCHMODE_ORDERED)) {
		$wks_from_dates_ls = get_weeks($survey_num, $l_site);
		if (isset($wks_from_dates_ls['err'])) {
			continue;
		}

		// All Week Starting Dates
		$wks_ls = get_start_of_week_dates($wks_from_dates_ls['start_date'], $wks_from_dates_ls['end_date']);

		$weekly_counts_ls[$l_site] = get_counts($survey_num, $site_num, $wks_ls);
	}

	foreach ($weekly_counts_ls as $lsite => $cts) {
		foreach ($host_cts as $dt => $host_arr) {
			if (isset($cts[$dt])) {
				$host_cts[$dt]['ctrl'] += $cts[$dt]['ctrl'];
				$host_cts[$dt]['exp'] += $cts[$dt]['exp'];
			}
		}
	}

	return $host_cts;

}
