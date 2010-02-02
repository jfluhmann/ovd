<?php
/**
 * Copyright (C) 2009-2010 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **/
require_once(dirname(__FILE__).'/includes/core.inc.php');
require_once(dirname(__FILE__).'/includes/page_template.php');

if (! checkAuthorization('viewApplications'))
	redirect();

$types = array('linux' => 'linux' , 'windows' => 'windows', 'weblink' => _('Web'));
$regular_types = array('linux', 'windows');

$applicationDB = ApplicationDB::getInstance();

if (isset($_REQUEST['action'])) {
	if ($_REQUEST['action'] == 'manage') {
		if (isset($_REQUEST['id']))
			show_manage($_REQUEST['id'], $applicationDB);
	}

	if (! checkAuthorization('manageApplications'))
			redirect();

	if ($_REQUEST['action'] == 'modify' && $applicationDB->isWriteable()) {
		if (isset($_REQUEST['id'])) {
			modify_application($applicationDB, $_REQUEST['id'], $_POST, $_FILES);
			redirect();
		}
	}
	elseif ($_REQUEST['action'] == 'add' && $applicationDB->isWriteable()) {
		add_application($applicationDB, $_POST);
		redirect();
	}
	elseif ($_REQUEST['action'] == 'del' && $applicationDB->isWriteable()) {
		if (isset($_REQUEST['id']))
			del_application($applicationDB, $_REQUEST['id']);
		redirect();
	}
}

if (isset($_REQUEST['mass_action']) && $_REQUEST['mass_action'] == 'delete') {
	if (isset($_REQUEST['manage_static_applications']) && is_array($_REQUEST['manage_static_applications'])) {
		foreach ($_REQUEST['manage_static_applications'] as $app_id)
			del_application($applicationDB, $app_id);
	}

	redirect();
}

if (! isset($_GET['view']))
  $_GET['view'] = 'all';

if ($_GET['view'] == 'all')
  show_default($prefs, $applicationDB);

function show_default($prefs, $applicationDB) {
	global $types, $regular_types;
	$applications2 = $applicationDB->getList(true);
	$applications = array();
	foreach ($applications2 as $k => $v) {
		if ($v->getAttribute('static'))
			$applications[$k] = $v;
	}
	$is_empty = (is_null($applications) or count($applications)==0);

	$is_rw = $applicationDB->isWriteable();
	$can_manage_applications = isAuthorized('manageApplications');

	page_header();

	echo '<div>'; // general div
	echo '<h1>'._('Static Applications').'</h1>';
	echo '<div id="apps_list_div">'; // apps_list_div

	if ($is_empty) {
		echo _('No available application').'<br />';
	}
	else {
		echo '<div id="apps_list">'; // apps_list
		echo '<table class="main_sub sortable" id="applications_list_table" border="0" cellspacing="1" cellpadding="5">'; // table A
		echo '<thead>';
		echo '<tr class="title">';
		if (count($applications) > 1 and $is_rw and $can_manage_applications)
			echo '<th class="unsortable"></th>';
		echo '<th>'._('Name').'</th>';
		echo '<th>'._('Description').'</th>';
		echo '<th>'._('Type').'</th>';
		echo '</tr>';
		echo '</thead>';
		$count = 0;
		foreach($applications as $app) {
			$content = 'content'.(($count++%2==0)?1:2);

			if ($app->getAttribute('published')) {
				$status_change_value = 0;
			} else {
				$status_change_value = 1;
			}

			echo '<tr class="'.$content.'">';
			if (count($applications) > 1 and $is_rw and $can_manage_applications)
				echo '<td><input class="input_checkbox" type="checkbox" name="manage_static_applications[]" value="'.$app->getAttribute('id').'" /></td>';
			echo '<td><img src="media/image/cache.php?id='.$app->getAttribute('id').'" alt="" title="" /> ';
			if ($is_rw and $can_manage_applications)
				echo '<a href="?action=manage&id='.$app->getAttribute('id').'">';
			echo $app->getAttribute('name');
			if ($is_rw and $can_manage_applications)
				echo '</a>';
			echo '</td>';
			echo '<td>'.$app->getAttribute('description').'</td>';
			echo '<td style="text-align: center;"><img src="media/image/server-'.$app->getAttribute('type').'.png" alt="'.$app->getAttribute('type').'" title="'.$app->getAttribute('type').'" /><br />'.$app->getAttribute('type').'</td>';

			echo '<td><form action="" method="get">';
			echo '<input type="hidden" name="action" value="manage" />';
			echo '<input type="hidden" name="id" value="'.$app->getAttribute('id').'" />';
			echo '<input type="submit" value="'._('Manage').'"/>';
			echo '</form>';
			echo '</td>';

			if ($can_manage_applications) {
				echo '<td>';
				echo '<form action="" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this application?').'\');">';
				echo '<input type="hidden" name="action" value="del" />';
				echo '<input type="hidden" name="id" value="'.$app->getAttribute('id').'" />';
				echo '<input type="submit" value="'._('Delete').'" />';
				echo '</form>';
				echo '</td>';
			}

			echo '</tr>';
		}
		if (count($applications) > 1 and $is_rw and $can_manage_applications) {
			$content = 'content'.(($count++%2==0)?1:2);
			echo '<tfoot>';
			echo '<tr class="'.$content.'">';
			echo '<td colspan="5">';
			echo '<a href="javascript:;" onclick="markAllRows(\'applications_list_table\'); return false">'._('Mark all').'</a>';
			echo ' / <a href="javascript:;" onclick="unMarkAllRows(\'applications_list_table\'); return false">'._('Unmark all').'</a>';
			echo '</td>';
			echo '<td>';
			echo '<form action="" method="post" onsubmit="return updateMassActionsForm(this, \'applications_list_table\') && confirm(\''._('Are you sure you want to delete these static applications?').'\');;">';
			echo '<input type="hidden" name="mass_action" value="delete" />';
			echo '<input type="submit" name="delete" value="'._('Delete').'"/>';
			echo '</form>';
			echo '</td>';
			echo '</tr>';
			echo '</tfoot>';
		}
		echo '</table>'; // table A
		echo '</div>'; // apps_list
	}
	if ($is_rw and $can_manage_applications) {
		echo '<br />';
		echo '<h2>'._('Add a static application').'</h2>';
		echo '<div id="application_add">';
		$first_type = array_keys($types);
		$first_type = $first_type[0];
		$types2 = $types; // bug in php 5.1.6 (redhat 5.2)
		foreach ($types as $type => $name) {
			echo '<input class="input_radio" type="radio" name="type" value="'.$type.'" onclick="';
			foreach ($types2 as $type2 => $name2) { // bug in php 5.1.6
				if ( $type == $type2)
					echo '$(\'table_'.$type2.'\').show(); ';
				else
					echo '$(\'table_'.$type2.'\').hide(); ';
			}
			echo '" ';
			if ( $type == $first_type)
				echo 'checked="checked"';

			echo '/>';
			echo '<img src="media/image/server-'.$type.'.png" alt="'.$type.'" title="'.$type.'" />';
		}

		foreach ($types as $type => $name) {
			echo '<table id="table_'.$type.'"';
			if ( $type != $first_type)
				echo ' style="display: none" ';
			else
				echo ' style="display: visible" ';
			echo ' border="0" class="main_sub" cellspacing="1" cellpadding="3" >';

			echo '<form action="" method="post" enctype="multipart/form-data">';
			echo '<input type="hidden" name="action" value="add" />';
			$count = 0;
			foreach ($applicationDB->minimun_attributes() as $attr_name) {
				if (in_array($attr_name, array('type', 'package', 'desktopfile', 'icon_path' )))
					continue;
				$content = 'content'.(($count++%2==0)?1:2);
				echo '<tr class="'.$content.'">';
				echo '<td>';
				if ( $attr_name == 'executable_path') {
					if (in_array($type, $regular_types))
						echo _('Command');
					else
						echo _('URL');
				}
				else {
					echo _($attr_name);
				}
				echo '</td>';
				echo '<td>';
				echo '<input type="text" name="'.$attr_name.'" value="" size="50"/>';
				echo '</td>';
				echo '</tr>';
			}
			$content = 'content'.(($count++%2==0)?1:2);
			echo '<tr class="'.$content.'">';
			echo '<td>';
			echo _('MimeTypes');
			echo '</td>';
			echo '<td>';
			echo '<input type="text" name="mimetypes" value="" size="50"/>';
			echo '</td>';
			$content = 'content'.(($count++%2==0)?1:2);
			echo '<tr class="'.$content.'">';
			echo '<td colspan="2">';
			echo '<input type="submit" value="'._('Add').'" />';
			echo '<input type="hidden" name="published" value="1" />';
			echo '<input type="hidden" name="static" value="1" />';
			echo '<input type="hidden" name="type" value="'.$type.'" />';
			echo '</td>';
			echo '</tr>';
			echo '</form>';
			echo '</table>';
		}

		echo '</div>'; // application_add
	}

	echo '<br />';
	echo '<h2>'._('Web link configuration').'</h2>';
	echo 'Default browser for :';
	$browsers = $prefs->get('general', 'default_browser');
	if ( $browsers != array() && !is_null($browsers)) {
		echo '<table class="main_sub" border="0" cellspacing="1" cellpadding="3">';
		foreach ($browsers as $type => $browser) {
			$content = 'content'.(($count++%2==0)?1:2);
			echo '<tr class="'.$content.'">';
			if ($can_manage_applications) {
				echo '<form action="actions.php" method="post">';
				echo '<input type="hidden" name="name" value="default_browser" />';
				echo '<input type="hidden" name="action" value="add" />';
				echo '<input type="hidden" name="type" value="'.$type.'" />';
			}
			echo '<td>';
			echo $type;
			echo '</td>';
			echo '<td>';
			$apps = $applicationDB->getList(false, $type);
			echo '<select id="browser_'.$type.'"  name="browser">';
			echo '<option value="-1" >'._('None').'</option>';

			foreach ($apps as $mykey => $myval) {
				echo '<option value="'.$mykey.'" ';
				if (isset($browsers[$type]) && ($mykey == $browsers[$type]))
					echo 'selected="selected" ';
				echo '>'.$myval->getAttribute('name').'</option>';
			}
			echo '</select>';

			echo '</td>';
			if ($can_manage_applications) {
				echo '<td>';
				echo '<input type="submit" value="'._('Update').'" />';
				echo '</td>';
				echo '</form>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
	echo '</div>'; // general div
	page_footer();
	die();
}

function add_application($applicationDB, $data_) {
  if (! isset($data_['type']))
    return false;

  unset($data_['action']);
  $data_['id'] = 666; // little hack
  $a = $applicationDB->generateApplicationFromRow($data_);
  if (! $applicationDB->isOK($a))
    return false;
  $a->unsetAttribute('id');
  return $applicationDB->add($a);
}

function del_application($applicationDB, $id_) {
	$app = $applicationDB->import($id_);
	if (is_object($app)) {
		Abstract_Liaison::delete('StaticApplicationServer', $app->getAttribute('id'), NULL);
		return $applicationDB->remove($app);
	}
	return false;
}

function modify_application($applicationDB, $id_, $data_, $files_) {
	unset($data_['action']);
	$app = $applicationDB->import($id_);
	if (!is_object($app))
		return false;
	$attr_list = $app->getAttributesList();
	foreach ($data_ as $k => $v) {
		if (in_array($k, $attr_list)) {
			$app->setAttribute($k, $v);
		}
	}
	$applicationDB->update($app);
	if (array_key_exists('file_icon' ,$files_)) {
		$upload = $files_['file_icon'];

		$have_file = true;
		if($upload['error']) {
			switch ($upload['error']) {
				case 1: // UPLOAD_ERR_INI_SIZE
					popup_error('Oversized file for server rules');
					return false;
					break;
				case 3: // UPLOAD_ERR_PARTIAL
					popup_error('The file was corrupted while upload');
					return false;
					break;
				case 4: // UPLOAD_ERR_NO_FILE
					$have_file = false;
					break;
			}
		}

		if ($have_file) {
			$source_file = $upload['tmp_name'];
			if (! is_readable($source_file))
				die('file is not readable');
			
			if ( get_classes_startwith('Imagick') != array()) {
				
				$path_rw = $app->getIconPathRW();
				if (is_writable2($path_rw)) {
					try {
						$mypicture = new Imagick();
						$mypicture->readImage($source_file);
						$mypicture->scaleImage(32, 0);
						$mypicture->setImageFileName($app->getIconPathRW());
						$mypicture->writeImage();
					}
					catch (Exception $e) {
						popup_error('The icon is not an image');
						return false;
					}
				}
				else {
					Logger::error('main', 'getIconPathRW ('.$path_rw.') is not writeable');
					return false;
				}
			}
			else {
				Logger::info('main', 'No Imagick support found');
			}
		}
	}

	Abstract_Liaison::delete('StaticApplicationServer', $app->getAttribute('id'), NULL);

	return true;
}

function show_manage($id, $applicationDB) {
	global $types, $regular_types;
	$applicationsGroupDB = ApplicationsGroupDB::getInstance();
	$app = $applicationDB->import($id);
	if (!is_object($app))
		return false;

	$is_rw = $applicationDB->isWriteable();
	$can_manage_applications = isAuthorized('manageApplications');

	// App groups
	$appgroups = $applicationsGroupDB->getList();
	$groups_id = array();
	$liaisons = Abstract_Liaison::load('AppsGroup', $app->getAttribute('id'), NULL);
	foreach ($liaisons as $liaison)
		$groups_id []= $liaison->group;
	$groups = array();
	$groups_available = array();
	foreach ($appgroups as $group) {
		if (in_array($group->id, $groups_id))
			$groups[]= $group;
		else
			$groups_available[]= $group;
	}

	page_header();

	echo '<div>';
	echo '<h1><img src="media/image/cache.php?id='.$app->getAttribute('id').'" alt="" title="" /> '.$app->getAttribute('name').'</h1>';

	echo '<table class="main_sub" border="0" cellspacing="1" cellpadding="3">';
	echo '<tr class="title">';
// 	echo '<th>'._('Package').'</th>';
	echo '<th>'._('Type').'</th>';
	echo '<th>'._('Description').'</th>';
	if (in_array($app->getAttribute('type'), $regular_types))
		echo '<th>'._('Command').'</th>';
	else
		echo '<th>'._('URL').'</th>';

	if ($is_rw and $can_manage_applications) {
		echo '<th></th>';
	}
	echo '</tr>';

	echo '<tr class="content1">';

// 		echo '<td>'.$app->getAttribute('package').'</td>';
	echo '<td style="text-align: center;"><img src="media/image/server-'.$app->getAttribute('type').'.png" alt="'.$app->getAttribute('type').'" title="'.$app->getAttribute('type').'" /><br />'.$app->getAttribute('type').'</td>';
	echo '<td>'.$app->getAttribute('description').'</td>';
	echo '<td>';
	if (in_array($app->getAttribute('type'), $regular_types))
		echo $app->getAttribute('executable_path');
	else
		echo '<a href="'.$app->getAttribute('executable_path').'">'.$app->getAttribute('executable_path').'</a>';
	echo '</td>';

	if ($is_rw and $can_manage_applications) {
		echo '<td>';
		echo '<form action="" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this application?').'\');">';
		echo '<input type="hidden" name="action" value="del" />';
		echo '<input type="hidden" name="id" value="'.$app->getAttribute('id').'" />';
		echo '<input type="submit"  value="'._('Delete').'" />';
		echo '</form>';
		echo '</td>';
	}

	echo '</tr>';
	echo '</table>';

	if ($is_rw and $can_manage_applications) {
		echo '<br />';
		echo '<h2>'._('Modify').'</h2>';
		echo '<div id="application_modify">';

		echo '<form id="delete_icon" action="actions.php" method="post" style="display: none;">';
		echo '<input type="hidden" name="name" value="static_application" />';
		echo '<input type="hidden" name="action" value="del" />';
		echo '<input type="hidden" name="attribute" value="icon_file" />';
		echo '<input type="hidden" name="id" value="'.$app->getAttribute('id').'" />';
		echo '</form>';

		echo '<form action="" method="post" enctype="multipart/form-data" >'; // form A
		echo '<input type="hidden" name="action" value="modify" />';
		echo '<input type="hidden" name="published" value="1" />';
		echo '<input type="hidden" name="static" value="1" />';

		echo '<table class="main_sub" border="0" cellspacing="1" cellpadding="5">';
		$count = 1;
		$attr_list = $app->getAttributesList();
		foreach ($attr_list as $k => $v) {
			if (in_array($v, array('id', 'type', 'static', 'published', 'desktopfile', 'package', 'icon_path')))
				unset($attr_list[$k]);
		}

		foreach ($attr_list as $attr_name) {
			$content = 'content'.(($count++%2==0)?1:2);
			echo '<tr class="'.$content.'">';
			if ($attr_name == 'executable_path') {
				if (in_array($app->getAttribute('type'), $regular_types)) {
					echo '<td>'._('Command').'</td>';
				}
				else {
					echo '<td>'._('URL').'</td>';
				}
			}
			else {
				echo '<td>'._($attr_name).'</td>';
			}
			echo '<td>';
			echo '<input type="text" name="'.$attr_name.'" value="'.$app->getAttribute($attr_name).'" style="with:100%;"/>';
			echo '</td>';
			echo '</tr>';
		}
		
		if ( get_classes_startwith('Imagick') != array()) {
			$content = 'content'.(($count++%2==0)?1:2);
			echo '<tr class="'.$content.'">';
			echo '<td>'._('Icon').'</td>';
			echo '<td>';
			if (($app->getIconPath() != $app->getDefaultIconPath()) && file_exists($app->getIconPath())) {
				echo '<img src="media/image/cache.php?id='.$app->getAttribute('id').'" alt="" title="" /> ';
				echo '<input type="button" value="'._('Delete this icon').'" onclick="return confirm(\''._('Are you sure you want to delete this icon?').'\') && $(\'delete_icon\').submit();"/>';
				echo '<br />';
			}
			echo '<input type="file"  name="file_icon" /> ';
			echo '</td>';
			echo '</tr>';
		}
		else {
			Logger::info('main', 'No Imagick support found');
		}

		$content = 'content'.(($count++%2==0)?1:2);
		echo '<tr class="'.$content.'">';
		echo '<td colspan="2">';
		echo '<input type="submit" value="'._('Modify').'" />';
		echo '</td>';
		echo '</tr>';

		echo '</table>';

		echo '</form>'; // form A
		echo '</div>'; // application_modify
	}

	if (count($appgroups) > 0) {
		echo '<div>';
		echo '<h2>'._('Groups with this application').'</h2>';
		echo '<table border="0" cellspacing="1" cellpadding="3">';
		foreach ($groups as $group) {
			echo '<tr>';
			echo '<td>';
			echo '<a href="appsgroup.php?action=manage&id='.$group->id.'">'.$group->name.'</a>';
			echo '</td>';
			echo '<td><form action="actions.php" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this application from this group?').'\');">';
			echo '<input type="hidden" name="name" value="Application_ApplicationGroup" />';
			echo '<input type="hidden" name="action" value="del" />';
			echo '<input type="hidden" name="element" value="'.$id.'" />';
			echo '<input type="hidden" name="group" value="'.$group->id.'" />';
			echo '<input type="submit" value="'._('Delete from this group').'" />';
			echo '</form></td>';
			echo '</tr>';
		}

		if (count($groups_available) > 0) {
			echo '<tr>';
			echo '<form action="actions.php" method="post"><td>';
			echo '<input type="hidden" name="name" value="Application_ApplicationGroup" />';
			echo '<input type="hidden" name="action" value="add" />';
			echo '<input type="hidden" name="element" value="'.$id.'" />';
			echo '<select name="group">';
			foreach ($groups_available as $group)
				echo '<option value="'.$group->id.'">'.$group->name.'</option>';
			echo '</select>';
			echo '</td><td><input type="submit" value="'._('Add to this group').'" /></td>';
			echo '</form>';
			echo '</tr>';
		}
		echo '</table>';
		echo "<div>\n";
	}

	echo '</div>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
	page_footer();
	die();
}
