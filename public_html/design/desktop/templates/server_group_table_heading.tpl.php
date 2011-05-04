<?php
echo '<thead>
<tr>';

foreach ( $enabledFields as $key => $value )
{
  $class = $key;
  switch ($key) 
  {
    case 'int_ip':
    case 'ext_ip':
      $class .= " {sorter: 'ipAddress'}";
      break;
  }

  echo '<th class="'.$class.'">'.$value .'</th>';
}

  echo '</tr>
  </thead>';
