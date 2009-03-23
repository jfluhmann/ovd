<?php
/**
 * Copyright (C) 2009 Ulteo SAS
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

class ConfigElement_sliders_loadbalancing extends ConfigElement {
	public function toHTML() {
		$html_id = $this->htmlID();
		$html = '';
		
		$html .= '<table border="0" cellspacing="1" cellpadding="3">';
		$i = 0;
		foreach ($this->content as $key1 => $value1) {
			$label3 = $html_id.$this->formSeparator.$i.$this->formSeparator;
			$html .= '<tr>';
			$html .= '<td>';
				$html .= $key1;
				$html .= '<input type="hidden" id="'.$label3.'key" name="'.$label3.'key" value="'.$key1.'" size="25" />';
			$html .= '</td>';
			$html .= '<td>';
				$html .= '<div id="'.$html_id.$this->formSeparator.$key1.'_divb">';
				
				// horizontal slider control
				$html .= '<script type="text/javascript">';
				$html .= '
				Event.observe(window, \'load\', function() {
					new Control.Slider(\'handle'.$i.'\', \'track'.$i.'\', {
						range: $R(0,100),
						values: [';
						
						for($buf5=0;$buf5<100;$buf5++) {
							$html .= $buf5.',';
						}
						$html .= $buf5;
						
						$html .= '],
						sliderValue: '.$value1.',
						onSlide: function(v) {
							$(\'slidetxt'.$i.'\').innerHTML = v;
							$(\''.$label3.'value\').value = v;
						},
						onChange: function(v) {
							$(\'slidetxt'.$i.'\').innerHTML = v;
							$(\''.$label3.'value\').value = v;
						}
					});
				});
				';
				$html .= '</script>';
				
				$html .= '<div id="track'.$i.'" style="width: 200px; background-color: rgb(204, 204, 204); height: 10px;"><div class="selected" id="handle'.$i.'" style="width: 10px; height: 15px; background-color: #004985; cursor: move; left: 190px; position: relative;"></div></div>';
	
				$html .= '<input type="hidden" id="'.$label3.'value" name="'.$label3.'value" value="'.$value1.'" size="25" />';
				$html .= '</div>';
			$html .= '</td>';
			$html .= '<td>';
				$html .= '<div id="slidetxt'.$i.'" style="float: right;">'.$value1.'</div>';
			$html .= '</td>';
			$html .= '</tr>';
			$i += 1;
		}
		$html .= '</table>';
		return $html;
	}
}
