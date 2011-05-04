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
  $owner = R::relatedOne($domain,'owner');
  $hasOwner = ( $owner instanceof RedBean_OODBBean ) ? true : false;

  $vhost = $linker->getBean( $domain, 'apache_vhost' );
  $server = $linker->getBean($vhost, 'server');

  echo '<tr>
    <td><a href="http://'.$domain->getFQDN().'">'.$domain->getFQDN().'</a></td>
    <td>'. ($hasOwner ? '<a href="'.sprintf( mvc\retrieve('config')->sugarAccountUrl,  $owner->account_id ) .'">'.$owner->name.'</a>' : '') .'</td>
    <td>'. $domain->type .'</td>
    <td>'. $server->name .'</td>
    <td>'. $domain->tld .'</td>
    </tr>';
}

?>
    </tbody>
  </table>
</div>
