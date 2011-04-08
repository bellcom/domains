<?php
use MiMViC as mvc;  

/**
 * Module for showing servers
 *
 * @packaged default
 * @author Henrik Farre <hf@bellcom.dk>
 **/
class servers
{
  private $views = array();

  /**
   * Setup views
   *
   * @return void
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  public function __construct()
  {
    $this->views = array(
      'table' => array( 'tpl' => 'templates/servers_table.tpl.php'),
      'grouped' => array( 'tpl' => 'templates/servers.tpl.php' ),
      );
  }

  /**
   * Checks if a view exists in the current module
   *
   * @param string $view THe name of the view
   * @return bool
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  public function hasView( $view )
  {
    if ( isset($this->views[$view] ))
    {
      return true;
    }
    return false;
  }

  /**
   * Renders the selected view 
   *
   * @param string $view The name of the view
   * @param array $data Data for the template from outside this module
   * @return bool
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  public function executeView( $view, array $data )
  {
    if ( $this->hasView($view) )
    {
      switch ($view) 
      {
        case 'table':
          $data['servers'] = $this->getAll();
          break;
        case 'grouped':
        default:
          $data['servers_grouped'] = $this->getGroupedByType();
          break;
      }

      mvc\render($data['designPath'].$this->views[$view]['tpl'], $data);
    }
    else
    {
      throw new InvalidArgumentException( 'The requested view "'.$view.'" does not exist in this module' );
    }
    return true;
  }

  /**
   * Groups all servers by type (xen0,xenu) 
   *
   * @return array 
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  private function getGroupedByType()
  {
    $servers = R::find('server');
    $data = array();

    $groupedServers = array();

    foreach ($servers as $server) 
    {
      if ( is_null( $server->parent_id ) && $server->type == 'xen0' )
      {
        $groupedServers[ $server->id ]['xen0'] = $server;
      }
      if ( !is_null( $server->parent_id ) && $server->type == 'xenu' )
      {
        $groupedServers[ $server->parent_id ]['xenu'][] = $server;
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
  private function getAll()
  {
    return R::find('server');
  }

} // END class servers
