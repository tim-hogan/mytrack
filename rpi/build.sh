#!/bin/bash

#-------------------------------------------------------------------------
# Start

#*****************************************************************************************
# Current Directory
#*****************************************************************************************
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
DATE=$(date +'%Y-%m-%dT%H%M')
BRANCH="main"

#*****************************************************************************************
# Global defines
#*****************************************************************************************
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m' 


emptyandcreate () {
	if [ -d "$1" ]; then
		rm -r $1
	fi
	mkdir -p $1
}


#*****************************************************************************************
# Git pulls
#*****************************************************************************************
echo -e "Getting GPS mytrack from GitHub"
rm -fr .git
rm -fr *
git init
git remote add mytrack git@github.com:tim-hogan/mytrack.git
git pull mytrack $BRANCH

chmod +x ./rpi/src/install.sh
#*****************************************************************************************
# Start of build
#*****************************************************************************************
echo -e "Build start"

#create the install directory
emptyandcreate install

#create the package directory
emptyandcreate packagefiles

#copy the install script
cp ./rpi/src/install.sh						./install

#change directory to the files and copy them up.
cd packagefiles

emptyandcreate bin
cp ../rpi/src/GPSDaemon.php						./bin
cp ../rpi/src/GPS.service			      		./bin
cp ../rpi/src/GPSSetup.php						./bin

emptyandcreate includes
cp ../rpi/src/includes/classNMEA.php			./includes

emptyandcreate scripts
cp ../rpi/src/installAll.sh					    ./scripts

#All files now copied, now packge it up
tar -zcf ../install/rpi.tar.gz .

cd ..

#Copy back the build
cp ./rpi/build.sh .
chmod +x build.sh

echo -e "The file will be packaged with a password"
echo -en "${CYAN}"
zip -er install.zip install > /dev/null
echo -en "${NC}"

echo -e "${YELLOW}You will be asked for the deVT password as we are about to copy to the devt host${NC}"
echo -e "rename /var/www/html/static/mytrack/rpi/install.zip /var/www/html/static/mytrack/rpi/install-${DATE}.zip\n put install.zip /var/www/html/static/mytrack/rpi/install.zip" | sftp deVT@devt.nz

#rm -r install
#rm install.zip
#rm -r packagefiles
#rm -r rpi
#rm -r src
#rm version
#rm README.md


echo -e "${GREEN}Build Complete${NC}"
exit 0

