<?php
/**
 * Timetrack Plugin
 *
 * @author  peterfromearth <coder@peterfromearth.de>
 * @package dokuwiki\plugin\timetrack
 *
 */

if(!defined('DOKU_INC')) die();

/**
 * handles all the editor related things
 *
 * like displaying the editor and adding custom edit buttons
*/
class syntax_plugin_timetrack extends DokuWiki_Syntax_Plugin {
	
	/**
	 * @var helper_plugin_timetrack will hold the timetrack helper plugin
	 */
	public $tthlp = null;
	
	/**
	 * Constructor. Load helper plugin
	 */
	function __construct() {
		$this->tthlp = plugin_load('helper', 'timetrack');
		if(!$this->tthlp) msg('Loading the timetrack helper failed. Make sure the timetrack plugin is installed.', -1);
	}
	
	public function getType() { return 'substition'; }
	public function getSort() { return 200; }
	
	function connectTo($mode) {
		$this->Lexer->addSpecialPattern('\{\{timetrack>?.*?\}\}',$mode,'plugin_timetrack');
	}
	
	function handle($match, $state, $pos, Doku_Handler $handler) {	
		global $ID;
		if($namespace = trim($this->getConf('namespace_allowed'))) {
			if($this->tthlp->checkPageInNamespace($ID,$namespace) === false) return false;
		}
		$command = trim(substr($match, 11 ,-2),'> ');
        $flags = explode('&', $command);
        $flags = array_map('trim', $flags);
        $flags = array_filter($flags);
        
        $flags = $this->parseFlags($flags);

		$data = array(
			'flags' => $flags,
		);

        return $data;
	}
	
	function render($mode, Doku_Renderer $r, $data) {
		global $ID;
		global $INFO;
		$flags = $data['flags'];
		
		if(!$this->tthlp->ready()) {
			msg('DB not ready.', -1);
			return false;
		}
		
		
		
		$project_name = $flags['project']['name'] ? $flags['project']['name'] : p_get_first_heading($ID);
		$project_abbr = $flags['project']['id'] ? $flags['project']['id'] : noNS($ID);
		if(!$project_name) $project_name = noNS($ID);
		
		
		// $data is what the function handle return'ed.
		if($mode === 'xhtml'){
			
			$project_id = $this->tthlp->getProject($ID, $project_abbr);
			if(!$project_id) return false;
			
			$range = $this->tthlp->getMinMaxDayByProject($project_id);
			$data = $this->tthlp->getProjectSumByWeek($project_id);
			
			$dateStart = new DateTime($range['min']);
			$dateEnd = new DateTime($range['max']);
			if($dateStart == $dateEnd) {
				$dateEnd->add(new DateInterval('P1D'));
			} 
			
			$dateStartCopy = clone $dateStart;
			if($dateEnd <= $dateStartCopy->add(new DateInterval('P7D')) && $dateStart->format('W') !== $dateEnd->format('W')) {
			    $dateEnd->add(new DateInterval('P7D'));
			}

			$dateInterval = new DateInterval('P7D');
			
			$datePeriod = new DatePeriod($dateStart,$dateInterval,$dateEnd);


			$r->table_open();
			
			$r->tablerow_open();
			$r->tableheader_open();
			$r->doc .= $r->_xmlEntities($project_name);
			$r->tableheader_close();
			foreach($datePeriod as $date) {
				$week = $date->format("W");
				$content = ($last_year !== $date->format("Y")) ? $date->format("Y") . '/' : '';
				$content .= $week;
				
				$r->tableheader_open();
				$r->doc .= $r->_xmlEntities($content);
				$r->tableheader_close();
				
				$last_year =  $date->format("Y");
			}
			$r->tableheader_open();
			$r->doc .= $r->_xmlEntities($this->getLang('sum'));
			$r->tableheader_close();
			$r->tablerow_close();
			
			$sum_date = array();
			$sum_task = array();
			
			foreach($data as $task_id=>$weekValues) {
				$r->tablerow_open();
				$r->tableheader_open();
				$r->doc .= $r->_xmlEntities(reset($weekValues)['task_name']);
				$r->tableheader_close();
				foreach($datePeriod as $date) {
					$week = $date->format("YW");
					$r->tablecell_open();
					$cell =  isset($weekValues[$week]['hours'])?(int)$weekValues[$week]['hours']:0;
					$r->doc .= $r->_xmlEntities($cell);
					$sum_date[$week] += $cell;
					$sum_task[$task_id] += $cell;
					$r->tablecell_close();
				}
				
				$r->tableheader_open();
				$r->doc .= $r->_xmlEntities($sum_task[$task_id]);
				$r->tableheader_close();
				
				$r->tablerow_close();
			}
			
			//SUMS
			if(count($data) > 1) {
				$r->tablerow_open();
				$r->tableheader_open();
				$r->doc .= $r->_xmlEntities($this->getLang('sum'));
				$r->tableheader_close();
				foreach($datePeriod as $date) {
					$week = $date->format("YW");
					$r->tableheader_open();
					$r->doc .= $r->_xmlEntities($sum_date[$week]);
					$r->tableheader_close();
				}
				
				$r->tableheader_open();
				$r->doc .= $r->_xmlEntities(array_sum($sum_date));
				$r->tableheader_close();
				
				$r->tablerow_close();
			}
			$r->table_close();
			
			p_set_metadata($ID, array(
				'plugin_timetrack' => array(
				'update_time'=>$this->tthlp->getMaxUpdateTime($ID)
			)));
			
			return true;
		} else if ($mode === 'metadata') {
			$project_id = $this->tthlp->updateProject($ID, $project_abbr, $project_name,$flags['from'],$flags['to']);
			if(!$project_id) return false;
			
			
			foreach($flags['tasks'] as $task_id => $task_name) {
				$this->tthlp->updateTask($project_id,$task_id,$task_name);
			}
			
			$this->tthlp->setActiveTasks($project_id,array_merge(array($project_abbr),array_keys($flags['tasks'])));
			
			
		}
		return false;
	}
	
	/*
	 * parseFlags checks for tagfilter flags and returns them as true/false
	* @param $flags array
	* @return array tagfilter flags
	*/
	function parseFlags($flags){
		if(!is_array($flags)) return false;
		
		$conf = array(
			'project' => array('id'=>'','name'=>''),
			'tasks' => array(),
			'from' => null,
			'to'	=> null,
		);
		foreach($flags as $k=>$flag) {
			list($flag,$value) = explode('=',$flag,2);
			switch($flag) {
				case 'project':
					$data = explode('=',$value,2);
					if(count($data)!==2) {
						$data[1] = $data[0];
						$data[0] = '';
					}
					$conf['project']['id'] = $data[0];
					$conf['project']['name'] = $data[1];
					break;
				case 'task':
					$data = explode('=',$value,2);
					if(count($data) != 2) continue;
					$conf['tasks'][$data[0]] = $data[1];
					break;
				case 'from':
				case 'start':
					$conf['from'] = strtotime($value);
					break;
				case 'to':
				case 'end':
					$conf['to'] = strtotime($value);
					break;
			}
		}
	
		return $conf;
	}
	
	
}