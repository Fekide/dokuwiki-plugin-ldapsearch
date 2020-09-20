<?php

/**
 * General tests for the ldapsearch plugin
 *
 * @group plugin_ldapsearch
 * @group plugins
 */
class general_plugin_ldapsearch_test extends DokuWikiTest
{

	/**
	 * Initialize default settings
	 *
	 * @return void
	 */
	function setUp(){
		global $conf;

		parent::setUp();
		$conf['plugin']['ldapsearch']['allow_overrides'] = 1;
		$conf['plugin']['ldapsearch']['name'] = 'default';
		$conf['plugin']['ldapsearch']['hostname'] = 'ldap.forumsys.com';
		$conf['plugin']['ldapsearch']['port'] = '389';
		$conf['plugin']['ldapsearch']['basedn'] = 'dc=example,dc=com';
		$conf['plugin']['ldapsearch']['binddn'] = 'cn=read-only-admin,dc=example,dc=com';
		$conf['plugin']['ldapsearch']['bindpassword'] = 'password';
		$conf['plugin']['ldapsearch']['attributes'] = 'cn,mail,telephonenumber';
		$conf['plugin']['ldapsearch']['scope'] = 'sub';
		$conf['plugin']['ldapsearch']['skipempty'] = '0';
	}

	/**
	 * Simple test to make sure the plugin.info.txt is in correct format
	 */
	public function test_plugininfo()
	{
		$file = __DIR__ . '/../plugin.info.txt';
		$this->assertFileExists($file);

		$info = confToHash($file);

		$this->assertArrayHasKey('base', $info);
		$this->assertArrayHasKey('author', $info);
		$this->assertArrayHasKey('email', $info);
		$this->assertArrayHasKey('date', $info);
		$this->assertArrayHasKey('name', $info);
		$this->assertArrayHasKey('desc', $info);
		$this->assertArrayHasKey('url', $info);

		$this->assertEquals('ldapsearch', $info['base']);
		$this->assertRegExp('/^https?:\/\//', $info['url']);
		$this->assertTrue(mail_isvalid($info['email']));
		$this->assertRegExp('/^\d\d\d\d-\d\d-\d\d$/', $info['date']);
		$this->assertTrue(false !== strtotime($info['date']));
	}

	/**
	 * Test to ensure that every conf['...'] entry in conf/default.php has a corresponding meta['...'] entry in
	 * conf/metadata.php.
	 */
	public function test_plugin_conf()
	{
		$conf_file = __DIR__ . '/../conf/default.php';
		if (file_exists($conf_file)) {
			include $conf_file;
		}
		$meta_file = __DIR__ . '/../conf/metadata.php';
		if (file_exists($meta_file)) {
			include $meta_file;
		}

		$this->assertEquals(
			gettype($conf),
			gettype($meta),
			'Both ' . DOKU_PLUGIN . 'ldapsearch/conf/default.php and ' . DOKU_PLUGIN . 'ldapsearch/conf/metadata.php have to exist and contain the same keys.'
		);

		if (gettype($conf) != 'NULL' && gettype($meta) != 'NULL') {
			foreach ($conf as $key => $value) {
				$this->assertArrayHasKey(
					$key,
					$meta,
					'Key $meta[\'' . $key . '\'] missing in ' . DOKU_PLUGIN . 'ldapsearch/conf/metadata.php'
				);
			}

			foreach ($meta as $key => $value) {
				$this->assertArrayHasKey(
					$key,
					$conf,
					'Key $conf[\'' . $key . '\'] missing in ' . DOKU_PLUGIN . 'ldapsearch/conf/default.php'
				);
			}
		}
	}

	/**
	 * Test that the ldap connection is working
	 */
	public function test_ldapsearch_default()
	{
		$info = array();

		$instructions = p_get_instructions('{{ldapsearch> search="default"}}');
		$xhtml = p_render('xhtml', $instructions, $info);

		$this->assertStringContainsString("table", $xhtml);
		$this->assertStringContainsString("Cn", $xhtml);
		$this->assertStringContainsString("Mail", $xhtml);
		$this->assertStringContainsString("Telephonenumber", $xhtml);
		$this->assertStringContainsString("Albert Einstein", $xhtml);
		$this->assertStringContainsString("einstein@ldap.forumsys.com", $xhtml);
		$this->assertStringContainsString("314-159-2653", $xhtml);
		$this->assertStringContainsString("admin", $xhtml);
		$this->assertStringContainsString("Mathematicians", $xhtml);

	}


	/**
	 * Test that the ldap connection is working
	 */
	public function test_ldapsearch_filtered()
	{
		$info = array();

		$instructions = p_get_instructions('{{ldapsearch> search="default" filter="(objectClass=person)"}}');
		$xhtml = p_render('xhtml', $instructions, $info);

		$this->assertStringContainsString("table", $xhtml);
		$this->assertStringContainsString("Cn", $xhtml);
		$this->assertStringContainsString("Mail", $xhtml);
		$this->assertStringContainsString("Telephonenumber", $xhtml);
		$this->assertStringContainsString("Albert Einstein", $xhtml);
		$this->assertStringContainsString("einstein@ldap.forumsys.com", $xhtml);
		$this->assertStringContainsString("314-159-2653", $xhtml);
		$this->assertStringContainsString("admin", $xhtml);
		$this->assertStringNotContainsString("Mathematicians", $xhtml);

	}

		/**
	 * Test that the ldap connection is working
	 */
	public function test_ldapsearch_skipempty()
	{
		$info = array();

		$instructions = p_get_instructions('{{ldapsearch> search="default" filter="(objectClass=person)" skipempty="1"}}');
		$xhtml = p_render('xhtml', $instructions, $info);

		$this->assertStringContainsString("table", $xhtml);
		$this->assertStringContainsString("Cn", $xhtml);
		$this->assertStringContainsString("Mail", $xhtml);
		$this->assertStringContainsString("Telephonenumber", $xhtml);
		$this->assertStringContainsString("Albert Einstein", $xhtml);
		$this->assertStringContainsString("einstein@ldap.forumsys.com", $xhtml);
		$this->assertStringContainsString("314-159-2653", $xhtml);
		$this->assertStringNotContainsString("admin", $xhtml);
		$this->assertStringNotContainsString("Mathematicians", $xhtml);

	}
}
