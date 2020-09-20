<?php
/**
 * Options for the ldapsearch plugin
 *
 * @author Felix Haase <felix.haase@feki.de>
 */

$meta['allow_overrides'] = array('onoff');
$meta['name'] = array('string', '_pattern' => '/^(\w+\|?)*$/'); 
$meta['hostname'] = array('string'); 
$meta['port'] = array('string', '_pattern' => '/^(\d+\|?)*$/'); 
$meta['basedn'] = array('string'); 
$meta['binddn'] = array('string'); 
$meta['bindpassword'] = array('password'); 
$meta['attributes'] = array('string', '_pattern' => '/^((\w+,)*\w+\|?)*$/'); 
$meta['scope']   = array('string','_pattern' => '/^((sub|one|base)\|?)*$/');
$meta['skipempty'] = array('string', '_pattern' => '/^([01]\|?)*$/');
