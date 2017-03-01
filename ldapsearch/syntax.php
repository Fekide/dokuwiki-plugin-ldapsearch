<?php
/**
 * PHP Includes via Syntax
 *
 * Please create the directory "phpincludes" in your installation of
 * DokuWiki. Now you can put there any HTML or PHP File you want to
 * this directory.
 *
 * <phpinc=filename>
 * 
 * The syntax includes the PHP file per include an puts the result into
 * the wiki page.
 *
 * @license    GNU_GPL_v2
 * @author     Darren Hemphill <contact [alt] rendezz [dlot] co [dlot] za>
 * @adoptedby  Oliver Geisen <oliver [at-sign] rehkopf-geisen [dotsign] de>
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
 
class syntax_plugin_ldapsearch extends DokuWiki_Syntax_Plugin {

	var $ldapsearch_conf;
	function getInfo(){
		return array(
			'author' => 'Baseline IT',
			'email'  => 'info@baseline-remove-this-it.co.za',
			'date'   => '2017-03-01',
			'name'   => 'LDAP Search',
			'desc'   => 'Allows you to do an inline LDAP search using LDAP URL syntax or configured searches',
			'url'    => 'https://www.dokuwiki.org/plugin:ldapsearch',
		);
	}


	function getType(){ return 'substition'; }
	function getSort(){ return 1; }

	function connectTo($mode) {
		$searchTriggerPattern = '\[ldapsearch .*?\]';
		$this->Lexer->addSpecialPattern($searchTriggerPattern ,$mode,'plugin_ldapsearch');
	}

	function handle($match, $state, $pos, &$handler) {
		$urlSyntax = 'ldap:\/\/([-\w.]+):([0-9]+)\/([^\?]*)\?([^\?]*)\?(base|one|sub)\?([^\]]+)';
		$paramSyntax = ' (\w+)=("[^"]+"|[^\s\]]+)';
		// build the ldapsearch_conf here only once
		if(!$this->ldapsearch_conf) {
			$this->build_ldapsearch_conf();
		}
		switch ($state) {
			case DOKU_LEXER_SPECIAL : {
				$matches = array();
				if(preg_match("/$urlSyntax/",$match,$matches)) {
					$ldapDetails = array(
								'hostname' => $matches[1],
								'port' => $matches[2],
								'basedn' => $matches[3],
								'attributes' => explode(",",$matches[4]),
								'scope' => $matches[5],
								'filter' => $matches[6],
							);
					$data = array( 'result' => null, 'ldapDetails' => $ldapDetails );
					return array($state, $data);          
				} elseif(preg_match_all("/$paramSyntax/",$match,$matches, PREG_SET_ORDER)) {
					$ldapDetails = array();
					foreach($matches as $pair) {
						$value = preg_replace('/^"(.*?)"$/','$1',$pair[2]);
						$ldapDetails[$pair[1]] = $value;
					}
					// return null if no name specified
					if(!$ldapDetails['search']) { return null; }
					if($this->getConf('allow_overrides')) {
						// allow overrides
						foreach (array('hostname','port','basedn','attributes','scope','binddn','bindpassword') as $key) {
							if(!$ldapDetails[$key]) 
							{ 
								$ldapDetails[$key] = $this->ldapsearch_conf[$ldapDetails['search']][$key]; 
							}
						}
					}
					// explode attributes
					$ldapDetails['attributes'] = explode(',',$ldapDetails['attributes']);
					// on its way
					$data = array( 'result' => null, 'ldapDetails' => $ldapDetails );
					return array($state, $data);          
				} else {
					return null;
				}
			}

			default:
				return array($state);
		}
	}

	function build_ldapsearch_conf() {
		$set_index = array();
		foreach(explode('|',$this->getConf('name')) as $name) {
			$this->ldapsearch_conf[$name] = array();
			array_push($set_index,$name);
		}
		foreach(array('hostname','port','basedn','attributes','scope','binddn','bindpassword') as $param) {
			$count = 0;
			foreach(explode('|',$this->getConf($param)) as $value) {
				$this->ldapsearch_conf[$set_index[$count]][$param] = $value;
				$count++;
			}
		}
		//print_r($this->ldapsearch_conf);
	}

	function ldapsearch_search($ldapDetails) {
		//print_r($ldapDetails);
		if(!$ldapDetails['port']) { $ldapDetails['port'] = 389; }
		if($ldap_handle = ldap_connect($ldapDetails['hostname'],$ldapDetails['port'])) {
			ldap_set_option($ldap_handle, LDAP_OPT_PROTOCOL_VERSION, 3) ;
			if(ldap_bind($ldap_handle,$ldapDetails['binddn'],$ldapDetails['bindpassword'])) {
				$value = "";
				if($ldapDetails['scope'] == 'sub') {
					$results = ldap_search($ldap_handle,$ldapDetails['basedn'],$ldapDetails['filter'],$ldapDetails['attributes']);
					$info = ldap_get_entries($ldap_handle, $results);
					$value = $info[0][strtolower($ldapDetails['attributes'][0])][0];
				} elseif($ldapDetails['scope'] == 'one') {
					$results = ldap_list($ldap_handle, $ldapDetails['basedn'], $ldapDetails['filter'],$ldapDetails['attributes']);
					$info = ldap_get_entries($ldap_handle, $results);
					$value = $info[0][strtolower($ldapDetails['attributes'][0])][0];
				} elseif($ldapDetails['scope'] == 'base') {
					$results = ldap_read($ldap_handle, $ldapDetails['basedn'], $ldapDetails['filter'],$ldapDetails['attributes']);
					$info = ldap_get_entries($ldap_handle, $results);
					$value = $info[strtolower($ldapDetails['attributes'][0])][0];
				} else {
					$value = "Unknown scope ".$ldapDetails['scope']."\n";
				}
				ldap_unbind($ldap_handle);
				return $value;
			} else {
				return "Failed to bind to LDAP on ".$ldapDetails['hostname'].":".$ldapDetails['port']."\n";
			}
		} else {
			return "Failed to connect to LDAP on ".$ldapDetails['hostname'].":".$ldapDetails['port']."\n";
		}
	}

	function render($mode, &$renderer, $indata) {
		if($mode == 'xhtml'){
			list($state, $data) = $indata;
			$result = $date['result'];
			$ldapDetails = $data['ldapDetails'];

			switch ($state) {
				case DOKU_LEXER_SPECIAL : {
					error_log("render $match\n",3,"/tmp/mylog.txt");
					$content = $this->ldapsearch_search($ldapDetails);
					$renderer->doc .= $content;
					break;
				}

			}
			return true;
		}

		// unsupported $mode
		return false;
	} 
}

?>
