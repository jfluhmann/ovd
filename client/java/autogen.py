#! /usr/bin/python

import os, glob, pysvn

path = os.path.dirname( os.path.realpath( __file__ ) )

# Detect the version
if os.environ.has_key("OVD_VERSION"):
	version = os.environ["OVD_VERSION"]
else:
	c = pysvn.Client()
	revision = c.info(path)["revision"].number
	version = "99.99~trunk+svn%05d"%(revision)

langs = []
for pofile in glob.glob(path+"/po/*.po"):
	name = os.path.basename(pofile)[:-3]
	node = '<exec executable="msgfmt" dir=".">'
	node+= '<arg value="--java2"/>'
	node+= '<arg value="-d"/>'
	node+= '<arg value="jar/ressources"/>'
 	node+= '<arg value="-r"/>'
 	node+= '<arg value="Messages"/>'
 	node+= '<arg value="-l"/>'
 	node+= '<arg value="%s"/>'%(name)
 	node+= '<arg value="%s"/>'%(pofile)
 	node+= '</exec>'
	langs.append(node)

f = file(os.path.join(path, "build.xml.in"), "r")
content = f.read()
f.close()

content = content.replace("@VERSION@", str(version))
content = content.replace("@MESSAGE_LANGS@", "\n".join(langs))

f = file(os.path.join(path, "build.xml"), "w")
f.write(content)
f.close()
