<?php
use MiMViC as mvc;  

class contentHandler implements mvc\ActionHandler
{
  public function exec($params)
  {
    $data = array();
    $designPath = 'design/'.mvc\retrieve('theme').'/';

    $module = isset($params['module']) ? $params['module'] : 'servers';
    $view   = isset($params['view']) ? $params['view'] : 'index';
                 
    $data['module']     = $module;
    $data['view']       = $view;
    $data['designPath'] = $designPath;
    $data['title']      = '';
    $data['uri']        = '/'.$module.'/'.$view;
    $data['uriArray']   = array('/',$module,$view);

    switch ($module) 
    {
      case 'domains':
        $domains = R::find('domain');
        $data['title'] = 'Domains';
        $data['domains'] = $domains;
        $data['template'] = $designPath.'templates/domains.tpl.php';
        break;

      case 'accounts':
        $data['title'] = 'Domains to accounts';
        $data['domains'] = getUnrelatedMainDomains();
        $data['template'] = $designPath.'templates/accounts.tpl.php';
        break;

      case 'servers':
        $data['title'] = 'Servers';
        $data['hasFieldSelector'] = true;
        $data['avaliableFields']  = getAvaliableFields('servers');
        $data['enabledFields']    = getEnabledFields('servers');
        $data['serversGrouped']   = getGroupedByType();
        $data['template'] = $designPath.'templates/servers_list.tpl.php';
        break;

      case 'search':
        $data['title'] = 'Search';
        $data['template'] = $designPath.'templates/search.tpl.php';
        break;

      case 'cleanup':
        $data['title'] = 'Cleanup';
        $data['template'] = $designPath.'templates/cleanup.tpl.php';
        break;

      default:
        $data['title'] = '404 Page not found';
        $data['template'] = $designPath.'templates/error.tpl.php';
        $data['error'] = array(
          'code' => '404',
          'msg' => 'Page not found',
        );
        break;
    }

    mvc\render($designPath.'templates/header.tpl.php', $data);
    mvc\render($designPath.'templates/top_menu.tpl.php', $data);

    if ( isset( $data['hasFieldSelector'] ) && $data['hasFieldSelector'] )
    {
      mvc\render($designPath.'templates/field_selector.tpl.php',$data);
    }

    if ( mvc\retrieve('debug') )
    {
      $data['values']['params'] = $params;
      mvc\render($designPath.'templates/debug.tpl.php',$data);
    }

    mvc\render($data['template'],$data);

    mvc\render($designPath.'templates/footer.tpl.php', $data);
  }
}
