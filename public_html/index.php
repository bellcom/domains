<?php
require 'includes/bootstrap.php';
use MiMViC as mvc;  

mvc\get('/service/ajax/:action/:format/*', mvc\Action('ajaxHandler') );  
mvc\post('/service/datacollector/', mvc\Action('dataCollectorHandler') );  
mvc\get('/:module', mvc\Action('contentHandler') );  
mvc\get('/:module/', mvc\Action('contentHandler') );  
mvc\get('/:module/:view/*', mvc\Action('contentHandler') );  
mvc\get('/*', mvc\Action('contentHandler') );  

mvc\start();
