<?php
/**
 *
 * Plugin timetrack
 * 
 * @package dokuwiki\plugin\timetrack
 * @author     peterfromearth <coder@peterfromearth.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Class action_plugin_data
 */
class action_plugin_timetrack extends DokuWiki_Action_Plugin {

    /**
     * will hold the data helper plugin
     * @var helper_plugin_timetrack
     */
    var $tthlp = null;

    /**
     * Constructor. Load helper plugin
     */
    function __construct(){
        $this->tthlp = plugin_load('helper', 'timetrack');
        
    }

    /**
     * Registers a callback function for a given event
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE', $this, 'add_menu_item');
        $controller->register_hook('MENU_ITEMS_ASSEMBLY', 'AFTER', $this, 'add_menu_item_new');
        
        $controller->register_hook('TPL_ACTION_GET', 'BEFORE', $this, 'define_action');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'ajax');
//         $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'checkCacheBefore');
        $controller->register_hook('PARSER_CACHE_USE', 'AFTER', $this, 'checkCacheAfter');
    }
    
    
    public function checkCacheAfter(Doku_Event $event, $param) {
    	/* @var $cache  cache_renderer */
    	$cache = $event->data;
    	if($cache->mode !== 'xhtml') return;
    	if(p_get_metadata($cache->page,'plugin_timetrack update_time') < $this->tthlp->getMaxUpdateTime($cache->page))
    		$event->result = false;
    }

    /**
     * Add an item to sitetools
     * Can use `tpl_action()` because dokuwiki accepts 'youraction' by the define_action() handler below.
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function add_menu_item(Doku_Event $event, $param) {
    	global $lang;
    	global $ID;
    	if(!$this->tthlp->isPageTracked($ID) && !$this->tthlp->isUserInDb()) return true;
    	
    	$lang['btn_plugin_timetrack'] = $this->getLang('timetrack');
    
    	$event->data['items']['plugin_timetrack'] = tpl_action('plugin_timetrack', true, 'li', true);
    }
    
    public function add_menu_item_new(Doku_Event $event, $param) {
        global $lang;
        global $ID;
        if(!$this->tthlp->isPageTracked($ID) && !$this->tthlp->isUserInDb()) return true;
        
        $lang['btn_plugin_timetrack'] = $this->getLang('timetrack');
        
        $event->data['items'][] = new \dokuwiki\plugin\timetrack\MenuItem();
    }
    
    /**
     * Accepts the 'youraction' action, while using the default action link properties.
     * Entries of $event->data can be modified eventually.
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function define_action(Doku_Event $event, $param) {
    	if ($event->data['type'] != 'plugin_timetrack') {
    		return;
    	}
    
    	$event->preventDefault();
    }
    
    public function ajax(Doku_Event $event, $param) {
    	global $INPUT;
    	sleep(1);
    	
    	if ($event->data !== 'plugin_timetrack') {
    		return;
    	}
    	$event->stopPropagation();
    	$event->preventDefault();
    	
    	$result = array();
    	
    	if(!checkSecurityToken()) {
    		$result['dialog'] = 'security token false';
    		
    		$json = new JSON($result);
    		echo $json->encode($result);
    		exit;
    	}
    	
    	
    	$cmd = $INPUT->str('cmd');
    	if(!cmd) $cmd = 'current';
    	
    	$pageid = cleanID($INPUT->str('pageid'));
    	
    	//if current page is not tracked or readable, do not show current action
    	if($cmd === 'current' && (auth_quickaclcheck($pageid) < AUTH_READ || !$this->tthlp->isPageTracked($pageid))) {
    		$cmd = 'overview';
    	}
    	
    	$yearweek = null;
    	if($INPUT->has('data') && isset($INPUT->arr('data')['yearweek'])) {
    		$yearweek = $INPUT->arr('data')['yearweek'];
    	} else if($INPUT->has('yearweek')) {
    		$yearweek = $INPUT->str('yearweek');
    	}

    	$daterange = $this->tthlp->getDateRangeByYearWeek($yearweek);
    	$errors = array();
    	if($cmd === 'current') {
    		$project_ids = $this->tthlp->getProjectIdsByPageId($pageid);
    		$dbUserValues = $this->tthlp->getUserTimeData($this->tthlp->getCurrentUser(),$daterange,$project_ids);

    		
    		if($INPUT->has('UserTime')) {
				$errors = $this->saveUserTime($INPUT->arr('UserTime'), $dbUserValues);
    		} 

    		if($INPUT->has('UserTime') && empty($errors)) {
    			$result = array(
    				'success' => true
    			);
    		}
			$dbTasks = $this->tthlp->getTasks($project_ids);
			$dialogHtml = $this->renderErrors($errors);
			$dialogHtml .= $this->tthlp->html_week(
					$dbUserValues,
					$daterange,
					$dbTasks,
					'current',
					$pageid);
			
			$result = array_merge($result,array(
				'dialog' => $this->tthlp->html_prepareDialog('current',
						$dialogHtml,
						$pageid),
				'cmd' => 'current',
			));
    		
    	} else if($cmd === 'overview' ) {
    		$result = array(
    			'dialog' => $this->tthlp->html_prepareDialog('overview',$this->tthlp->html_overview($this->tthlp->getCurrentUser(),$daterange,$pageid),$pageid),
    			'cmd' => 'overview',
    		);
    		
    	} else if($cmd === 'recent' ) {
    		$dbUserValues = $this->tthlp->getUserTimeData($this->tthlp->getCurrentUser(), $daterange);
    		
    		if($INPUT->has('UserTime')) {
				$errors = $this->saveUserTime($INPUT->arr('UserTime'), $dbUserValues);
    		} 
    		
    		if($INPUT->has('UserTime') && empty($errors)) {
    			$result = array(
    				'success' => true
    			);
    		}
    		$dbUserTasks = $this->tthlp->getRecentTasks($this->tthlp->getCurrentUser(), $daterange);
    		$dialogHtml = $this->renderErrors($errors);
    		$dialogHtml .= $this->tthlp->html_week($dbUserValues,$daterange,$dbUserTasks,'recent',$pageid);
    		$result = array_merge($result,array(
    			'dialog' => $this->tthlp->html_prepareDialog('recent',
    				$dialogHtml,
    				$pageid),
    			'cmd' => 'recent',
    		));

    	} else {
    		$result = array(
    			'dialog' => 'cmd unknown'
    		);
    	}

    	$json = new JSON($result);
    	echo $json->encode($result);
    	
    }
    
    public function validateUserTime($data) {
    	$dates = array();
    	$errors = array();
    	$task_ids = array();
    	foreach($data as $project_id => $taskValues) {
    		foreach($taskValues as $task_id => $dateValues) {
	    		foreach($dateValues as $date => $value) {
	    			if($value < 0) $value = 0;
	    			$dates[$date] += (int)$value;
	    			$task_ids[] = $task_id;
	    		}
    		}
    	}
    	
    	$task_ids = array_unique($task_ids);
    	
    	foreach($dates as $date=>$datevalue) {
    		if($datevalue + $this->tthlp->getHoursNotInTasksOnDate($this->tthlp->getCurrentUser(),$date,$task_ids) 
    				> $this->getConf('max_hours'))
    			$errors[] = sprintf($this->getLang('err_max_hours'),$date);
    	}
    	return $errors;
    }
    
    public function saveUserTime($userTime, &$dbUserValues) {
    	$errors = $this->validateUserTime($userTime);
    	 
    	if(empty($errors)) {
    		foreach($userTime as $project_id => $taskValues) {
    			if(!$this->tthlp->canAccessProject($project_id)) continue;
    			
    			$projectDetails = $this->tthlp->getProjectDetails($project_id);
    			foreach($taskValues as $task_id => $dateValues) {
    				foreach($dateValues as $date => $value) {
    					$res = true;
    					$value = (int)$value;
    					if($value < 0) $value = 0;
    					//not between from to
    					if(($projectDetails['from'] && $projectDetails['from'] > strtotime($date)) ||
    							($projectDetails['to'] && $projectDetails['to'] < strtotime($date)))
    						continue;
    					
    					if(isset($dbUserValues[$project_id][$task_id][$date]) && $dbUserValues[$project_id][$task_id][$date]['value'] != $value) {
    						$dbUserValues[$project_id][$task_id][$date]['value'] = $value;
    						$res = $this->tthlp->updateUserTime($dbUserValues[$project_id][$task_id][$date]['id'], $value);
    					} else if ($value){
    						$dbUserValues[$project_id][$task_id][$date]['value'] = $value;
    						
    						$res = $this->tthlp->insertUserTime($this->tthlp->getCurrentUser(), $task_id, $date, $value);
    					}
    					if($res !== true) {
    						$errors[] = $res;
    					}
    				}
    			}
    		}
    	} else {
    		foreach($userTime as $project_id => $taskValues) {
    			foreach($taskValues as $task_id => $dateValues) {
    				foreach($dateValues as $date => $value) {
    					$dbUserValues[$project_id][$task_id][$date]['value_db'] = $dbUserValues[$project_id][$task_id][$date]['value']?$dbUserValues[$project_id][$task_id][$date]['value']:0;
    					$dbUserValues[$project_id][$task_id][$date]['value'] = $value;
    				}
    			}
    		}
    	}
    	
    	return $errors;
    		 
    }
    
    
    public function renderErrors($errors) {
    	if(!empty($errors)) {
    		return '<div class="errors">' . implode('<br>',$errors) . '</div>';
    	}
    	return '';
    }
}
