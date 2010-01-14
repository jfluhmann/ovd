<?php
/**
 * Copyright (C) 2008,2009 Ulteo SAS
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
require_once(dirname(__FILE__).'/../../includes/core.inc.php');

class admin_ApplicationDB_sql extends ApplicationDB_sql{
	public function add($a){
		if (is_object($a)){
			$query_keys = "";
			$query_values = "";
			$attributes = $a->getAttributesList();
			foreach ($attributes as $key){
				$query_keys .= '`'.$key.'`,';
				$query_values .= '"'.mysql_escape_string($a->getAttribute($key)).'",';
			}
			$query_keys = substr($query_keys, 0, -1); // del the last ,
			$query_values = substr($query_values, 0, -1); // del the last ,
			$sql2 = SQL::getInstance();
			$res = $sql2->DoQuery('INSERT INTO @1 ( '.$query_keys.' ) VALUES ('.$query_values.' )',APPLICATION_TABLE);
			$id = $sql2->InsertId();
			$a->setAttribute('id', $id);

			// clean up the icon cache
			$a->getIcon();

			return ($res !== false);
		}
		return false;

	}
	public function remove($a){
		// TODO remove also all liasons
		if (is_object($a) && $a->hasAttribute('id') && is_numeric($a->getAttribute('id'))) {
			$sql2 = SQL::getInstance();
			$res = $sql2->DoQuery('DELETE FROM @1 WHERE @2 = %3', APPLICATION_TABLE, 'id', $a->getAttribute('id'));
			return ($res !== false);
		}
		else
			return false;

	}
//htmlspecialchars($data_, ENT_QUOTES);
	public function update($a){
		if ($this->isOK($a)){
			$query = 'UPDATE `'.APPLICATION_TABLE.'` SET ';
			$attributes = $a->getAttributesList();
			foreach ($attributes as $key){
				$query .=  '`'.$key.'` = \''.mysql_escape_string($a->getAttribute($key)).'\' , ';
			}
			$query = substr($query, 0, -2); // del the last ,
			$query .= ' WHERE `id` =\''.$a->getAttribute('id').'\'';

			$sql2 = SQL::getInstance();
			$res = $sql2->DoQuery($query);
			return ($res !== false);
		}
		return false;
	}

	public static function init($prefs_) {
		Logger::debug('main', 'APPLICATIONDB::sql::init');
		$sql_conf = $prefs_->get('general', 'sql');
		if (!is_array($sql_conf)) {
			Logger::error('main', 'APPLICATIONDB::sql::init sql conf not valid');
			return false;
		}
		@define('APPLICATION_TABLE', $sql_conf['prefix'].'application');
		$sql2 = SQL::newInstance($sql_conf);
		$APPLICATION_table_structure = array(
			'id' => 'int(8) NOT NULL auto_increment',
			'name' => 'text NOT NULL',
			'description' => 'text NOT NULL',
			'type' => 'text  NOT NULL',
			'executable_path' => 'text NOT NULL',
			'icon_path' => 'text default NULL',
			'package' => 'text NOT NULL',
			'desktopfile' => 'text default NULL',
			'mimetypes' => 'text default NULL',
			'published' => 'tinyint(1) default \'0\'',
			'static' => 'tinyint(1) default \'0\'');

		$ret = $sql2->buildTable($sql_conf['prefix'].'application', $APPLICATION_table_structure, array('id'));

		if ( $ret === false) {
			Logger::error('main', 'APPLICATIONDB::sql::init table '.APPLICATION_TABLE.' fail to created');
			return false;
		}
		else {
			Logger::debug('main', 'APPLICATIONDB::sql::init table '.APPLICATION_TABLE.' created');
			return true;
		}
	}

	public static function enable() {
		return true;
	}

	public function minimun_attributes() {
		return array('name', 'description', 'type', 'executable_path', 'icon_path', 'package', 'desktopfile');
	}
}
