<?php

# FROM PUPPET, DONT EDIT

# will post an array with stats to $collectURL

$collectURL = 'http://domains.bellcom.dk/service/datacollector/'; // FIXME: should not be hardcoded

$hostStats = array();
$facterOutput = trim( shell_exec('facter') );
$facterLines = explode("\n",$facterOutput);
foreach ($facterLines as $line)
{
  list($key,$value) = explode(' => ', $line);
  $hostStats[$key] = $value;
}

// Stop cron mail spam because of http request fails by just disabling error reporting. TODO find a more reliable IP service
$oldErrorReporting = error_reporting(0);
$hostStats['external_ip'] = getExternalIP();
error_reporting($oldErrorReporting);

if ($hostStats['virtual'] == 'xen0') 
{
  $domUs = parseXenDomUs();
  $hostStats['domUs'] = $domUs;
}

$vhosts = parseVhosts();
if (!empty($vhosts[0])) 
{
  $hostStats['vhosts'] = $vhosts;
}

$hostStats['disk']['partitions'] = partitionInfo();
if ($hostStats['virtual'] == 'xen0') 
{
  $hostStats['disk']['physical'] = physicalDiskInfo();
}

$hostStats['software_updates'] = softwareUpdates( $hostStats['lsbdistid'] );

$hostStats['uptime'] = getUptime();

function softwareUpdates( $operatingsystem )
{
  $updates = array();

  if ( $operatingsystem == 'Debian' )
  {
    shell_exec("apt-get -qq update");
    $aptget = trim(shell_exec("/usr/bin/apt-get -q -y --allow-unauthenticated -s upgrade | /bin/grep ^Inst | /usr/bin/cut -d\  -f2 | /usr/bin/sort"));
    $updates = (!empty($aptget) ? explode("\n",$aptget) : array());
  }

  return $updates;
}

/**
 * Returns uptime in seconds
 *
 * @return int
 * @author Henrik Farre <hf@bellcom.dk>
 **/
function getUptime()
{
  list( $upTime,$idleTime ) = explode(' ', trim(file_get_contents('/proc/uptime')));
  return $upTime;
}

/**
 * Gets model info from disks if hdparm is installed (and disks are named /dev/[h,s]d[a-z])
 *
 * @return array
 * @author Henrik Farre <hf@bellcom.dk>
 **/
function physicalDiskInfo()
{
  $disks = array();
  if (is_executable('/sbin/hdparm'))
  {
    foreach ( glob('/dev/[h,s]d[a-z]') as $disk )
    {
      $output = trim(shell_exec('/sbin/hdparm -i '. $disk .' 2>/dev/null'));
      $lines  = explode("\n",$output);
      foreach ( $lines as $line )
      {
        $line = trim($line);

        if ( strpos($line,'Model=') !== false  )
        {
          $clean  = array();
          $fields = explode(', ',$line);
          foreach ( $fields as $field )
          {
            list($key,$value) = explode('=',$field);
            $clean[] = trim( $key ).'='.trim( $value );
          }
          $str = implode('&',$clean);
          parse_str( $str , $result );
          $disks[$disk] = $result;
        }

        if ( strpos($line,'Config=') !== false  )
        {
          $disks[$disk]['type'] = 'harddrive';
          if ( strpos($line,'Removeable') !== false  )
          {
            $disks[$disk]['type'] = 'optical';
          }
          if ( strpos($line,'nonMagnetic') !== false  )
          {
            $disks[$disk]['type'] = 'optical';
          }
          break;
        }
      }
    }
  }
  return $disks;
}

function partitionInfo()
{
  // -TPl means: include filesystem type and use POSIX portable format, which means that long device names will stay on one line, and only show local filesystems
  $df = trim(shell_exec("df -TPl -x tmpfs | egrep -v '^Filesystem|Filsystem' | awk '{ print \"device=\" $1 \"&filesystem=\" $2 \"&disktotal=\" $3 \"&diskfree=\" $5 \"&diskused=\" $4 \"&capacity=\" $6 \"&mountpoint=\" $7 }'"));
  $dfLines = explode("\n", $df);
  foreach ($dfLines as $line)
  {
    parse_str($line,$output);
    $diskinfo[] = $output;
  }
  return $diskinfo;
}

function parseXenDomUs() 
{
  $xmlist = trim(shell_exec('/usr/sbin/xm list | tail -n +3 | cut -d\  -f 1'));
  return explode("\n", $xmlist);
}

function parseVhosts() 
{
  $vhosts = array();

  $sitesEnabled = glob( '/etc/apache2/sites-enabled/*' ); 
  foreach ( $sitesEnabled as $site )  
  {  
    if ( basename( $site ) == '000-default' )  
    {  
      continue;
    }  

    $vhostLines = file( $site, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ); 

    foreach ($vhostLines as $line) 
    {  
      # skip lines commented out (do the same for //?)
      if (preg_match('/^#/',trim($line))) 
      {  
        continue;
      }  
      $line = strtolower( trim( $line ) ); 

      if ( strpos($line,'<virtualhost') !== false )
      {  
        $vhost = array();
        $aliases = array();
        $vhost['filename'] = $site;
      }  

      if ( strpos($line,'</virtualhost') !== false && !empty($vhost) )
      {  
        $vhosts[] = $vhost;
      }  

      if ( strpos($line,'servername') !== false )  
      {  
        $vhost['servername'] = trim( strstr($line," ") ); 
        $vhost['domains'][] = array( 'name' => $vhost['servername'], 'type' => 'name' );
      }  

      if ( strpos($line,'serveralias') !== false )  
      {  
        $entries = explode(" ",$line);

        if ( !is_array($entries) )
        {  
          echo 'Something is wrong in the vhost "'.$site.'"'.PHP_EOL;
          break;
        }  

        if ( !isset($vhost['domains']) )
        {  
          $vhost['domains'] = array();
        }  

        unset($entries[0]);

        foreach ( $entries as $alias )
        {
          $aliases[] = array( 'name' => trim( $alias ), 'type' => 'alias' );
        }
        $vhost['domains'] = array_merge($vhost['domains'],$aliases);
      }

      if ( strpos($line,'documentroot') !== false  )
      {
        $vhost['documentroot'] = trim( strstr($line," "));
        $vhost['app'] = identifyApp($vhost['documentroot']);
      }

      if ( strpos($line,'serveradmin') !== false  )
      {
        $vhost['serveradmin'] = trim( strstr($line," "));
      }
    }
  }
  return $vhosts;
}

/**
 * Gets the external IP of the server
 *
 * @return string
 * @author Henrik Farre <hf@bellcom.dk>
 **/
function getExternalIP()
{
  $page = file_get_contents('http://checkip.dyndns.org/');
  preg_match('/(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)(?:[.](?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)){3}/',$page,$matches);
  return $matches[0];
}

/**
 * Tries to identify a web app by looking for specific files
 *
 * @param string $path DocumentRoot
 * @return array
 * @author Henrik Farre <hf@bellcom.dk>
 **/
function identifyApp($path)
{
  $knownApps = array(
    'eZ Publish' => array( 'app' => '/bin/php/ezcache.php', 'version' => '' ), 
    'Drupal'     => array( 'app' => '/scripts/drupal.sh', 'version' => '/CHANGELOG.txt'),
    //'Wordpress'  => array( 'app' => '', 'version' => ''),
  ); 

  foreach ( $knownApps as $name => $identifiers )
  {  
    if ( is_file($path.$identifiers['app']) )
    {  
      $version = 0.0;
      return array( 'name' => $name, 'version' => $version ); 
    }  
  }  
  return array( 'name' => 'Unknown app', 'version' => 0.0 ); 
}

$data = base64_encode( serialize( $hostStats ));

if ( isset( $argv[1] ) && $argv[1] == 'test' )
{
  print_r($hostStats);
  file_put_contents('data.tmp', $data);
  die("[DONE]\n");
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $collectURL);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, array( 'data' => $data ));
curl_exec($ch);
