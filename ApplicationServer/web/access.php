<?php
/**
 * Copyright (C) 2008 Ulteo SAS
 * http://www.ulteo.com
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

if (! isset($_SESSION) || ! isset($_SESSION['session']))
	die('CRITICAL ERROR'); // That's odd !

$server = $_SERVER['SERVER_NAME'];
$session = $_SESSION['session'];

if (! isset($session) || $session == '')
	die('CRITICAL ERROR'); // That's odd !

if (! isset($_GET['application_id']) || $_GET['application_id'] == '')
	die('CRITICAL ERROR'); // That's odd !

$sshuser = get_from_file(SESSION_PATH.'/'.$session.'/clients/ssh_user');
$sshpass = get_from_file(SESSION_PATH.'/'.$session.'/clients/hexasshpasswd');

$width = @$_SESSION['width'];
$height = @$_SESSION['height'];

if ($_SESSION['mode'] != 'portal' || ($_SESSION['mode'] == 'portal' && $_GET['application_id'] != 'portal')) {
	$vncpass = get_from_file(SESSION_PATH.'/'.$session.'/clients/hexavncpasswd');
	$rfbport = get_from_file(SESSION_PATH.'/'.$session.'/sessions/'.$_GET['application_id'].'/rfb_port');

	$geometry = @file_get_contents(SESSION_PATH.'/'.$session.'/sessions/'.$_GET['application_id'].'/geometry');
	if ($geometry !== false) {
		$buf = explode('x', $geometry);
		$width = $buf[0];
		$height = $buf[1];
	}
}

header('Content-Type: text/xml; charset=utf-8');

$dom = new DomDocument('1.0', 'utf-8');
$session_node = $dom->createElement('session');
$dom->appendChild($session_node);

$parameters_node = $dom->createElement('parameters');
$parameters_node->setAttribute('width', $width);
$parameters_node->setAttribute('height', $height);
$parameters_node->setAttribute('view_only', $_SESSION['parameters']['view_only']);
$session_node->appendChild($parameters_node);

$ssh_node = $dom->createElement('ssh');
$ssh_node->setAttribute('host', $server);
$ssh_node->setAttribute('user', $sshuser);
$ssh_node->setAttribute('passwd', $sshpass);
$ports = array(443, 993, 995);
foreach ($ports as $port) {
	$port_node = $dom->createElement('port');
	$port_text_node = $dom->createTextNode($port);
	$port_node->appendChild($port_text_node);
	$ssh_node->appendChild($port_node);
}
$session_node->appendChild($ssh_node);

if ($_SESSION['mode'] != 'portal' || ($_SESSION['mode'] == 'portal' && $_GET['application_id'] != 'portal')) {
	$vnc_node = $dom->createElement('vnc');
	$vnc_node->setAttribute('port', $rfbport);
	$vnc_node->setAttribute('passwd', $vncpass);
	$vnc_node->setAttribute('quality', $_SESSION['parameters']['quality']);
	$session_node->appendChild($vnc_node);
}

if (isset($_SESSION['parameters']['enable_proxy']) && $_SESSION['parameters']['enable_proxy'] == 1) {
	$proxy_node = $dom->createElement('proxy');
	$proxy_node->setAttribute('type', $_SESSION['parameters']['proxy_type']);
	$proxy_node->setAttribute('host', $_SESSION['parameters']['proxy_host']);
	$proxy_node->setAttribute('port', $_SESSION['parameters']['proxy_port']);
	$proxy_node->setAttribute('username', $_SESSION['parameters']['proxy_username']);
	$proxy_node->setAttribute('password', $_SESSION['parameters']['proxy_password']);
	$session_node->appendChild($proxy_node);
}

$xml = $dom->saveXML();

echo $xml;
