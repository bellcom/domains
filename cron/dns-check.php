<?php
/*
* Looks up DNS A records for all domains, and updates dns_info if they aren't pointing at the server the are associated with
* TODO - punycode domains, better return messages, do a lookup on * domains and only warn thats its not right, if domains points to IP in our range then return the servername too. Dont check domains that arent active
* That every domain only is stored in the domain table once, even if it exists on several servers, breaks the script if domains points to several servers - like pompdelux.dk
*/

require dirname(__FILE__).'/../public_html/includes/bootstrap.php';
use MiMViC as mvc;  

$linker = mvc\retrieve('beanLinker');

$dnsErrors = R::find('dns_error');
if ( empty($dnsErrors) )
{
  $dnsError = R::dispense('dns_error');
  $dnsError->error_code = 10;
  $dnsError->level = 'error';
  $dnsError->desc  = 'Domain name contains *';
  R::store($dnsError);
  unset($dnsError);

  $dnsError = R::dispense('dns_error');
  $dnsError->error_code = 20;
  $dnsError->level = 'error';
  $dnsError->desc  = 'No A record exists, or domains does not exist';
  R::store($dnsError);
  unset($dnsError);

  $dnsError = R::dispense('dns_error');
  $dnsError->error_code = 30;
  $dnsError->level = 'error';
  $dnsError->desc  = 'Domain does not point to server';
  R::store($dnsError);
  unset($dnsError);
}

$servers = R::find('server');

foreach ($servers as $server)
{
  $vhosts = R::find('apache_vhost','server_id=?',array($server->id));
  foreach ( $vhosts as $vhost )
  {
    $domains = R::find('domain','apache_vhost_id=?',array($vhost->id));
    foreach ($domains as $domain)
    {
      $status = cmp_ip($server->int_ip, $domain->getFQDN() );

      if ($status !== true) 
      {
        echo $server->name." / ".$domain->name." : ".$status."\n";
        $dnsError = R::findOne('dns_error','error_code=?',array($status));
        if ( !($dnsError instanceof RedBean_OODBBean) )
        {
          echo 'Could not find dns error with code "'.$status.'"'.PHP_EOL;
          continue;
        }
        $linker->link($domain,$dnsError);
        R::store($domain);
      }
    }
  }
}

/**
 * Only compares the last part of the IP
 * 
 */
function cmp_ip($serverip, $domain)
{
  $serverip = substr(strrchr($serverip,'.'),1);
  if (strpos($domain,'*') !== false)
  {
    #return $domain ."\t Wont lookup * domains. Fix it :)\n"; 
    return 10;
  }
  if ($dns = gethostbynamel($domain))
  {
    foreach ($dns as $ip)
    {
      if (substr(strrchr($ip,'.'),1) == $serverip)
      {
        return true;
      }
    }
    return 30;
  }
  else
  {
    return 20;
  }
}
?>
