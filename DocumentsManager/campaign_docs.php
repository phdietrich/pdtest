<?php
/**
 * campaign_docs.php - document management screen for campaign documents
 * 
 * @version $Id$
 */
require_once( "adindex3c/constants.php" );
require_once( "common/db/db.php" );
require_once( "adindex3c/documents_file_mgr.php" );

include 'layout.php';

$survey_num = (isset($_REQUEST['survey_num'])) ? (int)$_REQUEST['survey_num'] : 0;
$proj_name = (isset($_REQUEST['proj_name'])) ? $_REQUEST['proj_name'] : '';
$comm_partner_id = (isset($_REQUEST['comm_partner_id'])) ? $_REQUEST['comm_partner_id'] : 0;
$last_dir_opened = (isset( $_REQUEST['last_dir_id'])) ?  $_REQUEST['last_dir_id'] : "";

if (!$survey_num && (!$comm_partner_id || !$proj_name)) {
	echo "Required parameters are missing!";
	exit;
}

$err = array();
$msg = (isset($_GET['msg']) && "ua" == $_GET['msg']) ? "The project was Reassigned" : "";
$note = "";
$qs = ($msg) ? str_replace("&msg=ua", "", $_SERVER['QUERY_STRING']) : $_SERVER['QUERY_STRING'];

$comm_partner_error = 0;

$reassign = "";

if ($survey_num) {
	$sql = "SELECT b.ainame, c.comm_partner_id, c.comm_partner_name, c.location FROM boom1 b, comm_partners c WHERE b.survey_num = ? AND b.comm_partner_id=c.comm_partner_id";
	list($project_name, $comm_partner_id, $comm_partner_name, $location) = $db->getRow($sql, array($survey_num), DB_FETCHMODE_ORDERED);
	if ("Default" == $comm_partner_name || "" == $comm_partner_name) {
		$partner_num = $db->getOne("SELECT partner_num FROM boom1 WHERE survey_num = ?", array($survey_num));
		if (!$partner_num) {
			$comm_partner_id = $db->getOne("SELECT comm_partner_id FROM comm_partners WHERE comm_partner_name = 'TBD'");
			$comm_partner_name = "TBD";
			$location = "New York";
			$db->query("UPDATE boom1 SET comm_partner_id = ? WHERE survey_num = ?", array($comm_partner_id, $survey_num));
		} else {
			$cp_info = partner_to_comm($partner_num);
			$comm_partner_id = $cp_info['comm_partner_id'];
			$comm_partner_name = $cp_info['comm_partner_name'];
			$location = "New York";
		}
		$db->query("UPDATE boom1 SET comm_partner_id = ? WHERE survey_num = ?", array($comm_partner_id, $survey_num));
	} else {
		$proj_name = $project_name;
	}
	$where_claus = "survey_num = ?";
	$where_arr = array($survey_num);
} else {
	$sql = "SELECT comm_partner_name, location FROM comm_partners WHERE comm_partner_id = ?";
	list($comm_partner_name, $location) = $db->getRow($sql, array($comm_partner_id), DB_FETCHMODE_ORDERED);
	$where_claus = "(comm_partner_id = ? AND project_name = ?)";
	$where_arr = array($comm_partner_id, $proj_name);
	$survey_num = 0;
}

/**
 * Check if first time for this survey/comm_partner/project and if so, create default directories.
 *
 * Also, check if comm_partner_id has changed and update docs if so
 */
if (!$comm_partner_error) {
	$default_dirs = array('Analysis Reports', 'Contact Info', 'Media Plan', 'Pricing Approvals', 'Proposals', 'Recruitment Plan', 'Survey');

	/**
	 * Check if there are any dirs for this survey as we don't want to create duplicates
	 */
	$cp_id = $db->getOne("SELECT distinct comm_partner_id FROM campaign_docs_dirs WHERE survey_num = ?", array($survey_num));
	if (!$cp_id) {
		$dirs = $db->getOne("SELECT count(*) FROM campaign_docs_dirs WHERE comm_partner_id = ? AND project_name = ?", array($comm_partner_id, $proj_name));
	}elseif ($cp_id != $comm_partner_id ) {	// check if comm_partner_id changed
		$db->query("UPDATE campaign_docs SET comm_partner_id = ? WHERE survey_num = ?", array($comm_partner_id, $survey_num));
		$db->query("UPDATE campaign_docs_dirs SET comm_partner_id = ? WHERE survey_num = ?", array($comm_partner_id, $survey_num));
	}

	if (!$cp_id && !$dirs) {
		create_standard_dirs($db, $default_dirs, $survey_num, $comm_partner_id, $proj_name);
	}
}

if (isset($_POST['act'])) {
	switch ($_POST['act']) {
	case "err" :
		if (isset($_POST['err_msg'])) {
			$err[] = $_POST['err_msg'];
		}
		break;
	case "dir" :
		if (!isset($_POST['upd_id']) || !$_FILES['newfile']['name'][$_POST['upd_id']]) {
			$err[] = "You must choose a file to upload";
			break;
		}
		$dup = save_file($db, $_POST['upd_id'], $survey_num, $comm_partner_id, $proj_name);
		if ($dup) {
			$note  = "&quot;$dup&quot; was uploaded but another file with the same name already exists for this Campaign/Project.";
		} else {
			$msg = "The file was Uploaded.";
		}
		break;
		
	case "doc" :
		if (!isset($_POST['upd_id']) || !$_FILES['rplcfile']['name'][$_POST['upd_id']]) {
			$err[] = "You must choose a file to replace";
			break;
		}
		replace_file($db, $_POST['upd_id']);
		$msg = 'The file was Replaced.';
		break;
		
	case "del" :
		$err = delete_file($db, $_POST['upd_id']);
		if (empty($err)) {
			$msg = 'The file was Removed. <span onclick="subber(\'undo\', '.$_POST['upd_id'].', '.$last_dir_opened.')" class="psuedolink">Click to UNDO</span>';
		}
		
		break;
		
	case "undo" :
		$err = undelete_file($db, $_POST['upd_id']);
		if (empty($err)) {
			$msg = 'The file was Restored.';
		}
		
		break;
	case "new_dir":
		if (!isset($_POST['new_dir']) || !$_POST['new_dir']) {
			$err[] = "You must enter a folder name";
			break;
		}
		if (!isset($_POST['parent_dir']) || $_POST['parent_dir'] == "") {
			$err[] = "You must select a parent folder";
			break;
		}
		$err = new_dir($db, trim($_POST['new_dir']), $_POST['parent_dir'], $where_claus, $where_arr, $survey_num, $comm_partner_id, $proj_name);
		if (empty($err)) {
			$msg = 'The new directory was added.';
		}
		
		break;
	case "del_dir":
		$err = del_dir($db, $_POST['upd_id']);
		if (empty($err)) {
			$msg = 'The directory was deleted.';
		}
		break;
	case "reass":
		if (!isset($_POST['reassign'])) {
			$err[] = "You must Enter a survey Number to reassign this project.";
			break;
		}
		if ($db->getOne("SELECT count(*) FROM boom1 WHERE survey_num = ?", array((int)$_POST['reassign'])) < 1) {
			$err[] = "The survey number you entered does not exist.";
			break;
		}
		if ($db->getOne("SELECT count(*) FROM boom1 WHERE survey_num = ? and comm_partner_id = ?", array((int)$_POST['reassign'], $comm_partner_id)) < 1) {
			$err[] = "Cannot reassign - the Commissioning Partner for survey ".(int)$_POST['reassign']." is not the Commissioning Partner for the unassigned project.";
			break;
		}
		$err = reassign($db, $comm_partner_id, $proj_name, trim($_POST['reassign']), $default_dirs);
		if (empty($err)) {
			header("Location: /adindex3c/campaign_docs.php?survey_num=".trim($_POST['reassign'])."&msg=ua");
			exit;
		}
		break;
	}
}
 
$params = array(
    // Title dislayed in the header and browser window name
    // required
    'title' => "Campaign Documentation",
    'css' => array(
        '/css/dl_basic.css',
        '/css/dl_forms.css',
        '/css/colours.css',
        '/adindex3c/css/campaign_docs.css',
    ),
);

$layout->view('header', $params);

require_once("common/tooltip/tooltip.php"); 

$hdr = <<<PRINT_HTML
<div class="hdr_action">
<div class="hdr3">Commissioning Partner: <b>$comm_partner_name</b> <span class="nrml">($location)</span> &nbsp;&nbsp;&nbsp;&nbsp; Campaign: <b>$proj_name</b></div>
<div class="clearfloat"></div>
$reassign
</div>
PRINT_HTML;



$comm_partner_directories = "";
$sql = "SELECT dir_id, dir_name FROM campaign_docs_dirs WHERE $where_claus ORDER BY dir_id";
$res = $db->query($sql, $where_arr);

$comm_partner_directories = array();
while (list($dir_id, $dir_name) = $res->fetchRow(DB_FETCHMODE_ORDERED)) {
	$comm_partner_directories[$dir_id] = $dir_name;
}
?>
<script type="text/javascript">
var d=document,w=window;
var dirs = <?=json_encode($comm_partner_directories)?>;

// Expand-Collapse individual or all directories
function exp_coll(e_c, id, last_dir) {
	switch (e_c) {
	case 'e':
		d.getElementById("dir_e_"+id).style.display='none';
		d.getElementById("dir_c_"+id).style.display='';
		d.getElementById("dir_act_"+id).style.display='';
		if (d.forms['update'].elements["doc_ctr_"+id].value > 0) {
			d.getElementById("docs_"+id).style.display='';
		}
		break;
	case 'c':
		d.getElementById("dir_e_"+id).style.display='';
		d.getElementById("dir_c_"+id).style.display='none';
		d.getElementById("dir_act_"+id).style.display='none';
		if (d.forms['update'].elements["doc_ctr_"+id].value > 0) {
			d.getElementById("docs_"+id).style.display='none';
		}
		break;
	case 'e_all':
		d.getElementById("e_all").style.display='none';
		d.getElementById("c_all").style.display='';
		for (var key in dirs) {
			if (dirs.hasOwnProperty(key)) {
				d.getElementById("dir_e_"+key).style.display='none';
				d.getElementById("dir_c_"+key).style.display='';
				d.getElementById("dir_act_"+key).style.display='';
				if (d.forms['update'].elements["doc_ctr_"+key].value > 0) {	// don't expand docs section if there aren't any docs
					d.getElementById("docs_"+key).style.display='';
				}
			}
		}
		break;
	case 'c_all':
		d.getElementById("e_all").style.display='';
		d.getElementById("c_all").style.display='none';
		for (var key in dirs) {
			if (dirs.hasOwnProperty(key)) {
				if (last_dir != key) {	// keep directory acted upon open.
					d.getElementById("dir_e_"+key).style.display='';
					d.getElementById("dir_c_"+key).style.display='none';
					d.getElementById("dir_act_"+key).style.display='none';
					d.getElementById("docs_"+key).style.display='none';
					d.getElementById("doc_new_"+key).style.display='none';
				} else {
					d.getElementById("dir_e_"+key).style.display='none';
					d.getElementById("dir_c_"+key).style.display='';
					d.getElementById("doc_new_"+key).style.display='none';
				}
			}
		}
		break;
	}
}

// Open popup to load select document/s
function get_doc(id, zip) {
	var param = (zip) ? "id_list=" + id : "id=" + id;
	fwin = window.open("/adindex3c/documents_display.php?"+param,"", "height=300,width=300,top=100,left=100,resizable=no");
}

// update hidden form fields and submit form
function subber(typ, id, dir_id) {
	if ("new_dir" == typ) {
		d.forms['sub_folder_form'].elements['act'].value = typ;
		d.forms['sub_folder_form'].submit();
		return;
	}
	
	d.forms['update'].elements['act'].value = typ;
	d.forms['update'].elements['upd_id'].value = id;
	d.forms['update'].elements['last_dir_id'].value = dir_id;
	d.forms['update'].submit();
}

// Collect all documents checked for ZIP download and call get_doc function
function zipper() {
	var tag_list = d.getElementsByTagName("input");
	var cb_arr = [];
	var idx = 0;
	for (var i=0; i<tag_list.length; i++) {
		if ('checkbox' == tag_list[i].type && tag_list[i].checked == true) {
			cb_arr[idx] = tag_list[i].name.substr(4, (tag_list[i].name.length-1));
			idx++;
		}
	}
	var cb_list = cb_arr.join(",");
	if (cb_list.length < 1) {
		d.forms['update'].elements['act'].value = 'err';
		d.forms['update'].elements['upd_id'].value = '';
		d.forms['update'].elements['last_dir_id'].value = '';
		d.forms['update'].elements['err_msg'].value = 'You must check at least one file to include in a zip archive.';
		d.forms['update'].submit();
	}
	if (d.forms['update'].elements['err_msg'].value == "") {
		if (d.getElementById('err_msg') != null) {
			d.getElementById('err_msg').style.display = "none";
		}
		get_doc(cb_list, 1);
	}
}

// Toggle all/none zip list
function zip_toggle(a_n, id) {
	var zip_id, doc_id, t_f;
	
	switch (a_n) {
	case 'all':
		d.getElementById("zip_all_"+id).style.display='none';
		d.getElementById("zip_none_"+id).style.display='';
		t_f = true;
		break;
	case 'none':
		d.getElementById("zip_all_"+id).style.display='';
		d.getElementById("zip_none_"+id).style.display='none';
		t_f = false;
		break;
	}
	
	var tag_list = d.getElementsByTagName("input");
	for (var i=0; i<tag_list.length; i++) {
		if ('checkbox' == tag_list[i].type && tag_list[i].id.indexOf("doc_zip_"+id+"_") >= 0) {
			zip_id = tag_list[i].id;
			doc_id = zip_id.substr(zip_id.lastIndexOf("_") + 1), zip_id.length;
			d.forms['update'].elements['zip_' + doc_id].checked = t_f;
		}
	}
}

function load_dialog(action, params) {
	var wWidth = get_window_width();
	var dialogId = "sub_folder_dialog";

	d.getElementById(dialogId).style.display = "inline";
	d.getElementById(dialogId).style.visibility = "visible";
	var dialogWidth = parseInt(d.getElementById(dialogId).offsetWidth);
	d.getElementById(dialogId).style.left = Math.round(wWidth/2) - Math.round(dialogWidth/2) + 'px';
	d.getElementById(dialogId).style.top = document.documentElement.scrollTop + 180 + 'px';
}

function get_window_width() {
	if (self.innerWidth) {
		// Reasonable browsers
		return self.innerWidth;
	} else if (d.documentElement && d.documentElement.clientHeight > 0) {
		// IE
		return d.documentElement.clientWidth;
	}
	
	return 800;
}

</script>

<form method="post" name="update" action="campaign_docs.php?<?=$qs?>" enctype="multipart/form-data">
<input type="hidden" name="act" value=""/>
<input type="hidden" name="upd_id" value=""/>
<input type="hidden" name="last_dir_id" value=""/>
<input type="hidden" name="err_msg" value=""/>
<?php
if ($err) {
	echo '<div id="err_msg" class="message error"><span class="type">Error</span>'.implode("<li>", $err).'</div>';
}
if ($msg) {
	echo '<div class="message success"><span class="type">Success</span>'.$msg.'</div>';
}
if ($note) {
	echo '<div class="message warning"><span class="type">Note</span>'.$note.'</div>';
}

echo $hdr;

if ($comm_partner_error) {
	$layout->view('footer');
	exit;
}

echo <<<PRINT_HTML
<div class="exp_coll_all">
<span style="display:inline" id="e_all" name="e_all"><span class="ec_all" onclick="exp_coll('e_all', 0, '')">&#43; Expand All</span></span>
<span style="display:none" id="c_all" name="c_all"><span class="ec_all" onclick="exp_coll('c_all', 0, '')">&#45; Collapse All</span></span>
</div>
<div><span class="reset_btn" onclick="load_dialog('new_dir','');">Add new Folder</span></div>
<div><span class="reset_btn" onclick="d.forms['update'].reset();">Clear Forms Fields</span></div>

<table id="filetree">
<tr>
<td>
PRINT_HTML;

/**
 * Get Directories
 */
$ttl = 0;

show_dirs($db, $where_claus, $where_arr, $default_dirs);

if ($ttl) {		// print zip list download button
	$zipper = <<<PRINT_HTML
<input type="button" class="btn normal" value="Download Zip List" onclick="zipper();"/>
PRINT_HTML;
} else {
	$zipper = "";
}

echo <<<PRINT_HTML
</td>
</tr>
<tr>
<td><div class="zip_btn">$zipper</div></td>
</tr>
</table>
</form>

<script type="text/javascript">exp_coll('c_all', 0, '$last_dir_opened');</script>
PRINT_HTML;


$sorted_dirs = $comm_partner_directories;
asort($sorted_dirs);

$selbox = <<<PRINT_HTML
<select name="parent_dir">
<option value=''>--Select a Parent Folder--</option>
<option value='0'>Top Level</option>

PRINT_HTML;

foreach ($sorted_dirs as $id => $name) {
	$selbox .= "<option value=\"$id\">$name</option>\n";
}

$selbox .= "</select>\n";
?>
<!-- Sub-Folder dialog box -->
<div id="sub_folder_dialog">
<form name="sub_folder_form" action="campaign_docs.php?<?=$qs?>" method="post">
<input type="hidden" name="act" value=""/>
<table class="sf_dialog">
<tr>
	<th colspan="2">Add new Folder</th>
</tr>
<tr>
	<td class="ralign">Folder Name:</td>
	<td><input type="text" size="50" maxlength="75" name="new_dir" value=""/></td>
</tr>
<tr>
	<td class="ralign">Parent Folder:
	</td>
	<td><?=$selbox?></td>
</tr>

<tr>
	<td colspan=2 class="sect_divider"></td>
</tr>
<tr>
	<td><input type="button" class="btn normal" value="Add Folder" onclick="subber('new_dir', '', '');"/></td>
	<td class="ralign"><a href="#" onClick="document.getElementById('sub_folder_dialog').style.visibility='hidden'">Close</a></td>
</tr>
</table>
</form>
</div>

<?php

$layout->view('footer');
