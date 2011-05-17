<?php
require 'mimvic/uvic.php';
use MiMViC as mvc;  

$config = require dirname(__FILE__).'/../config.php';
mvc\store('config',$config);

require 'redbean/rb.php';
require 'models/class.Model_Domain.php';
require 'models/class.Model_Drive.php';
require 'classes/class.ajaxHandler.php';
require 'classes/class.contentHandler.php';
require 'classes/class.dataCollectorHandler.php';
require 'classes/class.searchHandler.php';
require 'functions.php';
//require 'mobileDetect/MobileDetect.class.php';

$dsn = 'mysql:host='.mvc\retrieve('config')->dbHost.';dbname='.mvc\retrieve('config')->dbName;
R::setup($dsn,mvc\retrieve('config')->dbUsername,mvc\retrieve('config')->dbPassword);
$linker = new RedBean_LinkManager( R::$toolbox );
mvc\store('beanLinker',$linker);
// TODO: RedBean freeze schema

//$detect = MobileDetect::getInstance();

$debug = false;
mvc\store('debug',$debug);
if ( $debug )
{
  R::debug( true );
}

//$theme = $detect->isMobile() ? 'mobile' : 'desktop';
$theme = 'desktop';
mvc\store('theme',$theme);
