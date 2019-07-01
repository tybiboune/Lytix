<?php
$version="4.19";
function RandomString($length = 20) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
#**************************************************************************#
function dialog_menu ($args) {
    $pipes = array (NULL, NULL, NULL);
    # Allow user to interact with dialog
    $in = fopen ('php://stdin', 'r');
    $out = fopen ('php://stdout', 'w');
    # But tell PHP to redirect stderr so we can read it
    $p = proc_open ('dialog '.$args, array (
        0 => $in,
        1 => $out,
        2 => array ('pipe', 'w')
    ), $pipes);
    # Wait for and read result
    $result = stream_get_contents ($pipes[2]); #inhalt
    // Close all handles
    fclose ($pipes[2]);
    fclose ($out);
    fclose ($in);
    proc_close ($p);  #exit code
    return $result;
}
#****************************************************************#

function dialog ($args) {
    $pipes = array (NULL, NULL, NULL);
    # Allow user to interact with dialog
    $in = fopen ('php://stdin', 'r');
    $out = fopen ('php://stdout', 'w');
    # But tell PHP to redirect stderr so we can read it
    $p = proc_open ('dialog '.$args, array (
        0 => $in,
        1 => $out,
        2 => array ('pipe', 'w')
    ), $pipes);
    # Wait for and read result
    $result1 = stream_get_contents ($pipes[2]); #inhalt
    // Close all handles
    fclose ($pipes[2]);
    fclose ($out);
    fclose ($in);
    $result2=proc_close ($p);  #exit code
    $result=$result2."|".$result1;
    return $result;
}
#****************************************************************#
function dialog_input ($args) {
    $pipes = array (NULL, NULL, NULL);
    # Allow user to interact with dialog
    $in = fopen ('php://stdin', 'r');
    $out = fopen ('php://stdout', 'w');
    # But tell PHP to redirect stderr so we can read it
    $p = proc_open ('dialog '.$args, array (
        0 => $in,
        1 => $out,
        2 => array ('pipe', 'w')
    ), $pipes);
    # Wait for and read result
    $result = stream_get_contents ($pipes[2]);
    // Close all handles
    fclose ($pipes[2]);
    fclose ($out);
    fclose ($in);
    proc_close ($p);
    return $result;
}
#****************************************************************#
function scan_nodes($db,$coin)
{
  $db->exec("DELETE FROM nodes where coin='$coin'");
  
  $results = $db->query("SELECT * from coin where coin_name='$coin'");
  $row = $results->fetchArray();
  $daemon=$row['daemon'];
  $cli=$row['cli'];
  $home=$row['home_dir'];
  $prefix=$row['datadir'];
  $conf=$row['confname'];
  $prefix_len=strlen($prefix);
  if ( $handle = opendir($home) )
  { 
    // einlesen der Verzeichnisses
    while (($file = readdir($handle)) !== false)
    {
      $pos = strpos($file, substr($prefix,1));
      if ($pos) 
      { 
        $z=substr($file,$prefix_len);
        #dialog("--msgbox '$z' 5 40");
        $alias=$z;
        $status="";
        $block=0;
        $aktiv=0;
        $ip="";
        
        #exec("$cli -conf=$conf -datadir=$home/$prefix$alias masternode status >mnstatus.txt 2>&1",$lines,$result);
        exec("$cli -conf=$conf -datadir=$home/$prefix$alias masternodedebug >mnstatus.txt 2>&1",$lines,$result);

        #dialog("--title 'Masternodedebug: $coin -> $alias' --textbox 'mnstatus.txt' 0 0");

        $status_file = file("mnstatus.txt");
        $status="";
        $status=$status_file[0];
        $status=str_replace("'", '', $status);
        $status=trim($status);
        
        $sync="no";
        exec("$cli -conf=$conf -datadir=$home/$prefix$alias mnsync status >mnstatus.txt 2>&1",$lines,$result);
        #dialog("--title 'Sync: Masternode: $mn' --textbox 'mnstatus.txt' 0 0");

        
        $resp = file_get_contents("mnstatus.txt");
        $obj = json_decode($resp);
        $sync=$obj->IsBlockchainSynced;
        #dialog("--msgbox '$alias Sync status: $sync' 15 40");

#        $status_file = file("mnstatus.txt");
#        $z=$status_file[0];
#        $pos=strpos($z,"true");
#        if ($pos > 0)
#          {
#            $sync="yes";
#            #dialog("--msgbox 'status: $status' 15 40");
#          }
        
        
        $db->exec("INSERT INTO nodes(coin,alias,status,block,ip,aktiv,sync) VALUES ('$coin', '$alias', '$status', $block, '$ip', $aktiv, '$sync')");
        $errno=$db->lastErrorCode();
        if ($errno > 0) {
           $err=$db->lastErrorMsg();
           dialog("--msgbox 'SQL-Error Scan Nodes: $errno -> $err' 90 90");
        }
      }
    }
    closedir($handle);
  }  #ende -> if ( $handle = opendir($home) )
}
#****************************************************************#
function stop_all_nodes($db)
{
  $coin=$GLOBALS['coin'];
  $results = $db->query("SELECT * from coin where coin_name='$coin'");
  $row = $results->fetchArray();
  $home=$row['home_dir'];
  $prefix=$row['datadir'];

  scan_nodes($db,$coin);

  $results = $db->query("SELECT * from nodes where coin='$coin'");
  while ($row = $results->fetchArray()) 
  {
    $alias=$row['alias'];
    dialog ("--title 'Stop all $coin Nodes' --infobox 'Please wait..stopping $alias' 5 50");
    exec("systemctl stop $prefix$alias.service");
    sleep(3);
  }
}
#****************************************************************#
function start_all_nodes($db)
{
  $coin=$GLOBALS['coin'];
  $results = $db->query("SELECT * from coin where coin_name='$coin'");
  $row = $results->fetchArray();
  $home=$row['home_dir'];
  $prefix=$row['datadir'];

  scan_nodes($db,$coin);

  $results = $db->query("SELECT * from nodes where coin='$coin'");
  while ($row = $results->fetchArray()) 
  {
    $alias=$row['alias'];
    dialog ("--title 'Start all $coin Nodes' --infobox 'Please wait..starting $alias' 5 50");
    exec("systemctl start $prefix$alias.service");
    sleep(3);
  }
}

#****************************************************************#
function scan_conf($db) 
{
  $db->exec("DELETE FROM conf");
  $coin=$GLOBALS['coin'];
  $results = $db->query("SELECT * from coin where coin_name='$coin'");
  $row = $results->fetchArray();
  $home=$row['home_dir'];
  $prefix=$row['datadir'];
  $conf=$row['confname'];

  if ( $handle = opendir($home) )
  { 
    $mn_array=array();
    $i=0;
    while (($file = readdir($handle)) !== false)
    {
      $pos = strpos($file, substr($prefix,1));
      if ($pos) 
      { 
        $mn_array[$i]=$file;
        $i++;
      }
    }
    closedir($handle);
    $c=count($mn_array);
    for($i=0; $i < $c; $i++)
    {
      $z=$home."/".$mn_array[$i]."/$conf";
      #dialog("--msgbox '$z' 5 40"); 
      $conf_file = file("$z");
      for($j=0;$j < count($conf_file); $j++)
      {
        $z=$conf_file[$j];
        #dialog("--msgbox '$z' 5 40");
        #....................................
        if (substr($z,0,7) == "rpcport" )
        {
          $t = explode("=", $z);
          $rpcport=$t[1];
          #dialog("--msgbox '--> rpcport: $rpcport' 5 40");  
        }
        #..................................#
        if (substr($z,0,4) == "port" )
        {
          $t = explode("=", $z);
          $port=$t[1];
          #dialog("--msgbox '--> port: $port' 5 40");  
        }
        #..................................#
        if (substr($z,0,4) == "bind" )
        {
          $t = explode("=", $z);
          $bind=$t[1];
          $bind=trim($bind);
        }
      }  #end of for($j=0;$j < count($conf_file); $j++)
      $sql="INSERT INTO conf (coin, alias, port, rpcport, ip) ";
      $mna=$mn_array[$i];
      $sql.="VALUES ('$coin', '$mna', $port, $rpcport, '$bind')"; 
      $db->exec($sql);
    }
  } #end of  if ( $handle = opendir($home) )
  
}
#****************************************************************#
function get_privkey($db,$alias,$ip)
{
  $coin=$GLOBALS['coin'];
  $results = $db->query("SELECT * from coin where coin_name='$coin'");
  $row = $results->fetchArray();
  $daemon=$row['daemon'];
  $cli=$row['cli'];
  $home=$row['home_dir'];
  $prefix=$row['datadir'];
  $conf=$row['confname'];
  $port=$row["port"];
  $hoch_rpcport=$port;
  $lowport=$port;
  #get port
  $c = $db->querySingle("SELECT COUNT(*) as c FROM conf WHERE coin='$coin'");
  #dialog("--msgbox 'c von conf=$c' 5 40");   
  if ($c > 0) 
  {
    $port=$lowport-1;   #ipv4 only
    $results = $db->query("SELECT *from conf where coin='$coin' ORDER BY port DESC");
    while ($row = $results->fetchArray()) 
    {
      $lowport=$row['port'];
    }
    #dialog("--msgbox 'c=$c  ->lowport: $lowport' 5 40");  
  }

  $c = $db->querySingle("SELECT COUNT(*) as c FROM ipv6"); 
  if ($c == 0) 
  {
    $port=$lowport-1;   #ipv4 only
    #dialog("--msgbox 'ipv4 only  c=$c  ->port: $port' 5 40");  
  }


  # get rpcport
  $results = $db->query("SELECT * from conf where coin='$coin' ORDER BY rpcport ASC"); 
  while ($row = $results->fetchArray()) 
  {
    $hoch_rpcport=$row['rpcport'];
  }

  $rpcuser=RandomString();
  $rpcpassword=RandomString();
  $rpcallowip="127.0.0.1";
  $rpcport=$hoch_rpcport+1;
  $pos=strpos($ip,":");
  if ($pos > 0)
  {
     $ip="[".$ip."]";
  }

 $z="rpcuser=$rpcuser\n";
 $z.="rpcpassword=$rpcpassword\n";
 $z.="rpcallowip=127.0.0.1\n";
 $z.="rpcport=$rpcport\n";
 $z.="listen=1\n";
 $z.="server=1\n";
 $z.="daemon=1\n";
 $z.="logintimestamps=1\n";
 $z.="maxconnections=32\n";
 $z.="masternode=0\n";
 $z.="port=$port\n";
 $z.="bind=$ip\n";
 $z.="externalip=$ip\n";
 #$z.="masternodeprivkey=$privkey\n";

 if (!file_exists($home)) mkdir($home); 
 $folder="$home/$prefix$alias";
 $file=$folder."/".$conf;
 file_put_contents($file, $z);
 
 #dialog("--title 'Privkey' --textbox '$file' 0 0");
 dialog ("--title 'Generating Privkey' --infobox 'Please wait..' 5 50");
 $punkte="..";
 #dialog("--title 'Privkey' --msgbox 'Starte $daemon: -conf=$conf -datadir=$home/$prefix$alias ' 25 40");
 exec("systemctl start $prefix$alias");
 sleep(5);
 dialog ("--title 'Generating Privkey' --infobox 'Please wait$punkte' 5 50");
 #dialog("--title 'Privkey' --msgbox 'Starte $cli: -conf=$conf -datadir=$home/$prefix$alias ' 25 40");
 do 
 {
   exec("$cli -conf=$conf -datadir=$home/$prefix$alias createmasternodekey >mnstatus.txt 2>&1");
   #dialog("--title 'Privkey' --textbox 'mnstatus.txt' 0 0");
   $z = file("mnstatus.txt");
   $p=$z[0];
   $err=substr($p,0,5);
   #dialog("--title 'Privkey' --msgbox 'err: $p' 25 40");
   $punkte.="..";
   dialog ("--title 'Generating Privkey' --infobox 'Please wait$punkte' 5 50");
   sleep(3);
 } while ($err=="error");
 
 #dialog("--msgbox 'First PrivKey: $p ' 5 40");
 $punkte.="..";
 dialog ("--title 'Generating Privkey' --infobox 'Please wait$punkte' 5 50");
 exec("systemctl stop $prefix$alias");
 sleep(5);
 return $p;

}
#-----------------------------------------------------------------#
function make_systemservice($db,$alias)
{
  $coin=$GLOBALS['coin'];
  $results = $db->query("SELECT * from coin where coin_name='$coin'");
  $row = $results->fetchArray();
  $daemon=$row['daemon'];
  $cli=$row['cli'];
  $repo=$row['repo'];
  $port=$row['port'];
  $home=$row['home_dir'];
  $prefix=$row['datadir'];
  $conf=$row['confname'];

  $coin_user="root";
  $z="[Unit]\n";
  $z.="Description=$prefix$alias service\n";
  $z.="After=network.target\n";
  $z.="[Service]\n";
  $z.="User=$coin_user\n";
  $z.="Group=$coin_user\n";
  $z.="Type=forking\n";
  $z.="ExecStart=/usr/local/bin/$daemon -conf=/$coin_user/$home/${prefix}$alias/$conf -datadir=/$coin_user/$home/${prefix}$alias\n";
  $z.="ExecStop=-/usr/local/bin/$cli  -conf=/$coin_user/$home/${prefix}$alias/$conf -datadir=/$coin_user/$home/${prefix}$alias stop\n";
  $z.="Restart=always\n";
  $z.="PrivateTmp=true\n";
  $z.="TimeoutStopSec=60s\n";
  $z.="TimeoutStartSec=10s\n";
  $z.="StartLimitInterval=120s\n";
  $z.="StartLimitBurst=5\n";
  $z.="[Install]\n";
  $z.="WantedBy=multi-user.target\n";
  $file="/etc/systemd/system/${prefix}$alias.service";
  file_put_contents($file, $z);
  exec("systemctl enable ${prefix}$alias.service"); 


} # end of  make_systemservice($db,$alias)
#-----------------------------------------------------------------#
function make_conf($db,$alias,$privkey,$ip)
{
  $coin=$GLOBALS['coin'];
  $results = $db->query("SELECT * from coin where coin_name='$coin'");
  $row = $results->fetchArray();
  $daemon=$row['daemon'];
  $cli=$row['cli'];
  $repo=$row['repo'];
  $port=$row['port'];
  $home=$row['home_dir'];
  $prefix=$row['datadir'];
  $conf=$row['confname'];
  $hoch_rpcport=$port;
  $lowport=$port;
  #get port
  #$c = $db->querySingle("SELECT COUNT(*) as c FROM conf WHERE coin='$coin'");
  #dialog("--msgbox 'c von conf=$c' 5 40");   
  #if ($c > 0) 
  #{
  #  $port=$lowport-1;   #ipv4 only
  #  $results = $db->query("SELECT *from conf where coin='$coin' ORDER BY port DESC");
  #  while ($row = $results->fetchArray()) 
  #  {
  #    $lowport=$row['port'];
  #  }
  #  #dialog("--msgbox 'c=$c  ->lowport: $lowport' 5 40");  
  #}

  $c = $db->querySingle("SELECT COUNT(*) as c FROM ipv6"); 
  if ($c == 0) 
  {

    $results = $db->query("SELECT coin,port from conf where coin='$coin' ORDER BY port ASC"); 
    while ($row = $results->fetchArray()) 
    {
      $lowport=$row['port'];
    }
    $port=$lowport-1;   #ipv4 only
    #dialog("--msgbox 'ipv4 only  c=$c  ->port: $port' 5 40");  
  }


  # get rpcport
  $results = $db->query("SELECT * from conf where coin='$coin' ORDER BY rpcport ASC"); 
  while ($row = $results->fetchArray()) 
  {
    $hoch_rpcport=$row['rpcport'];
  }



  $rpcuser=RandomString();
  $rpcpassword=RandomString();
  $rpcallowip="127.0.0.1";
  $rpcport=$hoch_rpcport +1;
  $pos=strpos($ip,":");
  if ($pos > 0) $ip="[".$ip."]";
  $z="rpcuser=$rpcuser\n";
  $z.="rpcpassword=$rpcpassword\n";
  $z.="rpcallowip=127.0.0.1\n";
  $z.="rpcport=$rpcport\n";
  $z.="listen=1\n";
  $z.="server=1\n";
  $z.="daemon=1\n";
  $z.="logintimestamps=1\n";
  $z.="maxconnections=32\n";
  $z.="masternode=1\n";
  $z.="port=$port\n";
  $z.="bind=$ip\n";
  $z.="externalip=$ip\n";
  $z.="masternodeprivkey=$privkey\n";

  if (!file_exists($home)) mkdir($home); 
  $folder="$home/$prefix$alias";
  $file=$folder."/".$conf;
  file_put_contents($file, $z);
  return($ip);
} # end of m;ake_conf
#-----------------------------------------------------------------#
function fastsync($db,$alias)
{
  $coin=$GLOBALS['coin'];
  $results = $db->query("SELECT * from coin where coin_name='$coin'");
  $row = $results->fetchArray();
  $home=$row['home_dir'];
  $prefix=$row['datadir'];
  
  #dialog("--msgbox 'Fastsync-Check' 10 90");
  $coin=$GLOBALS['coin'];
  $c = $db->querySingle("SELECT COUNT(*) as c FROM nodes WHERE coin='$coin' and sync='1'");         
  if ($c == 0)
  {
    dialog("--msgbox 'No masternode available for  Fastsync !' 10 90");
  }
  else
  {
    $results = $db->query("SELECT * from nodes where coin='$coin' and sync='1'");
    $row = $results->fetchArray();
    $sync_alias=$row['alias'];
    #dialog("--msgbox 'Fastsync-alias: $sync_alias' 10 90");
    dialog ("--infobox 'Please wait for Fastsync...' 5 50");
    exec("systemctl stop $prefix$sync_alias");
    sleep(10);
    
    $active_node="$home/$prefix$sync_alias";
#    exec("ls -l -R >fs.log");
    dialog ("--infobox 'Copying blocks...$active_node/blocks -> $home/$prefix$alias/blocks' 5 50");
    exec("cp -r $active_node/blocks $home/$prefix$alias/  2>>fs.log");
    dialog ("--infobox 'Copying chainstate...$active_node/chainstate -> $home/$prefix$alias/chainstate' 5 50");
    exec("cp -r -v $active_node/chainstate $home/$prefix$alias/  2>>fs.log");
    dialog ("--infobox 'Copying sporks...$active_node/sporks $home/$prefix$alias/sporks' 5 50");
    exec("cp -r -v $active_node/sporks $home/$prefix$alias/  2>>fs.log");
    dialog ("--infobox 'Copying peers.dat...$active_node/peers.dat $home/$prefix$alias/peers.dat' 5 50");
    exec("cp  -v $active_node/peers.dat $home/$prefix$alias/peers.dat 2>>fs.log");
    exec("systemctl start $prefix$sync_alias");


  }
  
}
#-----------------------------------------------------------------#
function masternode_menu ($coin,$mn,$db) 
{
  $coin=$GLOBALS['coin'];
  if ($coin == "NONE")
  {
   dialog("--msgbox 'Please select a coin first !' 5 40");
   return;
  }
  
  
  $results = $db->query("SELECT * from coin where coin_name='$coin'");
  $row = $results->fetchArray();
  $coin_name=$row['coin_name'];
  $daemon=$row['daemon'];
  $cli=$row['cli'];
  $repo=$row['repo'];
  $port=$row['port'];
  $home=$row['home_dir'];
  $prefix=$row['datadir'];
  $conf=$row['confname'];

  do {
    $alias=$GLOBALS['alias'];
    exec("systemctl status ${prefix}$alias.service  >mnstatus.txt"); 
    $t= file("mnstatus.txt");
    $z=$t[2];
    $pos=strpos($z,"running");
    if($pos > 0)
    {
      $status="running";
    }
    else
    {
      $status="inactive";
    }

    $args=" --clear --title 'Submenu: Masternode [Coin: $coin  -  Masternode: $alias ]' --menu '' 30 80 30 ";
    $args.="1  'Getinfo' ";
    $args.="-  '------------------' ";
    $args.="2  'Status' ";
    $args.="-  '------------------' ";
    $args.="3  'Start/Stop Systemctl ($status)' ";
    $args.="-  '------------------' ";
    $args.="4  'Edit $conf' ";
    $args.="-  '------------------' ";
    $args.="5  'Select Masternode' ";
    $args.="-  '------------------' ";
    $args.="6  'Install Masternode' ";
    $args.="-  '--------------' ";
    $args.="7  'Delete Masternode' ";
    $args.="-  '--------------' ";
    $args.="8  'Stop all $coin Nodes' ";
    $args.="-  '--------------' ";
    $args.="9  'Start all $coin Nodes' ";
    $args.="-  '--------------' ";
    $args.="A  'Systemctl status' ";
    $args.="-  '--------------' ";
    $args.="B  'Debug Command' ";
    $args.="-  '--------------' ";
    $args.="0  'Back to Main-Menu'";
    $menu_item=dialog_menu($args);
    echo "$menu_item\n";
    if ($menu_item == "1")
    {
      exec("$cli -conf=$conf -datadir=$home/$prefix$alias getinfo >mnstatus.txt 2>&1",$lines,$result);
      dialog("--title 'Masternode: $coin_name - $alias' --textbox 'mnstatus.txt' 0 0");
    }
    #........................................................................#
    if ($menu_item == "2")
    {
      exec("$cli -conf=$conf -datadir=$home/$prefix$alias masternode status >mnstatus.txt 2>&1",$lines,$result);
      $t= file("mnstatus.txt");
      $z=$t[0];
#      $z.="\n".$t[1];
#      $z.="\n".$t[2];
#      $z.="\n".$t[3];
#      $z.="\n".$t[4];
#      $z.="\n".$t[5];
#      $z.="\n".$t[6];
#      $z.="\n".$t[7];
      
      $z.=$t[1];
      $z.=$t[2];
      $z.=$t[3];
      $z.=$t[4];
      $z.=$t[5];
      $z.=$t[6];
      $z.=$t[7];
      
      
      dialog("--title 'Masternode: $coin_name - $mn' --msgbox '$z' 15 90");
    }
    #........................................................................#
    if ($menu_item == "3")
    {
      exec("systemctl status ${prefix}$alias.service  >mnstatus.txt"); 
      $t= file("mnstatus.txt");
      $z=$t[2];
      $pos=strpos($z,"running");
      dialog ("--infobox 'Please wait.....' 5 50");
      if($pos > 0)
      {
         exec("systemctl stop ${prefix}$alias.service");
         $status="inactive";
      }
      else
      {
        exec("systemctl start ${prefix}$alias.service");
        $status="running";
      }
    }
    #........................................................................#
    if ($menu_item == "4")
    {
     $file="$home/$prefix$alias/$conf";
     #dialog("--title 'Masternode: $mn' --textbox '$file' 0 0");
     $res=dialog_input("--title 'Masternode: $mn' --editbox '$file' 0 0");
     $t = explode("|", $res);  #0=exitcode 1=inhalt
     #dialog("--title 'Debug editbox' --msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
     if ($t[0] =="") return;   #erfassung ganz beenden
     file_put_contents($file, $t[0]);
    }
    #........................................................................#

    if ($menu_item == "5")    #select masternode
    {
      $db->exec("DELETE FROM tempsort");
      $z="";
      $prefix_len=strlen($prefix);
      if ( $handle = opendir($home) )
      { 
        // einlesen der Verzeichnisses
        $mn_array=array();
        $i=1;
        $args=" --clear --title 'Select a Masternode' --menu '' 30 80 30 ";

        while (($file = readdir($handle)) !== false)
        {
            $pos = strpos($file, substr($prefix,1));
            if ($pos) 
            { 
              $z=substr($file,$prefix_len);
              $db->exec("INSERT INTO tempsort(feld) VALUES ('$z')");
            }
        }
        closedir($handle);
        $args=" --clear --title 'Select a Masternode' --menu '' 30 80 30 ";
        $mn_array=array();
        $i=1;
        $results = $db->query("SELECT * from tempsort ORDER BY feld");
        while ($row = $results->fetchArray()) 
        {
          $z=$row["feld"];
          $mn_array[$i]=$z;
          $i_str=strval($i);
          $args.="$i_str  '$z' ";
          $i++;       
        }
        do 
        {
          $menu=dialog_menu($args);
          $index=intval($menu);
          $mn=$mn_array[$index];
        } while ($menu = "0");
      }  #ende -> if ( $handle = opendir($home) )
      $GLOBALS['alias']=$mn;
    }
    #..............Install Masternode.........................................#
    if ($menu_item == "6") 
    {
       scan_conf($db);
       scan_nodes($db,$coin);

       $ipv4only=0;
       $c = $db->querySingle("SELECT COUNT(*) as c FROM ipv6"); 
       if ($c == 0) 
       {
         $ipv4only=1;
         dialog("--msgbox 'You are using ipv4only (Several Nodes with same IPV4 IP). Working is not guaranteed.' 5 40"); 
       }

       $args=" --clear --title 'Select IP' --menu '' 30 80 30 ";
       $ip_array=array();
       $i=1;
       
       $results = $db->query("SELECT ip from ipv4");
       while ($row = $results->fetchArray()) 
       {
         $ip=$row['ip'];
         $ip_array[$i]=$ip;
         $i_str=strval($i);
         $c = $db->querySingle("SELECT COUNT(*) as count FROM conf WHERE coin='$coin' and ip LIKE '%$ip%'");         
         $t=" in use";
         if (($c == 0) or ($ipv4only == 1)) 
         {
           $t="free";
           $args.="$i_str  '$ip $t' ";
           $i++;
         }
       }
       
       $results = $db->query("SELECT ip from ipv6");
       while ($row = $results->fetchArray()) 
       {
         $ip=$row['ip'];
         $ip_array[$i]=$ip;
         $i_str=strval($i);
         $c = $db->querySingle("SELECT COUNT(*) as count FROM conf WHERE coin='$coin' and ip LIKE '%$ip%'");         
         $t=" in use";
         if ($c == 0) 
         {
           $t="free";
           $args.="$i_str  '$ip $t' ";
           $i++;
         }
       }
       do 
       {
         $menu=dialog_menu($args);
         $index=intval($menu);
         $ip=$ip_array[$index];
       } while ($menu = "0"); 
       #dialog("--msgbox 'Selected IP: $ip' 5 40"); 
       if ($ip =="")
       {
        dialog("--msgbox 'No IP selected or no IP free - Install aborted' 15 40"); 
        return;
       }

       do {
        $res=dialog("--inputbox 'New alias ?' 0 0");  
        $t = explode("|", $res);  #0=exitcode 1=inhalt
        if ($t[0] > 0) return;   #erfassung ganz beenden
        $alias=$t[1];
       } while ($t[1] == "");


       $folder="$home/$prefix$alias";
       mkdir($folder);
       make_systemservice($db,$alias);
       $privkey=get_privkey($db,$alias,$ip);
       if ($privkey == "abort")
       {
         dialog("--msgbox 'Installation aborted' 5 40");
       }
       else
       {
         $externalip=make_conf($db,$alias,$privkey,$ip);          
       }
       
       fastsync($db,$alias);
       $GLOBALS['alias']=$alias; 
 
       exec("systemctl start $prefix$alias");
       $ok=0;
       do
       {
         dialog ("--infobox 'New masternode is starting...' 5 50");
         sleep(5);
         exec("$cli -conf=$conf -datadir=$home/$prefix$alias getinfo >mnstatus.txt 2>&1",$lines,$result);
         #dialog("--title 'Masternode: $coin_name - $mn' --textbox 'mnstatus.txt' 0 0");
         $t= file("mnstatus.txt");
         $z=$t[0];
         $z=trim($z);
         if ($z=="{") $ok=1;
       } while ($ok==0);
 
       system('clear');
       echo "\n";
       echo "Copy and paste the line below in your masternode.conf on your PC\n";
       echo  "\n"; 
       echo "$alias $externalip:$port $privkey\n";
       echo "<alias> <ip:port> <privkey>\n";
       echo  "\n";
       echo "Then goto your PC wallet, make a new address and send your collateral payment.\n";
       echo "Add the txid and outputindex to the line\n";
       $line = readline("<Press Enter if done>");

    } # end of 6 - Install Masternode
    
    #.................delete masternode......................................#
    if ($menu_item == "7")   #delete masternode
    {
       $args="--title 'Delete Masternode $mn' --defaultno  --yesno 'Do you really want to delete $coin Masternode  $mn ?' 7 60";
       $res=dialog($args);
       #dialog("--msgbox 'Antwort: $res' 5 40");  
       if ($res == 0) 
       {
         exec("systemctl stop ${prefix}$alias.service");
         sleep(5);
         exec("systemctl diable ${prefix}$alias.service");
         exec("rm -R $home/${prefix}$alias"); 
         $file="/etc/systemd/system/${prefix}$alias.service";
         unlink($file);
         dialog("--title '' --msgbox 'Masternode: $alias deleted !' 5 40"); 
         $GLOBALS['alias']="NONE";
       }  
    }
    #.........................................................................#
    if ($menu_item == "8")   #stop all nodes
    {
       $args="--title 'Stop all $coin Masternodes' --defaultno  --yesno 'Do you really want to stop ALL $coin Masternode ?' 7 60";
       $res=dialog($args);
       #dialog("--msgbox 'Antwort: $res' 5 40");  
       if ($res == 0) 
       {
         stop_all_nodes($db);
         $GLOBALS['alias']="NONE";
       }
    }  
    #.........................................................................#
    if ($menu_item == "9")   #start all nodes
    {
       $args="--title 'Stop all $coin Masternodes' --defaultno  --yesno 'Do you really want to start ALL $coin Masternode ?' 7 60";
       $res=dialog($args);
       #dialog("--msgbox 'Antwort: $res' 5 40");  
       if ($res == 0) 
       {
         start_all_nodes($db);
         $GLOBALS['alias']="NONE";
       }
    }
    if ($menu_item == "A")
    {
      exec("systemctl -l status ${prefix}$alias.service  >mnstatus.txt"); 
      dialog("--title 'Ststus: $coin_name - $alias' --textbox 'mnstatus.txt' 0 0");
    }  

    if ($menu_item == "B")
    {
      echo "$daemon -conf=$conf -datadir=$home/$prefix$alias \n";
      echo "$cli -conf=$conf -datadir=$home/$prefix$alias getinfo \n";
      $line = readline("<Press Enter to return>");
    }  


  } while ($menu_item != "0");

}
#****************************************************************#
function get_ipv6 ($db) 
{
  $db->exec("DELETE FROM ipv6");
  exec('/sbin/ifconfig' ,$lines, $result);
  $c=count($lines);
  for($i=1; $i < $c; $i++)
  {
    $z=$lines[$i];
    $t=explode(" ",$z);
    $t10=$t[10];
    $t12=$t[12];
    $pos=strpos($t12,"/");
    if (($t10 == "inet6") and  ($pos > 0))
    {
      $v=explode("/",$t12);
      $ipv6=$v[0];
      $db->exec("INSERT INTO ipv6(ip) VALUES ('$ipv6')");

    }
  }
  $db->exec("DELETE FROM ipv6 where ip LIKE 'fe80%'"); 
}
#****************************************************************#
function get_ip_neu($db)
{
  $db->exec("DELETE FROM ipv6");
  exec('/sbin/ifconfig >ip.txt');
  $lines=file("ip.txt");
  $z=$lines[0];
  $t=explode(" ",$z);  
  $network_interfacename=$t[0];
  #dialog("--msgbox 'Interfacename: $network_interfacename' 5 40");

  exec('ip addr show $network_interfacename | grep -vw "inet" | grep "global" | grep -w "inet6" | cut -d/ -f1  >ipv6_addresses.txt');
  $lines=file('ipv6_addresses.txt');
  $c=count($lines);
  for($i=0; $i < $c; $i++)
  {
    $z=$lines[$i];
    $ipv6=str_replace("inet6", "",$z );
    $ipv6=trim($ipv6); 
    #dialog("--msgbox '$ipv6' 5 40");
    $db->exec("INSERT INTO ipv6(ip) VALUES ('$ipv6')");
  }
  $db->exec("DELETE FROM ipv6 where ip LIKE 'fe80%'"); 
  
  $db->exec("DELETE FROM ipv4");
  exec('ip addr show $network_interfacename | grep -vw "inet6" | grep "global" | grep -w "inet" | cut -d/ -f1  >ipv4_addresses.txt');
  $lines=file('ipv4_addresses.txt');
  $c=count($lines);
  for($i=0; $i < $c; $i++)
  {
    $z=$lines[$i];
    $ipv4=str_replace("inet", "",$z );
    $ipv4=trim($ipv4); 
    #dialog("--msgbox '$ipv4' 5 40");
    $db->exec("INSERT INTO ipv4(ip) VALUES ('$ipv4')");
  }
  #$db->exec("DELETE FROM ipv6 where ip LIKE 'fe80%'"); 


}
#***************************************************************#

function wallet_menu ($coin,$mn) 
{
do {
    $args=" --clear --title 'Submenu: Masternode [Coin: $coin_name  -  Masternode: $A ]' --menu '' 30 80 30 ";
    $args.="1  'Getinfo' ";
    $args.="2  'Status' ";
    $args.="3  'Edit Conf-File' ";
    $args.="4  '--------------' ";
    $args.="5  'Select Masternode' ";
    $args.="0  'Back to Main-Menu'";
    $menu_item=dialog($args);
    echo "$menu_item\n";
    if ($menu_item == "1")
    {
    
    }
  } while ($menu_item != "0");

}
#****************************************************************#
function insert_coin($db) 
{

  #Coin Name
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Coin Name ?' 0 0");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $coin_name=$t[1];
  } while ($t[1] == "");


  #Coin Daemon
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Coin Daemon ?' 0 0");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $coin_daemon=$t[1];
  } while ($t[1] == "");
  #dialog("--msgbox 'Coin Daemon: $coin_daemon' 5 40");  

  #Coin Cli
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Coin Cli ?' 0 0");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $coin_cli=$t[1];
  } while ($t[1] == "");
  #dialog("--msgbox 'Coin Cli: $coin_cli' 5 40");  

  #Coin Repo
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste'   --inputbox 'Coin Repo/Url ?' 6 60");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] == 1) return;   #erfassung ganz beenden
    if ($t[0] == 255) return;   #erfassung ganz beenden
    $coin_repo=$t[1];
  } while ($t[1] == "");

  #Coin port
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Coin Port ?' 0 0");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $coin_port=$t[1];
  } while ($t[1] == "");

  #Home-Dir
  #do {
  #  $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Muco Home Folder ?' 0 0 'muco'");  
  #  $t = explode("|", $res);  #0=exitcode 1=inhalt
  #  #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
  #  if ($t[0] > 0) return;   #erfassung ganz beenden
  #  $coin_home_dir=$t[1];
  #} while ($t[1] == "");
   $coin_home_dir="muco";

  #Prefix
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Folder Prefix ?' 0 0");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $coin_prefix=$t[1];
  } while ($t[1] == "");

  #Coin Conf
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Name of Coin Conf?' 0 0");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $coin_conf=$t[1];
  } while ($t[1] == "");


    #sqlite3 muco.db3 "INSERT INTO coin (coin_name,daemon,cli,repo,port,home_dir,user,datadir,confname,status) VALUES ('$edit_coin','$edit_daemon','$edit_cli','$edit_repo', '$edit_unpack ','$edit_port','$edit_home_dir','root','$edit_prefix','$edit_conf','' )" 2>/tmp/muco_err.tmp
    $sql="INSERT INTO coin (coin_name,daemon,cli,repo,port,home_dir,user,datadir,confname,status) ";
    $sql.="VALUES ('$coin_name','$coin_daemon','$coin_cli','$coin_repo','$coin_port','$coin_home_dir','root','$coin_prefix','$coin_conf','')"; 
    $db->exec($sql);
    $errno=$db->lastErrorCode();
    if ($errno > 0) {
      $err=$db->lastErrorMsg();
      dialog("--msgbox 'SQL-Error: $errno -> $err' 5 40");
    }
    else
    {
      dialog("--msgbox 'Coin $coin_name stored successfully.' 10 40");
      if (!file_exists($coin_home_dir)) mkdir($coin_home_dir); 
      exec("mkdir aunpack-temp");
      $base=basename($coin_repo);
      $base=trim($base);
      unlink($base);
      $pos = strpos($base, ".zip");
      $ext="";
      if ($pos > 0) $ext = "zip";
      #dialog("--msgbox 'ext: $ext' 15 40");
      exec("wget $coin_repo");
      unset ($lines);
      unset ($result);
      $cmd="aunpack -v --extract-to='aunpack-temp' $base";
      #dialog("--msgbox '$cmd' 15 40");
      if ($ext == "zip")
      {
        exec("unzip -d ./aunpack-temp $base", $lines, $result);
      }
      else
      {
        exec("aunpack -v --extract-to='aunpack-temp' $base", $lines, $result);
      }
      
      
      
      if ($result == 0){
        exec("mv -v aunpack-temp/* /usr/local/bin");
        exec("chmod -v +x  /usr/local/bin/*");
      }  
      if (file_exists("/usr/local/bin/$coin_daemon")) {
        dialog("--msgbox '$coin_daemon installed successfully' 15 40");
      } 
      else {
        dialog("--msgbox '$coin_daemon installation failed !' 15 40");
      } 
      
      if (file_exists("/usr/local/bin/$coin_cli")) {
        dialog("--msgbox '$coin_cli installed successfully' 15 40");
      } 
      else {
        dialog("--msgbox '$coin_cli installation failed !' 15 40");
      } 
      $GLOBALS['coin']=$coin_name;
    }
    
#  
}
#****************************************************************#
function edit_coin($db) 
{
  $coin=$GLOBALS['coin'];
  $results = $db->query("SELECT * from coin where coin_name='$coin'");
  $row = $results->fetchArray();
  $daemon=$row['daemon'];
  $cli=$row['cli'];
  $repo=$row['repo'];
  $port=$row['port'];
  $home=$row['home_dir'];
  $prefix=$row['datadir'];
  $conf=$row['confname'];
  $status=$row['status'];
  # Coin name
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Coin Name ?' 0 0 '$coin'");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $coin_name=$t[1];
  } while ($t[1] == "");
  $sql="UPDATE coin SET coin_name='$coin_name' WHERE coin_name='$coin'";
  $db->exec($sql);
  $errno=$db->lastErrorCode();
  if ($errno > 0) {
    $err=$db->lastErrorMsg();
    dialog("--msgbox 'SQL-Error Coin-Name: $errno -> $err' 90 40");
  }
  $GLOBALS['coin']=$coin_name;

  # Coin daemon
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Coin Daemon ?' 0 0 '$daemon'");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $daemon=$t[1];
  } while ($t[1] == "");
  $sql="UPDATE coin SET daemon='$daemon' WHERE coin_name='$coin'";
  $db->exec($sql);
  $errno=$db->lastErrorCode();
  if ($errno > 0) {
    $err=$db->lastErrorMsg();
    dialog("--msgbox 'SQL-Error Coin-Daemon: $errno -> $err' 90 40");
  }

  # Coin cli
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Coin Cli ?' 0 0 '$cli'");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $cli=$t[1];
  } while ($t[1] == "");
  $sql="UPDATE coin SET cli='$cli' WHERE coin_name='$coin'";
  $db->exec($sql);
  $errno=$db->lastErrorCode();
  if ($errno > 0) {
    $err=$db->lastErrorMsg();
    dialog("--msgbox 'SQL-Error Coin-Cli: $errno -> $err' 90 40");
  }

  # Coin repo
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Coin Repo Url ?' 0 0 '$repo'");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $repo=$t[1];
  } while ($t[1] == "");
  $sql="UPDATE coin SET repo='$repo' WHERE coin_name='$coin'";
  $db->exec($sql);
  $errno=$db->lastErrorCode();
  if ($errno > 0) {
    $err=$db->lastErrorMsg();
    dialog("--msgbox 'SQL-Error Repo: $errno -> $err' 90 40");
  }

  # Coin port
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Coin Port ?' 0 0 '$port'");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $port=$t[1];
  } while ($t[1] == "");
  $sql="UPDATE coin SET port='$port' WHERE coin_name='$coin'";
  $db->exec($sql);
  $errno=$db->lastErrorCode();
  if ($errno > 0) {
    $err=$db->lastErrorMsg();
    dialog("--msgbox 'SQL-Error Coin-Port: $errno -> $err' 90 40");
  }

  # home und Prefix darf man nicht mehr nachträglich ändern können !
  # Muco home
  #do {
  #  $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Muco Home ?' 0 0 '$home'");  
  #  $t = explode("|", $res);  #0=exitcode 1=inhalt
  #  #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
  #  if ($t[0] > 0) return;   #erfassung ganz beenden
  #  $home=$t[1];
  #} while ($t[1] == "");
  #$sql="UPDATE coin SET home_dir='$home' WHERE coin_name='$coin'";
  #$db->exec($sql);
  #$errno=$db->lastErrorCode();
  #if ($errno > 0) {
  #  $err=$db->lastErrorMsg();
  #  dialog("--msgbox 'SQL-Error Muco Home: $errno -> $err' 90 40");
  #}
  #  Muco Prefix
  #do {
  #  $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Muco Prefix ?' 0 0 '$prefix'");  
  #  $t = explode("|", $res);  #0=exitcode 1=inhalt
  #  #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
  #  if ($t[0] > 0) return;   #erfassung ganz beenden
  #  $prefix=$t[1];
  #} while ($t[1] == "");
  #$sql="UPDATE coin SET datadir='$prefix' WHERE coin_name='$coin'";
  #$db->exec($sql);
  #$errno=$db->lastErrorCode();
  #if ($errno > 0) {
  #  $err=$db->lastErrorMsg();
  #  dialog("--msgbox 'SQL-Error Muco Prefix: $errno -> $err' 90 40");
  #}

  #  Coin conf
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Coin Confname ?' 0 0 '$conf'");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $conf=$t[1];
  } while ($t[1] == "");
  $sql="UPDATE coin SET confname='$conf' WHERE coin_name='$coin'";
  $db->exec($sql);
  $errno=$db->lastErrorCode();
  if ($errno > 0) {
    $err=$db->lastErrorMsg();
    dialog("--msgbox 'SQL-Error Coin Conf: $errno -> $err' 90 40");
  }

  #  Coin status
  do {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'Default Coin (yes/no) ?' 0 0 '$status'");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $status=$t[1];
  } while ($t[1] == "");

  $sql="UPDATE coin SET status='no'";
  $db->exec($sql);
  
  $sql="UPDATE coin SET status='$status' WHERE coin_name='$coin'";
  $db->exec($sql);
  $errno=$db->lastErrorCode();
  if ($errno > 0) {
    $err=$db->lastErrorMsg();
    dialog("--msgbox 'SQL-Error Coin Status: $errno -> $err' 90 40");
  }
 
}


#****************************************************************#
function re_install($db) 
{
  $coin=$GLOBALS['coin'];
  if ($coin =="NONE") return;
  $coin_home_dir="muco";
  $results = $db->query("SELECT * from coin where coin_name='$coin'");
  $row = $results->fetchArray();
  $daemon=$row['daemon'];
  $cli=$row['cli'];
  $coin_repo=$row['repo'];

  do 
  {
    $res=dialog("--help-button  --hfile 'temp.hlp' --help-label 'F1-Help'  --hline 'Shift-Insert for copy/paste' --inputbox 'New Update-Url ?' 5 40 ''");  
    $t = explode("|", $res);  #0=exitcode 1=inhalt
    #dialog("--msgbox 'Exitcode: $t[0] * Inhalt: $t[1]' 5 40");
    if ($t[0] > 0) return;   #erfassung ganz beenden
    $coin_repo=$t[1];
  } while ($t[1] == "");

  unlink("/usr/local/bin/$daemon");
  unlink("/usr/local/bin/$cli");
  

      if (!file_exists($coin_home_dir)) mkdir($coin_home_dir); 
      exec("mkdir aunpack-temp");
      $base=basename($coin_repo);
      $base=trim($base);
      unlink($base);
      $pos = strpos($base, ".zip");
      $ext="";
      if ($pos > 0) $ext = "zip";
      #dialog("--msgbox 'ext: $ext' 15 40");
      exec("wget $coin_repo");
      unset ($lines);
      unset ($result);
      $cmd="aunpack -v --extract-to='aunpack-temp' $base";
      #dialog("--msgbox '$cmd' 15 40");
      if ($ext == "zip")
      {
        exec("unzip -d ./aunpack-temp $base", $lines, $result);
      }
      else
      {
        exec("aunpack -v --extract-to='aunpack-temp' $base", $lines, $result);
      }
      
      if ($result == 0){
        exec("mv -v aunpack-temp/* /usr/local/bin");
        exec("chmod -v +x  /usr/local/bin/*");
      }  
      if (file_exists("/usr/local/bin/$coin_daemon")) {
        dialog("--msgbox '$daemon installed successfully' 15 40");
      } 
      else {
        dialog("--msgbox '$daemon installation failed !' 15 40");
      } 
      
      if (file_exists("/usr/local/bin/$coin_cli")) {
        dialog("--msgbox '$cli installed successfully' 15 40");
      } 
      else {
        dialog("--msgbox '$cli installation failed !' 15 40");
      } 
      $GLOBALS['coin']=$coin_name;
    

}
#****************************************************************#

function coin_menu($coin,$mn,$db) 
{
$coin=$GLOBALS['coin'];
do 
{
  $coin=$GLOBALS['coin'];
  $args=" --clear --title 'Submenu: Coin [ Selected Coin: $coin ]' --menu '' 30 80 30 ";
  $args.="1  'Select Coin' ";
  $args.="-  '------------------'  ";
  $args.="2  'Insert Coin' ";
  $args.="-  '------------------'  ";
  $args.="3  'Edit Coin' ";
  $args.="-  '------------------'  ";
  $args.="4  'Overview' ";
  $args.="-  '------------------'  ";
  $args.="5  'Delete  Coin' ";
  $args.="-  '------------------'  ";
  $args.="6  'Re-Install Wallet' ";
  $args.="-  '------------------'  ";
  $args.="0  'Back to Main-Menu'";
  $menu_item=dialog_menu($args);
  #---------------------------------------------------------------------#
  if ($menu_item == "1")
  {
    $count = $db->querySingle("SELECT COUNT(*) as count FROM coin");
    if ($count == 0) 
    {  
      $args="--title 'No coin stored !'  --msgbox 'You have not yet saved any coin !' 5 40";
      $res=dialog($args);
      $GLOBALS['coin']="NONE";
    }
    else
    {
      $args=" --clear --title 'Select a Coin' --menu '' 30 80 30 ";
      $coin_array=array();
      $results = $db->query("SELECT * from coin");
      $i=1;
      while ($row = $results->fetchArray()) {
        $coin_name=$row['coin_name'];
        $coin_array[$i]=$coin_name;
        $i_str=strval($i);
        $args.="$i_str  '$coin_name' ";
        $i++;
      }
      do {
        $menu=dialog_menu($args);
        $index=intval($menu);
        $coin=$coin_array[$index];
      } while ($menu = "0"); 
      $GLOBALS['coin']=$coin;
      $GLOBALS['alias']="NONE";
    }
  }
  #---------------------------------------------------------------------#
  if ($menu_item == "2")
  {
   insert_coin($db);
  }
  if ($menu_item == "3")
  {
   edit_coin($db);
  }

  if ($menu_item == "4")
  {
    $results = $db->query("SELECT * from coin where coin_name='$coin'");
    while ($row = $results->fetchArray()) {
      $coin_name=$row['coin_name'];
      $daemon=$row['daemon'];
      $cli=$row['cli'];
      $repo=$row['repo'];
      $port=$row['port'];
      $home=$row['home_dir'];
      $prefix=$row['datadir'];
      $conf=$row['confname'];
      $status=$row['status'];
      $z="Daemon: $daemon\n";
      $z.="Cli: $cli\n";
      $z.="Repo Url: $repo\n";
      $z.="Port: $port\n";
      $z.="Muco Home: $home\n";
      $z.="Muco Prefix: $prefix\n";
      $z.="Conf Name: $conf\n";
      $z.="Default coin: $status\n";
      dialog("--title 'Coin: $coin' --msgbox '$z' 15 100"); 
    }  
  }

  if ($menu_item == "5")  # Delete coin
  {
       $args="--title 'Delete Coin $coin' --defaultno  --yesno 'Do you want to delete coin $coin ?' 7 45";
    $res=dialog($args);
    #dialog("--msgbox 'Antwort: $res' 5 40");  
    if ($res == 0) {
      $db->exec("DELETE FROM coin WHERE coin_name='$coin'");
      dialog("--title '' --msgbox 'Coin: $coin deleted !' 5 40"); 
      $GLOBALS['coin']="NONE";
    }
    else {
      return;
    }  
  }
   if ($menu_item == "6")
   {
     re_install($db);
   }  
  
} while ($menu_item != "0");

}
#****************************************************************#
function masternodes_overview($db) 
{
  $z="";
  $results = $db->query("SELECT coin_name from coin");
  while ($row = $results->fetchArray()) 
  {
    $coin=$row['coin_name'];
    #$GLOBALS['coin']=$coin;
    scan_nodes($db,$coin);
    $results2 = $db->query("SELECT coin,alias,status from nodes WHERE coin='$coin' ORDER BY alias");
    while ($row2 = $results2->fetchArray()) 
    {
      $alias=$row2['alias'];
      $status=$row2['status'];
      $z.="$coin -> $alias: $status\n";
      
      #dialog("--msgbox '$coin -> $alias  Status: $status' 5 80");
    }
  
  }
  dialog("--msgbox '$z' 80 80");
}
#****************************************************************#
function getOwnIP($default = false)
{
    $ips = gethostbynamel('localhost');
    
    foreach ($ips as $ip)
        if ($ip != "127.0.0.1") return $ip;
        
    return $default;
}
#****************************************************************#
function load_default_coin($db)
{
  $count = $db->querySingle("SELECT COUNT(*) as count FROM coin");
  if ($count > 0) 
  {  
      $results = $db->query("SELECT * from coin where status='yes'");
      $row = $results->fetchArray();
      $coin=$row['coin_name'];
      $daemon=$row['daemon'];
      $cli=$row['cli'];
      $repo=$row['repo'];
      $port=$row['port'];
      $home=$row['home_dir'];
      $prefix=$row['datadir'];
      $conf=$row['confname'];
      $status=$row['status'];
      $GLOBALS['coin']=$coin;
  }   
  
}
 #**************************************************************#
function datenvorlauf($db)
{

  load_default_coin($db);

  $coin=$GLOBALS['coin'];
  
  get_ip_neu($db); //Erfasung aller IP Adressen
  #.............................................................#
 
  scan_nodes($db,$coin);  
  #$c = $db->querySingle("SELECT COUNT(*) as count FROM nodes WHERE coin='$coin' and status=4");
  #dialog("--msgbox '$coin MN aktiv mit Status 4: $c' 5 40"); 
  
  $GLOBALS['alias']="NONE";
  $results = $db->query("SELECT * from nodes where coin='$coin'");
  $row = $results->fetchArray();
  $GLOBALS['alias']=$row['alias']; 
 

}
#*****************************************************************#
##################################################################
## begin of main  ################################################

echo "export NCURSES_NO_UTF8_ACS=1";

mkdir("muco");
$db = new SQLite3('muco.db3');

$db->exec('DROP TABLE IF EXISTS conf');
$db->exec('DROP TABLE IF EXISTS ipv4');
$db->exec('DROP TABLE IF EXISTS ipv6');
$db->exec('DROP TABLE IF EXISTS nodes');
$db->exec('DROP TABLE IF EXISTS tempsort');

$db->exec('CREATE TABLE IF NOT EXISTS coin (coin_name STRING, daemon STRING, cli STRING ,repo STRING,unpack STRING,port STRING,home_dir STRING,user STRING,datadir STRING,confname STRING, status STRING)');
$db->exec('CREATE TABLE IF NOT EXISTS conf (coin STRING, alias STRING, port INTEGER, rpcport INTEGER, ip STRING)');
$db->exec('CREATE TABLE IF NOT EXISTS ipv4 (ip STRING)');
$db->exec('CREATE TABLE IF NOT EXISTS ipv6 (ip STRING)');
$db->exec('CREATE TABLE IF NOT EXISTS nodes (coin STRING, alias STRING, status STRING, block INTEGER, ip STRING, aktiv INTEGER, sync STRING)');
$db->exec('CREATE TABLE IF NOT EXISTS tempsort (feld STRING)');

$GLOBALS['coin']="NONE";
$count = $db->querySingle("SELECT COUNT(*) as count FROM coin");
if ($count == 0) 
{
    $args="--title 'No coin stored !'  --yesno 'Do you want to insert the first coin ?' 7 45";
    $res=dialog($args);
    #dialog("--msgbox 'Antwort: $res' 5 40");  
    if ($res == 0) {
     insert_coin($db);
    }
}

datenvorlauf($db);


$A=$GLOBALS['alias'];
#$sys_status="stopped";

#$coin_confname="pegasus.conf";
$coin=$GLOBALS['coin'];
do {
  $coin=$GLOBALS['coin'];
  $args=" --clear --title 'Doc`s  Multi-Coin-Installer $version [Coin: $coin - Masternode: $A ]' --menu '' 30 80 30 ";
  $args.="1  'Masternodes Overview' ";
  $args.="-  '------------------'  ";
  $args.="2  'Masternode Menu' ";
  $args.="-  '------------------'  ";
  $args.="3  'Coin Menu' ";
  $args.="-  '------------------'  ";
  #$args.="4  'Wallet Menu' ";
  #$args.="-  '------------------'  ";
  $args.="0  'Exit'";
  $menu=dialog_menu($args);
  $t = explode("|", $menu);  #0=exitcode 1=inhalt
  #$menu=$t[0];
  if ($menu == 1) masternodes_overview($db);
  if ($menu == 2) masternode_menu($coin_name,$A,$db);
  if ($menu == 3) coin_menu($coin_name,$A,$db);
  #if ($menu == 4) wallet_menu($coin_name,$A,$db);
  
} while ($menu != "0");
$db->close();
?>
