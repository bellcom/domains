<?php
use MiMViC as mvc;  

class ajaxHandler implements mvc\ActionHandler
{
  private $json = array();
  const OK = 'ok';
  const WARNING = 'warning';
  const ERROR = 'error';

  /**
   * undocumented function
   *
   * @return void
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  private function setJsonMsg( array $fields, $type = self::OK )
  {
    $this->json['msg_type'] = $type;
    $this->json['error'] = ($type != self::OK) ? true : false;
    $this->json = array_merge($this->json,$fields);
  }
  private function setJsonData( array $data )
  {
    $this->json = $data;
  }

  /**
   * Returns the json with correct headers
   *
   * @return void
   * @author Henrik Farre <hf@bellcom.dk>
   **/
  public function outPutJson()
  {
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Content-type: application/json');
    echo json_encode($this->json);
  }

  public function exec($params)
  {
    register_shutdown_function( array($this,'outPutJson') );

    $this->setJsonMsg( array('msg' => 'No data returned'), self::ERROR );

    switch ($params['action']) 
    {
      case 'accountsToDomains':

        if ( empty($_GET['account_id']) || empty($_GET['domains']))
        {
          $this->setJsonMsg( array('msg' => 'Some fields are missing'), self::ERROR );
        }
        else
        {
          $owner = false;
          $owner = R::findOne('owner', 'account_id=?',array( $_GET['account_id'] ));
          if ( !( $owner instanceof RedBean_OODBBean ) )
          {
            $owner = R::dispense("owner");
            $owner->name = $_GET['account_name'];
            $owner->account_id = $_GET['account_id'];
            $id = R::store($owner);
          }

          foreach ($_GET['domains'] as $id ) 
          {
            $domain = R::load( 'domain', $id );
            R::associate( $owner, $domain );

            $otherDomainsDefinedInVhost = R::find("domain","apache_vhost_id = ?",array($domain->apache_vhost_id));
            foreach ($otherDomainsDefinedInVhost as $otherDomain) 
            {
              if ( in_array( $otherDomain->id, $_GET['domains'] ) )
              {
                continue;
              }
              R::associate( $owner, $otherDomain );
            }
          }

          $domains = getUnrelatedMainDomains();
          $html = '';
          foreach ($domains as $domain) 
          {
            $html .= '<option value="'.$domain->id.'">'.$domain->name.'</option>';
          }

          $this->setJsonMsg( array(
              'msg'      => $owner->name .' set as owner of domains',
              'content'  => $html,
            ));
        }
        break;
      case 'getDomains':
        $serverID = (int) $_GET['serverID'];
        $server = R::load("server",$serverID);

        $linker = mvc\retrieve('beanLinker');
        $vhostIDs = $linker->getKeys($server,'apache_vhost');

        $domains = array();
        foreach ($vhostIDs as $ID )
        {
          $domains = array_merge( $domains, R::find('domain', 'apache_vhost_id=?',array($ID)) );
        }

        if ( !empty($domains) )
        {
          $html = '<h1>Domains in vhosts on server (excluding www aliases)</h1><div class="domains list">';
          foreach ($domains as $domain) 
          {
            // ignore www aliases
            if ( $domain->sub == 'www' && $domain->type == 'alias')
            {
              continue;
            }
            // TODO: dns_info is missing
            $html .= '<div class="domain">
              <div class="status">'. (!empty($domain->dns_info) ? '<img src="/design/desktop/images/error.png" title="'.$domain->dns_info.'" class="icon"/>' : '').'</div>
              <div class="name'. ($domain->is_active ? '' : ' inactive')  .'">'. (($domain->type == 'alias') ? '- ' : '') .'<a href="http://'.$domain->getFQDN().'">'. $domain->getFQDN() .'</a></div>
              <br class="cls"/>
              </div>';
          }
          $html .= '</div>';

          $this->setJsonMsg( array(
              'msg'      => 'ok',
              'content'  => $html,
            ));
        }
        else
        {
          $this->setJsonMsg( array( 'msg' => 'No domains found on server'), self::WARNING);
        }

        break;
      case 'search':
        if( isset($_GET['query']) && isset($_GET['type']))
        {
          $type  = $_GET['type'];
          $query = $_GET['query'];

          $query = $query .'%';
          if ( isset($_GET['wildcards']) && $_GET['wildcards'] == 'both')
          {
            $query = '%'.$query;
          }

          $handler = new searchHandler( $type );
          $json = $handler->query($query);

          $this->setJsonData( $json );
        }
        else
        {
          $this->setJsonMsg( array( 'msg' => 'Missing paramaters'), self::ERROR);
        }
        break;
      case 'missingDomRelation':

        $servers = R::find( "server", "type = ?", array('xenu') );
        $content = array();

        foreach ( $servers as $server )
        {
          $dom0 = false;
          $dom0 = R::getParent( $server );
          if ( !($dom0 instanceof RedBean_OODBBean ) || empty( $dom0->name ) )
          {
            $content[] = $server->name;
          }
        }

        if ( empty($content) )
        {
          $this->setJsonMsg( array( 'msg' => 'No servers found', 'msg_type' => self::OK), self::ERROR);
        }
        else
        {
          $this->setJsonMsg( array( 'msg' => 'ok','content'=>implode('<br/>',$content)));
        }
        break;
      case 'missingFieldsOnServer':

        $servers = R::find( "server");
        $content = array();
        $allowedMissing = array( 'comment' );

        foreach ( $servers as $server ) 
        {
          $missingFields = array();
          foreach ( $server->getIterator() as $key => $value )
          {
            if ( in_array($key,$allowedMissing))
            {
              continue;
            }

            if ( empty( $value ) || is_null($value) )
            {
              $missingFields[] = $key;
            }
          }
          if ( !empty($missingFields) )
          {
            $content[] = 'Id: '. $server->id . (!empty($server->name) ? ' ('.$server->name.')' : '' ) .' is missing: '. implode(', ',$missingFields);
          }
        }

        $this->setJsonMsg( array( 'msg' => 'ok','content'=>implode('<br/>',$content)));
        break;

      case 'inactiveDomains':

        $domains = R::find( "domain", "is_active=?",array(false));
        $content = array();

        foreach ( $domains as $domain )
        {
          $server    = R::load( "server", $domain->server_id );
          $content[] = $domain->name .' on '. $server->name .' last updated '. date( 'd-m-Y H:i:s', $domain->updated );
        }

        if ( empty($content) )
        {
          $this->setJsonMsg( array( 'msg' => 'No inactive domains found','msg_type'=> self::OK), self::ERROR);
        }
        else
        {
          $this->setJsonMsg( array( 'msg' => 'ok','content'=>implode('<br/>',$content)));
        }
        break;
      case 'notUpdatedRecently':

        $content = array();
        $type = $params['segments'][0][0];
        $ts   = mktime();

        switch ($type) 
        {
          case 'domains':
            $results = R::find( "domain", "updated < ?", array( $ts ) );
            foreach ( $results as $domain )
            {
              $content[] = $domain->getFQDN();
            }
            break;
          case 'servers':
            $results = R::find( "server", "updated < ?", array( $ts ) );
            foreach ( $results as $result )
            {
              $content[] = $result->name;
            }
            break;
        }

        if ( empty($content) )
        {
          $this->setJsonMsg( array( 'msg' => 'No '. $type .' not updated the last 3 days','msg_type'=> self::OK), self::ERROR);
        }
        else
        {
          $this->setJsonMsg( array( 'msg' => 'ok','content'=>implode('<br/>',$content)));
        }
        break;

        // TODO: should be generalized to allow editing of other fields
      case 'editServerComment': 

        $serverID = (int) $_GET['serverID'];
        $server = R::load( "server", $serverID );

        if ( $server instanceof RedBean_OODBBean )
        {
          $content = '<form action="/service/ajax/saveServerComment/?serverID='.$serverID.'" method="post" id="serverCommentForm">
            <p>
            <textarea name="comment" rows="10" cols="50">'. $server->comment .'</textarea><br/>
            <input type="submit" name="serverCommentSaveAction" value="Save" />
            </p>
            </form';

          $this->setJsonMsg( array( 'msg' => 'ok', 'content' => $content));
        }
        else
        {
          $this->setJsonMsg( array( 'msg' => 'Unknown server'), self::ERROR);
        }
        break;
        // TODO: should be generalized to allow editing of other fields
      case 'saveServerComment':

        $comment = $_GET['comment'];
        $serverID = (int) $_GET['serverID'];
        $server = R::load( "server", $serverID );
        $server->comment = $comment;
        R::store($server);

        $this->setJsonMsg( array( 'msg' => 'Comment stored'));

        break;
      case 'setEnabledFields':

        $type = $_GET['type'];
        $availableFields = getAvaliableFields($type);
        $enabledFields = array();

        foreach ( $_GET['field'] as $key )
        {
          if ( isset($availableFields[$key]) )
          {
            $enabledFields[$key] = $availableFields[$key];
          }
        }

        setcookie('enabledFields', serialize( array( $type => $enabledFields) ), time()+36000, '/' );
        $this->setJsonMsg( array( 'msg' => 'Enabled fields set'));
        break;
      case 'getServerList':
        $data['hasFieldSelector'] = true;
        $data['avaliableFields']  = getAvaliableFields('servers');
        $data['enabledFields']    = getEnabledFields('servers');
        $data['serversGrouped']   = getGroupedByType();
        $data['template'] = 'design/desktop/templates/servers_list.tpl.php';
        ob_start();
        mvc\render($data['template'], $data);
        $content = ob_get_clean();

        $this->setJsonMsg( array( 'msg' => 'ok', 'content' => $content));
        break;
      case 'getFieldList':
        // TODO: should not be hardcoded
        $avaliableFields  = getAvaliableFields('servers');
        $enabledFields    = getEnabledFields('servers');
        $avaliableLi = '';
        $enabledLi   = '';
        $enabledFieldKeys = array_keys($enabledFields);

        foreach ( $avaliableFields as $key => $prettyName )
        {
          if (in_array($key,$enabledFieldKeys))
          {
            $enabledLi .= '<li id="field='.$key.'">'.$prettyName.'</li>';
          }
          else
          {
            $avaliableLi .= '<li id="field='.$key.'">'.$prettyName.'</li>';
          }
        }

        $content = '<div class="sortableListContainer first">
          <h2>Enabled fields</h2>
          <ul class="connectedSortable" id="enabledFields">
          '.$enabledLi.'
          </ul>
          </div>
          <div class="sortableListContainer last">
          <h2>Avaliable fields</h2>
          <ul class="connectedSortable" id="avaliableFields">
          '.$avaliableLi.'
          </ul>
          </div>';
        $this->setJsonMsg( array( 'msg' => 'ok', 'content' => $content));
        break;
      default:
        $this->setJsonMsg( array( 'msg' => 'Unknown action'), self::ERROR);
        break;
    };  
  }
}
