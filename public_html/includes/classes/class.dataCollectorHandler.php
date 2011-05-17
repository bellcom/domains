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
    $server->ext_ip  = ( preg_match('/^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)(?:[.](?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)){3}$/', $data['external_ip'] ) ? $data['external_ip'] : '');

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
        // TODO: set drives inactive to make sure only active drives are is_active = true;
        $drive = R::findOne("drive", "serial_no=?", array($disk['SerialNo']));
        if ( !($drive instanceof RedBean_OODBBean) )
        {
          $drive = R::dispense('drive');
          $drive->created     = mktime();
          $drive->updated     = mktime();
          $drive->is_active   = true;
          $drive->setBrand($disk['Model']);
          $drive->model       = $disk['Model'];
          $drive->serial_no   = $disk['SerialNo'];
          $drive->fw_revision = $disk['FwRev'];
          $drive->type        = $disk['type'];
        }
        else
        {
          $drive->is_active = true;
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

      foreach ($data['vhosts'] as $v) 
      {
        $vhost = R::findOne('vhost','server_id=? AND server_name=?',array($serverID,$v['servername']));
        if ( !($vhost instanceof RedBean_OODBBean) )
        {
          $vhost = R::dispense('vhost');
          $vhost->created = $updateTimestamp;
        }

        $vhost->updated       = $updateTimestamp;
        $vhost->server_name   = $v['servername'];
        $vhost->file_name     = ( isset($v['filename'])     ? $v['filename'] : null );
        $vhost->document_root = ( isset($v['documentroot']) ? $v['documentroot'] : null );
        $vhost->server_admin  = ( isset($v['serveradmin'])  ? $v['serveradmin'] : null );

        $app = null;
        if ( isset($v['app']['name']) )
        {
          $app = R::findOne('app','name=?',array($v['app']['name']));
          if ( !($app instanceof RedBean_OODBBean) )
          {
            $app = R::dispense('app');
            $app->name = $v['app']['name'];
            R::store($app);
          }
        }
        
        $vhost->app_version   = ( isset( $v['app']['version'] ) ? $v['app']['version'] : null);
        //$vhost->is_valid      = true;
        $vhost->comment       = '';

        if ( $app instanceof RedBean_OODBBean )
        {
          $linker->link($vhost,$app);
        }
        $linker->link($vhost,$server);
        $vhostID = R::store($vhost);

        foreach ($v['domains'] as $domainEntry) 
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
          /*
          $sql = 'sub=? AND sld=? AND tld=? AND vhost_id=?';
          $args = array($sub,$sld,$tld,$vhostID);
          if ( is_null($sub) )
          {
            $sql = 'sub IS NULL AND sld=? AND tld=? AND vhost_id=?';
            $args = array($sld,$tld,$vhostID);
          }
           
          $domain = R::findOne('domain',$sql,$args);
           */

          $domain = R::findOne('domain','name = ?', array($domainEntry['name']));

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
          $domain->is_active = true;

          //$linker->link($domain,$vhost);
          $domainID = R::store($domain);

          $vhostEntry = R::findOne('vhostEntry','type = ? AND vhost_id = ? AND domain_id = ?', array($domainEntry['type'],$vhostID,$domainID));
          if ( !($vhostEntry instanceof RedBean_OODBBean) )
          {
            $vhostEntry = R::dispense('vhostEntry'); 
            $vhostEntry->created = $updateTimestamp;
            $vhostEntry->is_valid = true;
            $vhostEntry->note = '';
          }

          $vhostEntry->type = $domainEntry['type'];
          $vhostEntry->updated = $updateTimestamp;
          $linker->link($vhostEntry,$vhost);
          $linker->link($vhostEntry,$domain);
          $linker->link($vhostEntry,$server);
          R::store($vhostEntry);
        }

        // set is_active to false for those domains in the current vhost which has not been updated
        /*$notUpdatedDomains = R::find("domain","updated != ? AND vhost_id = ?", array( $updateTimestamp, $vhostID ));
        $domain = array();
        foreach ($notUpdatedDomains as $domain) 
        {
          $domain->is_active = false; 
          $domainID = R::store($domain);
        }*/
      }
    }
  }
}
