/*
 * Copyright (C) 2010-2011 Ulteo SAS
 * http://www.ulteo.com
 * Author David LECHEVALIER <david@ulteo.com> 2011
 * Author Thomas MOUTON <thomas@ulteo.com> 2010-2011
 * Author Guillaume DUPAS <guillaume@ulteo.com> 2010
 * Author Samuel BOVEE <samuel@ulteo.com> 2011
 * Author Julien LANGLOIS <julien@ulteo.com> 2011
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

package org.ulteo.ovd.client.remoteApps;

import java.awt.Rectangle;

import net.propero.rdp.RdesktopException;
import net.propero.rdp.RdpConnection;

import org.ulteo.Logger;
import org.ulteo.ovd.OvdException;
import org.ulteo.ovd.client.OvdClient;
import org.ulteo.ovd.client.authInterface.LoadingStatus;
import org.ulteo.ovd.Application;
import org.ulteo.ovd.sm.Callback;
import org.ulteo.ovd.sm.Properties;
import org.ulteo.ovd.sm.ServerAccess;
import org.ulteo.ovd.sm.SessionManagerCommunication;
import org.ulteo.ovd.sm.SessionManagerException;
import org.ulteo.rdp.OvdAppChannel;
import org.ulteo.rdp.OvdAppListener;
import org.ulteo.rdp.RdpConnectionOvd;
import java.net.UnknownHostException;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import javax.swing.ImageIcon;
import org.ulteo.ovd.integrated.OSTools;
import org.ulteo.utils.jni.WorkArea;
import org.ulteo.ovd.integrated.Spool;
import org.ulteo.ovd.integrated.SystemAbstract;

public abstract class OvdClientRemoteApps extends OvdClient implements OvdAppListener {

	protected Spool spool = null;
	protected SystemAbstract system = null;

	private int numberOfApplication = 0;
	private int ApplicationIncrement = 0;
	private int ApplicationIndex = 0;

	private int flags = 0;
	private Rectangle screensize = null;
	private int bpp = RdpConnectionOvd.DEFAULT_BPP;

	private boolean debugSeamless = false;
	protected boolean publicated = false;
	protected boolean showDesktopIcons = false;
	
	public OvdClientRemoteApps(SessionManagerCommunication smComm) {
		super(smComm, null, false);
	}

	public OvdClientRemoteApps(SessionManagerCommunication smComm, Callback obj) {
		super(smComm, obj, false);
	}

	@Override
	public String getInstance() {
		if (this.spool == null)
			return null;

		return this.spool.getInstanceName();
	}

	public Spool getSpool() {
		return this.spool;
	}

	public final void setSeamlessDebugEnabled(boolean enabled) {
		this.debugSeamless = enabled;
	}

	@Override
	protected void customizeConnection(RdpConnectionOvd co) {
		try {
			co.addOvdAppListener(this);
		} catch (OvdException ex) {
			Logger.error(co.getServer()+": Failed to add ovd applications listener: "+ex);
		}

		if (! OSTools.is_applet) {
			for (Application app : co.getAppsList()) {
				if (this.system.create(app) == null)
					org.ulteo.Logger.error("The "+app.getName()+" shortcut could not be created");

				int subStatus = this.ApplicationIndex * this.ApplicationIncrement;
				this.obj.updateProgress(LoadingStatus.CLIENT_INSTALL_APPLICATION, subStatus);
				this.ApplicationIndex++;
			}
		}
		
		co.setShell("OvdRemoteApps");
	}

	@Override
	protected void uncustomizeConnection(RdpConnectionOvd co) {
		try {
			co.removeOvdAppListener(this);
		} catch (OvdException ex) {
			Logger.error(co.getServer()+": Failed to remove ovd applications listener: "+ex);
		}
	}
	
	@Override
	protected void runDisconnecting() {}

	@Override
	protected void runSessionTerminated() {
		if (this.spool != null) {
			this.spool.stop();
			this.spool.deleteTree();
			this.spool = null;
		}

		if (! OSTools.is_applet) {
			for (RdpConnectionOvd co : this.connections) {
				for (Application app : co.getAppsList())
					this.system.clean(app);
			}

			this.system.refresh();
		}
	}

	public abstract void ovdInited(OvdAppChannel o);

	public void ovdInstanceStarted(int instance_) {}
	public void ovdInstanceStopped(int instance_) {}
	public void ovdInstanceError(int instance_) {}

	protected void configureRDP(Properties properties) {
		this.screensize = WorkArea.getWorkAreaSize();

		this.flags = 0x00;
		this.flags |= RdpConnectionOvd.MODE_APPLICATION;

		if (properties.isMultimedia())
			this.flags |= RdpConnectionOvd.MODE_MULTIMEDIA;

		if (properties.isPrinters())
			this.flags |= RdpConnectionOvd.MOUNT_PRINTERS;

		if (properties.isDrives() == Properties.REDIRECT_DRIVES_FULL)
			this.flags |= RdpConnectionOvd.MOUNTING_MODE_FULL;
		else if (properties.isDrives() == Properties.REDIRECT_DRIVES_PARTIAL)
			this.flags |= RdpConnectionOvd.MOUNTING_MODE_PARTIAL;
		
		if (this.debugSeamless)
			this.flags |= RdpConnectionOvd.DEBUG_SEAMLESS;
		
		this.bpp = properties.getRDPBpp();
	}

	protected RdpConnectionOvd initRDPConnection(ServerAccess server) {
		if (server == null)
			return null;

		if (this.screensize == null) {
			Logger.error("Failed to initialize RDP connection: RDP configuration is not set");
			return null;
		}

		RdpConnectionOvd rc = null;

		try {
			rc = new RdpConnectionOvd(this.flags);
		} catch (RdesktopException ex) {
			Logger.error("Unable to create RdpConnectionOvd object: "+ex.getMessage());
			return null;
		}

		try {
			rc.initSecondaryChannels();
		} catch (RdesktopException ex) {
			Logger.error("Unable to init channels of RdpConnectionOvd object: "+ex.getMessage());
		}

		if (server.getModeGateway()) {
			if (server.getToken().equals("")) {
					Logger.error("Server need a token to be identified on gateway, so token is empty !");
					return null;
			} else {
				rc.setCookieElement("token", server.getToken());
			}

			try {
				rc.useSSLWrapper(server.getHost(), server.getPort());
			} catch(OvdException ex) {
				Logger.error("Unable to create RdpConnectionOvd SSLWrapper: " + ex.getMessage());
				return null;
			} catch(UnknownHostException ex) {
				Logger.error("Undefined error during creation of RdpConnectionOvd SSLWrapper: " + ex.getMessage());
				return null;
			}
		}

		rc.setServer(server.getHost());
		rc.setCredentials(server.getLogin(), server.getPassword());

		if (this.keymap != null)
			rc.setKeymap(this.keymap);

		if (! OSTools.is_applet) {
			HashMap<Integer, ImageIcon> appsIcons = new HashMap<Integer, ImageIcon>();
			List<String> mimesTypes = new ArrayList<String>();
			for (org.ulteo.ovd.sm.Application appItem : server.getApplications()) {
				if (this.isCancelled)
					return null;

				try {
					int subStatus = this.ApplicationIndex * this.ApplicationIncrement;
					this.obj.updateProgress(LoadingStatus.SM_GET_APPLICATION, subStatus);

					int appId = appItem.getId();
					ImageIcon appIcon = this.system.getAppIcon(appId);
					if (appIcon == null) {
						appIcon = this.smComm.askForIcon(Integer.toString(appItem.getId()));

						if (appIcon != null)
							appsIcons.put(appId, appIcon);
					}

					Application app = new Application(rc, appId, appItem.getName(), appItem.getMimes(), appIcon);

					for (String mimeType : app.getSupportedMimeTypes()) {
						if (mimesTypes.contains(mimeType))
							continue;

						mimesTypes.add(mimeType);
					}

					rc.addApp(app);
				} catch (SessionManagerException ex) {
					Logger.warn("Cannot get the \""+appItem.getName()+"\" icon: "+ex.getMessage());
				}
				this.ApplicationIndex++;
			}
			int updatedIcons = this.system.updateAppsIconsCache(appsIcons);
			if (updatedIcons > 0)
				Logger.info("Applications cache updated: "+updatedIcons+" icons");

			HashMap<String, ImageIcon> mimeTypesIcons = new HashMap<String, ImageIcon>();
			for (String each : mimesTypes) {
				if (this.system.getMimeTypeIcon(each) != null)
					continue;

				ImageIcon icon = null;
				try {
					icon = this.smComm.askForMimeTypeIcon(each);
				} catch (SessionManagerException ex) {
					Logger.error("Failed to get "+each+" icon from session manager: "+ex.getMessage());
					continue;
				}
				if (icon == null) {
					Logger.error("Weird. Mime type "+each+" has no icon?");
					continue;
				}

				mimeTypesIcons.put(each, icon);
			}

			updatedIcons = this.system.updateMimeTypesIconsCache(mimeTypesIcons);
			if (updatedIcons > 0)
				Logger.info("Mime-types cache updated: "+updatedIcons+" icons");

			mimeTypesIcons.clear();
			mimeTypesIcons = null;
		}

		// Ensure that width is multiple of 4
		// Prevent artifact on screen with a with resolution
		// not divisible by 4
		rc.setGraphic((int) this.screensize.width & ~3, (int) this.screensize.height, this.bpp);
		rc.setGraphicOffset(this.screensize.x, this.screensize.y);

		this.connections.add(rc);

		return rc;
	}

	protected Integer createRDPConnectionAndConnect(ServerAccess server) {
		RdpConnectionOvd co = this.initRDPConnection(server);

		if (co == null)
			return null;

		this.configureRDPConnection(co);

		co.connect();
		
		return new Integer(this.connections.indexOf(co));
	}

	@Override
	protected boolean createRDPConnections() {
		if (this.smComm == null) {
			Logger.error("[Programmer error] OvdclientRemoteApps.createRDPConnections() can be used only if 'smComm' variable is not null");
			return false;
		}
		
		Properties properties = this.smComm.getResponseProperties();

		this.configureRDP(properties);
		
		List<ServerAccess> serversList = this.smComm.getServers();
		this.numberOfApplication = 0;

		for (ServerAccess server : serversList)
			this.numberOfApplication += server.getApplications().size();

		this.ApplicationIncrement = 100 / numberOfApplication;
		this.ApplicationIndex = 0;

		for (ServerAccess server : serversList) {
			if (this.isCancelled)
				return false;

			if (this.initRDPConnection(server) == null)
				return false;
		}
		this.obj.updateProgress(LoadingStatus.SM_GET_APPLICATION, 100);
		this.ApplicationIndex = 0;
		
		return true;
	}

	@Override
	public boolean checkRDPConnections() {
		String session_status = null;
		try {
			session_status = this.smComm.askForSessionStatus();
		} catch (SessionManagerException ex) {
			Logger.error("checkRDPConnections -- Failed to get session status from session manager: "+ex.getMessage()+". Will exit.");
			for (RdpConnectionOvd co : this.performedConnections) {
				this.hide(co);
			}
			return false;
		}
		if (session_status == null) {
			Logger.error("checkRDPConnections -- Failed to get session status from session manager: Internal error. Will exit.");
			for (RdpConnectionOvd co : this.performedConnections) {
				this.hide(co);
			}
			return false;
		}

		if (!(session_status.equalsIgnoreCase(SessionManagerCommunication.SESSION_STATUS_INITED) || session_status.equalsIgnoreCase(SessionManagerCommunication.SESSION_STATUS_ACTIVE))) {
			Logger.info("checkRDPConnections -- Your session has ended. Will exit.");
			for (RdpConnectionOvd co : this.performedConnections) {
				this.hide(co);
			}
			return false;
		}

		boolean retry = false;

		int nbApps = 0;
		int nbAppsAvailable = 0;

		for (RdpConnectionOvd co : this.performedConnections) {
			int nbAppsByServer = co.getAppsList().size();
			nbApps += nbAppsByServer;

			RdpConnection.State state = co.getState();

			if (state == RdpConnection.State.CONNECTED) {
				nbAppsAvailable += nbAppsByServer;
				continue;
			}

			if (state != RdpConnection.State.FAILED) {
				Logger.debug("checkRDPConnections "+co.getServer()+" -- Bad connection state("+state+"). Will continue normal process.");
				continue;
			}

			int tryNumber = co.getTryNumber();
			if (tryNumber < 1) {
				Logger.debug("checkRDPConnections "+co.getServer()+" -- Bad try number("+tryNumber+"). Will continue normal process.");
				continue;
			}

			if (tryNumber > 1) {
				Logger.error("checkRDPConnections "+co.getServer()+" -- Several try to connect failed.");
				this.hide(co);
				continue;
			}

			Logger.warn("checkRDPConnections "+co.getServer()+" -- Connection failed. Will try to reconnect.");
			this.performedConnections.remove(co);
			co.connect();
			retry = true;
		}

		if (retry)
			return true;

		float percent = ((float) (100 * nbAppsAvailable)) / nbApps;
		if (percent < 50) {
			Logger.error("Less than 50 percent of applications are available("+percent+"%). Will exit.");
			return false;
		}
		Logger.warn("More than 50 percent of applications are available("+percent+"%). Will continue.");

		return true;
	}
	
	@Override
	protected void display(RdpConnection co) {}
	
	public boolean togglePublications() {
		if (this.publicated) {
			this.unpublish();
		} else {
			this.publish();
		}
		return this.publicated;
	}

	private void publish() {
		for (RdpConnectionOvd co : this.getAvailableConnections()) {
			if (! co.getOvdAppChannel().isReady())
				continue;
			boolean associate = (co.getFlags() & RdpConnectionOvd.MOUNTING_MODE_MASK) != 0;

			for (Application app : co.getAppsList()) {
				this.system.install(app, this.showDesktopIcons, associate);
			}
		}
		this.system.refresh();
		this.publicated = true;
	}

	private void unpublish() {
		for (RdpConnectionOvd co : this.getAvailableConnections()) {
			for (Application app : co.getAppsList()) {
				this.system.uninstall(app);
			}
		}
		this.system.refresh();
		this.publicated = false;
	}
}
