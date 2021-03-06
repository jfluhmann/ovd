# -*- coding: utf-8 -*-

# Copyright (C) 2008-2014 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2008, 2009, 2010, 2011, 2012
# Author Laurent CLOUET <laurent@ulteo.com> 2009-2010
# Author David LECHEVALIER <david@ulteo.com> 2012
# Author David PHAM-VAN <d.pham-van@ulteo.com> 2014
#
# This program is free software; you can redistribute it and/or 
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; version 2
# of the License
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

import httplib
from xml.dom import minidom
from xml.dom.minidom import Document

from ovd.Logger import Logger
from ovd.Communication.Dialog import Dialog as AbstractDialog

import Apt
from Platform.ApplicationsDetection import ApplicationsDetection
from Platform.DomainMicrosoft import DomainMicrosoft
from Platform.DomainNovell import DomainNovell
from Platform.DomainUlteo import DomainUlteo
from Platform.DomainLocal import DomainLocal
from Platform.Profile import Profile
from Platform.TS import TS
from Platform.Session import Session
from Platform.User import User

class Dialog(AbstractDialog):
	def __init__(self, role_instance):
		self.role_instance = role_instance
	
	
	@staticmethod
	def getName():
		return "aps"
	
	
	def process(self, request):
		path = request["path"]
		
		if request["method"] == "GET":
			Logger.debug("do_GET "+path)
			
			if path == "/applications":
				return self.req_applications(request)
			
			elif path.startswith("/application/icon/"):
				app_id = path[len("/application/icon/"):]
				return self.req_icon(app_id)
			
			elif  path == "/applications/static/sync":
				return self.req_sync_static_applications(request)
			elif  path == "/scripts/sync":
				return self.req_sync_scripts(request)
			elif path.startswith("/session/status/"):
				buf = path[len("/session/status/"):]
				return self.req_session_status(buf)
			
			elif path.startswith("/session/destroy/"):
				buf = path[len("/session/destroy/"):]
				return self.req_session_destroy(buf)
			
			elif path.startswith("/session/disconnect/"):
				buf = path[len("/session/disconnect/"):]
				return self.req_session_disconnect(buf)
			
			elif path.startswith("/debian/") and self.role_instance.canManageApplications():
				buf = path[len("/debian/"):]
				return self.req_debian_id(buf)
			
			return None
		
		elif request["method"] == "POST":
			Logger.debug("do_POST "+path)
			if path == "/session/create":
				return self.req_session_create(request)
			
			elif path == "/user/loggedin":
				return self.req_user_loggedin(request)
			
			elif path == "/user/logout":
				return self.req_user_logout(request)
			
			elif  path == "/debian" and self.role_instance.canManageApplications():
				return self.req_debian(request)
			
			elif path == "/applications/ids":
			  return self.req_applications_matching(request)
			
			return None
		
		return None
	
	
	@staticmethod
	def session2xmlstatus(session):
		doc = Document()
		rootNode = doc.createElement('session')
		rootNode.setAttribute("id", session.id)
		rootNode.setAttribute("status", session.status)
		
		if session.status == Session.SESSION_STATUS_DESTROYED and session.end_status is not None:
			rootNode.setAttribute("reason", session.end_status)
		
		doc.appendChild(rootNode)
		
		return doc
	
	
	def req_applications(self, request):
		doc = Document()
		rootNode = doc.createElement('applications')
		doc.appendChild(rootNode)
		
		self.role_instance.applications_mutex.acquire()
		
		for application in self.role_instance.applications.values():
			appNode = doc.createElement("application")
			appNode.setAttribute("id", application["local_id"])
			appNode.setAttribute("name", application["name"])
			appNode.setAttribute("desktopfile", application["filename"])
			if application.has_key("description"):
				appNode.setAttribute("description", application["description"])
			if application.has_key("package"):
				appNode.setAttribute("package", application["package"])
			exeNode = doc.createElement("executable")
			exeNode.setAttribute("command", application["command"])
			#if application.has_key("icon"):
			#	exeNode.setAttribute("icon", application["icon"])
			for mime in application["mimetypes"]:
				mimeNode = doc.createElement("mime")
				mimeNode.setAttribute("type", mime)
				appNode.appendChild(mimeNode)
			
			appNode.appendChild(exeNode)
			
			rootNode.appendChild(appNode)
		
		self.role_instance.applications_mutex.release()
		
		return self.req_answer(doc)
	
	
	def req_applications_matching(self, request):
		try:
			document = minidom.parseString(request["data"])
			rootNode = document.documentElement
			
			if rootNode.nodeName != "applications":
				raise Exception("invalid root node")
			
			matching = []
			applicationNodes = rootNode.getElementsByTagName("application")
			for node in applicationNodes:
				matching.append((node.getAttribute("id"), node.getAttribute("local_id")))
		
		except Exception:
			Logger.exception("Invalid xml input")
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		self.role_instance.applications_mutex.acquire()
		
		self.role_instance.applications_id_SM = {}
		
		for (sm_id, local_id) in matching:
			if not self.role_instance.applications.has_key(local_id):
				continue
			
			self.role_instance.applications[local_id]["id"] = sm_id
			self.role_instance.applications_id_SM[sm_id] = self.role_instance.applications[local_id]
		
		self.role_instance.applications_mutex.release()
		
		doc = Document()
		rootNode = doc.createElement('applications')
		rootNode.setAttribute("matching", "ok")
		doc.appendChild(rootNode)
		
		return self.req_answer(doc)
	
	
	def req_icon(self, app_id):
		if self.role_instance.applications is None:
			return self.req_unauthorized()
		
		self.role_instance.applications_mutex.acquire()
		
		if not self.role_instance.applications_id_SM.has_key(app_id):
			self.role_instance.applications_mutex.release()
			return self.req_unauthorized()
		
		app =  self.role_instance.applications_id_SM[app_id]
		
		self.role_instance.applications_mutex.release()
		
		appsdetect = ApplicationsDetection()
		data = appsdetect.getIcon(app["filename"])
		if data is None:
			return self.req_not_found()
		
		response = {}
		response["code"] = httplib.OK
		response["Content-Type"] = "image/png"
		response["data"] = data
		return response
	
	
	def req_session_create(self, request):
		if self.role_instance.stopping():
			return self.req_stopping(request)
		
		environment = DomainUlteo()
		try:
			document = minidom.parseString(request["data"])
			sessionNode = document.documentElement
			
			if sessionNode.nodeName != "session":
				raise Exception("invalid root node")
			
			if not sessionNode.hasAttribute("id"):
				raise Exception("invalid root node")
			
			if not sessionNode.hasAttribute("mode"):
				raise Exception("invalid root node")
			
			session = {}
			session["id"] = sessionNode.getAttribute("id")
			session["mode"] = sessionNode.getAttribute("mode")
			
			if len(session["id"])==0:
				raise Exception("Missing attribute id")
			
			if session["mode"] == "desktop":
				session["mode"] = Session.MODE_DESKTOP
			elif session["mode"] == "applications":
				session["mode"] = Session.MODE_APPLICATIONS
			else:
				raise Exception("Missing attribute id")
			
			nodes = sessionNode.getElementsByTagName("environment")
			if len(nodes)>0:
				environmentNode = nodes[0]
				name = environmentNode.getAttribute("id")
				
				if name == "Microsoft":
					environment = DomainMicrosoft()
				elif name == "Novell":
					environment = DomainNovell()
				elif name == "Local":
					environment = DomainLocal()
				else:
					raise Exception("unknown environment '%s'"%(name))
				
				ret = environment.parse(environmentNode)
				if ret is False:
					raise Exception("invalid environment schema")
			
			
			userNode = sessionNode.getElementsByTagName("user")[0]
			
			for attr in ["login", "password", "displayName"]:
				if not userNode.hasAttribute(attr):
					raise Exception("invalid child node: missing attribute "+attr)
				
				session[attr] = userNode.getAttribute(attr)
			
			applications = {}
			
			self.role_instance.applications_mutex.acquire()
			applicationNodes = sessionNode.getElementsByTagName("application")
			for node in applicationNodes:
				if node.parentNode != sessionNode:
					continue
				
				app_id = node.getAttribute("id")
				if self.role_instance.applications_id_SM.has_key(app_id):
					applications[app_id] = self.role_instance.applications_id_SM[app_id]
				
				elif self.role_instance.static_apps.applications.has_key(app_id):
					applications[app_id] = self.role_instance.static_apps.applications[app_id]
				
				else:
					self.role_instance.applications_mutex.release()
					Logger.warn("Unknown application id %s"%(app_id))
					raise Exception("Unknown application id %s"%(app_id))
			
			self.role_instance.applications_mutex.release()
			
			shellNode = sessionNode.getElementsByTagName("shell")[0]
			
			session["parameters"] = {}
			for node in sessionNode.getElementsByTagName("parameter"):
				session["parameters"][node.getAttribute("name")] = node.getAttribute("value")
			
			
			nodes = sessionNode.getElementsByTagName("profile")
			if len(nodes)>0:
				profileNode = nodes[0]
				for attribute in ("rid", "uri", "profile_mode"):
					if len(profileNode.getAttribute(attribute)) == 0:
						raise Exception("Empty attribute "+attribute)
			else:
				profileNode = None
			
			sharedfolderNodes = sessionNode.getElementsByTagName("sharedfolder")
			for node in sharedfolderNodes:
				for attribute in ("rid", "uri", "name"):
					if len(node.getAttribute(attribute)) == 0:
						raise Exception("Empty attribute "+attribute)
		
		except Exception:
			Logger.exception("Invalid xml input")
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		user = User(session["login"], {"displayName": session["displayName"], "password": session["password"]})
		if session["parameters"].has_key("locale"):
			user.infos["locale"] = session["parameters"]["locale"]
		
		session = Session(session["id"], session["mode"], user, session["parameters"], applications.values())
		session.setDomain(environment)
		session.setShellConf(shellNode)
		session.init()
		
		if profileNode is not None or len(sharedfolderNodes)>0:
			profile = Profile(session)
		
		if profileNode is not None:
			folder = {}
			for (key, value) in profileNode.attributes.items():
				folder[key] = value
			
			profile.setProfile(folder)
		
		for sharedFolderNode in sharedfolderNodes:
			folder = {}
			for (key, value) in sharedFolderNode.attributes.items():
				folder[key] = value
			
			profile.addSharedFolder(folder)
		
		if self.role_instance.sessions.has_key(session.id):
			Logger.warn("Session %s already exist, aborting creation"%(session.id))
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "user already exist")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		self.role_instance.sessions[session.id] = session
		self.role_instance.spool_action("create", session.id)
		
		return self.req_answer(self.session2xmlstatus(session))
	
	
	def req_session_status(self, session_id):
		if self.role_instance.sessions.has_key(session_id):
			session = self.role_instance.sessions[session_id]
		else:
			session = Session(session_id, None, None, None, None)
			session.status = "unknown"
		
		return self.req_answer(self.session2xmlstatus(session))
	
	
	def req_session_destroy(self, session_id):
		if self.role_instance.sessions.has_key(session_id):
			session = self.role_instance.sessions[session_id]
			if session.status not in [Session.SESSION_STATUS_WAIT_DESTROY, Session.SESSION_STATUS_DESTROYED, Session.SESSION_STATUS_ERROR]:
				# Switch the session status without warn the session manager
				session.switch_status(Session.SESSION_STATUS_WAIT_DESTROY)
				self.role_instance.spool_action("destroy", session.id)
		else:
			session = Session(session_id, None, None, None, None)
			session.status = Session.SESSION_STATUS_UNKNOWN
		
		return self.req_answer(self.session2xmlstatus(session))
	
	def req_session_disconnect(self, session_id):
		if self.role_instance.sessions.has_key(session_id):
			session = self.role_instance.sessions[session_id]
			if session.status == Session.SESSION_STATUS_ACTIVE:
				self.role_instance.spool_action("disconnect", session.id)
		else:
			session = Session(session_id, None, None, None, None)
			session.status = Session.SESSION_STATUS_UNKNOWN
		
		return self.req_answer(self.session2xmlstatus(session))
	
	def req_user_loggedin(self, request):
		try:
			document = minidom.parseString(request["data"])
			rootNode = document.documentElement
			
			if rootNode.nodeName != "user":
				raise Exception("invalid root node")
			
			if not rootNode.hasAttribute("login"):
				raise Exception("invalid root node")
			
			login = rootNode.getAttribute("login")
			
		except:
			Logger.warn("Invalid xml input !!")
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		try:
			ret = TS.getSessionID(login)
		except Exception:
			Logger.exception("RDP server dialog failed ... ")
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "internalerror")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		rootNode.setAttribute("loggedin", str((ret is not None)).lower())
		
		return self.req_answer(document)
	
	
	def req_user_logout(self, request):
		try:
			document = minidom.parseString(request["data"])
			rootNode = document.documentElement
			
			if rootNode.nodeName != "user":
				raise Exception("invalid root node")
			
			if not rootNode.hasAttribute("login"):
				raise Exception("invalid root node")
			
			login = rootNode.getAttribute("login")
			
		except:
			Logger.warn("Invalid xml input !!")
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		
		session = self.role_instance.get_session_from_login(login)
		
		if session is None:
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "unknown user")
			doc.appendChild(rootNode)
			
			return self.req_answer(doc)
		
		self.role_instance.spool_action("logoff", session.id)
		
		return self.req_answer(document)
	
	
	def req_debian(self, request):
		try:
			document = minidom.parseString(request["data"])
			rootNode = document.documentElement
			if rootNode.nodeName != "debian":
				raise Exception("invalid root node")
			
			request = rootNode.getAttribute("request")
			if request not in ["upgrade", "install", "remove", "available"]:
				raise Exception("usage")
			
			packageNodes = rootNode.getElementsByTagName("package")
			if request in ["install", "remove"] and len(packageNodes)==0:
				raise Exception("usage")
			
			packages = []
			for packageNode in packageNodes:
				packages.append(packageNode.getAttribute("name"))
		
		except Exception:
			Logger.exception("Invalid xml input")
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
		
		if request == "available":
			req = Apt.Request_Available()
		else:
			req = Apt.Request_Packages(request, packages)
		
		req_id = self.role_instance.apt.add(req)
		
		return self.req_answer(self.debian_request2xml(req_id, req))
	
	
	def req_stopping(self, req):
		doc = Document()
		rootNode = doc.createElement('error')
		rootNode.setAttribute("id", "server is stopping")
		doc.appendChild(rootNode)
		return self.req_answer(doc)

	
	def req_debian_id(self, req):
		try:
			(rid, request) = req.split("/", 2)
			req = self.role_instance.apt.get(rid)
			if req is None:
				req = Apt.Request()
				req.status = "unknown"
				return self.req_answer(self.debian_request2xml(rid, req))
			
			if request == "status":
				return self.req_answer(self.debian_request2xml(rid, req))
			
			elif request in ["stdout", "stderr"]:
				response = {}
				response["code"] = httplib.OK
				response["Content-Type"] = "text/plain"
				response["data"] = req.getLog(request)
				return response
			
			else:
				raise Exception("usage")
			
		except Exception:
			Logger.exception("Invalid xml input")
			doc = Document()
			rootNode = doc.createElement('error')
			rootNode.setAttribute("id", "usage")
			doc.appendChild(rootNode)
			return self.req_answer(doc)
	
	
	def req_sync_static_applications(self, request):
		self.role_instance.setStaticAppsMustBeSync(True)
		
		doc = Document()
		rootNode = doc.createElement('applications')
		doc.appendChild(rootNode)
		
		return self.req_answer(doc)
	
	def req_sync_scripts(self, request):
		self.role_instance.setScriptsMustBeSync(True)
		doc = Document()
		rootNode = doc.createElement('scripts')
		doc.appendChild(rootNode)
		
		return self.req_answer(doc)
	
	@staticmethod
	def debian_request2xml(rid, request):
		doc = Document()
		rootNode = doc.createElement('debian_request')
		rootNode.setAttribute("id", rid)
		rootNode.setAttribute("status", request.getStatus())
		doc.appendChild(rootNode)
		
		return doc
