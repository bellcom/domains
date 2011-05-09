<?php
use MiMViC as mvc;  

$hardware = unserialize($server->hardware);
echo '<tr class="'.$server->type.'">';

foreach ( $enabledFields as $key => $value )
{
  switch ($key) 
  {
    case 'software_updates':
      $updates = (!is_null($server->$key) ? unserialize($server->$key) : '' );
      echo '<td class="software_updates">';
      if ( !empty($updates) )
      {
        echo '<div class="tooltip_trigger">'.count($updates).'</div>
        <div class="tooltip">'.implode('<br/>',$updates).'</div>';
      }
      echo '</td>';
      break;
    case 'uptime':
      echo '<td class="'.$key.'">'. ( ($server->$key>0) ? (int)( $server->$key/60/60/24 ).' days' : '' ) .'</td>';
      break;
    case 'os':
      echo '<td class="os '.strtolower( $server->os ).'">'.$server->os.'</td>';
      break;
    case 'cpu_count':
      echo '<td class="hardware cpu'. ( ( $hardware['cpucount'] ) ? '' : ' error' ) .'">'. ( ( $hardware['cpucount'] )?:'<span class="error">?</span>' ) .'</td>';
      break;
    case 'memory':
      echo '<td class="hardware memory'. ( (empty($hardware['memory'])) ? ' error' : '' ) .'">'. ( (empty($hardware['memory'])) ? '?' : $hardware['memory'] ) .'</td>';
      break;
    case 'drives':
      $drives = R::related( $server, 'drive');
      echo '<td class="hardware drives">';
      if ( !empty($drives) )
      {
        foreach ( $drives as $d )
        {
          echo '<div class="tooltip_trigger"><img src="/design/desktop/images/'.($d->type=='harddrive' ? 'harddrive':'drive-cdrom').'.png" class="icon"/></div>
            <div class="tooltip">
            '. $d->brand .'<br/>
            Model: '. $d->model.'<br/>
            Serial: '.$d->serial_no.'<br/>
            Firmware: '.$d->fw_revision .'</div>';
        }
      } 
      echo '</td>';
      break;
    case 'partitions':
      echo '<td class="hardware partitions">';
      if ( isset($hardware['partitions']) )
      {
        foreach( $hardware['partitions'] as $part )
        {
          $capacity = str_replace('%','', $part['capacity'] );
          $msg = '';
          $img = '';
          if ( $capacity > 80 )
          {
            $msg = 'Partition is more than 80% full<br/>';
            $img = 'information';
          }
          if ( $capacity > 90 )
          {
            $msg = 'Partition is more than 90% full<br/>';
            $img = 'error';
          }
          if ( $capacity > 95 )
          {
            $msg = 'Partition is more than 95% full!<br/>';
            $img = 'exclamation';
          }

          echo '<div class="tooltip_trigger drive';
          if (!empty($img))
          {
            echo ' warning"><img src="/design/desktop/images/'.$img.'.png" class="icon"/>';
          }
          else
          {
            echo '"><img src="/design/desktop/images/partitions.png" class="icon"/>';
          }
          echo '</div>
            <div class="tooltip">'.$msg;
          foreach ( $part as $key => $value )
          {
            echo $key .' = '. $value .'<br/>';
          }
          echo '</div>';
        }
      }
      echo '</td>';
      break;
    case 'actions':
      echo '<td class="actions">';

      //$vhosts = R::find('apache_vhost','server_id=?',array($server->id));

      $count = R::getCell("SELECT count(*) AS count FROM apache_vhost WHERE server_id = ?",array($server->id));

      if ( $count['count'] > 0 )
      {
        echo '<a class="ajaxRequest" href="/service/ajax/getDomains/?serverID='.$server->id.'"><img src="/design/desktop/images/domain_template.png" /></a>';
      }

      echo '</td>';
      break;
    case 'comment':
      echo '<td><a class="ajaxRequest" href="/service/ajax/editServerComment/?serverID='.$server->id.'"><img src="/design/desktop/images/pencil';
      if ( empty( $server->comment ) )
      {
        echo '_add';
      }
      echo '.png" alt="Edit comment" class="icon"/></a>'.( !empty( $server->comment ) ? $server->comment : '').'</td>';
      break;
    default:
      echo '<td class="'.$key.'">'.$server->$key.'</td>';
      break;
  }
}
echo '</tr>';
