<?php

/**
 * DokuWiki Plugin ldapsearch (Syntax Component)
 *
 * @license    GNU_GPL_v2
 * @author     Darren Hemphill <contact [alt] rendezz [dlot] co [dlot] za>
 * @adoptedby  Oliver Geisen <oliver [at-sign] rehkopf-geisen [dotsign] de>
 * @adoptedby  Felix Haase <felix [dotsign] haase [at-sign] feki [dotsign] de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
	die();
}

class syntax_plugin_ldapsearch extends DokuWiki_Syntax_Plugin
{
	var $ldapsearch_conf;
	/**
	 * @return string Syntax mode type
	 */
	public function getType()
	{
		return 'substition';
	}

	/**
	 * @return string Paragraph type
	 */
	public function getPType()
	{
		return 'normal';
	}

	/**
	 * @return int Sort order - Low numbers go before high numbers
	 */
	public function getSort()
	{
		return 1;
	}

	/**
	 * Connect lookup pattern to lexer.
	 *
	 * @param string $mode Parser mode
	 */
	public function connectTo($mode)
	{
		$this->Lexer->addSpecialPattern('\{\{ldapsearch>.*?\}\}', $mode, 'plugin_ldapsearch');
	}

	/**
	 * Handle matches of the ldapsearch syntax
	 *
	 * @param string       $match   The match of the syntax
	 * @param int          $state   The state of the handler
	 * @param int          $pos     The position in the document
	 * @param Doku_Handler $handler The handler
	 *
	 * @return array Data for the renderer
	 */
	public function handle($match, $state, $pos, Doku_Handler $handler)
	{

		$urlSyntax = 'ldap:\/\/([-\w.]+):([0-9]+)\/([^\?]*)\?([^\?]*)\?(base|one|sub)\?([^\]]+)';
		$paramSyntax = ' (\w+)=("[^"]+"|[^\s\]]+)';
		// build the ldapsearch_conf here only once
		if (!$this->ldapsearch_conf) {
			$this->build_ldapsearch_conf();
		}
		switch ($state) {
			case DOKU_LEXER_SPECIAL: {
					$matches = array();
					if (preg_match("/$urlSyntax/", $match, $matches)) {
						$ldapDetails = array(
							'hostname' => $matches[1],
							'port' => $matches[2],
							'basedn' => $matches[3],
							'attributes' => explode(",", $matches[4]),
							'scope' => $matches[5],
							'filter' => $matches[6],
						);
						$data = $this->ldapsearch_search($ldapDetails);
						return array($state, $data);
					} elseif (preg_match_all("/$paramSyntax/", $match, $matches, PREG_SET_ORDER)) {
						$ldapDetails = array();
						foreach ($matches as $pair) {
							$value = preg_replace('/^"(.*?)"$/', '$1', $pair[2]);
							$ldapDetails[$pair[1]] = $value;
						}
						// return null if no name specified
						if (!$ldapDetails['search']) {
							return null;
						}
						if ($this->getConf('allow_overrides')) {
							// allow overrides
							foreach (array('hostname', 'port', 'basedn', 'attributes', 'scope', 'binddn', 'bindpassword') as $key) {
								if (!$ldapDetails[$key]) {
									$ldapDetails[$key] = $this->ldapsearch_conf[$ldapDetails['search']][$key];
								}
							}
						}
						// explode attributes
						if (is_string($ldapDetails['attributes'])) {
							$ldapDetails['attributes'] = explode(',', $ldapDetails['attributes']);
						}
						// on its way
						$data = $this->ldapsearch_search($ldapDetails);
						return array($state, $data);
					} else {
						return null;
					}
				}

			default:
				return array($state);
		}
	}

	function build_ldapsearch_conf()
	{
		$set_index = array();
		foreach (explode('|', $this->getConf('name')) as $name) {
			$this->ldapsearch_conf[$name] = array();
			$set_index[] = $name;
		}
		foreach (array('hostname', 'port', 'basedn', 'attributes', 'scope', 'binddn', 'bindpassword') as $param) {
			$count = 0;
			foreach (explode('|', $this->getConf($param)) as $value) {
				if ($param == 'attributes') {
					$value = explode(',', $value);
				}
				$this->ldapsearch_conf[$set_index[$count]][$param] = $value;
				$count++;
			}
		}
		dbg(print_r($this->ldapsearch_conf, TRUE));
	}

	function getTable($data)
	{
		$table = '';
		$row = 0;
		$col = 0;

		if (isset($data['count']) && $data['count'] > 0) {
			$table .= '<table class="inline">';
			$header = '<tr class="row' . $row++ . '">';
			foreach (array_keys($data[0]) as $attribute) {
				$header .= '<th class="col' . $col++ . ' leftalign">'
					. preg_replace('/(?<!^)[A-Z]/', ' $0', ucfirst($attribute))
					. '</th>';
			}
			$header .= '</tr>';
			$table .= $header;
			for ($i = 0; $i < $data['count']; $i++) {
				# code...
				$row_content = '<tr class="row' . $row++ . '">';
				$col = 0;
				foreach ($data[$i] as $key => $values) {

					$row_content .= '<td class="col' . $col++ . ' leftalign">' . implode('<br>', $values) . '</td>';
				}
				$row_content .= '</tr>';
				$table .= $row_content;
			}
			$table .= '</table>';
		} elseif (is_array($data)) {
			$table .= '<table class="inline">';
			$header = '<tr class="row' . $row++ . '">';
			foreach (array_keys($data) as $attribute) {
				$header .= '<th class="col' . $col++ . ' leftalign">'
					. preg_replace('/(?<!^)[A-Z]/', ' $0', ucfirst($attribute))
					. '</th>';
			}
			$header .= '</tr>';
			$table .= $header;
			$row_content = '<tr class="row' . $row++ . '">';
			$col = 0;
			foreach ($data as $values) {
				$col_content = is_array($values) ? implode('<br>', $values) : $values;
				$row_content .= '<td class="col' . $col++ . ' leftalign">' . $col_content . '</td>';
			}
			$row_content .= '</tr>';
			$table .= $row_content;
			$table .= '</table>';
		} else {
			$table = print_r($data, TRUE);
		}

		return $table;
	}

	function ldapsearch_search($ldapDetails)
	{
		dbg(print_r($ldapDetails, TRUE));
		if (!$ldapDetails['port']) {
			$ldapDetails['port'] = 389;
		}
		if (!$ldapDetails['filter']) {
			$ldapDetails['filter'] = '(objectClass=*)';
		}

		if ($ldap_handle = ldap_connect($ldapDetails['hostname'], $ldapDetails['port'])) {
			ldap_set_option($ldap_handle, LDAP_OPT_PROTOCOL_VERSION, 3);
			if (ldap_bind($ldap_handle, $ldapDetails['binddn'], $ldapDetails['bindpassword'])) {
				$value = "";
				if ($ldapDetails['scope'] == 'sub') {
					$results = ldap_search($ldap_handle, $ldapDetails['basedn'], $ldapDetails['filter'], $ldapDetails['attributes']);
					// ldap_sort($ldap_handle,$results,$ldapDetails['attributes'][0]);
					$info = ldap_get_entries($ldap_handle, $results);
					$data = $this->ldapsearch_filter_array($info);
				} elseif ($ldapDetails['scope'] == 'one') {
					$results = ldap_list($ldap_handle, $ldapDetails['basedn'], $ldapDetails['filter'], $ldapDetails['attributes']);
					// ldap_sort($ldap_handle,$results,$ldapDetails['attributes'][0]);
					$info = ldap_get_entries($ldap_handle, $results);
					$data = $this->ldapsearch_filter_array($info);
				} elseif ($ldapDetails['scope'] == 'base') {
					$results = ldap_read($ldap_handle, $ldapDetails['basedn'], $ldapDetails['filter'], $ldapDetails['attributes']);
					$info = ldap_get_entries($ldap_handle, $results);
					$data = $this->ldapsearch_filter_array($info);
				} else {
					$data = ["error" => "Unknown scope " . $ldapDetails['scope'] . "\n"];
				}
				ldap_unbind($ldap_handle);
				return $data;
			} else {
				return ["error" => "Failed to bind to LDAP on " . $ldapDetails['hostname'] . ":" . $ldapDetails['port'] . "\n"];
			}
		} else {
			return ["error" => "Failed to connect to LDAP on " . $ldapDetails['hostname'] . ":" . $ldapDetails['port'] . "\n"];
		}
	}

	function ldapsearch_filter_array($data)
	{
		for ($i = 0; $i < $data['count']; $i++) {
			unset($data[$i]['dn']);
			foreach ($data[$i] as $key => $value) {
				unset($data[$i]['dn'][$key]['count']);
			}
		}
		// unset($data['count']);
		return $data;
	}

	/**
	 * Render xhtml output or metadata
	 *
	 * @param string        $mode     Renderer mode (supported modes: xhtml)
	 * @param Doku_Renderer $renderer The renderer
	 * @param array         $data     The data from the handler() function
	 *
	 * @return bool If rendering was successful.
	 */
	public function render($mode, Doku_Renderer $renderer, $data)
	{
		if ($mode !== 'xhtml') {

			list($state, $result) = $data;

			switch ($state) {
				case DOKU_LEXER_SPECIAL: {
						//error_log("render $match\n",3,"/tmp/mylog.txt");
						$content = $this->getTable($result);
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
