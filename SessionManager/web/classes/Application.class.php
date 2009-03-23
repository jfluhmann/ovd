<?php
/**
 * Copyright (C) 2008 Ulteo SAS
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

require_once(dirname(__FILE__).'/../includes/core.inc.php');

class Application {
	private $attributes;

	public function __construct($id_=NULL, $name_=NULL, $description_=NULL, $type_=NULL, $executable_path_=NULL, $package_=NULL, $icon_path_=NULL, $published_=true,$desktopfile_=NULL) {
		$this->attributes = array();
		$this->attributes['id'] = $id_;
		$this->attributes['name'] = $name_;
		$this->attributes['description'] = $description_;
		$this->attributes['type'] = $type_;
		$this->attributes['executable_path'] = $executable_path_;
		$this->attributes['icon_path'] = $icon_path_;
		$this->attributes['package'] = $package_;
		$this->attributes['published'] = (bool)($published_);
		$this->attributes['desktopfile'] = $desktopfile_;

	}

	public function __toString() {
		$ret = 'Application(';
		foreach ($this->attributes as $k=>$attr)
				$ret .= "'$k':'$attr', ";
		$ret .= ')';
		return $ret;
	}

	public function setAttribute($myAttribute_,$value_){
		$this->attributes[$myAttribute_] = $value_;
	}

	public function hasAttribute($myAttribute_){
		return isset($this->attributes[$myAttribute_]);
	}

	public function getAttribute($myAttribute_){
		if (isset($this->attributes[$myAttribute_]))
			return $this->attributes[$myAttribute_];
		else
			return NULL;
	}

	public function haveIcon() {
		if (file_exists(CACHE_DIR.'/image/application/'.$this->getAttribute('id').'.png'))
			return true;

		if (!check_folder(CACHE_DIR.'/image') || !check_folder(CACHE_DIR.'/image/application'))
			return false;
		
		return $this->getIcon();
	}

	public function getIcon() {
		$servers_liaisons = Abstract_Liaison::load('ApplicationServer', $this->getAttribute('id'), NULL);
		$servers = array();
		foreach ($servers_liaisons as $servers_liaison) {
			$buf = Abstract_Server::load($servers_liaison->group);

			if ($buf != false && $buf->isOnline())
				$servers[] = $buf;
		}

		if (!is_array($servers) || count($servers) == 0)
			return false;

		$random_server = $servers[array_rand($servers)];

		$buf = query_url('http://'.$random_server->fqdn.':'.$random_server->web_port.'/webservices/icon.php?path='.base64_encode($this->getAttribute('icon_path')).'&desktopfile='.base64_encode($this->getAttribute('desktopfile')));

		if ($buf == false || $buf == '')
			return false;

		@file_put_contents(CACHE_DIR.'/image/application/'.$this->getAttribute('id').'.png', $buf);

		return true;
	}

	public function groups(){
		Logger::debug('main','APPLICATION::groups');
		$result = array();
		$rows = Abstract_Liaison::load('AppsGroup', $this->attributes['id'],NULL);
		foreach ($rows as $row){
			$g = new AppsGroup();
			$g->fromDB($row->group);
			$result []= $g;
		}
		return $result;
	}

	public function getAttributesList() {
		return array_keys($this->attributes);
	}

	public function toXML() {
		$list_attr = $this->getAttributesList();
		foreach ($list_attr as $k => $v) {
			if (in_array($v, array('executable_path', 'icon_path')))
				unset($list_attr[$k]);
		}

		$dom = new DomDocument();
		$application_node = $dom->createElement('application');
		$executable_node = $dom->createElement('executable');

		if ( $this->hasAttribute('executable_path'))
			$executable_node->setAttribute('command', $this->attributes['executable_path']);
		if ( $this->hasAttribute('icon_path'))
			$executable_node->setAttribute('icon', $this->attributes['icon_path']);

		foreach ($list_attr as $attr_name) {
			$application_node->setAttribute($attr_name, $this->attributes[$attr_name]);
		}
		$application_node->appendChild($executable_node);
		$dom->appendChild($application_node);
		return $dom->saveXML();
	}

	protected function delete(){
		Logger::debug('main','APPLICATION::delete');
		unset($this->attributes);
		$this->attributes = array();
	}
}
