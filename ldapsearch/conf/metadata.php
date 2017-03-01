<?php
/**
 * Metadata for configuration manager plugin
 * Additions for the ldapsearch plugin
 *
 * @author    Oliver Geisen <oliver [at-sign] rehkopf-geisen [dotsign] de>
 */
$meta['name'] = array('string', '_pattern' => '/^(\w+\|?)*$/'); 
$meta['hostname'] = array('string'); 
$meta['port'] = array('string'); 
$meta['basedn'] = array('string'); 
$meta['binddn'] = array('string'); 
$meta['bindpassword'] = array('string'); 
$meta['attributes'] = array('string'); 
$meta['scope'] = array('string', '_pattern' => '/(one\|?|sub\|?|base\|?)*/'); 
$meta['allow_overrides'] = array('onoff');
