<?php
/*
* Looks up DNS A records for all domains, and updates dns_info if they aren't pointing at the server the are associated with
* TODO - punycode domains, better return messages, do a lookup on * domains and only warn thats its not right, if domains points to IP in our range then return the servername too. Dont check domains that arent active
* That every domain only is stored in the domain table once, even if it exists on several servers, breaks the script if domains points to several servers - like pompdelux.dk
*/

require dirname(__FILE__).'/../public_html/includes/bootstrap.php';
use MiMViC as mvc;  

$servers = R::find('server');

foreach ($servers as $server)
{
  $vhostEntries = R::find('vhostEntry','server_id=?',array($server->id));

  foreach ( $vhostEntries as $entry)
  {
    $domain = R::load('domain',$entry->domain_id);

    //echo $server->name." / ".$domain->name."\n";

    try
    {
      cmp_ip($server->ext_ip, $domain );
      $entry->note = '';
      $entry->is_valid = true;
    }
    catch( Exception $e )
    {
      //echo $e->getMessage()."\n";

      $entry->note = $e->getMessage();
      $entry->is_valid = false;
    }
    R::store($entry);
  }
}

/**
 * 
 */
function cmp_ip($serverIP, RedBean_OODBBean $domain)
{
  if (strpos($domain->name,'*') !== false)
  {
    throw new Exception('Domain name contains *');
  }
  if ( $dns = gethostbynamel($domain->name) )
  {
    $error = true;

    $registeredIPs = array();
    $related = R::related($domain,'ip_address');
    
    if ( !empty($related) )
    {
      foreach ( $related as $rel )
      {
        $registeredIPs[ $rel->value ] = $rel;
      }
    }

    $updateTimestamp = mktime();

    foreach ( $dns as $ip )
    {
      $ipAddress = null;
      if ( empty($registeredIPs) || !isset($registeredIPs[$ip]) )
      {
        $ipAddress = R::dispense('ip_address');
        $ipAddress->created = $updateTimestamp;
        $ipAddress->value = $ip;
        $ipAddress->type = 'A';
#        error_log(__LINE__.':'.__FILE__.' Creating: '. $ip .' => '. $domain->name); // hf@bellcom.dk debugging
      }
      else
      {
        if ( isset($registeredIPs[$ip]) )
        {
          $ipAddress = $registeredIPs[$ip];
#          error_log(__LINE__.':'.__FILE__.' Updating: '. $ip .' => '. $domain->name); // hf@bellcom.dk debugging
          unset($registeredIPs[$ip]);
        }
      }

      if ( $ipAddress instanceof RedBean_OODBBean )
      {
        $ipAddress->updated = $updateTimestamp;
        R::store($ipAddress);
#        error_log(__LINE__.':'.__FILE__.' Storing: '.$ipAddress->value); // hf@bellcom.dk debugging
        R::associate( $domain, $ipAddress );
      }

      if ( $ip == $serverIP )
      {
        $error = false;
      }
    }

    if ( !empty($registeredIPs) ) // clean ip not used any more
    {
      foreach ( $registeredIPs as $ip )
      {
        R::unassociate($ip,$domain);
        R::trash($ip);
      }
    }

    if ($error)
    {
      throw new Exception('Domain does not point to server');
    }
  }
  else
  {
    throw new Exception('No A record exists, or domains does not exist');
  }
}
?>
