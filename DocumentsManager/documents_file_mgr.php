<?php 
/**
 * documents_file_mgr.php - handles files processing for campaign_docs.php and comm_partner_docs.php
 * Use php OCI functions instead of PEAR when accessing file content because they are easier to work wth BLOBs
 * 
 * @version $Id$
 */

$pi = pathinfo( __FILE__ );
$zip_dir = substr($pi['dirname'], 0 , (strrpos($pi['dirname'], "/"))) . '/adindex3c/doc_zips/';
if (isset($_SERVER['PHP_SERVER_TYPE']) && $_SERVER['PHP_SERVER_TYPE'] != 'PROD') {
	if (!is_dir($zip_dir)) {
		exit("<b>Error: You must create directory $zip_dir with permissions 777 (so apache can write to it) in your installation.</b>");
	}
}

define('ZIP_PATH', $zip_dir);

/**
 * Create a new document entry
 *
 * @param resource $db
 * @param int $id
 * @param int $survey_num
 * @param int $comm_partner_id
 * @param string $project_name
 * @return error message
 */
function save_file($db, $id, $survey_num, $comm_partner_id, $project_name) {
	$OraDB = $db->connection;
	$file_handle = fopen($_FILES["newfile"]["tmp_name"][$id],"rb");
	$file_contents = fread($file_handle,filesize($_FILES["newfile"]["tmp_name"][$id]));
	$ext = strtolower(pathinfo($_FILES["newfile"]["name"][$id], PATHINFO_EXTENSION));
	$name = str_replace(("." . $ext),"",$_FILES["newfile"]["name"][$id]);
	$type = $_FILES["newfile"]["type"][$id];
	
	/**
	 * Check to see if file already exists for warning message
	 */
	$sql = "SELECT count(*) FROM campaign_docs WHERE survey_num = ? AND comm_partner_id = ? AND project_name = ? AND file_name = ? AND file_extension = ?";
	$dup = $db->getOne($sql, array($survey_num, $comm_partner_id, $project_name, $name, $ext));
	
	$sql = <<<SQL_STMT
INSERT INTO campaign_docs 
	(survey_num, comm_partner_id, project_name, dir_id, file_name, file_extension, file_type, file_binary, file_last_updated, delete_flag) 
	VALUES (:survey_num, :comm_partner_id, :project_name, :dir_id, :file_name, :file_extension, :file_type, EMPTY_BLOB(), :file_last_updated, 0) 
	RETURNING file_binary into :file_binary
SQL_STMT;

	$Stmt = oci_parse($OraDB,$sql);
	$Blob = oci_new_descriptor($OraDB,OCI_D_LOB);

	oci_bind_by_name($Stmt, ":survey_num",$survey_num);
	oci_bind_by_name($Stmt, ":comm_partner_id",$comm_partner_id);
	oci_bind_by_name($Stmt, ":project_name",$project_name);
	oci_bind_by_name($Stmt, ":dir_id",$id);
	oci_bind_by_name($Stmt, ":file_name",$name);
	oci_bind_by_name($Stmt, ":file_extension",$ext);
	oci_bind_by_name($Stmt, ":file_type",$type);
	oci_bind_by_name($Stmt, ":file_binary", $Blob, -1, OCI_B_BLOB);
	oci_bind_by_name($Stmt, ":file_last_updated", time());
	oci_execute($Stmt,OCI_DEFAULT);

	$Blob->save($file_contents);
	oci_commit($OraDB);
	$Blob->close() ;

	fclose($file_handle);
	
	if ($dup > 0) {
		return "$name.$ext";
	}
}

/**
 * replace a document entry
 *
 * @param resource $db
 * @param int $id
 */
function replace_file($db, $id) {
	$OraDB = $db->connection;
	$file_handle = fopen($_FILES["rplcfile"]["tmp_name"][$id],"rb");
	$file_contents = fread($file_handle,filesize($_FILES["rplcfile"]["tmp_name"][$id]));
	$ext = strtolower(pathinfo($_FILES["rplcfile"]["name"][$id], PATHINFO_EXTENSION));
	$name = str_replace(("." . $ext),"",$_FILES["rplcfile"]["name"][$id]);
	$type = $_FILES["rplcfile"]["type"][$id];
	
	$sql = <<<SQL_STMT
UPDATE campaign_docs 
	SET file_name = :file_name, file_extension = :file_extension, file_type = :file_type, file_binary = EMPTY_BLOB(), file_last_updated = :file_last_updated
	WHERE doc_id = $id
	RETURNING file_binary into :file_binary
SQL_STMT;

	$Stmt = oci_parse($OraDB,$sql);
	$Blob = oci_new_descriptor($OraDB, OCI_D_LOB);

	oci_bind_by_name($Stmt, ":file_name",$name);
	oci_bind_by_name($Stmt, ":file_extension",$ext);
	oci_bind_by_name($Stmt, ":file_type",$type);
	oci_bind_by_name($Stmt, ":file_binary", $Blob, -1, OCI_B_BLOB);
	oci_bind_by_name($Stmt, ":file_last_updated", time());
	oci_execute($Stmt,OCI_DEFAULT);

	$Blob->save($file_contents);
	oci_commit($OraDB);
	$Blob->close() ;

	fclose($file_handle);
}

/**
 * get a document to send to browser
 *
 * @param resource $db
 * @param int $id
 */
function get_file($db, $id) {
	$sql = "SELECT file_name, file_extension, file_type, file_binary FROM campaign_docs WHERE doc_id = ?";
	$row = $db->getRow($sql, array($id), DB_FETCHMODE_ORDERED);
	
	if (!$row) {
		echo "There was a problem retrieving the file.";
		exit;
	}
	$filename = $row[0] . "." . $row[1];
	header('Content-Type: ' . $row[2]);
    header('Pragma: cache');
	header('Cache-Control: public, must-revalidate, max-age=0');
	header('Connection: close');
	header('Expires: '.date('r', time()+60*60));
	header('Last-Modified: '.date('r', time()));
	header("Content-Disposition: attachment; filename=\"$filename\"");
	print $row[3];
}

/**
 * mark a document as deleted
 *
 * @param resource $db
 * @param int $id
 * @return error message
 */
function delete_file($db, $id) {
	$sql = "UPDATE campaign_docs SET delete_flag=1 WHERE doc_id = ?";
	$db->query($sql, array($id));
	
	if ( $db->affectedRows() < 1) {
		return array("There was a problem deleting the file.");
		exit;
	}
	return array();
}

/**
 * mark a deleted file as active
 *
 * @param resource $db
 * @param int $id
 * @return error message
 */
function undelete_file($db, $id) {
	$sql = "UPDATE campaign_docs SET delete_flag=0 WHERE doc_id = ?";
	$db->query($sql, array($id));
	
	if ( $db->affectedRows() < 1) {
		return array("There was a problem restoring the file.");
		exit;
	}
	return array();
}

/**
 * Bundle up documents in Zip file
 *
 * @param resource $db
 * @param string $id_list
 * @return zip contents
 */
function zipit($db, $id_list) {
	$zip_fname = 'docs_'.date('Y_m_d')."_".mt_rand(1000000,10000000).'.zip';
	$zip = new ZipArchive;
	$res = $zip->open(ZIP_PATH . $zip_fname, ZipArchive::CREATE);
	if ($res !== TRUE) {
		return array("There was a creating a Zip file.");
		exit;
	}

	$sql = "SELECT file_name, file_extension, file_binary FROM campaign_docs WHERE doc_id IN ($id_list)";
	$res = $db->query($sql);
	while (list($file_name, $file_extension, $file_binary) = $res->fetchRow(DB_FETCHMODE_ORDERED)) {
		$zip->addFromString($file_name.".".$file_extension, $file_binary);
	}
	
	$zip->close();
	
	$contents = file_get_contents(ZIP_PATH . $zip_fname);
	header('Content-Type: application/zip');
	header('Pragma: cache');
	header('Cache-Control: public, must-revalidate, max-age=0');
	header('Connection: close');
	header('Expires: '.date('r', time()+60*60));
	header('Last-Modified: '.date('r', time()));
	header("Content-Disposition: attachment; filename=\"$zip_fname\"");
	print $contents;

	unlink(ZIP_PATH . $zip_fname);
}

/**
 * Create a new directory
 *
 * @param resource $db
 * @param string $new_dir
 * @param int $parent_dir
 * @param string $where_claus
 * @param string $where_arr
 * @param int $survey_num
 * @param int $comm_partner_id
 * @param string $proj_name
 * @return error messages
 */
function new_dir($db, $new_dir, $parent_dir, $where_claus, $where_arr, $survey_num, $comm_partner_id, $proj_name) {
	$dir_exists = $db->getOne("SELECT count(*) FROM campaign_docs_dirs WHERE dir_name = ? AND $where_claus", array_merge(array($new_dir), $where_arr));
	if ($dir_exists) {
		return array("The folder already exists under $proj_name.");
	} else {
		$parent_level = $db->getOne("SELECT dir_level FROM campaign_docs_dirs WHERE dir_id = ? AND $where_claus", array_merge(array($parent_dir), $where_arr));
		$dir_id = $db->getOne("SELECT dir_id_seq.nextval FROM dual");
		$sql = "INSERT INTO campaign_docs_dirs (dir_id, dir_name, dir_level, dir_parent, survey_num, comm_partner_id, project_name) VALUES (?, ?, ?, ?, ?, ?, ?)";
		$res = $db->query($sql, array($dir_id, $new_dir, ($parent_level+1), $parent_dir, $survey_num, $comm_partner_id, $proj_name));
	}
	return array();
}

/**
 * Delete an empty directory
 *
 * @param resource $db
 * @param int $id
 * @return error messages
 */
function del_dir($db, $id) {
	/**
	 * Ensure the directory is a childless node because it would be messy if it weren't
	 */
	$dir_child = $db->getOne("SELECT count(*) FROM campaign_docs_dirs WHERE dir_parent = ?", array($id));
	if ($dir_child) {
		return array("There was a problem deleting the non-empty directory.");
		exit;
	}
	$sql = "DELETE FROM  campaign_docs_dirs WHERE dir_id = ?";
	$db->query($sql, array($id));
	
	if ( $db->affectedRows() < 1) {
		return array("There was a problem deleting the directory.");
		exit;
	}
	return array();
	
}

/**
 * Assign an unassigned project to a survey_num
 *
 * @param resource $db
 * @param int $comm_partner_id
 * @param string $proj_name
 * @param string $comm_partner_name
 * @return error messages
 */
function unassign_project($db, $comm_partner_id, $proj_name, $comm_partner_name) {
	$proj_exists = $db->getOne("SELECT count(*) FROM campaign_docs_unassigned WHERE comm_partner_id = ? AND project_name = ?", array($comm_partner_id, $proj_name));
	if ($proj_exists) {
		return array("The document repository already exists for commissioning partner '$comm_partner_name', Project '$proj_name'.");
	}
	
	$sql = "INSERT INTO campaign_docs_unassigned (comm_partner_id, project_name) VALUES (?, ?)";
	$res = $db->query($sql, array($comm_partner_id, $proj_name));
	return array();
}

/**
 * Remove unassigned project
 *
 * @param res $db
 * @param int $comm_partner_id
 * @param string $proj_name
 * @return array
 */
function delete_unassign_project($db, $comm_partner_id, $proj_name) {
	$docs = $db->getOne("SELECT count(*) FROM campaign_docs WHERE comm_partner_id = ? AND project_name = ?", array($comm_partner_id, $proj_name));
	if ($docs) {
		return array("The document repository for Project $proj_name has documents - cannot delete without removing the documents.");
	}
	echo 'asddd';
	$sql = "DELETE FROM campaign_docs_unassigned WHERE comm_partner_id = ? AND project_name = ?";
	$db->query($sql, array($comm_partner_id, $proj_name));
	return array();
}

/**
 * Reassing unassigned project to a survey_num
 *
 * @param res $db
 * @param int $comm_partner_id
 * @param string $proj_name
 * @param int $survey_num
 * @param array $default_dirs
 * @return array
 */
function reassign($db, $comm_partner_id, $proj_name, $survey_num, $default_dirs) {
	$sql = "SELECT ainame FROM boom1 WHERE survey_num = ?";
	$ainame = $db->getOne($sql, array($survey_num));
	
	/**
	 * Check if they already created the default directories for the survey and if so, transfer from the unassigned project;
	 * Otherwise, just change the survey_num and project_name in the campaign_docs and campaign_docs_dirs records.
	 */
	$def_dirs = "('" . implode("','",$default_dirs) . "')";
	$sql = "SELECT dir_name, dir_id FROM campaign_docs_dirs WHERE dir_name IN $def_dirs AND survey_num = ?";
	$dir_array = $db->getAssoc($sql, false, array($survey_num));
	if (!empty($dir_array)) {
		$sql = <<<SQL_STMT
SELECT cd.doc_id, cd.dir_id, cdd.dir_name 
FROM campaign_docs cd, campaign_docs_dirs cdd 
WHERE cd.comm_partner_id = ? 
	AND cd.project_name = ? 
	AND cd.dir_id = cdd.dir_id
	AND cdd.dir_name IN $def_dirs
SQL_STMT;
		$res = $db->query($sql, array($comm_partner_id, $proj_name));
		while (list($doc_id, $dir_id, $dir_name) = $res->fetchRow(DB_FETCHMODE_ORDERED)) {
			$new_dir_id = (isset($dir_array[$dir_name])) ? $dir_array[$dir_name] : $dir_id;
			$sql = "UPDATE campaign_docs SET dir_id = ?, survey_num = ?, project_name = ? WHERE comm_partner_id = ? AND project_name = ?";
			$db->query($sql, array($new_dir_id, $survey_num, $ainame, $comm_partner_id, $proj_name));
		}

		$sql = "UPDATE campaign_docs_dirs SET survey_num = ?, project_name = ? WHERE comm_partner_id = ? AND project_name = ? AND dir_name NOT IN $def_dirs";
		$db->query($sql, array($survey_num, $ainame, $comm_partner_id, $proj_name));
	} else {
		$sql = "UPDATE campaign_docs SET survey_num = ?, project_name = ? WHERE comm_partner_id = ? AND project_name = ?";
		$db->query($sql, array($survey_num, $ainame, $comm_partner_id, $proj_name));
		$sql = "UPDATE campaign_docs_dirs SET survey_num = ?, project_name = ? WHERE comm_partner_id = ? AND project_name = ? ";
		$db->query($sql, array($survey_num, $ainame, $comm_partner_id, $proj_name));
	}
	
	$sql = "DELETE FROM campaign_docs_unassigned WHERE comm_partner_id = ? AND project_name = ?";
	$db->query($sql, array($comm_partner_id, $proj_name));
	return array();
}

/**
 * Create the standard directories for every project
 *
 * @param resource $db
 * @param string $default_dirs
 * @param int $survey_num
 * @param int $comm_partner_id
 * @param string $proj_name
 */
function create_standard_dirs($db, $default_dirs, $survey_num, $comm_partner_id, $proj_name) {
 	foreach ($default_dirs as $dir_name) {
		$dir_id = $db->getOne("SELECT dir_id_seq.nextval FROM dual");
		$sql = "INSERT INTO campaign_docs_dirs (dir_id, dir_name, dir_level, dir_parent, survey_num, comm_partner_id, project_name) VALUES(?, ?, ?, ?, ?, ?, ?)";
		$db->query($sql, array($dir_id, $dir_name, "1", "0", $survey_num, $comm_partner_id, $proj_name));
	}
}

/**
 * Check if partner is already in comm_partners and add it if not
 */
function partner_to_comm($partner_num) {
	global $db;

	$partner_name = $db->getOne("SELECT partner_name FROM ai_partners WHERE partner_num = ?", array($partner_num));
	if (!$partner_name) {
		die("Error retrieving Partner information.");
	}

	$comm_partner_id = $db->getOne("SELECT comm_partner_id FROM comm_partners WHERE comm_partner_name = ?", array($partner_name));
	if ($comm_partner_id) {
		return array("comm_partner_id" => $comm_partner_id, "comm_partner_name" => $partner_name);
	}

	/**
	 * Add new comm partner
	 */
	$new_comm_partner_id = $db->getOne("SELECT comm_partner_id_seq.nextval FROM dual");
	$sql = "INSERT INTO comm_partners (comm_partner_id, comm_partner_name, location) VALUES (?, ?, ?)";
	$db->query($sql, array($new_comm_partner_id, $partner_name, "New York"));

	return array("comm_partner_id" => $new_comm_partner_id, "comm_partner_name" => $partner_name);
}

/**
 * Show directories
 * This assumes /adindex3c/css/campaign_docs.css to work
 *
 * @param string $where_claus
 * @param array $where_arr
 * @param array $default_dirs
 */
function show_dirs($db, $where_claus, $where_arr, $default_dirs) {
	$name_arr = array();
	$parent_arr = array();
	$level_arr = array();
	$dirs = array();
	$dir_del_arr = array();

	/**
	 * Oracle hierarchical query
	 */
	$sql = <<<SQL_STMT
SELECT dir_id, dir_name, dir_level, dir_parent FROM campaign_docs_dirs WHERE $where_claus 
START WITH dir_parent = 0 
CONNECT BY NOCYCLE PRIOR dir_id = dir_parent
ORDER SIBLINGS BY dir_name
SQL_STMT;
	$res = $db->query($sql, $where_arr);
	while (list($dir_id, $dir_name, $dir_level, $dir_parent) = $res->fetchRow(DB_FETCHMODE_ORDERED)) {
		$dirs[] = $dir_id;
		$name_arr[$dir_id] = $dir_name;
		$parent_arr[$dir_id] = $dir_parent;
		$level_arr[$dir_id] = $dir_level;
	}

	$prev_lvl = 0;
	foreach ($dirs as $id) {
		$name = $name_arr[$id];
		$lvl = $level_arr[$id];
		$par_id =  $parent_arr[$id];
		
		/**
		 * We can delete nodes if they aren't default directories or parent nodes
		 */
		if (!in_array($name_arr[$id], $default_dirs)) {
			$dir_del_arr[$id] = $par_id;
		}
		if (isset($dir_del_arr[$par_id])) {
			unset($dir_del_arr[$par_id]);
		}
		
		if ($lvl == $prev_lvl) {
			echo  "</div>\n";
		}elseif ($lvl < $prev_lvl) {
			echo  "</div>\n" . str_repeat("</div>\n", ($prev_lvl - $lvl));
			$prev_lvl = $lvl;
		} else {
			$prev_lvl = $lvl;
		}
		
		echo <<<PRINT_HTML
<div class="dir_block">
<div>
	<span style="display:inline" id="dir_c_$id" name="dir_c_$id" class="plus_minus" onclick="exp_coll('c', $id, '')">
		<img src="/images/docs_dir_col.png" class="icons"/>
		<b>$name</b>
	</span>
	
	<span style="display:none" id="dir_e_$id" name="dir_e_$id" class="plus_minus" onclick="exp_coll('e', $id, '')">
		<img src="/images/docs_dir_exp.png" class="icons"/>
		<b>$name</b>
	</span>
	
	&nbsp;<span id="dir_total_$id" class="smlspan"> </span>
</div>

<div id="dir_act_$id" name="dir_act_$id" class="new_dir" style="display:inline">
	<img src="/images/docs_new_doc.png" id="icon_doc_new_$id" name="icon_doc_new_$id" class="plus_minus" onclick="d.getElementById('doc_new_$id').style.display='inline';d.getElementById('icon_doc_new_$id').style.display='none';"/>
	<div id="doc_new_$id" name="doc_new_$id" class="doc_new">
		New Document
		<input class="ipt_file" size="50" type="file" name="newfile[$id]"/>
		<input class="btn sml" type="button" value="Upload" onclick="subber('dir', $id, $id);"/>
	</div>
</div>

<div id="docs_$id" name="docs_$id" class="doc_block">

PRINT_HTML;

		echo show_docs($db, $id, $lvl, $where_claus, $where_arr, $dir_del_arr);
		echo "</div>\n";
			
		/**
		 * Reached the end of a branch so print ending divs
		 */
		if ("1" == $lvl && $lvl < $prev_lvl) {
			for ($k=$prev_lvl; $k>0; $k--) {
				echo  "</div>\n";
			}
		}
	}
	
	echo "<script type=\"text/javascript\">\n";
	foreach ($dir_del_arr as $id => $par_id) {
		echo <<<PRINT_HTML
d.getElementById('dir_total_$id').innerHTML = '<img src="/images/docs_delete_dir.png" id="del_dir_$id" name="del_dir_$id" alt="Delete Folder" class="plus_minus" onclick="subber(\'del_dir\', $id, $par_id);"/>';

PRINT_HTML;
	}
	echo "</script>\n";
}

/**
 * Show documents within a directory
 *
 * @param resource $db
 * @param int $id
 * @param int $lvl
 * @param string $where_claus
 * @param string $where_arr
 * @param array $dir_del_arr
 * @return html string
 */
function show_docs($db, $id, $lvl, $where_claus, $where_arr, &$dir_del_arr) {
	global $ttl;
	
	$icons = array(
		'dir_exp' => 'docs_dir_exp.png',
		'dir_col' => 'docs_dir_col.png',
		'doc' => 'docs_doc.png',
		'docx' => 'docs_doc.png',
		'xls' => 'docs_xls.png',
		'xlsx' => 'docs_xls.png',
		'ppt' => 'docs_ppt.png',
		'pptx' => 'docs_ppt.png',
		'gif' => 'docs_picture.png',
		'jpg' => 'docs_picture.png',
		'jpeg' => 'docs_picture.png',
		'png' => 'docs_picture.png',
		'bmp' => 'docs_picture.png',
		'other' => 'docs_file.png',
		'avi' => 'docs_video.png',
		'flv' => 'docs_video.png',
		'mpg' => 'docs_video.png',
		'mpeg' => 'docs_video.png',
		'wmv' => 'docs_video.png',
		'htm' => 'docs_html.png',
		'html' => 'docs_html.png',
		'js' => 'docs_script.png',
		'mp3' => 'docs_music.png',
		'mp4' => 'docs_music.png',
		'wma' => 'docs_music.png',
		'pdf' => 'docs_pdf.png',
		'txt' => 'docs_txt.png',
		'csv' => 'docs_txt.png',
		'rtf' => 'docs_txt.png',
		'zip' => 'docs_zip.png',
		'rar' => 'docs_zip.png',
		'7z' => 'docs_zip.png',
		'fla' => 'docs_flash.png',
		'swf' => 'docs_flash.png',
	);

	/**
	 * Print all Docs in a directory
	 */
	$sql = "SELECT doc_id, file_name, file_extension, file_last_updated FROM campaign_docs WHERE $where_claus AND delete_flag = 0 AND dir_id = ? ORDER BY UPPER(file_name)";
	$res = $db->query($sql, array_merge($where_arr, array($id)));

	$ctr = 0;
	$html_str = "";
	$docs = array();

	while (list($doc_id, $doc_name, $ext, $file_last_updated) = $res->fetchRow(DB_FETCHMODE_ORDERED)) {
		$docs[] = $doc_id;
		
		if (!$ctr) {
			/**
			 * Print filelist header
			 */
			$html_str .= <<<PRINT_HTML
	<table class="doclist">
	<tr>
		<td width="45%">Document (click to download)</td>
		<td>Last Updated</td>
		<td>Replace</td>
		<td>Delete</td>
		<td style="width:100px;">Zip List 
			<span style="display:inline" id="zip_all_$id" name="zip_all_$id"><span class="zip_toggle" href="#" onclick="zip_toggle('all', $id);">(All)</span></span>
			<span style="display:none" id="zip_none_$id" name="zip_all_$id"><span class="zip_toggle" href="#" onclick="zip_toggle('none', $id);">(None)</span></span>
		</td>
	</tr>		
PRINT_HTML;
		}
		
		$file_last_updated = date('m/d/y', $file_last_updated) ." at " . date('h:i A', $file_last_updated);
		
		$icon = (isset($icons[$ext])) ? $icons[$ext] : $icons['other'];
	
		$html_str .= <<<PRINT_HTML
	<tr>
		<th style="width:400px;"><img src="/images/$icon" class="icons"/> <a href="#" onclick="get_doc($doc_id, 0);" class="filename"><b>$doc_name.$ext</b></a></th>
		<td>$file_last_updated</td>
		<td>
			<img src="/images/docs_new_doc.png" id="icon_doc_rplc_$doc_id" name="icon_doc_rplc_$doc_id" class="plus_minus" onclick="d.getElementById('doc_rplc_$doc_id').style.display='inline';d.getElementById('icon_doc_rplc_$doc_id').style.display='none';"/>
			<div id="doc_rplc_$doc_id" name="doc_rplc_$doc_id" class="doc_new">
				<input class="ipt_file" size="35" type="file" name="rplcfile[$doc_id]"/>
				<input class="btn sml" type="button" value="Replace" onclick="subber('doc', $doc_id, $id);"/>
			</div>
			
		</td>
		<td style="padding:0px;vertical-align:middle;text-align:center;"><img src="/images/trash.gif" title="Remove" class="plus_minus" onclick="subber('del', $doc_id, $id);"/></td>
		<td><input name="zip_$doc_id" id="doc_zip_{$id}_{$doc_id}" value="1" type="checkbox"></td>
	</tr>
PRINT_HTML;

		$ctr++;
		$ttl++;
	}

	$html_str .= "<input type=\"hidden\" name=\"doc_ctr_$id\" value=\"$ctr\"/>\n";
	/**
	 * Print closing tags of filelist if there were any docs in the directory
	 */
	if ($ctr) {	
		$html_str .= "</table>\n";
	}

	if ($ctr) {	
		$html_str .= <<<PRINT_HTML
<script type="text/javascript">
d.getElementById('dir_total_$id').innerHTML = '($ctr Documents)';

PRINT_HTML;

		$doc_ctr = count($docs);
		for ($i=0; $i<$doc_ctr; $i++) {
			$html_str .= "d.getElementById(\"doc_rplc_{$docs[$i]}\").style.display='none';\n";
		}
		
		$html_str .= "</script>\n";
		
		if (isset($dir_del_arr[$id])) {
			unset($dir_del_arr[$id]);
		}
	}
	
	return $html_str;
}
