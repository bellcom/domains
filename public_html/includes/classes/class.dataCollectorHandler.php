<?php

use MiMViC as mvc;  

class dataCollectorHandler implements mvc\ActionHandler
{
  public function exec($params)
  {
    $data = unserialize( base64_decode( $_POST['data'] ) );
    $linker = mvc\retrieve('beanLinker');

    if ( $data['hostname'] == null || empty( $data['hostname'] ) )
    {
      die('Missing hostname');
    }

    $server = R::findOne("server", "name = ? ", array($data['hostname']));

    if ( !($server instanceof RedBean_OODBBean) )
    {
      $server = R::dispense('server');
      $server->created = mktime();
    }
    $server->updated = mktime();
    $server->name    = $data['hostname'];
    $server->int_ip  = $data['ipaddress'];
    $server->ext_ip  = '';

    $hardware = array(
      'memory'   => $data['memorysize'],
      'cpucount' => $data['processorcount'],
      'cpu'      => $data['processor0'],
      );

    if ( isset($data['disk']['partitions']) )
    {
      $hardware['partitions'] = $data['disk']['partitions'];
    }

    $server->kernel_release   = $data['kernelrelease'];
    $server->os               = $data['lsbdistid'];
    $server->os_release       = $data['lsbdistrelease'];
    $server->arch             = $data['hardwaremodel'];
    $server->hardware         = serialize($hardware);
    $server->type             = $data['virtual'];
    $server->comment          = $server->comment; // keep existing comment - should be dropped when schema is frozen
    $server->is_active        = true;
    $server->uptime           = ( isset( $data['uptime'] ) ? $data['uptime'] : 0.0 );
    $server->software_updates = ( !empty($data['software_updates']) ? serialize( $data['software_updates'] ) : null );
    $serverID = R::store($server);

    if ( isset($data['disk']['physical']) )
    {
      foreach ( $data['disk']['physical'] as $disk )
      {
        $drive = R::findOne("harddrive", "serial_no=?", array($disk['SerialNo']));
        if ( !($drive instanceof RedBean_OODBBean) )
        {
          $drive = R::dispense('harddrive');
          $drive->created     = mktime();
          $drive->updated     = mktime();
          $drive->is_active   = true;

          $brand = ( isset($disk['brand']) ? $disk['brand'] : 'Unknown' );

          // Try an educated guess
          if ( $brand == 'Unknown' )
          {
            $found = false;
            if ( !$found && substr($disk['Model'], 0, 2) == 'ST')
            {
              $brand = 'Seagate';
              $found = true;
            }
            if ( !$found && substr($disk['Model'], 0, 2) == 'IC')
            {
              $brand = 'IBM';
              $found = true;
            }
            if ( !$found && substr($disk['Model'], 0, 3) == 'WDC')
            {
              $brand = 'Western Digital';
              $found = true;
            }
            if ( !$found && substr($disk['Model'], 0, 7) == 'TOSHIBA')
            {
              $brand = 'Toshiba';
              $found = true;
            }
            if ( !$found && substr($disk['Model'], 0, 7) == 'SAMSUNG')
            {
              $brand = 'Samsung';
              $found = true;
            }
            if ( !$found && substr($disk['Model'], 0, 6) == 'MAXTOR')
            {
              $brand = 'Maxtor';
              $found = true;
            }
          }

          $drive->brand       = $brand;
          $drive->model       = $disk['Model'];
          $drive->serial_no   = $disk['SerialNo'];
          $drive->fw_revision = $disk['FwRev'];
        }
        else
        {
          $drive->updated = mktime();
        }

        R::store($drive);
        R::associate($server,$drive);
      }
    }

    if ( $server->type === 'xen0' && isset($data['domUs']) && !empty($data['domUs']) )
    {
      foreach ($data['domUs'] as $domUName) 
      {
        $domU = array();
        $result = array();

        $domU = R::findOne("server", "name=?", array($domUName));
        if ( !($domU instanceof RedBean_OODBBean) )
        {
          $domU = R::dispense('server');
          $domU->name = $domUName;
          $domU->created = mktime();
          $domU->updated = mktime();
          $domU->type = 'xenu';
          R::attach($server,$domU);
          $domUID = R::store($domU);
        }
        else
        {
          $domU->updated = mktime();
          R::attach($server,$domU); // server is parent of domU
        }
      }
    }

    // Handle domains
    if ( isset($data['vhosts']) )
    {
      $updateTimestamp = mktime();

      foreach ($data['vhosts'] as $vhost) 
      {
        $apacheVhost = R::findOne('apache_vhost','server_id=? AND server_name=?',array($serverID,$vhost['servername']));
        if ( !($apacheVhost instanceof RedBean_OODBBean) )
        {
          $apacheVhost = R::dispense('apache_vhost');
          $apacheVhost->created = $updateTimestamp;
        }

        $apacheVhost->updated       = $updateTimestamp;
        $apacheVhost->server_name   = $vhost['servername'];
        $apacheVhost->file_name     = ( isset($vhost['filename']) ? $vhost['filename'] : null );
        $apacheVhost->document_root = ( isset($vhost['documentroot']) ? $vhost['documentroot'] : null );
        $apacheVhost->server_admin  = ( isset($vhost['serveradmin']) ? $vhost['serveradmin'] : null );

        $app = null;
        if ( isset($vhost['app']['name']) )
        {
          $app = R::findOne('app','name=?',array($vhost['app']['name']));
          if ( !($app instanceof RedBean_OODBBean) )
          {
            $app = R::dispense('app');
            $app->name = $vhost['app']['name'];
            R::store($app);
          }
        }
        
        $apacheVhost->app_version   = ( isset( $vhost['app']['version'] ) ? $vhost['app']['version'] : null);
        $apacheVhost->is_valid      = true;
        $apacheVhost->comment       = '';

        if ( $app instanceof RedBean_OODBBean )
        {
          $linker->link($apacheVhost,$app);
        }
        $linker->link($apacheVhost,$server);
        $apacheVhostID = R::store($apacheVhost);

        foreach ($vhost['domains'] as $domainEntry) 
        {
          if ( empty($domainEntry['name']) )
          {
            continue;
          }

          $domainParts = array();
          $domainParts = array_reverse( explode('.', $domainEntry['name']) );
          // TODO: check if 2 first parts are an ccTLD, see http://publicsuffix.org/list/
          $tld = null;
          $sld = null;
          $sub = null;
          $tld = array_shift( $domainParts );
          $sld = array_shift( $domainParts );
          $sub = ( !empty( $domainParts ) ? implode( '.', array_reverse( $domainParts ) ) : null );

          $sql = 'sub=? AND sld=? AND tld=? AND apache_vhost_id=?';
          $args = array($sub,$sld,$tld,$apacheVhostID);
          if ( is_null($sub) )
          {
            $sql = 'sub IS NULL AND sld=? AND tld=? AND apache_vhost_id=?';
            $args = array($sld,$tld,$apacheVhostID);
          }
          $domain = R::findOne('domain',$sql,$args);

          if ( !($domain instanceof RedBean_OODBBean) )
          {
            $domain = R::dispense('domain'); 
            $domain->created = $updateTimestamp;
          }
          $domain->updated   = $updateTimestamp;
          $domain->sub       = ( empty( $sub ) ? null : $sub );
          $domain->sld       = $sld;
          $domain->tld       = $tld;
          $domain->name      = $domainEntry['name'];
          $domain->type      = $domainEntry['type'];
          $domain->is_active = true;

          $linker->link($domain,$apacheVhost);
          $domainID = R::store($domain);
        }

        // set is_active to false for those domains in the current vhost which has not been updated
        $notUpdatedDomains = R::find("domain","updated != ? AND apache_vhost_id = ?", array( $updateTimestamp, $apacheVhostID ));
        $domain = array();
        foreach ($notUpdatedDomains as $domain) 
        {
          $domain->is_active = false; 
          $domainID = R::store($domain);
        }
      }
    }
  }
}
