#!/bin/bash
#*****************************************************************************************
# Global defines
#*****************************************************************************************
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m' 

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

UNINSTALL=0

function usage()
{
    echo "Usage:"
    echo "    INSTALL"
    echo "    -------"
    echo "    $0 "
    echo " "
    echo "    UNINSTALL"
    echo "    ---------"
    echo "    $0 -u"
    echo " "
    echo "    HELP"
    echo "    -------"
    echo "    $0 -h"
    echo " "
}


while getopts ":huw:" o; do
    case "${o}" in
        h)
            usage
            exit 0
            ;;
        u)
            UNINSTALL=1
            ;;
        *)
            usage
            exit 1
            ;;
    esac
done

#Checks
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}ERROR: Please run as root with sudo${NC}" >&2
  exit 1
fi

if [ "$UNINSTALL" -eq 1 ] ; then
    echo -e "Unistall GPSDaemon"
    systemctl stop GPS
    rm /etc/systemd/system/GPS.service
    systemctl daemon-reload
    rm -r /etc/GPS
    rm -r /var/GPS
    echo -e "${GREEN}Uninstall complete${NC}"
    exit 0;
fi

#*****************************************************************************************
# Decompress Files
#*****************************************************************************************
#
#directory
echo "Creating temporary directory"
if [ -d "${DIR}/tmpfiles" ] ; then
    rm -r ${DIR}/tmpfiles
fi
mkdir -p ${DIR}/tmpfiles
echo "Decompress files"
tar -C ${DIR}/tmpfiles -zxf ${DIR}/rpi.tar.gz

echo -n -e "${YELLOW}Do we need to create a new Machine Id Y/n ${NC}"
read DUMMY
if [ "$DUMMY" == "Y" ] ; then
    chmod 644 /etc/machine-id
    openssl rand -hex 16 > /etc/machine-id
    echo "New machine ID Created"
fi

mkdir -p /etc/GPS
cp ${DIR}/tmpfiles/bin/GPSDaemon.php /etc/GPS/GPSDaemon.php
cp ${DIR}/tmpfiles/bin/GPSSetup.php /etc/GPS/GPSSetup.php

mkdir -p /etc/GPS/includes
cp ${DIR}/tmpfiles/includes/classNMEA.php /etc/GPS/includes/classNMEA.php

chmod +x /etc/GPS/GPSDaemon.php

mkdir -p /var/GPS
chmod 777 /var/GPS


echo "Copy the installAll script"
cp ${DIR}/tmpfiles/scripts/installAll.sh ../installAll.sh
chmod +x ../installAll.sh

#create configuration file
echo "[source]
api=myTrackApi.php
hostname=track.devt.nz
maxspeed=500
mindist=50
" > /etc/GPS/GPS.conf


#Need to copy the service file 
cp ${DIR}/tmpfiles/bin/GPS.service /etc/systemd/system

echo "Enabling the GPS service"
systemctl daemon-reload

systemctl start GPS
systemctl enable GPS
systemctl status GPS

echo "Cleanup"
#rm -r ${DIR}/tmpfiles

echo -e "${GREEN}Installed${NC}"