<div id="domains" class="page list">

  <table class="tablesorter">
    <thead>
      <tr>
        <th>Name</th>
        <th>Owner</th>
        <th>Type</th>
        <th>Server</th>
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

  $vhost = $linker->getBean( $domain, 'apache_vhost' );
  $server = $linker->getBean($vhost, 'server');

  echo '<tr>
    <td><a href="http://'.$domain->getFQDN().'">'.$domain->getFQDN().'</a></td>
    <td>'. $ownerString .'</td>
    <td>'. $domain->type .'</td>
    <td>'. $server->name .'</td>
    <td>'. $domain->tld .'</td>
    </tr>';
}

?>
    </tbody>
  </table>
</div>
