<div id="domains" class="page list">

  <table class="tablesorter">
    <thead>
      <tr>
        <th>Name</th>
        <th>Owner</th>
        <th>Server</th>
        <th>IP addresses</th>
        <th>TLD</th>
      </tr>
    </thead>
    <tbody>

<?php
use MiMViC as mvc;  

$linker = mvc\retrieve('beanLinker');

foreach ( $domains as $domain ) 
{
  $owners = array();
  $owners = R::related($domain,'owner');

  $ownerString = '';
  foreach ( $owners as $owner )
  {
    $ownerString .= '<a href="'.sprintf( mvc\retrieve('config')->sugarAccountUrl,  $owner->account_id ) .'">'.$owner->name.'</a> ';
  }

  $vhostEntries = R::find( 'vhostEntry', 'domain_id = ?', array($domain->id) );
  $servers = array();
  foreach ( $vhostEntries as $entry )
  {
    $server = $linker->getBean( $entry, 'server' );
    $servers[] = $server->name;
  }

  $ips = array();
  $ipString = '';
  $ipAddresses = R::related($domain,'ip_address');
  if ( !empty($ipAddresses) )
  {
    foreach ($ipAddresses as $ip)
    {
      $ips[] = $ip->value;
    }
    $ipString = implode(', ',$ips);
  }

  echo '<tr>
    <td><a href="http://'.$domain->name.'">'.$domain->name.'</a></td>
    <td>'. $ownerString .'</td>
    <td>'. implode(', ',$servers) .'</td>
    <td>'. $ipString .'</td>
    <td>'. $domain->tld .'</td>
    </tr>';
}

?>
    </tbody>
  </table>
</div>
