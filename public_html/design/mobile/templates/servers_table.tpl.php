<a href="/servers/grouped/">View as grouped</a>
<div id="servers" class="page list">
<?php
if ( count($servers) > 0 )
{
  echo '<table>
<tr>
<th>Name</th>
<th>IP</th>
<th>Type</th>
<th>OS</th>
<th>Arch</th>
</tr>';
  foreach ($servers as $server) 
  {
    echo '<tr>
      <td>'.$server->name.'</td>
      <td>'.$server->ip.'</td>
      <td>'.$server->type.'</td>
      <td>'.$server->os.'</td>
      <td>'.$server->arch.'</td>
      </tr>';
  }
  echo '</table>';
}
else
{
  echo 'Ingen servere registeret';
}

?>
</div>
