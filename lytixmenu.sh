#!/bin/bash
VERSION="1.0.7"
coin_name="lytix"
coin_daemon="lytixd"
coin_cli="lytix-cli"
coin_repo="https://github.com/LytixChain/lytix/releases/download/v1.7.5b/lytix-1.7.5-x86_64-linux-gnu.tar.gz"
coin_file="lytix-1.7.5-x86_64-linux-gnu.tar.gz"
coin_unpack="tar xzvf"
coin_option="--strip-components=2"
coin_port="27071"
coin_datadir=".lytix"
coin_confname="lytix.conf"


if [ ! -f "/usr/local/bin/$coin_daemon" ] ;  then
  apt-get install ufw -y
  apt-get -y install wget nano htop jq dialog
  apt-get -y install libzmq3-dev
  apt-get -y install libboost-system-dev libboost-filesystem-dev libboost-chrono-dev libboost-program-options-dev libboost-test-dev libboost-thread-dev
  apt-get -y install libevent-dev
  apt -y install software-properties-common
  add-apt-repository ppa:bitcoin/bitcoin -y
  apt-get -y update
  apt-get -y install libdb4.8-dev libdb4.8++-dev
  apt-get -y install libminiupnpc-dev
  rm $coin_repo
  wget $coin_repo
  $coin_unpack $coin_file $coin_option
  cp lytix* /usr/local/bin
  chmod +x  /usr/local/bin/lytixd
  chmod +x  /usr/local/bin/lytix-cli

  if [ ! -f "/usr/local/bin/lytixd" ] ; then
    echo "Lytixd installation failed"
    exit 1
  fi

  if [ ! -f "/usr/local/bin/lytix-cli" ] ; then
     echo "Lytix-cli installation failed"
     exit 1
  fi

  ufw allow 1515
fi

INPUT=/tmp/menu.sh.$$
export NCURSES_NO_UTF8_ACS=1
# Storage file for displaying cal and date command output
OUTPUT=/tmp/output.sh.$$
# get text editor or fall back to vi_editor
vi_editor=${EDITOR-vi}
# trap and delete temp files
trap "rm $OUTPUT; rm $INPUT; exit" SIGHUP SIGINT SIGTERM


###########################################
# make pre-conf  for ipv4 masternode
###########################################
function make_preconf(){
CONFIGFOLDER="/root/${coin_datadir}"
mkdir $CONFIGFOLDER 

# create <coin_name>.conf
RPCUSER=$(tr -cd '[:alnum:]' < /dev/urandom | fold -w10 | head -n1)
RPCPASSWORD=$(tr -cd '[:alnum:]' < /dev/urandom | fold -w22 | head -n1)
RPCPORT=$(($coin_port-1))
cat << EOF > $CONFIGFOLDER/$coin_confname
rpcuser=$RPCUSER
rpcpassword=$RPCPASSWORD
rpcallowip=127.0.0.1
rpcport=$RPCPORT
listen=0
server=1
daemon=1
port=$coin_port
logintimestamps=1
maxconnections=32
masternode=0
bind=$IPV4
externalip=$IPV4
EOF
} 
##  end of pre_conf #######################

###########################################
# make *.conf
###########################################
function make_conf(){
CONFIGFOLDER="/home/${new_user}/${coin_datadir}"
mkdir $CONFIGFOLDER 

# create <coin_name>.conf
RPCUSER=$(tr -cd '[:alnum:]' < /dev/urandom | fold -w10 | head -n1)
RPCPASSWORD=$(tr -cd '[:alnum:]' < /dev/urandom | fold -w22 | head -n1)
RPCPORT=$(($coin_port+$new_number))

cat << EOF > $CONFIGFOLDER/$coin_confname
rpcuser=$RPCUSER
rpcpassword=$RPCPASSWORD
rpcallowip=127.0.0.1
rpcport=$RPCPORT
listen=1
server=1
daemon=1
port=$coin_port
logintimestamps=1
maxconnections=32
masternode=1
bind=[$IP]
externalip=[$IP]
masternodeprivkey=$privkey
EOF
chown -R $new_user:$new_user /home/$new_user
} 
##  end of make_conf#######################
###########################################
# get privkey
###########################################
function get_privkey(){
command="$coin_cli createmasternodekey"
#echo "$command"
privkey=$($coin_cli createmasternodekey)
#echo "privkey: $privkey"
#echo "enter"
#read
}  
## end of get_privkey#######################
###########################################
# get ipv6 address
###########################################
function get_ip(){

rm user.txt
touch user.txt  
folder=( $(find /home/${coin_name}*  -maxdepth 0  -type d) )
for i in "${folder[@]}"; do
  IFS="_"
  set -- $i
  echo "$1"  >> user.txt
done

fs=$(stat -c %s user.txt)
if  [ $fs -gt 0 ] ;  then 
  rm ip_in_use.txt
  touch ip_in_use.txt
  while read line
  do
    conf=$line/$coin_datadir/$coin_confname
    lc1=$(wc -l $conf | cut -d " " -f 1)
    ((lc1++))
    for((i=1; i<$lc1; i++))
    do
      z1=$(head -n $i $conf | tail -n 1)
      if [[ $z1 == *"bind"* ]]; then
         echo "$z1"  >> ip_in_use.txt
      fi
    done

    #IP=$(head -n 12 $line/$coin_datadir/$coin_confname | tail -n 1)
    #echo "$IP"  >> ip_in_use.txt
  done < <(cat user.txt)
  #cat ip_in_use.txt
fi

#echo "press enter"
#read


ip addr show eth0 | grep -vw "inet" | grep "global" | grep -w "inet6" | cut -d/ -f1 | awk '{ print $2 }'  >ipv6_addresses.txt
i=1
ersatz_ip="NoIP"
clear
###  list of ipv6 addresses
#echo "Free IPv6 addresses:"
lc1=$(wc -l ipv6_addresses.txt | cut -d " " -f 1)
((lc1++))
for((i=1; i<$lc1; i++))
do
   z1=$(head -n $i ipv6_addresses.txt | tail -n 1)
   #echo "$i. Zeile ipv6_addresses:  $z1"
   lc2=$(wc -l ip_in_use.txt | cut -d " " -f 1)
   ((lc2++))
   in_use=0
   for((j=1; j<$lc2; j++))
   do
     z2=$(head -n $j ip_in_use.txt | tail -n 1)
     #echo "  $j. Zeile  ip_in_use.txt: $z2"
     if [[ $z2 == *"$z1"* ]]; then
        #echo "$z1 is already in use"
        in_use=1
     fi
   done   ## end of j-loop ##
   if [ $in_use == 0 ] ; then
        #echo "$i: $z1"
        ersatz_ip=$z1
   fi

done
echo 
### end of list ####

if [ $ersatz_ip == "NoIP" ] ; then
  echo "Sorry, you dont have any free IPv6 address."
  IP="NoIP"
  echo "Press ENTER to continue."
  read
else
  echo "Free IPv6  $ersatz_ip will be taken"
  IP=$ersatz_ip
fi

}   
#####  end  of get_ip   #####################################
###########################################
# install new masternode
###########################################
function new_masternode(){
clear
get_ip
if [ $IP == "NoIP" ] ; then
  return
fi

#echo "End get ip"
#echo "IP: $IP"
#echo "press  enter"
#read

# set new linux user
rm user.txt
touch user.txt
folder=( $(find /home/${coin_name}*  -maxdepth 0  -type d) )
for i in "${folder[@]}"; do
  IFS="_"
  set -- $i
  echo "$1"  >> user.txt
done
i=$(wc -l user.txt | cut -d " " -f 1)
#clear
#echo "$i user"
((i++))
new_number=$i
new_user=${coin_name}$i
#echo "next user: $new_user"

userpass=$(tr -cd '[:alnum:]' < /dev/urandom | fold -w22 | head -n1) 
adduser $new_user --shell /bin/false --gecos ""  <<EOF
$userpass
$userpass
EOF
## end of new user ##
# get IP address
#clear
# create privkey 
privkey="PRIVKEY"
get_privkey

# make  coin_name.conf
make_conf

# create /ect/systemd/system service 
cat << EOF > /etc/systemd/system/$new_user.service
[Unit]
Description=$new_user service
After=network.target
[Service]
User=root
Group=root
Type=forking
ExecStart=/usr/local/bin/$coin_daemon -conf=/home/$new_user/$coin_datadir/$coin_confname -datadir=/home/$new_user/$coin_datadir
ExecStop=-/usr/local/bin/$coin_cli  -conf=/home/$new_user/$coin_datadir/$coin_confname -datadir=/home/$new_user/$coin_datadir stop
Restart=always
PrivateTmp=true
TimeoutStopSec=60s
TimeoutStartSec=10s
StartLimitInterval=120s
StartLimitBurst=5
[Install]
WantedBy=multi-user.target
EOF


echo "Please wait...service is registered and starting"
systemctl daemon-reload
sleep 3
systemctl enable $new_user.service   
sleep 5
systemctl start $new_user.service

echo
echo "Put the line below in your masternode.conf on your PC"
echo 
echo $new_user [$IP]:27071 $privkey
echo
echo "Then goto your PC wallet, create a new address by typing getaccountaddress "+$new_user+" in the debug console and send your 5K payment to this address. Wait for a few confirmations, then get the txid and outputindex by typing masternode outputs, add those infos to the masternode.conf line"
echo "Save the file and restart your wallet on the PC."
echo "Goto to Debug Console and type:" 
echo 
echo "startmasternode alias false $new_user"
echo
echo "Press <ENTER> if all done."
read input
ALIAS="/home/${new_user}"
}

### check for  IPv4 masternode #############################

IPV4=$(ip addr show eth0 | grep -vw "inet6" | grep "global" | grep -w "inet" | cut -d/ -f1 | awk '{ print $2 }')

if [ -f /root/${coin_datadir}/${coin_confname} ] ; then
	echo "IPv4 masternode already installed."
        echo "If menu is not starting, press CTRL C and then"
        echo "type:  apt install dialog"
else
  echo "IPv4 masternode not yet installed. Please wait...installation is going on."
  make_preconf
  echo "Please wait 10 seconds..."
  $coin_daemon
  sleep 10
  privkey="PRIVKEY"
  get_privkey
  echo "Please wait 10 seconds..."
  $coin_cli stop
  sleep 10
  RPCUSER=$(tr -cd '[:alnum:]' < /dev/urandom | fold -w10 | head -n1)
  RPCPASSWORD=$(tr -cd '[:alnum:]' < /dev/urandom | fold -w22 | head -n1)
  RPCPORT=$(($coin_port-1))

cat << EOF > /root/${coin_datadir}/${coin_confname}
rpcuser=$RPCUSER
rpcpassword=$RPCPASSWORD
rpcallowip=127.0.0.1
rpcport=$RPCPORT
listen=1
server=1
daemon=1
port=$coin_port
logintimestamps=1
maxconnections=32
masternode=1
bind=$IPV4
externalip=$IPV4
masternodeprivkey=$privkey
EOF

# create /ect/systemd/system service 
cat << EOF > /etc/systemd/system/LYTIX.service
[Unit]
Description=LYTIX service
After=network.target
[Service]
User=root
Group=root
Type=forking
ExecStart=/usr/local/bin/$coin_daemon -conf=/root/$coin_datadir/$coin_confname -datadir=/root/$coin_datadir
ExecStop=-/usr/local/bin/$coin_cli  -conf=/root/$coin_datadir/$coin_confname -datadir=/root/$coin_datadir stop
Restart=always
PrivateTmp=true
TimeoutStopSec=60s
TimeoutStartSec=10s
StartLimitInterval=120s
StartLimitBurst=5
[Install]
WantedBy=multi-user.target
EOF

echo "Please wait...service is registered and starting"
systemctl daemon-reload
sleep 3
systemctl enable LYTIX 
sleep 5
systemctl start LYTIX 

echo
echo "Put the line below in your masternode.conf on your PC"
echo 
echo LYTIXZERO $IPV4:1515 $privkey
echo
echo "Then goto your PC wallet, create a new address by typing getaccountaddress LYTIXZERO in the debug console and send your 5K payment to this address. Wait for a few confirmations, then get the txid and outputindex by typing masternode outputs, add those infos to the masternode.conf line"
echo "Save the file and restart your wallet on the PC."
echo "Goto to Debug Console and type:" 
echo 
echo "startmasternode alias false LYTIXZERO"
echo
echo "Press <ENTER> if all done."
read input  

fi   ###  end of  check for IPv4 masternode

ALIAS="/root"


###########  end of new_masternode ########################################
#
# set infinite loop
#
while true
do

### display main menu ###
dialog --clear  --help-button --backtitle "" \
--title "Lytix Menu $VERSION [ Masternode: ${ALIAS} ]" \
--menu "" 20 60 20 \
1  "Show lytix.conf" \
2  "Edit lytix.conf" \
3  "Start masternode" \
4  "Stop  masternode" \
5  "Server Masternode Status" \
6  "Server Getinfo" \
7  "Show Services" \
8  "Select a masternode" \
9  "Install a masternode" \
A  "Show all IPs" \
0  "Exit" 2>"${INPUT}"

menuitem=$(<"${INPUT}")


# make decsion
case $menuitem in
        1) cd ~
           FILE="${ALIAS}/$coin_datadir/$coin_confname"
           dialog --textbox "${FILE}" 0 0
           ;;
        2) cd ~
           nano "${ALIAS}/$coin_datadir/$coin_confname"
        ;;
        3) cd ~
           A="$(cut -d'/' -f3 <<<$ALIAS)" 
           if [ $ALIAS == "/root" ] ; then 
             A="LYTIX"
           fi 
           systemctl start $A
        ;;
        4) cd ~
           A="$(cut -d'/' -f3 <<<$ALIAS)"
           if [ $ALIAS == "/root" ] ; then
             A="LYTIX"
           fi
           systemctl stop $A
           #echo -e "Press <ENTER> to continue \c"
           #read input
        ;;
        5) rm ~/mnstatus.txt
           rm ~/failed.txt
           A="$(cut -d'/' -f3 <<<$ALIAS)"
           if [ $ALIAS == "/root" ] ; then
            if [ $1=="debug" ] ; then 
               echo "$coin_cli  -conf=/root/$coin_datadir/$coin_confname -datadir=/root/$coin_datadir  masternode status >> mnstatus.txt 2> failed.txt"
            fi 
             $coin_cli  -conf=/root/$coin_datadir/$coin_confname -datadir=/root/$coin_datadir  masternode status >> mnstatus.txt 2> failed.txt
           else
             if [ $1 == "debug" ] ; then
               echo "$coin_cli  -conf=/home/$A/$coin_datadir/$coin_confname -datadir=/home/$A/$coin_datadir  masternode status >> mnstatus.txt 2> failed.txt " 
             fi
             $coin_cli  -conf=/home/$A/$coin_datadir/$coin_confname -datadir=/home/$A/$coin_datadir  masternode status >> mnstatus.txt 2> failed.txt
           fi
           fs=$(stat -c %s mnstatus.txt)
           if  [ $fs -gt 0 ] ;  then 
             dialog --textbox "mnstatus.txt" 0 0
           fi
           fs=$(stat -c %s failed.txt)
           if  [ $fs -gt 0 ] ;  then
             dialog --textbox "failed.txt" 0 0
           fi
           #echo -e "Press <ENTER> to continue "
           #read input
           ;;
        6) rm ~/mnstatus.txt
           rm ~/failed.txt
           #echo "Masternode Getinfo: ${ALIAS}" > $ALIAS/mnstatus.txt
           #chmod 777 $ALIAS/mnstatus.txt
           A="$(cut -d'/' -f3 <<<$ALIAS)"
           if [ $ALIAS == "/root" ] ; then
             #echo "$coin_cli  -conf=/root/$coin_datadir/$coin_confname -datadir=/root/$coin_datadir  masternode status >> mnstatus.txt 2> failed.txt"
             $coin_cli  -conf=/root/$coin_datadir/$coin_confname -datadir=/root/$coin_datadir  getinfo >> mnstatus.txt 2> failed.txt
           else
             #echo "$coin_cli  -conf=/home/$A/$coin_datadir/$coin_confname -datadir=/home/$A/$coin_datadir  masternode status >> mnstatus.txt 2> failed.txt " 
             $coin_cli  -conf=/home/$A/$coin_datadir/$coin_confname -datadir=/home/$A/$coin_datadir  getinfo >> mnstatus.txt 2> failed.txt
           fi
           #echo -e "Press <ENTER> to continue "
           #read input
           fs=$(stat -c %s mnstatus.txt)
           if  [ $fs -gt 0 ] ;  then 
             dialog --textbox "mnstatus.txt" 0 0
           fi
           fs=$(stat -c %s failed.txt)
           if  [ $fs -gt 0 ] ;  then
             dialog --textbox "failed.txt" 0 0
           fi
        ;;
        7) rm -f services.txt
           folder=( $(find /etc/systemd/system/*.service  -maxdepth 0  -type f) )
           for i in "${folder[@]}"; do
           IFS="_"
           set -- $i
           echo "$1" >> services.txt
           done
           dialog --textbox "services.txt" 0 0  
           ;;
        8) rm -f user.txt
           echo "/root" > user.txt
           folder=( $(find /home/${coin_name}*  -maxdepth 0  -type d) )
           for i in "${folder[@]}"; do
             IFS="_"
             set -- $i
             echo "$1"  >> user.txt
           done
           declare -a array
           i=1 #Index counter for adding to array
           j=1 #Option menu value generator
           while read line
           do
             array[ $i ]=$j
             (( j++ ))
             array[ ($i + 1) ]=$line
             (( i=($i+2) ))
          done < <(cat user.txt)

         #Define parameters for menu
         TERMINAL=$(tty) #Gather current terminal session for appropriate redirection
         HEIGHT=20
         WIDTH=76
         CHOICE_HEIGHT=16
         BACKTITLE="Back_Title"
         TITLE="Dynamic Dialog"
         MENU="Choose a file:"

         #Build the menu with variables & dynamic content
         CHOICE=$(dialog --clear \
                 --backtitle "$BACKTITLE" \
                 --title "$TITLE" \
                 --menu "$MENU" \
                 $HEIGHT $WIDTH $CHOICE_HEIGHT \
                 "${array[@]}" \
                 2>&1 >$TERMINAL)
         i=$CHOICE
         k=$(($i+$i))
         ALIAS=${array[ $k ]}
         ;;
        9)dialog --title "Install new Masternode" \
          --backtitle "" \
          --yesno "Are you sure you want to install \nnew Masternode ?" 7 40

          # Get exit status
          # 0 means user hit [yes] button.
          # 1 means user hit [no] button.
          # 255 means user hit [Esc] key.
          response=$?
          case $response in
            0) new_masternode  ;;
          esac
          ;;
        A) echo "IPV4 Address:" > ip_address
           ip addr show eth0 | grep -vw "inet6" | grep "global" | grep -w "inet" | cut -d/ -f1 | awk '{ print $2 }'  >> ip_address 
           echo " " >>ip_address
           echo "IPV6 Address:" >> ip_address
           ip addr show eth0 | grep -vw "inet" | grep "global" | grep -w "inet6" | cut -d/ -f1 | awk '{ print $2 }'  >> ip_address
           dialog --textbox "ip_address" 0 0
           ;;
        0) break;;
esac

done

# if temp files found, delete em
[ -f $OUTPUT ] && rm $OUTPUT
[ -f $INPUT ] && rm $INPUT 
