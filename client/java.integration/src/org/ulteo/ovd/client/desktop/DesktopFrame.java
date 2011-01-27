/*
 * Copyright (C) 2010 Ulteo SAS
 * http://www.ulteo.com
 * Author Guillaume DUPAS <guillaume@ulteo.com> 2010
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
 */

package org.ulteo.ovd.client.desktop;

import java.awt.Dimension;
import java.awt.GraphicsConfiguration;
import java.awt.GraphicsEnvironment;
import java.awt.Image;
import java.awt.Insets;
import java.awt.Toolkit;
import java.awt.event.InputEvent;
import java.awt.event.KeyEvent;
import java.awt.event.WindowEvent;
import java.awt.event.WindowListener;

import javax.swing.JFrame;
import javax.swing.KeyStroke;
import net.propero.rdp.InputListener;
import net.propero.rdp.RdesktopCanvas;

import org.ulteo.ovd.client.authInterface.LogoutPopup;
import org.ulteo.rdp.RdpActions;

public class DesktopFrame extends JFrame implements WindowListener, InputListener {

	private Image logo = null;
	private RdpActions actions = null;
	public static int screenWidth = Toolkit.getDefaultToolkit().getScreenSize().width;
	public static int screenHeight = Toolkit.getDefaultToolkit().getScreenSize().height;
	private static GraphicsConfiguration gconf = GraphicsEnvironment.getLocalGraphicsEnvironment().getDefaultScreenDevice().getDefaultConfiguration();
	private static Insets insets = Toolkit.getDefaultToolkit().getScreenInsets(gconf);
	public static Dimension SMALL_RES = new Dimension(800,600);
	public static Dimension MEDUIM_RES = new Dimension(1024,768);
	public static Dimension HIGH_RES = new Dimension(1280,678);
	public static Dimension MAXIMISED = new Dimension(screenWidth-insets.left-insets.right, screenHeight-insets.top-insets.bottom);
	public static Dimension FULLSCREEN = new Dimension(screenWidth, screenHeight);
	public static Dimension DEFAULT_RES = DesktopFrame.FULLSCREEN;

	private boolean fullscreen = false;
	private RdesktopCanvas canvas = null;
	private ScrollableDesktopFrame scrollFrame = null;
	private KeyStroke fullscreen_keystroke = null;
	
	public DesktopFrame(Dimension dim, boolean fullscreen_, RdpActions actions_) {
		this.fullscreen = fullscreen_;
		this.actions = actions_;
		this.logo = getToolkit().getImage(getClass().getClassLoader().getResource("pics/ulteo.png"));
		setIconImage(logo);
		setSize(dim);
		setPreferredSize(dim);
		this.setTitle("Ulteo Remote Desktop");
		setResizable(false);
		setDefaultCloseOperation(JFrame.DO_NOTHING_ON_CLOSE);
		setLocation(0, 0);
		setVisible(false);
		this.addWindowListener(this);

		if (this.fullscreen)
			this.initFullscreen();
		
		pack();
	}

	private void initFullscreen() {
		this.setAlwaysOnTop(this.fullscreen);
		this.setUndecorated(this.fullscreen);

		this.fullscreen_keystroke = KeyStroke.getKeyStroke(KeyEvent.VK_ENTER, InputEvent.CTRL_DOWN_MASK);

		this.scrollFrame = new ScrollableDesktopFrame(this);
	}

	public void destroy() {
		if (this.fullscreen) {
			this.scrollFrame.setVisible(false);
			this.scrollFrame.dispose();
			this.scrollFrame = null;
		}

		this.setVisible(false);
		this.dispose();
	}

	public void setCanvas(RdesktopCanvas canvas_) {
		this.canvas = canvas_;

		if (! this.fullscreen)
			return;

		this.canvas.getInput().addKeyStroke(this.fullscreen_keystroke);
		this.canvas.getInput().addInputListener(this);
		this.scrollFrame.setCanvas(this.canvas);
	}

	private void escapeFromFullsreen() {
		if (! this.fullscreen || this.scrollFrame == null || ! this.isVisible() || this.scrollFrame.isVisible())
			return;

		this.setVisible(false);
		this.scrollFrame.setVisible(true);
	}

	private void switchToFullsreen() {
		if (! this.fullscreen || this.scrollFrame == null || this.isVisible() || ! this.scrollFrame.isVisible())
			return;

		this.scrollFrame.setVisible(false);
		this.setVisible(true);
	}

	@Override
	public void windowActivated(WindowEvent arg0) {}

	@Override
	public void windowClosed(WindowEvent arg0) {}

	@Override
	public void windowClosing(WindowEvent arg0) {
		if (this.actions != null)
			new LogoutPopup(this, this.actions);
		else
			System.err.println("Can't manage disconnection request: rdpAction is null");
	}

	@Override
	public void windowDeactivated(WindowEvent arg0) {}

	@Override
	public void windowDeiconified(WindowEvent arg0) {}

	@Override
	public void windowIconified(WindowEvent arg0) {}

	@Override
	public void windowOpened(WindowEvent arg0) {}

	public void keyStrokePressed(KeyStroke keystroke, KeyEvent ke) {
		if (! keystroke.equals(this.fullscreen_keystroke))
			return;
		
		if (ke.getComponent() == this.canvas) {
			this.escapeFromFullsreen();
		}
		else if (ke.getComponent() == this.scrollFrame.getView()) {
			this.switchToFullsreen();
		}
	}
}
