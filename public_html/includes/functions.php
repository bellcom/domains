<?php

/**
 * Returns all the domains that does not have an owner and is of type: name
 *
 * @return array Contains Domain Beans 
 * @author Henrik Farre <hf@bellcom.dk>
 **/
function getUnrelatedMainDomains()
{
  $allDomains = R::find('domain');
  $unrelatedDomains = array();

  foreach ( $allDomains as $domain )
  {
    if ( $domain->type !== 'name' )
    {
      continue;
    } 

    $relations = array();
    $relations = R::related($domain,'owner');
    if ( empty($relations) )
    {
      $unrelatedDomains[] = $domain;
    }
  }

  return $unrelatedDomains;
}

/**
 * Groups all servers by type (xen0,xenu) 
 *
 * @return array 
 * @author Henrik Farre <hf@bellcom.dk>
 **/
function getGroupedByType()
{
  $servers = R::find('server');
  $data = array();

  $groupedServers = array();

  foreach ($servers as $server) 
  {
    if ( is_null( $server->parent_id ) && $server->type == 'xen0' )
    {
      $groupedServers['virtual'][ $server->id ]['xen0'] = $server;
    }
    if ( !is_null( $server->parent_id ) && $server->type == 'xenu' )
    {
      $groupedServers['virtual'][ $server->parent_id ]['xenu'][] = $server;
    }
    if ( $server->type == 'physical' )
    {
      $groupedServers['physical'] = $server;
    }
  }

  return $groupedServers;
}

/**
 * Returns all servers
 *
 * @return array
 * @author Henrik Farre <hf@bellcom.dk>
 **/
function getAll()
{
  return R::find('server');
}

/**
 * Returns an array contaning which fields is enabled for a given view 
 *
 * @param string $view
 * @return array 
 * @author Henrik Farre <hf@bellcom.dk>
 **/
function getEnabledFields( $view )
{
  if ( isset($_COOKIE['enabledFields']) )
  {
    $enabledFields = unserialize( $_COOKIE['enabledFields'] );
    if ( isset($enabledFields[$view]) )
    {
      return $enabledFields[$view];
    }
  }

  // Return all
  $availableFields = getAvaliableFields($view);

  return $availableFields;
}

/**
 * Returns all avaliable fields for an given view
 *
 * @todo: use view var to select
 * @param string $view
 * @return array 
 * @author Henrik Farre <hf@bellcom.dk>
 **/
function getAvaliableFields( $view )
{
  $availableFields = array(
    'name'             => 'Name',
    'int_ip'           => 'IP (internal)',
    'ext_ip'           => 'IP (external)',
    'uptime'           => 'Uptime',
    'os'               => 'OS',
    'os_release'       => 'OS release',
    'software_updates' => 'Software updates',
    'kernel_release'   => 'Kernel',
    'arch'             => 'Arch',
    'cpu_count'        => 'CPU count',
    'memory'           => 'Memory',
    'harddrives'       => 'Harddrives',
    'partitions'       => 'Partitions',
    'actions'          => 'Actions',
    'comment'          => 'Comments',
  );

  return $availableFields;
}
