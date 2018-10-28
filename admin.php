<?php

use dokuwiki\Form\Form;

/**
 * Plugin timetrack
 * 
 * @package dokuwiki\plugin\timetrack
 * @author     peterfromearth <coder@peterfromearth.de>
 */

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_timetrack extends DokuWiki_Admin_Plugin {
	
	/**
	 * @var helper_plugin_timetrack will hold the timetrack helper plugin
	 */
	public $tthlp = null;
	
	//constructor
	function __construct() {
		global $conf;
		$this->tmp = $conf['tmpdir'] .'/';
	
		$this->tthlp = plugin_load('helper', 'timetrack');
		if(!$this->tthlp) msg('Loading the timetrack helper failed. Make sure the timetrack plugin is installed.', -1);
	}
	
	
	/**
	 * handle user request
	 */
	function handle() {
		global $INPUT;

		if($INPUT->bool('export') && checkSecurityToken()){
			require_once(DOKU_INC.'inc/fetch.functions.php');
			$data = $this->tthlp->getAll();
			if(!is_array($data)) $data = array('empty');
			$filename = 'timetrack'.time() . '.csv';
			$fp = fopen($this->tmp.$filename, 'w');
			if($fp) {
				
				$keys = array_keys($data[0]);
				fputcsv($fp,$keys);
				foreach($data as $line){
					fputcsv($fp,$line);
				}
				
				sendFile($this->tmp . $filename, 'text/comma-separated-values', true, 0);
				exit;
			}
		}
	}
	
	/**
	 * output appropriate html
	 */
	function html() {
		echo '<h1>' . $this->getLang('timetrack') . '</h1>';
		
		ptln ( '<form action="' . wl ( $ID ) . '" method="post">' );
		
		$form = new Form(array(
				'id'=>'timetrack-form'
		));
		$form->setHiddenField('do', 'admin');
		$form->setHiddenField('page', 'timetrack');
		
		
		$form->addButton('export', 'Excel');
		echo $form->toHTML();
		
	}
	
}

