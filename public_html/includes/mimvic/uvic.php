<?php

/*

Copyright (c) 2010 Zohaib Sibt-e-Hassan ( MaXPert )

MiMViC v0.9.10

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.

*/

namespace MiMViC;

/**
* Class for throwing MiMViC specific exceptions
*/
class MiMViCException extends \Exception{
}

/**
* Class for throwing Not callable exception thrown by callIfCallable exceptions
*/
class NotCallableException extends MiMViCException{
}

/**
* Class for action handling
*/
interface ActionHandler {
	public function exec($params);
}

/**
* Global array to hold configuration of data for the framework
* 
* @var array
*/
$uvicConfig = array();

/**
* Route maps for the trigger actions
* 
* @var array
*/
$uvicConfig['maps'] = array( );

/**
* Associative array for holding the user stored data
* 
* @var array
*/
$uvicConfig['userData'] = array( );

/**
* Associative array for holding bechmark data
*
* @var array
*/
$uvicConfig['bechmark'] = array( );

/**
* Store the user associated data key value pairs
* 
* @param mixed $name name of parameter to be set
* @param mixed $value value of parameter to be set
*/
function store($name, &$value){
	global $uvicConfig;
	$uvicConfig['userData'][$name] = $value;
}


/**
* Retrieve the user stored value
* 
* @param mixed $name
* @return mixed stored data against given $name
*/
function retrieve($name){
	global $uvicConfig;
    if(!isset($uvicConfig['userData'][$name]))
        return null;
	return $uvicConfig['userData'][$name];
}

/**
* Remove the regular expression ?.+ from URL
*
* @param string $url url to strip get parameters from it
*/
function uRemoveGetParams($url){
	$relUrl = explode('?', $url);
	return $relUrl[0];
}

/**
* Get URI segement after the script URI
* 
* @return string relative uri containing the path after the index.php
*/
function ugetURI(){
	//Seprate Segments
	$req=uRemoveGetParams($_SERVER['REQUEST_URI']);
	$page=$_SERVER['SCRIPT_NAME'];

	// Try if its mod_rewrite
	if( stripos($req, $page) === FALSE && isset( $_SERVER['REDIRECT_URL'] ) ){
		$page = explode('/', $page);
		$page = array_slice($page, 0, -1);
		if (count($page) > 1){
			$page = join('/', $page).'/';
		}else{
			$page = '';
		}
	}
	
	//Bug fix 2/20/2008 if no index.php was present at the end :D
	if(strlen($req)<strlen($page)){
		$req=$page;
	}
	
	
	//make sure the end part exists...
	$req=str_replace($page,'',$req);
	
	// if the starting '/' is missing preppend it
	if(strlen($req)=== 0 || $req[0]!=='/'){
		$req = '/'.$req;
	}
	
	return $req;
}

/**
* Get request method
* 
* @return string lowered case request methond ( currently supporting GET, PUT, DELETE, POST)
*/
function ugetReqMethod(){
	return strtolower( $_SERVER['REQUEST_METHOD'] );
}

/**
* Escape string for regular expression so that it can be used in literal terms
* @param	string	string value to be escaped
* @return	string	escaped string for regular expression 
*/ 
function uescapeForRegex($str){
	$esc_syms = array('^','[','.','$','{','*','(',"\\",'/','+',')','|', '?', '>', '<');
	foreach($esc_syms as $sym)
		$str = str_replace($sym, "\\".$sym, $str);
	return $str;
}

/**
* Create pattern out of seperated segments (exploded at / from URI)
* and according to there type compile a regular expression for the system
* @param array $elms array of segments exploded from url (will be dealt literally)
* @return string a compiled regular expression string 
*/ 
function ucompileExpression($elms){
	$namedParamRex = '([^\/]+)';
	$anyParamRex = '(.*)';
	$exp = array();
	foreach($elms as $elm){
		//For named parameter
		$v = '';
		if(strlen($elm) && $elm[0] == ':')
			$v = $namedParamRex;
		elseif($elm === '*')
			$v = $anyParamRex;
		else
			$v = uescapeForRegex($elm);
		$exp[] = $v;
	}
	
	return '/^'.implode('\/', $exp).'$/i';
}

/**
* Parse URI segement. Tries to match URL on given pattern without using regexp for performance.
* Pattern can have expression like following
*    # /foo/:varname/bar => param['varname'] will contain value
*    # /foo/* /bar/* => param['segments'] will contain all * parsed params
* 
* @param string $pattern custom pattern expression
* @param string $ur the url to match against the pattern
* @return mixed parsed array containing associative array of 'name' => 'value for param', 'segments' => array() containg '*' params; false if url doesn't match
*/
function uparseURIParams($pattern, $ur){
	$psegs = explode('/', $pattern); //Pattern segments
	//$usegs = explode('/', $ur); //URI segments
	
	//Compile Regular expression out of pattern string
	$exp = ucompileExpression($psegs);
	$matches = array();
	$m = preg_match($exp, $ur, $matches);
	
	//If matching fails return false
	if(!$m){
		return false;
	}
	
	//Map and populate values from matched $matches to returnable string
	$ret = array('segments' => array());
	$i = 1;
	foreach($psegs as $pseg){
		if(strlen($pseg) && $pseg[0] == ':'){
			$ret[substr($pseg, 1)] = $matches[$i++];
		}elseif($pseg === '*'){
			$ret['segments'][] = explode('/', $matches[$i++]);
		}
	}
	return $ret;
}

/**
* Tries to check if the $obj can be called through $params, throws NotCallableException in case of failure 
* @param 	mixed 	$obj 	array, or string name, or function object that would be tried to be called
* @param 	array 	$params	set of parameters to be passed to the function
* @return	mixed	value returned by function call
*/
function ucallIfCallable($obj, $params){
	$func_name = '';
	$isCallable = is_callable($obj, true, $func_name);
	if($isCallable){
		return call_user_func($obj, $params);
	}
	throw new NotCallableException();
}
	
/**
* Tries to check if the $obj can be called through $params if failure occurs invokes the error trigger 
* @param 	mixed 	$obj 	array, or string name, or function object that would be tried to be called
* @param 	array 	$params	set of parameters to be passed to the function
* @return	mixed	value returned by function call, no value in case of failure
*/
function ucallNHandle($obj, $params){
	try{
		return ucallIfCallable($obj, $params);
	}catch(NotCallableException $e){
		//TODO: Introduce the mechanism to handle failure to call Error 404 
	}
}

/**
* Trigger the $uri matching function according to request $method
* 
* @param mixed $uri URI obtained from request
* @param mixed $method REQUEST method
*/
function utriggerFunction($uri, $method){
	global $uvicConfig;
	$map = &$uvicConfig['maps'];
	
	foreach($map[$method] as $patrn => $info){
		//Try to match
		$cParams = uparseURIParams($patrn, $uri);
		$ret = false;
		//Catch validity and call
		if( is_array($cParams) )
		{
			// Select the first fit method and call it
			foreach($info as $inf){
				if( $inf['agent'] === false || preg_match($inf['agent'], $_SERVER['HTTP_USER_AGENT']) > 0 ){
					if( is_array($inf['func']) ){
						if( count($inf['func']) == 2 && is_string($inf['func'][1]) ){
							$ret = ucallNHandle($inf['func'], $cParams);
						}else{
							//Chained call from array
							$ret = array();
							foreach($inf['func'] as $arg){
								if( is_callable($arg) ){
									$ret[] = ucallNHandle($arg, $cParams);
								}
							}
						}
					}elseif( is_callable( $inf['func']) ){
						//If string or function handler
						$ret = ucallNHandle($inf['func'], $cParams);
					}
					return $ret;
				}
			}
		}
	}
	
	//System failed to find any match
	return NULL;
}

/**
* Trigger on request type 
* 
* @param mixed $method request method(s); can be array or * for all method types
* @param mixed $uri request urls
* @param mixed $func callback function
* @param mixed $agent requesting agent regular expression
*/
function dispatch($method, $uri, $func, $agent = false){
	global $uvicConfig;
	$map = &$uvicConfig['maps'];
    
	// for all methods *
    if($method == '*'){
        $method = array('get','post','put','delete');
	}
	
	//--If method was array then for all methods register the function
	if( is_array($method) ){
		foreach($method as $mthd){
			dispatch($mthd, $uri, $func, $agent = false);
		}
		return;
	}//--If URI was array then for all URIs register the function
	elseif( is_array($uri) ){
		foreach($uri as $one_url){
			dispatch($methd, $one_url, $func, $agent = false);
		}
		return;
	}
	
	if( !isset($map[$method]) ){
		$map[$method] = array();
	}
	
	if( !isset($map[$method][$uri]) ){
		$map[$method][$uri] = array();
	}
	
	$map[$method][$uri][] = array('method'=> $method, 'func'=> $func, 'agent' => $agent);
}

/**
* get request register
* 
* @param mixed $uri request url
* @param mixed $func callback function name or function itself
* @param mixed $agent requesting agent regular expression
*/
function get($uri, $func, $agent = false){
	dispatch('get', $uri, $func, $agent);
}

/**
* post request register
* 
* @param mixed $uri request url
* @param mixed $func callback function name or function itself
* @param mixed $agent requesting agent regular expression
*/
function post($uri, $func, $agent = false){
	dispatch('post', $uri, $func, $agent);
}

/**
* put request register
* 
* @param mixed $uri request url
* @param mixed $func callback function name or function itself
* @param mixed $agent requesting agent regular expression
*/
function put($uri, $func, $agent = false){
	dispatch('put', $uri, $func, $agent);
}

/**
* delete request register
* 
* @param mixed $uri request url
* @param mixed $func callback function name or function itself
* @param mixed $agent requesting agent regular expression
*/
function delete($uri, $func, $agent = false){
	dispatch('delete', $uri, $func, $agent);
}

/**
* Create approriate callable object for an ActionHandler derived class of 
* specified $name
* @param string $name	name of class to instantiate
* @param object $cls	pre-constructed object (if anything other than using default constructor is to be used)
*/
function Action($name, $clsObj = NULL){
	if($clsObj === NULL)
		$clsObj = new $name();
	return array(&$clsObj, 'exec');
}


/**
* Render template with $template_name file path and $_tempateData containing associative data
*/
function render($template_name,$_templateData=array()){
	if(stristr($template_name,'.php')===FALSE)
		$template_name=$template_name.'.php';
	
	//Create variables for each of sent data index
	extract($_templateData,EXTR_OVERWRITE);
	
	//Check existance and load file
	if(file_exists($template_name))
		require($template_name);
	else
		return NULL;
		
	return true;
}

/**
* Start benchmark timer for given $name
* 
* @param string $name name of marker to save
*/
function startBenchmark($name){
	global $uvicConfig;
	
	$uvicConfig['benchmark'][$name] = microtime();
}

/**
* Calculate total time consumed for given benchmark $name
* 
* @param string $name name of the marker to calculate total time for
* @return mixed null incase of $name mark not being found; float otherwise containing the total time consumed
*/
function calcBenchmark($name){
	global $uvicConfig;
	
	if( !isset($uvicConfig['benchmark'][$name]) )
		return null;
	
	list($startMic, $startSec) = explode(' ', $uvicConfig['benchmark'][$name]);
	list($endMic, $endSec) = explode(' ', microtime());
	
	return (float)($endMic + $endSec) - (float)($startMic + $startSec);
}

/**
* Reset a maps variable; useful for creating re-routing mechanism
* Fitering routes as subset levels
*/
function reset(){
	$uvicConfig['maps'] = array();
}

/**
* start engine
*/
function start(){
	$url = ugetURI();
	$ret = utriggerFunction( $url , ugetReqMethod() );
	return $ret;
}

?>
