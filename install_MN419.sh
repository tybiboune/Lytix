#!/bin/bash
apt-get -y install wget nano htop jq dialog mc php php-cli php-sqlite3 sqlite3 unzip atool
apt-get -y install libzmq3-dev
apt-get -y install libboost-system-dev libboost-filesystem-dev libboost-chrono-dev libboost-program-options-dev libboost-test-dev libboost-thread-dev
apt-get install build-essential libtool autotools-dev autoconf pkg-config libssl-dev -y
apt-get install libboost-all-dev -y
apt-get -y install libevent-dev
apt-get -y install libdb5.3++-dev
apt -y install software-properties-common
add-apt-repository ppa:bitcoin/bitcoin -y
apt-get -y update
apt-get -y install libdb4.8-dev libdb4.8++-dev
apt-get -y install libminiupnpc-dev
rm muco419.php 2>/dev/null
wget https://github.com/tybiboune/Lytix/blob/master/multicoin.php
echo
echo Start with: php multicoin.php

