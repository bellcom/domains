<?php
use MiMViC as mvc;  
/**
 * Handles searching of beans, returns formatted results
 *
 * @packaged default
 * @author Henrik Farre <hf@bellcom.dk>
 **/
class searchHandler
{
  private $type = null;
  private $format = null;
  const JSON_AUTOCOMPLETE = 10; // Format for use with jquery ui autocomplete plugin

  public function __construct( $type = null, $format = self::JSON_AUTOCOMPLETE ) 
  {
    if ( is_null( $type ) )
    {
      throw new Exception( 'Type must be set!' );
    }
    $this->type = $type;
    $this->format = $format;
  }

  /**
   * Performs the search
   *
   * @param string $str The string to search for
   * @return array
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  public function query( $str )
  {
    if ( !method_exists($this, $this->type.'Search' ) )
    {
      throw new Exception( 'Searching for "'. $this->type .'" is not supported' );
    }

    $results = call_user_func(array($this,$this->type.'Search'),$str);

    return $this->formatResults( $results );
  }

  /**
   * Searches for domains
   *
   * @param string $str The string to search for
   * @return array
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  private function domainSearch( $str )
  {
    $finalResults = array();
    $initialResults = R::find('domain', 'name LIKE ?',array($str));
    $domainResults = array();

    foreach ($initialResults as $result )
    {
      $formattedResult = new StdClass;
      $owners = array();
      $owners  = R::related( $result, 'owner' );
      $linker = mvc\retrieve('beanLinker');
      $vhost = $linker->getBean($result,'apache_vhost');
      $server = $linker->getBean($vhost,'server');

      if ( !isset( $domainResults[ $result->name ] ) )
      {
        $domainResults[ $result->name ] = array();
        $domainResults[ $result->name ]['domains'] = array();
        $domainResults[ $result->name ]['owners']  = array();
        $domainResults[ $result->name ]['servers'] = array();
      }

      $domainResults[ $result->name ]['domains'][] = $result;
      $domainResults[ $result->name ]['owners'] = array_merge( $domainResults[ $result->name ]['owners'], $owners );
      $domainResults[ $result->name ]['servers'][] = $server;
    }

    foreach ( $domainResults as $name => $result )
    {
      $owners = array();
      $servers = array();
      $id = $name;

      if ( !empty($result['owners']) )
      {
        foreach ( $result['owners'] as $owner )
        {
          if ( isset($owners[$owner->account_id]) )
          {
            continue;
          }
          $owners[$owner->account_id] = '<a href="'. sprintf( mvc\retrieve('config')->sugarAccountUrl,  $owner->account_id ) .'">'. $owner->name .'</a>';
        }
        $owner = 'owned by '.implode(',',$owners);
      }

      if ( !empty($result['servers']) )
      {
        foreach ( $result['servers'] as $server )
        {
          $servers[] = $server->name;
        }
        $server = 'exists on '.implode(', ',$servers);
      }
      $formattedResult = array(
        'id'    => $id,
        'label' => $id,
        'type'  => $this->type,
        'desc'  => '<tr><td></td><td>'.$id .'</td><td>'. $owner .' '. $server .'</td></tr>'
      );
      $finalResults[] = $formattedResult;
    } 

    return $finalResults;
  }

  /**
   * Searches for servers
   *
   * @return array
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  private function serverSearch( $str )
  {
    $finalResults = array();
    $initialResults = R::find('domain', 'name LIKE ?',array($str));

    foreach ($initialResults as $result )
    {
      $formattedResult = array(
        'id'    => $result->id,
        'label' => $result->name,
        'type'  => $this->type,
        'desc'  => '<td></td><td>'.$result->name .'</td><td>type: '. $result->type  .' internal ip: '. $result->int_ip .'</td>'
      );
      $finalResults[] = $formattedResult;
    }

    return $finalResults;
  }

  /**
   * Searches for accounts
   *
   * @return void
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  private function accountSearch( $str )
  {
    $finalResults = array();
    require 'class.sugarCrmConnector.php';
    try
    {
      $sugar = sugarCrmConnector::getInstance(); 
      $sugar->connect(mvc\retrieve( 'config' )->sugarLogin, mvc\retrieve( 'config' )->sugarPassword);

      $results = $sugar->getEntryList( 'Accounts', "accounts.account_type = 'Customer' AND accounts.name LIKE '$str'", 'name', 0, array('name') );
      foreach ($results->entry_list as $result) 
      {
        $formattedResult = array(
          'id'    => $result->id,
          'label' => $result->name_value_list[0]->value,
          'type'  => $this->type,
          'value' => $result->name_value_list[0]->value,
        );
        $finalResults[] = $formattedResult;
      }
    }
    catch(Exception $e)
    {
      // TODO: return error msg
      error_log(__LINE__.':'.__FILE__.' '.$e->getMessage()); // hf@bellcom.dk debugging
    }
    return $finalResults;
  }

  /**
   * Formats the results
   *
   * @param array $results Result to format
   * @return array
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  private function formatResults( array $results )
  {
    $formattedResult = array();
    foreach ( $results as $result )
    {
      $entry = array();
      foreach ( array_keys($result) as $key )
      {
        $entry[$key] = $result[$key];
      }

      $formattedResult[] = $entry;
    }
    return $formattedResult;
  }
} // END class searchHandler
