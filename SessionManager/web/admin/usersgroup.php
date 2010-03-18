<?php
/**
 * Copyright (C) 2008,2009 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com>
 * Author Julien LANGLOIS <julien@ulteo.com>
 * Author Jeremy DESVAGES <jeremy@ulteo.com>
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

if (! checkAuthorization('viewUsersGroups'))
	redirect('index.php');


$schedules = array(
	3600	=>	_('1 hour'),
	86400	=>	_('1 day'),
	604800	=>	_('1 week')
);

if (isset($_REQUEST['action'])) {
  if ($_REQUEST['action']=='manage') {
    if (isset($_REQUEST['id']))
      show_manage($_REQUEST['id']);
  }

	if (! checkAuthorization('manageUsersGroups'))
		redirect('index.php');


  if ($_REQUEST['action']=='add') {
    if ($_REQUEST['type'] == 'static')
      $id = action_add();
    elseif ($_REQUEST['type'] == 'dynamic')
      $id = action_add_dynamic();
    if ($id !== false)
      redirect('usersgroup.php?action=manage&id='.$id);
  }
  elseif ($_REQUEST['action']=='del') {
    if (isset($_REQUEST['id'])) {
      $req_ids = $_REQUEST['id'];
      if (!is_array($req_ids))
        $req_ids = array($req_ids);
      foreach ($req_ids as $req_id)
        action_del($req_id);
      redirect('usersgroup.php');
    }
  }
  elseif ($_REQUEST['action']=='modify') {
    if (isset($_REQUEST['id'])) {
      action_modify($_REQUEST['id']);
      redirect();
    }
  }
  elseif ($_REQUEST['action']=='modify_rules') {
    if (isset($_REQUEST['id'])) {
	  action_modify_rules($_REQUEST['id']);
      redirect();
    }
  }
  elseif ($_REQUEST['action']=='set_default') {
    if (isset($_REQUEST['id'])) {
      $req_id = $_REQUEST['id'];

      action_set_default($req_id);
      redirect();
    }
  }
  elseif ($_REQUEST['action']=='unset_default') {
    if (isset($_REQUEST['id'])) {
      $req_id = $_REQUEST['id'];

      action_unset_default($req_id);
      redirect();
    }
  }

  redirect();
}

if (! isset($_GET['view']))
  $_GET['view'] = 'all';

if ($_GET['view'] == 'all')
  show_default();

function action_add() {
  if (! (isset($_REQUEST['name']) && isset($_REQUEST['description'])))
    return false;

  if ($_REQUEST['name'] == '') {
    popup_error(_('You must define a name to your usergroup'));
    return false;
  }

  $userGroupDB = UserGroupDB::getInstance();
  if (! $userGroupDB->isWriteable())
      return false;

  $g = new UsersGroup(NULL,$_REQUEST['name'], $_REQUEST['description'], 1);
  $res = $userGroupDB->add($g);
  if (!$res)
    die_error('Unable to create user group '.$res,__FILE__,__LINE__);

  popup_info(_('UserGroup successfully added'));
  return $g->getUniqueID();
}

function action_add_dynamic() {
  if (! (isset($_REQUEST['name']) && isset($_REQUEST['description'])))
    return false;

  if ($_REQUEST['name'] == '') {
    popup_error(_('You must define a name to your usergroup'));
    return false;
  }

  $userGroupDB = UserGroupDB::getInstance();

  $rules = array();
  foreach ($_POST['rules'] as $rule) {
    if ($rule['value'] == '') {
      popup_error(_('You must give a value to each rule of your usergroup'));
      return false;
    }

    $buf = new UserGroup_Rule(NULL);
    $buf->attribute = $rule['attribute'];
    $buf->type = $rule['type'];
    $buf->value = $rule['value'];

    $rules[] = $buf;
  }

  if ($_REQUEST['cached'] === '0')
    $g = new UsersGroup_dynamic(NULL, $_REQUEST['name'], $_REQUEST['description'], 1, $rules, $_REQUEST['validation_type']);
  else
    $g = new UsersGroup_dynamic_cached(NULL, $_REQUEST['name'], $_REQUEST['description'], 1, $rules, $_REQUEST['validation_type'], $_REQUEST['schedule']);
  $res = $userGroupDB->add($g);
  if (!$res)
    die_error('Unable to create dynamic user group '.$res,__FILE__,__LINE__);
  popup_info(_('UserGroup successfully added'));
  return $g->getUniqueID();
}

function action_del($id) {
  $userGroupDB = UserGroupDB::getInstance();

  $group = $userGroupDB->import($id);
  if (! is_object($group))
    die_error('Group "'.$id.'" is not OK',__FILE__,__LINE__);

  if ($group->type == 'static') {
    if (! $userGroupDB->isWriteable())
     return false;
  }

  if (! $userGroupDB->remove($group))
    die_error('Unable to remove group "'.$id.'" is not OK',__FILE__,__LINE__);

  popup_info(_('UserGroup successfully deleted'));
  return true;
}

function action_modify($id) {
	if (! checkAuthorization('manageUsersGroups'))
		return false;

  $userGroupDB = UserGroupDB::getInstance();
  if ((str_startswith($id,'static_')) && (! $userGroupDB->isWriteable()))
     return false;

  $group = $userGroupDB->import($id);
  if (! is_object($group))
    die_error('Group "'.$id.'" is not OK',__FILE__,__LINE__);

  $has_change = false;

  if (isset($_REQUEST['name'])) {
    $group->name = $_REQUEST['name'];
    $has_change = true;
  }

  if (isset($_REQUEST['description'])) {
    $group->description = $_REQUEST['description'];
    $has_change = true;
  }

  if (isset($_REQUEST['published'])) {
    $group->published = (bool)$_REQUEST['published'];
    $has_change = true;
  }

  if (isset($_REQUEST['schedule'])) {
    $group->schedule = $_REQUEST['schedule'];
    $has_change = true;
  }

  if (! $has_change)
    return false;

  if (! $userGroupDB->update($group))
    die_error('Unable to update group "'.$id.'"',__FILE__,__LINE__);

  popup_info(_('UserGroup successfully modified'));
  return true;
}

function action_modify_rules($id) {
	$userGroupDB = UserGroupDB::getInstance();
	
	$group = $userGroupDB->import($id);
	if (! is_object($group))
		die_error('Group "'.$id.'" is not OK',__FILE__,__LINE__);

	$rules = array();
	foreach ($_POST['rules'] as $rule) {
		if ($rule['value'] == '') {
			popup_error(_('You must give a value to each rule of your usergroup'));
			return false;
		}

		$buf = new UserGroup_Rule(NULL);
		$buf->attribute = $rule['attribute'];
		$buf->type = $rule['type'];
		$buf->value = $rule['value'];
		$buf->usergroup_id = $id;

		$rules[] = $buf;
	}
	$group->rules = $rules;

	$group->validation_type = $_REQUEST['validation_type'];

	if (! $userGroupDB->update($group))
		die_error('Unable to update group "'.$id.'"',__FILE__,__LINE__);
	else
		popup_info(sprintf(_("Rules of '%s' successfully modified"), $group->name));
}

function action_set_default($id_) {
  try {
    $prefs = new Preferences_admin();
  }
  catch (Exception $e) {
    // Error header sauvergarde
    return False;
  }

  $userGroupDB = UserGroupDB::getInstance();

  $group = $userGroupDB->import($id_);
  if (! is_object($group)) {
    popup_error('No such group id "'.$id_.'"');
    return False;
  }

  $mods_enable = $prefs->set('general', 'user_default_group', $id_);
  if (! $prefs->backup()) {
    Logger::error('main', 'usersgroup.php action_default: Unable to save $prefs');
    return False;
  }

  popup_info(_('UserGroup successfully modified'));
  return True;
}

function action_unset_default($id_) {
  try {
    $prefs = new Preferences_admin();
  }
  catch (Exception $e) {
    // Error header sauvergarde
    return False;
  }

  $userGroupDB = UserGroupDB::getInstance();

  $group = $userGroupDB->import($id_);
  if (! is_object($group)) {
    popup_error('No such group id "'.$id_.'"');
    return False;
  }

  $default_id = $prefs->get('general', 'user_default_group');
  if ($id_ != $default_id) {
    popup_error('Group id "'.$id_.'" is not the default group');
    return False;
  }

  $mods_enable = $prefs->set('general', 'user_default_group', NULL);
  if (! $prefs->backup()) {
    Logger::error('main', 'usersgroup.php action_default: Unable to save $prefs');
    return False;
  }

  popup_info(_('UserGroup successfully modified'));
  return True;
}

function show_default() {
  global $schedules;

  $userGroupDB = UserGroupDB::getInstance();
  $userDB = UserDB::getInstance();
  $groups = $userGroupDB->getList(true);
  $has_group = ! (is_null($groups) or (count($groups) == 0));

  $can_manage_usersgroups = isAuthorized('manageUsersGroups');

  page_header();

  echo '<div id="usersgroup_div" >';
  echo '<h1>'._('User groups').'</h1>';

  echo '<div id="usersgroup_list">';

  if (! $has_group)
    echo _('No available user group').'<br />';
  else {
     $all_static = true;
     foreach($groups as $group){
       if ($group->type != 'static' || $userGroupDB->isWriteable()) {
         $all_static = false;
         break; // no need to continue;
       }
     }
    echo '<table class="main_sub sortable" id="usergroups_list" border="0" cellspacing="1" cellpadding="5">';
    echo '<tr class="title">';
    if ( (!$all_static || $userGroupDB->isWriteable()) and $can_manage_usersgroups and count($groups) > 1) {
      echo '<th class="unsortable"></th>'; // masse action
    }
    echo '<th>'._('Name').'</th>';
    echo '<th>'._('Description').'</th>';
    echo '<th>'._('Status').'</th>';
    echo '<th>'._('Type').'</th>';
    echo '</tr>';

    $count = 0;
    foreach($groups as $group){
      $content = 'content'.(($count++%2==0)?1:2);
      if ($group->published)
        $publish = '<span class="msg_ok">'._('Enabled').'</span>';
      else
        $publish = '<span class="msg_error">'._('Blocked').'</span>';

      echo '<tr class="'.$content.'">';
      if ($can_manage_usersgroups) {
        if ($group->type != 'static' || $userGroupDB->isWriteable() and count($groups) > 1) {
          echo '<td><input class="input_checkbox" type="checkbox" name="id[]" value="'.$group->getUniqueID().'" /></td>';
        }
        else if ( !$all_static and count($groups) > 1) {
          echo '<td></td>';
        }
      }
      echo '<td><a href="?action=manage&id='.$group->getUniqueID().'">'.$group->name.'</a></td>';
      echo '<td>'.$group->description.'</td>';
      echo '<td class="centered">'.$publish.'</td>';
      echo '<td class="centered">'.$group->type.'</td>';

      echo '<td><form action="">';
      echo '<input type="submit" value="'._('Manage').'"/>';
      echo '<input type="hidden" name="action" value="manage" />';
      echo '<input type="hidden" name="id" value="'.$group->getUniqueID().'" />';
      echo '</form></td>';

      if (($group->type != 'static' || $userGroupDB->isWriteable()) and $can_manage_usersgroups) {
        echo '<td><form action="" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this group?').'\');">';
        echo '<input type="submit" value="'._('Delete').'"/>';
        echo '<input type="hidden" name="action" value="del" />';
        echo '<input type="hidden" name="id" value="'.$group->getUniqueID().'" />';
        echo '</form></td>';
      }
      else if ( !$all_static and $can_manage_usersgroups) {
        echo '<td></td>';
      }
      echo '</tr>';
    }
    $content = 'content'.(($count++%2==0)?1:2);
    if ( (!$all_static || $userGroupDB->isWriteable()) and $can_manage_usersgroups and count($groups) > 1) {
      echo '<tfoot>';
      echo '<tr class="'.$content.'">';
      echo '<td colspan="6"><a href="javascript:;" onclick="markAllRows(\'usergroups_list\'); return false">'._('Mark all').'</a> / <a href="javascript:;" onclick="unMarkAllRows(\'usergroups_list\'); return false">'._('Unmark all').'</a></td>';
	  echo '<td>';
	  echo '<form action="usersgroup.php" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete these groups?').'\') && updateMassActionsForm(this, \'usergroups_list\');">';
	  echo '<input type="hidden" name="action" value="del" />';
	  echo '<input type="submit" value="'._('Delete').'"/>';
	  echo '</form>';
	  echo '</td>';
      echo '</tr>';
      echo '</tfoot>';
    }
    echo '</table>';
  }

  echo '</div>';

  if ($userGroupDB->isWriteable()) {
    $usergroup_types = array('static' => _('Static'), 'dynamic' => _('Dynamic'));
  }
  else {
    $usergroup_types = array('dynamic' => _('Dynamic'));
  }


	if ($can_manage_usersgroups) {
		echo '<div>';
		echo '<h2>'._('Create a new group').'</h2>';

		$first_type = array_keys($usergroup_types);
		$first_type = $first_type[0];
		$usergroup_types2 = $usergroup_types; // bug in php 5.1.6 (redhat 5.2)
		foreach ($usergroup_types as $type => $name) {
			echo '<input class="input_radio" type="radio" name="type" value="'.$type.'" onclick="';
			foreach ($usergroup_types2 as $type2 => $name2) { // bug in php 5.1.6
				if ($type == $type2)
					echo '$(\'table_'.$type2.'\').show(); ';
				else
					echo '$(\'table_'.$type2.'\').hide(); ';
			}
			echo '"';
			if ($type == $first_type)
				echo ' checked="checked"';

			echo ' />';
			echo $name;
		}

		foreach ($usergroup_types as $type => $name) {
			$count = 2;
			echo '<form action="" method="post">';
			echo '<table id="table_'.$type.'"';
			if ( $type != $first_type)
				echo ' style="display: none" ';
			else
				echo ' style="display: visible" ';
			echo ' border="0" class="main_sub" cellspacing="1" cellpadding="5" >';
			echo '<input type="hidden" name="action" value="add" />';
			echo '<input type="hidden" name="type" value="'.$type.'" />';
			echo '<tr class="content'.(($count++%2==0)?1:2).'">';
			echo '<th>'._('Name').'</th>';
			echo '<td><input type="text" name="name" value="" /></td>';
			echo '</tr>';

			echo '<tr class="content'.(($count++%2==0)?1:2).'">';
			echo '<th>'._('Description').'</th>';
			echo '<td><input type="text" name="description" value="" /></td>';
			echo '</tr>';
		
			if (str_startswith($type, 'dynamic')) {
				echo '<tr class="content'.(($count++%2==0)?1:2).'">';
				echo '<th>'._('Cached').'</th>';
				echo '<td>';
				echo '<input type="radio" name="cached" value="0" checked="checked" onchange="$(\'schedule_select\').hide();" /> '._('No');
				echo '<input type="radio" name="cached" value="1" onchange="$(\'schedule_select\').show();" /> '._('Yes');
				echo ' <span id="schedule_select" style="display: none;"><br />'._('Time between two updates:').' <select name="schedule">';
				foreach ($schedules as $interval => $text)
					echo '<option value="'.$interval.'">'.$text.'</option>';
				echo '</select></span>';
				echo '</td>';
				echo '</tr>';
				echo '<tr class="content'.(($count++%2==0)?1:2).'">';
				echo '<th>'._('Validation type').'</th>';
				echo '<td><input type="radio" name="validation_type" value="and" checked="checked" /> '._('All').' <input type="radio" name="validation_type" value="or" /> '._('At least one').'</td>';
				echo '</tr>';

				echo '<tr class="content'.(($count++%2==0)?1:2).'">';
				echo '<th>'._('Filters').'</th>';
				echo '<td>';

				$i = 0;
				$filter_attributes = $userDB->getAttributesList();
				foreach ($filter_attributes as $key1 => $value1) {
					if ( $value1 == 'password')
						unset($filter_attributes[$key1]);
				}
				$filter_types = UserGroup_Rule::$types;
				echo '<table border="0" cellspacing="1" cellpadding="3">';
				echo '<tr>';
				echo '<td><select name="rules[0][attribute]">';
				foreach ($filter_attributes as $filter_attribute)
					echo '<option value="'.$filter_attribute.'">'.$filter_attribute.'</option>';
				echo '</select></td>';
				echo '<td><select name="rules[0][type]">';
				foreach ($filter_types as $filter_type) {
					echo '<option value="'.$filter_type.'">'.$filter_type.'</option>';
				}
				echo '</select></td>';
				echo '<td><input type="text" name="rules[0][value]" value="" /></td>';
				echo '<td><input style="display: none;" type="button" onclick="del_field(this.parentNode.parentNode); return false;" value="-" /><input type="button" onclick="add_field(this.parentNode.parentNode); return false;" value="+" /></td>';
				echo '</tr>';
				echo '</table>';

				echo '</td>';
				echo '</tr>';
			}

			echo '<tr class="content1">';
			echo '<td class="centered" colspan="2"><input type="submit" value="'._('Add').'" /></td>';
			echo '</tr>';
			echo '</table>';
			echo '</form>';
		}
		echo '</div>';
	} //if ($can_manage_usersgroups)

  echo '</div>';
  page_footer();
}

function show_manage($id) {
  global $schedules;
  
  $prefs = Preferences::getInstance();
  if (! $prefs)
    die_error('get Preferences failed',__FILE__,__LINE__);

  $userGroupDB = UserGroupDB::getInstance();

  $group = $userGroupDB->import($id);
  
  if (! is_object($group)) {
    die_error(_('Failed to load usergroup'));
  }
  
  $usergroupdb_rw = $userGroupDB->isWriteable();

  $policy = $group->getPolicy();
  $policy_rule_enable = 0;
  $policy_rules_disable = 0;
  foreach($policy as $key => $value) {
	  if ($value === true)
		  $policy_rule_enable++;
	  else
		  $policy_rules_disable++;
  }

  $buffer = $prefs_policy = $prefs->get('general', 'policy');
  $default_policy = $prefs_policy['default_policy'];

  if (! is_object($group))
    die_error('Group "'.$id.'" is not OK',__FILE__,__LINE__);

  if ($group->published) {
    $status = '<span class="msg_ok">'._('Enabled').'</span>';
    $status_change = _('Block');
    $status_change_value = 0;

  } else {
    $status = '<span class="msg_error">'._('Blocked').'</span>';
    $status_change = _('Enable');
    $status_change_value = 1;
  }

  $users = $group->usersLogin();
  sort($users);
  $has_users = (count($users) > 0);

  $userDB = UserDB::getInstance();

  $usersList = new UsersList($_REQUEST);
  $users_all = $usersList->search();
  $search_form = $usersList->getForm(array('action' => 'manage', 'id' => $id, 'search_user' => true));

  if (is_null($users_all))
    $users_all = array();
  $users_available = array();
  foreach($users_all as $user) {
    $found = false;
    foreach($users as $user2) {
      if ($user2 == $user->getAttribute('login'))
	$found = true;
    }

    if (! $found)
      $users_available[]= $user->getAttribute('login');
  }

  // Default usergroup
  $is_default_group = ($prefs->get('general', 'user_default_group') == $id);

  // Publications
  $groups_apps = array();
  foreach ( Abstract_Liaison::load('UsersGroupApplicationsGroup',  $id, NULL) as $group_a) {
    $obj = new AppsGroup();
    $obj->fromDB($group_a->group);

    if (is_object($obj))
	$groups_apps[]= $obj;
  }

  $groups_apps_all = getAllAppsGroups();
  $groups_apps_available = array();
  foreach($groups_apps_all as $group_apps) {
    if (! in_array($group_apps, $groups_apps))
      $groups_apps_available[]= $group_apps;
  }

	$can_manage_usersgroups = isAuthorized('manageUsersGroups');
	$can_manage_publications = isAuthorized('managePublications');
	$can_manage_sharedfolders = isAuthorized('manageServers');


  page_header();
  echo '<div id="users_div">';
  echo '<h1><a href="?">'._('User groups management').'</a> - '.$group->name.'</h1>';

  echo '<table class="main_sub" border="0" cellspacing="1" cellpadding="5">';
  echo '<tr class="title">';
  echo '<th>'._('Description').'</th>';
  echo '<th>'._('Status').'</th>';
  echo '</tr>';

  echo '<tr class="content1">';
  echo '<td>'.$group->description.'</td>';
  echo '<td>'.$status.'</td>';
  echo '</tr>';
  echo '</table>';


 	if ($can_manage_usersgroups) {
		echo '<div>';
		echo '<h2>'._('Settings').'</h1>';

		if ($group->type == 'static' and $can_manage_usersgroups and $usergroupdb_rw) {
			echo '<form action="" method="post">';
			if ($is_default_group) {
				echo '<input type="submit" value="'._('Remove from default').'"/>';
				echo '<input type="hidden" name="action" value="unset_default" />';
			} else {
				echo '<input type="submit" value="'._('Define as default').'"/>';
				echo '<input type="hidden" name="action" value="set_default" />';
			}

			echo '<input type="hidden" name="id" value="'.$group->getUniqueID().'" />';
			echo '</form>';
			echo '<br/>';
		}

		if ($usergroupdb_rw || ($group->type != 'static')) {
			echo '<form action="" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this group?').'\');">';
			echo '<input type="submit" value="'._('Delete this group').'"/>';
			echo '<input type="hidden" name="action" value="del" />';
			echo '<input type="hidden" name="id" value="'.$id.'" />';
			echo '</form>';
			echo '<br/>';

			echo '<form action="" method="post">';
			echo '<input type="hidden" name="action" value="modify" />';
			echo '<input type="hidden" name="id" value="'.$id.'" />';
			echo '<input type="hidden" name="published" value="'.$status_change_value.'" />';
			echo '<input type="submit" value="'.$status_change.'"/>';
			echo '</form>';
			echo '<br/>';

			echo '<form action="" method="post">';
			echo '<input type="hidden" name="action" value="modify" />';
			echo '<input type="hidden" name="id" value="'.$id.'" />';
			echo '<input type="text" name="name"  value="'.$group->name.'" size="50" /> ';
			echo '<input type="submit" value="'._('Update the name').'"/>';
			echo '</form>';
			echo '<br/>';
	
			echo '<form action="" method="post">';
			echo '<input type="hidden" name="action" value="modify" />';
			echo '<input type="hidden" name="id" value="'.$id.'" />';
			echo '<input type="text" name="description"  value="'.$group->description.'" size="50" /> ';
			echo '<input type="submit" value="'._('Update the description').'"/>';
			echo '</form>';
		}
    
		if ($group->type == 'dynamiccached') {
			echo '<form action="" method="post">';
			echo '<input type="hidden" name="action" value="modify" />';
			echo '<input type="hidden" name="id" value="'.$id.'" />';

			echo ' <select name="schedule">';
			foreach ($schedules as $interval => $text) {
				echo '<option value="'.$interval.'"';
				if ($group->schedule == $interval)
					echo ' selected="selected"';
				echo '>'.$text.'</option>';
			}
			echo '</select>';
			echo '<input type="submit" value="'._('Update the schedule').'"/>';
			echo '</form>';
		}

		echo '</div>';
		echo '<br/>';
	}


  if (str_startswith($group->type,'dynamic')) {
    echo '<div>';
    echo '<h2>'._('Rules').'</h1>';

	if ($can_manage_usersgroups) {
		echo '<form action="" method="post">';
		echo '<input type="hidden" name="action" value="modify_rules" />';
		echo '<input type="hidden" name="id" value="'.$id.'" />';
	}
echo '<table class="main_sub" border="0" cellspacing="1" cellpadding="3">';
echo '<tr class="content1">';
echo '<th>'._('Validation type').'</th>';
echo '<td><input type="radio" name="validation_type" value="and"';
if ($group->validation_type == 'and')
	echo ' checked="checked"';
echo ' /> '._('All').' <input type="radio" name="validation_type" value="or"';
if ($group->validation_type == 'or')
	echo ' checked="checked"';
echo ' /> '._('At least one').'</td>';
echo '</tr>';

echo '<tr class="content2">';
echo '<th>'._('Filters').'</th>';
echo '<td>';

$i = 0;
$filter_attributes = $userDB->getAttributesList();
foreach ($filter_attributes as $key1 => $value1) {
	if ($value1 == 'password')
		unset($filter_attributes[$key1]);
}

$filter_types = UserGroup_Rule::$types;
echo '<table border="0" cellspacing="1" cellpadding="3">';
$i = 0;
foreach ($group->rules as $rule) {
	echo '<tr>';
	echo '<td><select name="rules['.$i.'][attribute]">';
	foreach ($filter_attributes as $filter_attribute) {
		echo '<option value="'.$filter_attribute.'"';
		if ($rule->attribute == $filter_attribute)
			echo ' selected="selected"';
		echo '>'.$filter_attribute.'</option>';
	}
	echo '</select></td>';
	echo '<td><select name="rules['.$i.'][type]">';
	foreach ($filter_types as $filter_type) {
		echo '<option value="'.$filter_type.'"';
		if ($rule->type == $filter_type)
			echo ' selected="selected"';
		echo '>'.$filter_type.'</option>';
	}
	echo '</select></td>';
	echo '<td><input type="text" name="rules['.$i.'][value]" value="'.$rule->value.'" /></td>';
	if ($can_manage_usersgroups) {
		echo '<td>';

		echo '<input';
		if (($i == 0 && count($group->rules) == 1) || $i == count($group->rules))
			echo ' style="display: none;"';
		echo ' type="button" onclick="del_field(this.parentNode.parentNode); return false;" value="-" />';

		echo '<input';
		if ($i+1 != count($group->rules))
			echo ' style="display: none;"';
		echo ' type="button" onclick="add_field(this.parentNode.parentNode); return false;" value="+" />';

		echo '</td>';
	}
	echo '</tr>';

	$i++;
}
echo '</table>';

echo '</td>';
echo '</tr>';
echo '</table>';
echo '<br />';
	if ($can_manage_usersgroups) {
		echo '<input type="submit" value="'._('Update rules').'" />';
		echo '</form>';
	}

    echo '</div>';
	echo '<br />';
  }

  // Users list
if (count($users_all) > 0 || count($users) > 0) {
    echo '<div>';
    echo '<h2>'._('List of users in this group').'</h2>';
    echo '<table border="0" cellspacing="1" cellpadding="3">';

    if (count($users) > 0) {
      foreach($users as $user) {
	echo '<tr>';
	echo '<td><a href="users.php?action=manage&id='.$user.'">'.$user.'</td>';
	echo '<td>';
	if ($usergroupdb_rw && $group->type == 'static' && !$group->isDefault() and $can_manage_usersgroups) {
		echo '<form action="actions.php" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this user?').'\');">';
		echo '<input type="hidden" name="action" value="del" />';
		echo '<input type="hidden" name="name" value="User_UserGroup" />';
		echo '<input type="hidden" name="group" value="'.$id.'" />';
		echo '<input type="hidden" name="element" value="'.$user.'" />';
		echo '<input type="submit" value="'._('Delete from this group').'" />';
		echo '</form>';
		echo '</td>';
	}
	echo '</tr>';
      }
    }

    if ((count ($users_available) >0) && $usergroupdb_rw && $group->type == 'static' and $can_manage_usersgroups) {
      echo '<tr><form action="actions.php" method="post"><td>';
      echo '<input type="hidden" name="action" value="add" />';
      echo '<input type="hidden" name="name" value="User_UserGroup" />';
      echo '<input type="hidden" name="group" value="'.$id.'" />';
      echo '<select name="element">';
      foreach($users_available as $user)
	echo '<option value="'.$user.'" >'.$user.'</option>';
      echo '</select>';
      echo '</td><td><input type="submit" value="'._('Add to this group').'" /></td>';
      echo '</form></tr>';
    }

    echo '</table>';

    if ($usergroupdb_rw && $group->type == 'static' and $can_manage_usersgroups) {
      echo '<br/>';
      echo $search_form;
    }
    echo '</div>';
    echo '<br/>';
  }

  // Publications part
  if (count($groups_apps_all)>0) {
    echo '<div>';
    echo '<h2>'._('List of publications for this group').'</h1>';
    echo '<table border="0" cellspacing="1" cellpadding="3">';

    if (count($groups_apps)>0) {
      foreach($groups_apps as $groups_app) {
	echo '<tr>';
	echo '<td><a href="appsgroup.php?action=manage&id='.$groups_app->id.'">'.$groups_app->name.'</td>';
		if ($can_manage_publications) {
			echo '<td>';
			echo '<form action="actions.php" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this publication?').'\');">';
			echo '<input type="hidden" name="action" value="del" />';
			echo '<input type="hidden" name="name" value="Publication" />';
			echo '<input type="hidden" name="group_u" value="'.$id.'" />';
			echo '<input type="hidden" name="group_a" value="'.$groups_app->id.'" />';
			echo '<input type="submit" value="'._('Delete this publication').'" />';
			echo '</form>';
			echo '</td>';
		}
	echo '</tr>';
      }
    }

    if (count ($groups_apps_available) >0 and $can_manage_publications) {
      echo '<tr><form action="actions.php" method="post"><td>';
      echo '<input type="hidden" name="action" value="add" />';
      echo '<input type="hidden" name="name" value="Publication" />';
      echo '<input type="hidden" name="group_u" value="'.$id.'" />';
      echo '<select name="group_a">';
      foreach($groups_apps_available as $group_apps)
	echo '<option value="'.$group_apps->id.'" >'.$group_apps->name.'</option>';
      echo '</select>';
      echo '</td><td><input type="submit" value="'._('Add this publication').'" /></td>';
      echo '</form></tr>';
    }
    echo '</table>';
    echo '</div>';
  }


	// Policy of this group
	echo '<div>';
	echo '<h2>'._('Policy of this group').'</h2>';
	echo '<table border="0" cellspacing="1" cellpadding="3">';

	foreach($policy as $key => $value) {
		if ($value === false)
			continue;

		$extends_from_default = (in_array($key,$default_policy));
		$buffer = ($extends_from_default===true?' ('._('extend from default').')':'');

		echo '<tr>';
		echo '<td>'.$key.' '.$buffer.'</td>';
		if ($can_manage_usersgroups && ! $extends_from_default) {
			echo '<td>';
			echo '<form action="actions.php" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this rule?').'\');">';
			echo '<input type="hidden" name="name" value="UserGroup_PolicyRule" />';
			echo '<input type="hidden" name="action" value="del" />';
			echo '<input type="hidden" name="id" value="'.$group->getUniqueID().'" />';
			echo '<input type="hidden" name="element" value="'.$key.'" />';
			echo '<input type="submit" value="'._('Delete this rule').'" />';
			echo '</form>';
			echo '</td>';
		}
		echo '</tr>';
	}
	if ($can_manage_usersgroups && count($policy_rules_disable)>0 && (array_search(false, $policy) !== false)) {
		echo '<tr><form action="actions.php" method="post"><td>';
		echo '<input type="hidden" name="name" value="UserGroup_PolicyRule" />';
		echo '<input type="hidden" name="action" value="add" />';
		echo '<input type="hidden" name="id" value="'.$group->getUniqueID().'" />';
		echo '<select name="element">';

		foreach($policy as $key => $value) {
			if ($value === true)
				continue;

			echo '<option value="'.$key.'" >'.$key.'</option>';
		}
		echo '</select>';
		echo '</td><td><input type="submit" value="'._('Add this rule').'" /></td>';
		echo '</form></tr>';
	}

	echo '</table>';
	echo '</div>';
	echo '<br/>';

    $all_sharedfolders = SharedFolders::getAll();

    if (count($all_sharedfolders) > 0) {
		$available_sharedfolders = array();
		$used_sharedfolders = Abstract_SharedFolder::load_by_usergroup_id($group->getUniqueID());
		foreach ($all_sharedfolders as $sharedfolder) {
			if (in_array($sharedfolder->id, array_keys($used_sharedfolders)))
				continue;

			$available_sharedfolders[] = $sharedfolder;
		}

        echo '<br />';
		echo '<div>';
		echo '<h2>'._('Shared folders').'</h1>';

		echo '<table border="0" cellspacing="1" cellpadding="3">';
		foreach ($used_sharedfolders as $sharedfolder) {
			echo '<tr>';
			echo '<td><a href="sharedfolders.php?action=manage&amp;id='.$sharedfolder->id.'">'.$sharedfolder->name.'</a></td>';
			if ($can_manage_sharedfolders) {
				echo '<td><form action="actions.php" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this shared folder access?').'\');">';
				echo '<input type="hidden" name="name" value="SharedFolder_ACL" />';
				echo '<input type="hidden" name="action" value="del" />';
				echo '<input type="hidden" name="sharedfolder_id" value="'.$sharedfolder->id.'" />';
				echo '<input type="hidden" name="usergroup_id" value="'.$group->getUniqueID().'" />';
				echo '<input type="submit" value="'._('Delete access to this shared folder').'" />';
				echo '</form></td>';
			}
			echo '</tr>';
		}

		if (count($available_sharedfolders) > 0 && $can_manage_sharedfolders) {
			echo '<tr><form action="actions.php" method="post"><td>';
			echo '<input type="hidden" name="name" value="SharedFolder_ACL" />';
			echo '<input type="hidden" name="action" value="add" />';
			echo '<input type="hidden" name="usergroup_id" value="'.$group->getUniqueID().'" />';
			echo '<select name="sharedfolder_id">';
			foreach($available_sharedfolders as $sharedfolder)
				echo '<option value="'.$sharedfolder->id.'" >'.$sharedfolder->name.'</option>';
			echo '</select>';
			echo '</td><td><input type="submit" value="'._('Add access to this shared folder').'" /></td>';
			echo '</form></tr>';
		}
		echo '</table>';
		echo '</div>';
    }

  echo '</div>';
  page_footer();
  die();
}
