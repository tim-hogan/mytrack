#!/bin/bash
#-------------------------------------------------------------------------
# Scirpt to install a mytrack
# 
#   


#-------------------------------------------------------------------------
# Start

#*****************************************************************************************
# Current Directory
#*****************************************************************************************
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
DATE=$(date +'%Y-%m-%dT%H%M')

#*****************************************************************************************
# Global defines
#*****************************************************************************************
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m' 

#*****************************************************************************************
# Directories
#*****************************************************************************************
WEBDIR="/var/www/html/mytrack"
APACHEDIR="/etc/apache2"


#*****************************************************************************************
# Options
#*****************************************************************************************
UNINSTALL=false
INSTALLDB=true
INSTALLWEB=true

#*****************************************************************************************
# Names
#*****************************************************************************************
DBNAME="mytrack"
HOSTNAME="track.devt.nz"

function error()
{
    echo -e "${RED}ERROR: Invalid parameters${NC}"
}

function usage()
{
    echo "Usage:"
    echo "    INSTALL"
    echo "    -------"
    echo "    $0 [-fg] [-d domain name]"
    echo " "
    echo "    UNINSTALL"
    echo "    ---------"
    echo "    $0 -u [-d domain name]"
    echo " "
    echo "    OPTIONS"
    echo "    ---------"
    echo "    -d Domian/Host name to use. The default is [track.devt.nz]"
    echo "    -f Install files only"
    echo "    -g Install files and database (Skip website)"

}

while getopts ":d:fgho:u" o; do
    case "${o}" in
        d)
            HOSTNAME=${OPTARG}
            ;;
        f)
            INSTALLDB=false
            INSTALLWEB=false
            ;;
        g)
            INSTALLWEB=false
            ;;
        h)
            usage
            exit 0
            ;;
        u)
            UNINSTALL=true
            ;;
        *)
            error
            usage
            exit 1
            ;;
    esac
done


#check all parameters
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}ERROR: Please run as root with sudo${NC}" >&2
  exit 1
fi


#*****************************************************************************************
# Uninstall
#*****************************************************************************************
#
if $UNINSTALL ; then
    echo -e "${YELLOW}About to uninstall the mytrack solution, enter y to confirm${NC}"
    read DUMMY
    if [ "$DUMMY" == "y" ] ; then
        
        echo "Disabling WEB Site"
        a2dissite ${HOSTNAME}
        systemctl reload apache2
        rm $APACHEDIR/sites-available/${HOSTNAME}*
        
        echo "Removing Files"
        rm -r $WEBDIR

        echo "Removing Database"
        mysql -e "Drop database ${DBNAME}"
        DB_USER=$(/etc/vault/getKey -s mytrack -k DATABASE_USER)
        mysql -e "DROP USER '${DB_USER}'"
        vault deleteshelf -s mytrack
       
        echo "Deleteing SSL Certificate"
        certbot -n delete --cert-name $HOSTNAME
        echo -e  "${GREEN}Uninstall complete${NC}"
    else
        echo -e "${RED}Uninstall aborted by user input${NC}"
        exit 1
    fi

    exit 0
fi

echo -e "${GREEN}Starting install of mytrack solution${NC}"

#*****************************************************************************************
# Decompress Files
#*****************************************************************************************
#
#directory
if [ -d "${DIR}/tmpfiles" ] ; then
    rm -r ${DIR}/tmpfiles
fi
mkdir -p ${DIR}/tmpfiles
tar -C ${DIR}/tmpfiles -zxf ${DIR}/mytrack.tar.gz

#*****************************************************************************************
# Copy files
#*****************************************************************************************
#

echo "Removing old directories"
if [ -d "$WEBDIR" ] ; then
    rm -r $WEBDIR
fi

echo "Copying Web files"
mkdir -p $WEBDIR
cp -rT ${DIR}/tmpfiles/webfiles/ $WEBDIR
chown -R www-data:www-data $WEBDIR


#*****************************************************************************************
# Create database
#*****************************************************************************************
#

if $INSTALLDB ; then
	if [ -d /var/lib/mysql/${DBNAME} ] ; then 
		echo -n -e "${RED}The database ${YELLOW}${DBNAME} ${RED}already exists on this server, do you want to override ${NC}[y/n] "
        read DUMMY
        if [ "$DUMMY" != "y" ] ; then
             INSTALLDB=false
        else
            echo -n -e "${RED}***WARNING*** You are about to override and existing database, this will remove all data from it. Are you sure ${NC} [y/n]"
            read DUMMY
            if [ "$DUMMY" != "y" ] ; then
                INSTALLDB=false
            else
                mysql -e "DROP DATABASE ${DBNAME}"
            fi
        fi
    fi

    if $INSTALLDB ; then
        echo -e "Installing Database"
        mysql -e "CREATE DATABASE IF NOT EXISTS ${DBNAME}"
        mysql ${DBNAME} < ${DIR}/tmpfiles/sql/mytrack.sql
        #*****************************************************************************************
        # DO we need to create a new and password
        #*****************************************************************************************
        #
        echo -e "${YELLOW}Do you want to create a new database MySQL access ? y/n${NC}"
        read DUMMY
        if [ "$DUMMY" == "y" ] ; then
            
            DB_USER="$(openssl rand -hex 8)"
            DB_PW="$(openssl rand -hex 8)"
            PEPPER="$(openssl rand -hex 32)"
            COOKIE_KEY="$(openssl rand -hex 32)" 
            
            KEY_1="$(php -r 'echo base64_encode(openssl_random_pseudo_bytes(32));')" 
            DETAIL_KEY="$(php -r 'echo base64_encode(openssl_random_pseudo_bytes(32));')" 
            

            vault newshelf -s mytrack
            vault add -s mytrack -k PEPPER -v $PEPPER
            vault add -s mytrack -k DATABASE_NAME -v  mytrack
            vault add -s mytrack -k DATABASE_HOST -v  127.0.0.1
            vault add -s mytrack -k DATABASE_USER -v $DB_USER
            vault add -s mytrack -k DATABASE_PW -v $DB_PW
            vault add -s mytrack -k COOKIE_KEY -v $COOKIE_KEY
            vault add -s mytrack -k KEY_1 -v $KEY_1
            
            
            mysql -e "CREATE USER '${DB_USER}'@'%' IDENTIFIED BY '${DB_PW}'"
            mysql -e "GRANT ALL ON mytrack.* TO '${DB_USER}'@'%'"


        fi

        #create global record
        #echo -e "Creating gloabl record"
        #mysql mytrack -e "insert into global (global_default_homepage,global_default_domainname) values ('Admin.php','covidpass.notitia.nz')";
        
        #Create the first user and default source
        echo -e "Creating first user"
        SOURCE_KEY="$(php -r 'echo bin2hex(openssl_random_pseudo_bytes(16));')" 
        SOURCE_ACCESS_KEY="$(php -r 'echo bin2hex(openssl_random_pseudo_bytes(3));')" 
            
        #echo -e "Creating first user"
        #mysql mytrack -e "insert into source (source_name,source_company,source_email,source_key,source_access_key) values ('DEMONSTRATION COMPANY','DEMONSTRATION COMPANY','tim@mobilelocate.co.nz','${SOURCE_KEY}','${SOURCE_ACCESS_KEY}')";
        
        #create first user
        PEPPER=$(/etc/vault/getKey -s mytrack -k PEPPER)
        #create the first user in the database
        
        #echo "Createing first database admin user"
        #create the salt and hash
        SALT="$(openssl rand -hex 32)"
        HASH1="$(echo -n "${SALT}${PEPPER}" | openssl dgst -sha256 | cut -c 10-73)"
        HASH="$(echo -n "admin${HASH1}" | openssl dgst -sha256  | cut -c 10-73)"

        #echo "Username and passwords have been created"
    
        #mysql mytrack -e "INSERT into user (user_lastname,user_username,user_hash,user_salt,user_security,user_verified,user_default_page,user_timezone) values ('Administrator','admin','${HASH}','${SALT}',2047,1,'Admin.php','Pacific/Auckland')"
        
        #Create the first issuer
        #echo -e "Creating default issuer of health"
        #mysql mytrack -e "insert into issuer (issuer_name) values ('nzcp.identity.health.nz')";
        
    fi
else
	if [ -f "${DIR}/tmpfiles/sql/upgrade.sql" ] ; then
		echo "Upgrading database with new schema"
		mysql ${DBNAME} < ${DIR}/tmpfiles/sql/upgrade.sql
	fi
fi

#*****************************************************************************************
# Create Website
#*****************************************************************************************
#

if $INSTALLWEB ; then
    echo -e "Installing Website"

echo "<VirtualHost *:80>
ServerName $HOSTNAME
ServerAdmin webmaster@localhost
DocumentRoot /var/www/html/mytrack
<Directory /var/www/html/mytrack>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
</Directory>
SetEnv VAULTID 220759
SetEnv VAULT_SHELF mytrack
ErrorLog \${APACHE_LOG_DIR}/error.log
CustomLog \${APACHE_LOG_DIR}/access.log combined
Header always set Strict-Transport-Security \"max-age=63072000; includeSubdomains; preload\"
</VirtualHost>" > $APACHEDIR/sites-available/$HOSTNAME.conf

    echo -e "Enable web site"
    a2ensite $HOSTNAME
    systemctl reload apache2
    certbot -n --apache -d $HOSTNAME --redirect
    systemctl reload apache2
fi

echo -e "${GREEN}Completed install of covidpass solution${NC}"
exit 0