#!/usr/bin/env bash
#INSTALLATION INSTRUCTIONS
#------------------------- for Kali Linux
#
#0/ Quick MISP Instance on Kali Linux - Status
#---------------------------------------------
#
#1/ Prepare Kali with a MISP User
#--------------------------------
# To install MISP on Kali copy paste this in your r00t shell:
# wget -O /tmp/misp-kali.sh https://raw.githubusercontent.com/MISP/MISP/2.4/INSTALL/INSTALL.debian.sh && bash /tmp/misp-kali.sh
# /!\ Please read the installer script before randomly doing the above.
# The script is tested on a plain vanilla Kali Linux Boot CD and installs quite a few dependencies.

# Leave empty for NO debug messages.
DEBUG=

# Function Section

## Usage of this script
usage () {
  echo "Please specify what type of MISP if you want to install."
  space
  echo "${0} -c | Install ONLY MISP Core"                   # core
  echo "                    -V | Core + Viper"              # viper
  echo "                    -M | Core + MISP modules"       # modules
  echo "                    -D | Core + MISP dashboard"     # dashboard
  echo "                    -m | Core + Mail 2 MISP"        # mail2
  echo "                    -A | Install all of the above"  # all
  space
  echo "                    -C | Only do pre-install checks and exit" # pre
  space
  echo "Options can be combined: ${0} -V -D # Will install Core+Viper+Dashboard"
  space
}

checkOpt () {
  # checkOpt feature
  containsElement $1 "${options[@]}"
}

setOpt () {
  options=()
  for o in $@; do 
    option=$(
    case "$o" in
      ("-c") echo "core" ;;
      ("-V") echo "viper" ;;
      ("-M") echo "modules" ;;
      ("-D") echo "dashboard" ;;
      ("-m") echo "mail2" ;;
      ("-A") echo "all" ;;
      ("-C") echo "pre" ;;
      #(*) echo "$o is not a valid argument" ;;
    esac)
    options+=($option)
  done
}

# Extract debian flavour
checkFlavour () {
  FLAVOUR=$(lsb_release -s -i |tr [A-Z] [a-z])
}

# Dynamic horizontal spacer
space () {
  # Check terminal width
  num=`tput cols`
  for i in `seq 1 $num`; do
    echo -n "-"
  done
  echo ""
}

# Simple debug function with message
debug () {
  echo $1
  if [ ! -z $DEBUG ]; then
    echo "Debug Mode, press enter to continue..."
    read
  fi
}

# Check if element is contained in array
containsElement () {
  local e match="$1"
  shift
  for e; do [[ "$e" == "$match" ]] && return 0; done
  return 1
}

# Simple function to check command exit code
checkFail () {
  if [[ $2 -ne 0 ]]; then
    echo "iAmError: $1"
    echo "The last command exited with error code: $2"
    exit $2
  fi
}

# Check if misp user is present and if run as root
checkID () {
  if [[ $EUID == 0 ]]; then
   echo "This script cannot be run as a root"
   exit 1
  elif [[ $(id $MISP_USER >/dev/null; echo $?) -ne 0 ]]; then
    echo "There is NO user called '$MISP_USER' create a user '$MISP_USER' or continue as $USER? (y/n) "
    read ANSWER
    ANSWER=$(echo $ANSWER |tr [A-Z] [a-z])
    if [[ $ANSWER == "y" ]]; then
      useradd -s /bin/bash -m -G adm,cdrom,sudo,dip,plugdev,www-data $MISP_USER
      echo $MISP_USER:$MISP_PASSWORD | chpasswd
      echo "User $MISP_USER added, password is: $MISP_PASSWORD"
    elif [[ $ANSWER == "n" ]]; then
      echo "Using $USER as install user, hope that is what you want."
      MISP_USER=$USER
    else
      echo "yes or no was asked, try again."
      exit 1
    fi
  else
    echo "User ${MISP_USER} exists, skipping creation"
  fi
}

# check if sudo is installed
checkSudo () {
sudo -H -u $MISP_USER ls -la /tmp > /dev/null 2> /dev/null
if [[ $? -ne 0 ]]; then
  echo "sudo seems to be not installed or working, please fix this before continuing the installation."
  echo "apt install sudo # As root should be enough, make sure the $MISP_USER is able to run sudo."
  exit 1
fi
}

# check is /usr/local/src is RW by misp user
checkUsrLocalSrc () {
if [[ -e /usr/local/src ]]; then
  if [[ -w /usr/local/src ]]; then
    echo "Good, /usr/local/src exists and is writeable as $MISP_USER"
  else
    echo -n "/usr/local/src need to be writeable by $MISP_USER, permission to fix? (y/n)"
    read ANSWER
    ANSWER=$(echo $ANSWER |tr [A-Z] [a-z])
  fi
fi

}

# Because Kali is l33t we make sure we run as root
kaliOnRootR0ckz () {
  if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root"
   exit 1
  elif [[ $(id $MISP_USER >/dev/null; echo $?) -ne 0 ]]; then
    useradd -s /bin/bash -m -G adm,cdrom,sudo,dip,plugdev,www-data $MISP_USER
    echo $MISP_USER:$MISP_PASSWORD | chpasswd
  else
    # TODO: Make sure we consider this further down the road
    echo "User ${MISP_USER} exists, skipping creation"
  fi
}

# Setting generic MISP variables share by all flavours
MISPvars () {
  # Local non-root MISP user
  MISP_USER='misp'
  MISP_PASSWORD='Password1234'

  # MISP configuration variables
  PATH_TO_MISP='/var/www/MISP'
  MISP_BASEURL='https://misp.local'
  MISP_LIVE='1'
  CAKE="$PATH_TO_MISP/app/Console/cake"

  # Database configuration
  DBHOST='localhost'
  DBNAME='misp'
  DBUSER_ADMIN='root'
  DBPASSWORD_ADMIN="$(openssl rand -hex 32)"
  DBUSER_MISP='misp'
  DBPASSWORD_MISP="$(openssl rand -hex 32)"

  # Webserver configuration
  FQDN='misp.local'

  # OpenSSL configuration
  OPENSSL_CN=$FQDN
  OPENSSL_C='LU'
  OPENSSL_ST='State'
  OPENSSL_L='Location'
  OPENSSL_O='Organization'
  OPENSSL_OU='Organizational Unit'
  OPENSSL_EMAILADDRESS='info@localhost'

  # GPG configuration
  GPG_REAL_NAME='Autogenerated Key'
  GPG_COMMENT='WARNING: MISP AutoGenerated Key consider this Key VOID!'
  GPG_EMAIL_ADDRESS='admin@admin.test'
  GPG_KEY_LENGTH='2048'
  GPG_PASSPHRASE='Password1234'

  # php.ini configuration
  upload_max_filesize=50M
  post_max_size=50M
  max_execution_time=300
  memory_limit=512M
  PHP_INI=/etc/php/7.3/apache2/php.ini

  # apt config
  export DEBIAN_FRONTEND=noninteractive

  # sudo config to run $LUSER commands
  SUDO="sudo -u ${MISP_USER}"
  SUDO_WWW="sudo -u www-data"

  echo "Admin (${DBUSER_ADMIN}) DB Password: ${DBPASSWORD_ADMIN}"
  echo "User  (${DBUSER_MISP}) DB Password: ${DBPASSWORD_MISP}"
}

# Installing core dependencies
installDeps () {
  apt update
  apt install -qy etckeeper
  # Skip dist-upgrade for now, pulls in 500+ updated packages
  #sudo apt -y dist-upgrade
  gitMail=$(git config --global --get user.email ; echo $?)
  if [ "$?" -eq "1" ]; then 
    git config --global user.email "root@kali.lan"
  fi
  gitUser=$(git config --global --get user.name ; echo $?)
  if [ "$?" -eq "1" ]; then 
    git config --global user.name "Root User"
  fi

  apt install -qy postfix

  apt install -qy \
  curl gcc git gnupg-agent make openssl redis-server neovim zip libyara-dev python3-yara python3-redis python3-zmq \
  mariadb-client \
  mariadb-server \
  apache2 apache2-doc apache2-utils \
  libapache2-mod-php7.3 php7.3 php7.3-cli  php7.3-mbstring php-pear php7.3-dev php7.3-json php7.3-xml php7.3-mysql php7.3-opcache php7.3-readline php-redis php-gnupg \
  python3-dev python3-pip libpq5 libjpeg-dev libfuzzy-dev ruby asciidoctor \
  libxml2-dev libxslt1-dev zlib1g-dev python3-setuptools expect

  installRNG
}

# Test and install software RNG
installRNG () {
  modprobe tpm-rng 2> /dev/null
  if [ "$?" -eq "0" ]; then 
    echo tpm-rng >> /etc/modules
  fi
  apt install -qy rng-tools # This might fail on TPM grounds, enable the security chip in your BIOS
  service rng-tools start

  if [ "$?" -eq "1" ]; then 
    apt purge -qy rng-tools
    apt install -qy haveged
    /etc/init.d/haveged start
  fi
}

# On Kali, the redis start-up script is broken. This tries to fix it.
fixRedis () {
  # As of 20190124 redis-server init.d scripts are broken and need to be replaced
  mv /etc/init.d/redis-server /etc/init.d/redis-server_`date +%Y%m%d`

  echo '#! /bin/sh
### BEGIN INIT INFO
# Provides:		redis-server
# Required-Start:	$syslog
# Required-Stop:	$syslog
# Should-Start:		$local_fs
# Should-Stop:		$local_fs
# Default-Start:	2 3 4 5
# Default-Stop:		0 1 6
# Short-Description:	redis-server - Persistent key-value db
# Description:		redis-server - Persistent key-value db
### END INIT INFO

PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
DAEMON=/usr/bin/redis-server
DAEMON_ARGS=/etc/redis/redis.conf
NAME=redis-server
DESC=redis-server
PIDFILE=/var/run/redis.pid

test -x $DAEMON || exit 0
test -x $DAEMONBOOTSTRAP || exit 0

set -e

case "$1" in
  start)
	echo -n "Starting $DESC: "
	touch $PIDFILE
	chown redis:redis $PIDFILE
	if start-stop-daemon --start --quiet --umask 007 --pidfile $PIDFILE --chuid redis:redis --exec $DAEMON -- $DAEMON_ARGS
	then
		echo "$NAME."
	else
		echo "failed"
	fi
	;;
  stop)
	echo -n "Stopping $DESC: "
	if start-stop-daemon --stop --retry 10 --quiet --oknodo --pidfile $PIDFILE --exec $DAEMON
	then
		echo "$NAME."
	else
		echo "failed"
	fi
	rm -f $PIDFILE
	;;

  restart|force-reload)
	${0} stop
	${0} start
	;;
  *)
	echo "Usage: /etc/init.d/$NAME {start|stop|restart|force-reload}" >&2
	exit 1
	;;
esac

exit 0' | tee /etc/init.d/redis-server
  chmod 755 /etc/init.d/redis-server
  /etc/init.d/redis-server start
}

# generate MISP apache conf
genApacheConf () {
  echo "<VirtualHost _default_:80>
          ServerAdmin admin@localhost.lu
          ServerName misp.local

          Redirect permanent / https://misp.local

          LogLevel warn
          ErrorLog /var/log/apache2/misp.local_error.log
          CustomLog /var/log/apache2/misp.local_access.log combined
          ServerSignature Off
  </VirtualHost>

  <VirtualHost _default_:443>
          ServerAdmin admin@localhost.lu
          ServerName misp.local
          DocumentRoot $PATH_TO_MISP/app/webroot

          <Directory $PATH_TO_MISP/app/webroot>
                  Options -Indexes
                  AllowOverride all
  		            Require all granted
                  Order allow,deny
                  allow from all
          </Directory>

          SSLEngine On
          SSLCertificateFile /etc/ssl/private/misp.local.crt
          SSLCertificateKeyFile /etc/ssl/private/misp.local.key
  #        SSLCertificateChainFile /etc/ssl/private/misp-chain.crt

          LogLevel warn
          ErrorLog /var/log/apache2/misp.local_error.log
          CustomLog /var/log/apache2/misp.local_access.log combined
          ServerSignature Off
          Header set X-Content-Type-Options nosniff
          Header set X-Frame-Options DENY
  </VirtualHost>" | tee /etc/apache2/sites-available/misp-ssl.conf
}

# Add git pull update mechanism to rc.local - TODO: Make this better
gitPullAllRCLOCAL () {
  sed -i -e '$i \git_dirs="/usr/local/src/misp-modules/ /var/www/misp-dashboard /usr/local/src/faup /usr/local/src/mail_to_misp /usr/local/src/misp-modules /usr/local/src/viper /var/www/misp-dashboard"\n' /etc/rc.local
  sed -i -e '$i \for d in $git_dirs; do\n' /etc/rc.local
  sed -i -e '$i \    echo "Updating ${d}"\n' /etc/rc.local
  sed -i -e '$i \    cd $d && sudo git pull &\n' /etc/rc.local
  sed -i -e '$i \done\n' /etc/rc.local
}

# Composer on php 7.2 does not need any special treatment the provided phar works well
composer72 () {
  cd $PATH_TO_MISP/app
  mkdir /var/www/.composer ; chown www-data:www-data /var/www/.composer
  $SUDO_WWW php composer.phar require kamisama/cake-resque:4.1.2
  $SUDO_WWW php composer.phar config vendor-dir Vendor
  $SUDO_WWW php composer.phar install
}

# Composer on php 7.3 needs a recent version of composer.phar
composer73 () {
  cd $PATH_TO_MISP/app
  mkdir /var/www/.composer ; chown www-data:www-data /var/www/.composer
  # Update composer.phar
  # If hash changes, check here: https://getcomposer.org/download/ and replace with the correct one
  # Current Sum for: v1.8.3
  SHA384_SUM='48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5'
  sudo -H -u www-data php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  sudo -H -u www-data php -r "if (hash_file('SHA384', 'composer-setup.php') === '$SHA384_SUM') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); exit(137); } echo PHP_EOL;"
  checkFail "composer.phar checksum failed, please investigate manually. " $?
  sudo -H -u www-data php composer-setup.php
  sudo -H -u www-data php -r "unlink('composer-setup.php');"
  $SUDO_WWW php composer.phar require kamisama/cake-resque:4.1.2
  $SUDO_WWW php composer.phar config vendor-dir Vendor
  $SUDO_WWW php composer.phar install
}

# Enable various core services
enableServices () {
    update-rc.d mysql enable
    update-rc.d apache2 enable
    update-rc.d redis-server enable
  }

# Main MISP Dashboard install function
mispDashboard () {
  cd /var/www
  mkdir misp-dashboard
  chown www-data:www-data misp-dashboard
  $SUDO_WWW git clone https://github.com/MISP/misp-dashboard.git
  cd misp-dashboard
  /var/www/misp-dashboard/install_dependencies.sh
  sed -i "s/^host\ =\ localhost/host\ =\ 0.0.0.0/g" /var/www/misp-dashboard/config/config.cfg
  sed -i -e '$i \sudo -u www-data bash /var/www/misp-dashboard/start_all.sh\n' /etc/rc.local
  $SUDO_WWW bash /var/www/misp-dashboard/start_all.sh
  apt install libapache2-mod-wsgi-py3 -y
  echo "<VirtualHost *:8001>
      ServerAdmin admin@misp.local
      ServerName misp.local

      DocumentRoot /var/www/misp-dashboard

      WSGIDaemonProcess misp-dashboard \
         user=misp group=misp \
         python-home=/var/www/misp-dashboard/DASHENV \
         processes=1 \
         threads=15 \
         maximum-requests=5000 \
         listen-backlog=100 \
         queue-timeout=45 \
         socket-timeout=60 \
         connect-timeout=15 \
         request-timeout=60 \
         inactivity-timeout=0 \
         deadlock-timeout=60 \
         graceful-timeout=15 \
         eviction-timeout=0 \
         shutdown-timeout=5 \
         send-buffer-size=0 \
         receive-buffer-size=0 \
         header-buffer-size=0 \
         response-buffer-size=0 \
         server-metrics=Off

      WSGIScriptAlias / /var/www/misp-dashboard/misp-dashboard.wsgi

      <Directory /var/www/misp-dashboard>
          WSGIProcessGroup misp-dashboard
          WSGIApplicationGroup %{GLOBAL}
          Require all granted
      </Directory>

      LogLevel info
      ErrorLog /var/log/apache2/misp-dashboard.local_error.log
      CustomLog /var/log/apache2/misp-dashboard.local_access.log combined
      ServerSignature Off
  </VirtualHost>" | tee /etc/apache2/sites-available/misp-dashboard.conf
  a2ensite misp-dashboard
}

# TODO: dashboardCAKE () { }

# Core cake commands
coreCAKE () {
  $CAKE Live $MISP_LIVE
  $CAKE Baseurl $MISP_BASEURL

  $CAKE userInit -q

  $CAKE Admin setSetting "Plugin.ZeroMQ_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_event_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_object_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_object_reference_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_attribute_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_sighting_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_user_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_organisation_notifications_enable" true
  $CAKE Admin setSetting "Plugin.ZeroMQ_port" 50000
  $CAKE Admin setSetting "Plugin.ZeroMQ_redis_host" "localhost"
  $CAKE Admin setSetting "Plugin.ZeroMQ_redis_port" 6379
  $CAKE Admin setSetting "Plugin.ZeroMQ_redis_database" 1
  $CAKE Admin setSetting "Plugin.ZeroMQ_redis_namespace" "mispq"
  $CAKE Admin setSetting "Plugin.ZeroMQ_include_attachments" false
  $CAKE Admin setSetting "Plugin.ZeroMQ_tag_notifications_enable" false
  $CAKE Admin setSetting "Plugin.ZeroMQ_audit_notifications_enable" false
  $CAKE Admin setSetting "GnuPG.email" "admin@admin.test"
  $CAKE Admin setSetting "GnuPG.homedir" "/var/www/MISP/.gnupg"
  $CAKE Admin setSetting "GnuPG.password" "Password1234"
  $CAKE Admin setSetting "Plugin.Enrichment_services_enable" true
  $CAKE Admin setSetting "Plugin.Enrichment_hover_enable" true
  $CAKE Admin setSetting "Plugin.Enrichment_timeout" 300
  $CAKE Admin setSetting "Plugin.Enrichment_hover_timeout" 150
  $CAKE Admin setSetting "Plugin.Enrichment_cve_enabled" true
  $CAKE Admin setSetting "Plugin.Enrichment_dns_enabled" true
  $CAKE Admin setSetting "Plugin.Enrichment_services_url" "http://127.0.0.1"
  $CAKE Admin setSetting "Plugin.Enrichment_services_port" 6666
  $CAKE Admin setSetting "Plugin.Import_services_enable" true
  $CAKE Admin setSetting "Plugin.Import_services_url" "http://127.0.0.1"
  $CAKE Admin setSetting "Plugin.Import_services_port" 6666
  $CAKE Admin setSetting "Plugin.Import_timeout" 300
  $CAKE Admin setSetting "Plugin.Import_ocr_enabled" true
  $CAKE Admin setSetting "Plugin.Import_csvimport_enabled" true
  $CAKE Admin setSetting "Plugin.Export_services_enable" true
  $CAKE Admin setSetting "Plugin.Export_services_url" "http://127.0.0.1"
  $CAKE Admin setSetting "Plugin.Export_services_port" 6666
  $CAKE Admin setSetting "Plugin.Export_timeout" 300
  $CAKE Admin setSetting "Plugin.Export_pdfexport_enabled" true
  $CAKE Admin setSetting "MISP.host_org_id" 1
  $CAKE Admin setSetting "MISP.email" "info@admin.test"
  $CAKE Admin setSetting "MISP.disable_emailing" false
  $CAKE Admin setSetting "MISP.contact" "info@admin.test"
  $CAKE Admin setSetting "MISP.disablerestalert" true
  $CAKE Admin setSetting "MISP.showCorrelationsOnIndex" true
  $CAKE Admin setSetting "Plugin.Cortex_services_enable" false
  $CAKE Admin setSetting "Plugin.Cortex_services_url" "http://127.0.0.1"
  $CAKE Admin setSetting "Plugin.Cortex_services_port" 9000
  $CAKE Admin setSetting "Plugin.Cortex_timeout" 120
  $CAKE Admin setSetting "Plugin.Cortex_services_url" "http://127.0.0.1"
  $CAKE Admin setSetting "Plugin.Cortex_services_port" 9000
  $CAKE Admin setSetting "Plugin.Cortex_services_timeout" 120
  $CAKE Admin setSetting "Plugin.Cortex_services_authkey" ""
  $CAKE Admin setSetting "Plugin.Cortex_ssl_verify_peer" false
  $CAKE Admin setSetting "Plugin.Cortex_ssl_verify_host" false
  $CAKE Admin setSetting "Plugin.Cortex_ssl_allow_self_signed" true
  $CAKE Admin setSetting "Plugin.Sightings_policy" 0
  $CAKE Admin setSetting "Plugin.Sightings_anonymise" false
  $CAKE Admin setSetting "Plugin.Sightings_range" 365
  $CAKE Admin setSetting "Plugin.CustomAuth_disable_logout" false
  $CAKE Admin setSetting "Plugin.RPZ_policy" "DROP"
  $CAKE Admin setSetting "Plugin.RPZ_walled_garden" "127.0.0.1"
  $CAKE Admin setSetting "Plugin.RPZ_serial" "\$date00"
  $CAKE Admin setSetting "Plugin.RPZ_refresh" "2h"
  $CAKE Admin setSetting "Plugin.RPZ_retry" "30m"
  $CAKE Admin setSetting "Plugin.RPZ_expiry" "30d"
  $CAKE Admin setSetting "Plugin.RPZ_minimum_ttl" "1h"
  $CAKE Admin setSetting "Plugin.RPZ_ttl" "1w"
  $CAKE Admin setSetting "Plugin.RPZ_ns" "localhost."
  $CAKE Admin setSetting "Plugin.RPZ_ns_alt" ""
  $CAKE Admin setSetting "Plugin.RPZ_email" "root.localhost"
  $CAKE Admin setSetting "MISP.language" "eng"
  $CAKE Admin setSetting "MISP.proposals_block_attributes" false
  $CAKE Admin setSetting "MISP.redis_host" "127.0.0.1"
  $CAKE Admin setSetting "MISP.redis_port" 6379
  $CAKE Admin setSetting "MISP.redis_database" 13
  $CAKE Admin setSetting "MISP.redis_password" ""
  $CAKE Admin setSetting "MISP.ssdeep_correlation_threshold" 40
  $CAKE Admin setSetting "MISP.extended_alert_subject" false
  $CAKE Admin setSetting "MISP.default_event_threat_level" 4
  $CAKE Admin setSetting "MISP.newUserText" "Dear new MISP user,\\n\\nWe would hereby like to welcome you to the \$org MISP community.\\n\\n Use the credentials below to log into MISP at \$misp, where you will be prompted to manually change your password to something of your own choice.\\n\\nUsername: \$username\\nPassword: \$password\\n\\nIf you have any questions, don't hesitate to contact us at: \$contact.\\n\\nBest regards,\\nYour \$org MISP support team"
  $CAKE Admin setSetting "MISP.passwordResetText" "Dear MISP user,\\n\\nA password reset has been triggered for your account. Use the below provided temporary password to log into MISP at \$misp, where you will be prompted to manually change your password to something of your own choice.\\n\\nUsername: \$username\\nYour temporary password: \$password\\n\\nIf you have any questions, don't hesitate to contact us at: \$contact.\\n\\nBest regards,\\nYour \$org MISP support team"
  $CAKE Admin setSetting "MISP.enableEventBlacklisting" true
  $CAKE Admin setSetting "MISP.enableOrgBlacklisting" true
  $CAKE Admin setSetting "MISP.log_client_ip" false
  $CAKE Admin setSetting "MISP.log_auth" false
  $CAKE Admin setSetting "MISP.disableUserSelfManagement" false
  $CAKE Admin setSetting "MISP.block_event_alert" false
  $CAKE Admin setSetting "MISP.block_event_alert_tag" "no-alerts=\"true\""
  $CAKE Admin setSetting "MISP.block_old_event_alert" false
  $CAKE Admin setSetting "MISP.block_old_event_alert_age" ""
  $CAKE Admin setSetting "MISP.incoming_tags_disabled_by_default" false
  $CAKE Admin setSetting "MISP.footermidleft" "This is an autogenerated install"
  $CAKE Admin setSetting "MISP.footermidright" "Please configure accordingly and do not use in production"
  $CAKE Admin setSetting "MISP.welcome_text_top" "Autogenerated install, please configure and harden accordingly"
  $CAKE Admin setSetting "MISP.welcome_text_bottom" "Welcome to MISP on Kali"
  $CAKE Admin setSetting "Security.password_policy_length" 12
  $CAKE Admin setSetting "Security.password_policy_complexity" '/^((?=.*\d)|(?=.*\W+))(?![\n])(?=.*[A-Z])(?=.*[a-z]).*$|.{16,}/'
  $CAKE Admin setSetting "Session.autoRegenerate" 0
  $CAKE Admin setSetting "Session.timeout" 600
  $CAKE Admin setSetting "Session.cookie_timeout" 3600
  $CAKE Live $MISP_LIVE
}

# Setup GnuPG key
setupGnuPG () {
  echo "%echo Generating a default key
      Key-Type: default
      Key-Length: $GPG_KEY_LENGTH
      Subkey-Type: default
      Name-Real: $GPG_REAL_NAME
      Name-Comment: $GPG_COMMENT
      Name-Email: $GPG_EMAIL_ADDRESS
      Expire-Date: 0
      Passphrase: $GPG_PASSPHRASE
      # Do a commit here, so that we can later print "done"
      %commit
  %echo done" > /tmp/gen-key-script

  $SUDO_WWW gpg --homedir $PATH_TO_MISP/.gnupg --batch --gen-key /tmp/gen-key-script

  $SUDO_WWW sh -c "gpg --homedir $PATH_TO_MISP/.gnupg --export --armor $GPG_EMAIL_ADDRESS" | $SUDO_WWW tee $PATH_TO_MISP/app/webroot/gpg.asc
}

updateGOWNT () {
  AUTH_KEY=$(mysql -u $DBUSER_MISP -p$DBPASSWORD_MISP misp -e "SELECT authkey FROM users;" | tail -1)

  # TODO: Fix updateGalaxies
  #$CAKE Admin updateGalaxies
  curl --header "Authorization: $AUTH_KEY" --header "Accept: application/json" --header "Content-Type: application/json" -k -X POST https://127.0.0.1/galaxies/update
  $CAKE Admin updateTaxonomies
  # TODO: Fix updateWarningLists
  #$CAKE Admin updateWarningLists
  curl --header "Authorization: $AUTH_KEY" --header "Accept: application/json" --header "Content-Type: application/json" -k -X POST https://127.0.0.1/warninglists/update
  curl --header "Authorization: $AUTH_KEY" --header "Accept: application/json" --header "Content-Type: application/json" -k -X POST https://127.0.0.1/noticelists/update
  curl --header "Authorization: $AUTH_KEY" --header "Accept: application/json" --header "Content-Type: application/json" -k -X POST https://127.0.0.1/objectTemplates/update
}

# Generate rc.local
genRCLOCAL () {
  if [ ! -e /etc/rc.local ]; then
      echo '#!/bin/sh -e' | tee -a /etc/rc.local
      echo 'exit 0' | tee -a /etc/rc.local
      chmod u+x /etc/rc.local
  fi

  sed -i -e '$i \echo never > /sys/kernel/mm/transparent_hugepage/enabled\n' /etc/rc.local
  sed -i -e '$i \echo 1024 > /proc/sys/net/core/somaxconn\n' /etc/rc.local
  sed -i -e '$i \sysctl vm.overcommit_memory=1\n' /etc/rc.local
  sed -i -e '$i \sudo -u www-data bash /var/www/MISP/app/Console/worker/start.sh\n' /etc/rc.local
}

# Main MISP Modules install function
mispmodules () {
  sed -i -e '$i \sudo -u www-data misp-modules -l 0.0.0.0 -s &\n' /etc/rc.local
  $SUDO_WWW bash $PATH_TO_MISP/app/Console/worker/start.sh
  cd /usr/local/src/
  git clone https://github.com/MISP/misp-modules.git
  cd misp-modules
  # pip3 install
  pip3 install -I -r REQUIREMENTS
  pip3 install -I .
  pip3 install maec lief python-magic wand yara
  pip3 install git+https://github.com/kbandla/pydeep.git
  gem install pygments.rb
  gem install asciidoctor-pdf --pre
  $SUDO_WWW misp-modules -l 0.0.0.0 -s &
}

# Main Viper install function
viper () {
  cd /usr/local/src/
  debug "Installing Viper dependencies"
  apt-get install -y libssl-dev swig python3-ssdeep p7zip-full unrar-free sqlite python3-pyclamd exiftool radare2
  pip3 install SQLAlchemy PrettyTable python-magic
  debug "Cloning Viper"
  git clone https://github.com/viper-framework/viper.git
  chown -R $MISP_USER:$MISP_USER viper
  cd viper
  debug "Submodule update"
  $SUDO git submodule update --init --recursive
  debug "pip install scrapy"
  pip3 install scrapy
  debug "pip install reqs"
  pip3 install -r requirements.txt
  debug "pip uninstall yara"
  pip3 uninstall yara -y
  debug "Launching viper-cli"
  $SUDO /usr/local/src/viper/viper-cli -h > /dev/null
  debug "Launching viper-web"
  $SUDO /usr/local/src/viper/viper-web -p 8888 -H 0.0.0.0 &
  echo 'PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games:/usr/local/games:/usr/local/src/viper:/var/www/MISP/app/Console"' |tee /etc/environment
  echo ". /etc/environment" >> /home/${MISP_USER}/.profile
  debug "Setting misp_url/misp_key"
  $SUDO sed -i "s/^misp_url\ =/misp_url\ =\ http:\/\/localhost/g" /home/${MISP_USER}/.viper/viper.conf
  $SUDO sed -i "s/^misp_key\ =/misp_key\ =\ $AUTH_KEY/g" /home/${MISP_USER}/.viper/viper.conf

  debug "Fixing admin.db with default password"
  while [ "$(sqlite3 /home/${MISP_USER}/.viper/admin.db 'UPDATE auth_user SET password="pbkdf2_sha256$100000$iXgEJh8hz7Cf$vfdDAwLX8tko1t0M1TLTtGlxERkNnltUnMhbv56wK/U="'; echo $?)" -ne "0" ]; do
    # FIXME This might lead to a race condition, the while loop is sub-par
    chown $MISP_USER:$MISP_USER /home/${MISP_USER}/.viper/admin.db
    echo "Updating viper-web admin password, giving process time to start-up, sleeping 5, 4, 3,…"
    sleep 6
  done
  sed -i -e '$i \sudo -u misp /usr/local/src/viper/viper-web -p 8888 -H 0.0.0.0 &\n' /etc/rc.local
}

# Main function to fix permissions to something sane
permissions () {
  chown -R www-data:www-data $PATH_TO_MISP
  chmod -R 750 $PATH_TO_MISP
  chmod -R g+ws $PATH_TO_MISP/app/tmp
  chmod -R g+ws $PATH_TO_MISP/app/files
  chmod -R g+ws $PATH_TO_MISP/app/files/scripts/tmp
}

# Main mail2misp install function
mail2misp () {
  cd /usr/local/src/
  apt-get install -y cmake
  git clone https://github.com/MISP/mail_to_misp.git
  git clone git://github.com/stricaud/faup.git faup
  chown -R ${MISP_USER}:${MISP_USER} faup mail_to_misp
  cd faup
  $SUDO mkdir -p build
  cd build
  $SUDO cmake .. && $SUDO make
  make install
  ldconfig
  cd ../../
  cd mail_to_misp
  pip3 install -r requirements.txt
  $SUDO cp mail_to_misp_config.py-example mail_to_misp_config.py
  sed -i "s/^misp_url\ =\ 'YOUR_MISP_URL'/misp_url\ =\ 'http:\/\/localhost'/g" /usr/local/src/mail_to_misp/mail_to_misp_config.py
  sed -i "s/^misp_key\ =\ 'YOUR_KEY_HERE'/misp_key\ =\ '$AUTH_KEY'/g" /usr/local/src/mail_to_misp/mail_to_misp_config.py
}

# Final function to let the user know what happened
theEnd () {
  space
  echo "Admin (root) DB Password: $DBPASSWORD_ADMIN" > /home/${MISP_USER}/mysql.txt
  echo "User  (misp) DB Password: $DBPASSWORD_MISP" >> /home/${MISP_USER}/mysql.txt
  echo "Authkey: $AUTH_KEY" > /home/${MISP_USER}/MISP-authkey.txt

  clear
  space
  echo "MISP Installed, access here: https://misp.local"
  echo "User: admin@admin.test"
  echo "Password: admin"
  echo "MISP Dashboard, access here: http://misp.local:8001"
  space
  echo "The following files were created and need either protection or removal (shred on the CLI)"
  echo "/home/${MISP_USER}/mysql.txt"
  echo "/home/${MISP_USER}/MISP-authkey.txt"
  cat /home/${MISP_USER}/mysql.txt
  cat /home/${MISP_USER}/MISP-authkey.txt
  space
  echo "The LOCAL system credentials:"
  echo "User: ${MISP_USER}"
  echo "Password: ${MISP_PASSWORD}"
  space
  echo "viper-web installed, access here: http://misp.local:8888"
  echo "viper-cli configured with your MISP Site Admin Auth Key"
  echo "User: admin"
  echo "Password: Password1234"
  space
  echo "To enable outgoing mails via postfix set a permissive SMTP server for the domains you want to contact:"
  space
  echo "sudo postconf -e 'relayhost = example.com'"
  echo "sudo postfix reload"
  space
  echo "Enjoy using MISP. For any issues see here: https://github.com/MISP/MISP/issues"
  su - ${MISP_USER}
}

# Main Kalin Install function
installMISPonKali () {
  space
  debug "Disabling sleep etc…"
  gsettings set org.gnome.settings-daemon.plugins.power sleep-inactive-ac-timeout 0 2> /dev/null
  gsettings set org.gnome.settings-daemon.plugins.power sleep-inactive-battery-timeout 0 2> /dev/null
  gsettings set org.gnome.settings-daemon.plugins.power sleep-inactive-battery-type 'nothing' 2> /dev/null
  xset s 0 0 2> /dev/null
  xset dpms 0 0 2> /dev/null
  xset s off 2> /dev/null

  debug "Installing dependencies"
  installDeps

  debug "Enabling redis and gnupg modules"
  phpenmod -v 7.3 redis
  phpenmod -v 7.3 gnupg

  debug "Apache2 ops: dismod: status php7.2 - dissite: 000-default enmod: ssl rewrite headers php7.3 ensite: default-ssl"
  a2dismod status
  a2dismod php7.2
  a2enmod ssl rewrite headers php7.3
  a2dissite 000-default
  a2ensite default-ssl

  debug "Restarting mysql.service"
  systemctl restart mysql.service

  debug "Fixing redis rc script on Kali"
  fixRedis

  debug "git clone, submodule update everything"
  mkdir $PATH_TO_MISP
  chown www-data:www-data $PATH_TO_MISP
  cd $PATH_TO_MISP
  $SUDO_WWW git clone https://github.com/MISP/MISP.git $PATH_TO_MISP

  $SUDO_WWW git config core.filemode false

  cd $PATH_TO_MISP
  $SUDO_WWW git submodule update --init --recursive
  # Make git ignore filesystem permission differences for submodules
  $SUDO_WWW git submodule foreach --recursive git config core.filemode false

  cd $PATH_TO_MISP/app/files/scripts
  $SUDO_WWW git clone https://github.com/CybOXProject/python-cybox.git
  $SUDO_WWW git clone https://github.com/STIXProject/python-stix.git
  $SUDO_WWW git clone https://github.com/CybOXProject/mixbox.git

  debug "Installing python-cybox"
  cd $PATH_TO_MISP/app/files/scripts/python-cybox
  pip3 install .
  debug "Installing python-stix"
  cd $PATH_TO_MISP/app/files/scripts/python-stix
  pip3 install .
  # install STIX2.0 library to support STIX 2.0 export:
  debug "Installing cti-python-stix2"
  cd ${PATH_TO_MISP}/cti-python-stix2
  pip3 install -I .
  debug "Installing mixbox"
  cd $PATH_TO_MISP/app/files/scripts/mixbox
  pip3 install .
  # install PyMISP
  debug "Installing PyMISP"
  cd $PATH_TO_MISP/PyMISP
  pip3 install .

  # Install Crypt_GPG and Console_CommandLine
  debug "Installing pear Console_CommandLine"
  pear install ${PATH_TO_MISP}/INSTALL/dependencies/Console_CommandLine/package.xml
  debug "Installing pear Crypt_GPG"
  pear install ${PATH_TO_MISP}/INSTALL/dependencies/Crypt_GPG/package.xml

  debug "Installing composer with php 7.3 updates"
  composer73

  $SUDO_WWW cp -fa $PATH_TO_MISP/INSTALL/setup/config.php $PATH_TO_MISP/app/Plugin/CakeResque/Config/config.php

  chown -R www-data:www-data $PATH_TO_MISP
  chmod -R 750 $PATH_TO_MISP
  chmod -R g+ws $PATH_TO_MISP/app/tmp
  chmod -R g+ws $PATH_TO_MISP/app/files
  chmod -R g+ws $PATH_TO_MISP/app/files/scripts/tmp

  debug "Setting up database"
  if [ ! -e /var/lib/mysql/misp/users.ibd ]; then
    echo "
      set timeout 10
      spawn mysql_secure_installation
      expect \"Enter current password for root (enter for none):\"
      send -- \"\r\"
      expect \"Set root password?\"
      send -- \"y\r\"
      expect \"New password:\"
      send -- \"${DBPASSWORD_ADMIN}\r\"
      expect \"Re-enter new password:\"
      send -- \"${DBPASSWORD_ADMIN}\r\"
      expect \"Remove anonymous users?\"
      send -- \"y\r\"
      expect \"Disallow root login remotely?\"
      send -- \"y\r\"
      expect \"Remove test database and access to it?\"
      send -- \"y\r\"
      expect \"Reload privilege tables now?\"
      send -- \"y\r\"
      expect eof" | expect -f -

    mysql -u $DBUSER_ADMIN -p$DBPASSWORD_ADMIN -e "create database $DBNAME;"
    mysql -u $DBUSER_ADMIN -p$DBPASSWORD_ADMIN -e "grant usage on *.* to $DBNAME@localhost identified by '$DBPASSWORD_MISP';"
    mysql -u $DBUSER_ADMIN -p$DBPASSWORD_ADMIN -e "grant all privileges on $DBNAME.* to '$DBUSER_MISP'@'localhost';"
    mysql -u $DBUSER_ADMIN -p$DBPASSWORD_ADMIN -e "flush privileges;"

    enableServices

    $SUDO_WWW cat $PATH_TO_MISP/INSTALL/MYSQL.sql | mysql -u $DBUSER_MISP -p$DBPASSWORD_MISP $DBNAME

    echo "<?php
  class DATABASE_CONFIG {
          public \$default = array(
                  'datasource' => 'Database/Mysql',
                  //'datasource' => 'Database/Postgres',
                  'persistent' => false,
                  'host' => '$DBHOST',
                  'login' => '$DBUSER_MISP',
                  'port' => 3306, // MySQL & MariaDB
                  //'port' => 5432, // PostgreSQL
                  'password' => '$DBPASSWORD_MISP',
                  'database' => '$DBNAME',
                  'prefix' => '',
                  'encoding' => 'utf8',
          );
  }" | $SUDO_WWW tee $PATH_TO_MISP/app/Config/database.php
  else
    echo "There might be a database already existing here: /var/lib/mysql/misp/users.ibd"
    echo "Skipping any creations…"
    sleep 3
  fi

  debug "Generating Certificate"
  openssl req -newkey rsa:4096 -days 365 -nodes -x509 \
  -subj "/C=${OPENSSL_C}/ST=${OPENSSL_ST}/L=${OPENSSL_L}/O=${OPENSSL_O}/OU=${OPENSSL_OU}/CN=${OPENSSL_CN}/emailAddress=${OPENSSL_EMAILADDRESS}" \
  -keyout /etc/ssl/private/misp.local.key -out /etc/ssl/private/misp.local.crt

  debug "Generating Apache Conf"
  genApacheConf

  echo "127.0.0.1 misp.local" | tee -a /etc/hosts

  debug "Installing MISP dashboard"
  mispDashboard

  debug "Disabling site default-ssl, enabling misp-ssl"
  a2dissite default-ssl
  a2ensite misp-ssl

  for key in upload_max_filesize post_max_size max_execution_time max_input_time memory_limit
  do
      sed -i "s/^\($key\).*/\1 = $(eval echo \${$key})/" $PHP_INI
  done

  debug "Restarting Apache2"
  systemctl restart apache2

  debug "Setting up logrotate"
  cp $PATH_TO_MISP/INSTALL/misp.logrotate /etc/logrotate.d/misp
  chmod 0640 /etc/logrotate.d/misp

  $SUDO_WWW cp -a $PATH_TO_MISP/app/Config/bootstrap.default.php $PATH_TO_MISP/app/Config/bootstrap.php
  $SUDO_WWW cp -a $PATH_TO_MISP/app/Config/core.default.php $PATH_TO_MISP/app/Config/core.php
  $SUDO_WWW cp -a $PATH_TO_MISP/app/Config/config.default.php $PATH_TO_MISP/app/Config/config.php

  chown -R www-data:www-data $PATH_TO_MISP/app/Config
  chmod -R 750 $PATH_TO_MISP/app/Config

  debug "Setting up GnuPG"
  setupGnuPG

  chmod +x $PATH_TO_MISP/app/Console/worker/start.sh

  debug "Running Core Cake commands"
  coreCAKE

  debug "Update: Galaxies, Template Objects, Warning Lists, Notice Lists, Taxonomies"
  updateGOWNT

  debug "Generating rc.local"
  genRCLOCAL

  gitPullAllRCLOCAL

  debug "Installing misp-modules"
  mispmodules

  debug "Installing Viper"
  viper

  debug "Setting permissions"
  permissions

  debug "Running Then End!"
  theEnd
}


debug "Checking for parameters or Kali Install"
if [[ $# -ne 1 && $0 != "/tmp/misp-kali.sh" ]]; then
  usage
  exit 
else
  debug "Setting install options with given parameters."
  setOpt $@
  checkOpt core && echo "core selected"
  checkOpt viper && echo "viper selected"
  checkOpt modules && echo "modules selected"
  checkOpt dashboard && echo "dashboard selected"
  checkOpt mail2 && echo "mail2 selected"
  checkOpt all && echo "all selected"
  checkOpt pre && echo "pre selected"
fi

debug "Checking flavour"
checkFlavour
debug "Setting MISP variables"
MISPvars

if [ "${FLAVOUR}" == "kali" ]; then
  kaliOnRootR0ckz
  installMISPonKali
  exit
fi
