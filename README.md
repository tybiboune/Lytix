# Lytix
Easy masternodes install script. - forked from meikel's multicoins install script

currently ONLY works for masternodes, gonna adapt it to MAXnodes as soon as I can find the time

- download install_MN419.sh script to your VPS

- give the right permissions
sudo chmod +x install_MN419.sh

Then launch the script 
./install_MN419.sh

and follow the instructions carefully!

PRE-REQUISITES:

- 1 computer that will host the wallet that you'll use to create the MN addresses, send the collaterals and keep your coins safe, 
  aka "cold wallet". You won't need to keep that one online, once you've setup your masternodes and checked that they're running.
  
- 1 VPS with at least 1 CPU & 2 GB ram & a bit of harddisk (20 Go is ok), with UBUNTU 16.04 -> with this kind of configuration you can setup up to 10 Lytix masternodes (for now).
  
- OPTIONAL: a few ipv6 addresses pre-configured on your VPS (depending on the number of masternodes you plan to host... 15 masternodes = one ipv4 address + 14 ipv6 )- the way to do this depends on your VPS provider. IPV6 is not mandatory, if you only have one IPV4 address the script will work too.
  
  
  To get your collateral transaction id & outputIndex, you need to wait at least 1 confirmation.
  
  To activate your masternodes, you need to wait at least 16 confirmations for your collateral transaction.
  
  Enjoy the best masternodes project ever =)
