<?php
use dokuwiki\Form\Form;
use dokuwiki\Form\TagOpenElement;
use dokuwiki\Form\TagElement;

/**
 * Plugin timetrack
 * 
 * @package dokuwiki\plugin\timetrack
 * @author     peterfromearth <coder@peterfromearth.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_INC . 'inc/infoutils.php');

/**
 * This is the base class for all syntax classes, providing some general stuff
*/
class helper_plugin_timetrack extends DokuWiki_Plugin {

	/**
	 * @var helper_plugin_sqlite initialized via getDb()
	 */
	protected $db = null;
	
	/**
	 * Simple function to check if the database is ready to use
	 *
	 * @return bool
	 */
	public function ready() {
		return (bool) $this->getDb();
	}
	
	
	/**
	 * load the sqlite helper
	 *
	 * @return helper_plugin_sqlite
	 */
	public function getDb() {
		if($this->db === null) {
			$this->db = plugin_load('helper', 'sqlite');
			if($this->db === null) {
				msg('The timetrack plugin needs the sqlite plugin', -1);
				return false;
			}
			if(!$this->db->init('timetrack', dirname(__FILE__) . '/db/')) {
				$this->db = null;
				return false;
			}
		}
		
		return $this->db;
	}
	
	//////////////////////////////////////////////////////////////////////////
	// MODIFY FUNCTIONS
	//////////////////////////////////////////////////////////////////////////
	
	public function getUserIdByName($user, $create = true) {
		$sqlite = $this->getDb();
		if(!$sqlite) return false;
	
		$res = $sqlite->query("SELECT id FROM user WHERE user = ?", $user);
		$db_user_id = (int) $sqlite->res2single($res);
		$sqlite->res_close($res);
	
		if(!$db_user_id && $create) {
			$sqlite->query('INSERT OR IGNORE INTO user (user) VALUES (?)', $user);
				
			$res = $sqlite->query("SELECT id FROM user WHERE user = ?", $user);
			$db_user_id = (int) $sqlite->res2single($res);
			$sqlite->res_close($res);
		}
	
		return $db_user_id;
	}
	
	/**
	 * 
	 * @param string $page dokuwiki pageid
	 * @param boolean $create
	 * @return number
	 */
	public function getPageId($page, $create = true) {
		$sqlite = $this->getDb();
		
		$res = $sqlite->query("SELECT id FROM page WHERE page = ?", $page);
		$page_id = (int) $sqlite->res2single($res);
		
		if(!$page_id && $create) {
			$sqlite->query('INSERT OR IGNORE INTO page(page) VALUES (?)',$page);
		} else if(!$page_id){
			return null;
		} else {
			return $page_id;
		}
		
		$res = $sqlite->query("SELECT id FROM page WHERE page = ?", $page);
		$page_id = (int) $sqlite->res2single($res);
		
		return $page_id;
		
	}
	
	/**
	 * 
	 * @param string $pageid dokuwiki pageid
	 * @param string $abbr id
	 * @param string $name project name
	 * @return boolean|integer projects db id
	 */
	public function updateProject($pageid, $abbr = '', $name = '', $from = 0, $to = 0) {
		$sqlite = $this->getDb();

		if(!$abbr) $abbr = noNS($pageid);
		if(!$name) $name = noNS($pageid);
		
		$page_id = $this->getPageId($pageid);
		
		if(!$page_id) dbg(['no page id',$pageid,$abbr,$name]);
		$res = $sqlite->query('INSERT OR IGNORE INTO project (page_id, abbr) VALUES (?,?)', $page_id, $abbr);

		//fetch project_id
		$res = $sqlite->query("SELECT id FROM project WHERE page_id = ? AND abbr = ?", $page_id, $abbr);
		$project_id = (int) $sqlite->res2single($res);
		$sqlite->res_close($res);
		
		if(!$project_id) {
			msg("timetrack plugin: failed saving project", -1);
			return false;
		}
		
		$sqlite->query("UPDATE project SET name = ?, `from` = ?, `to` = ? WHERE id = ?", $name, $from, $to, $project_id);
		
		$task_id = $this->updateTask($project_id,$abbr,$name);
		
		return $project_id;
	}
	
	
	
	
	/**
	 * 
	 * @param integer $project_id
	 * @param string $abbr
	 * @param string $name
	 * @return boolean|integer task_id
	 */
	public function updateTask($project_id,$abbr,$name) {
		$sqlite = $this->getDb();
		
		$res = $sqlite->query('INSERT OR IGNORE INTO task (project_id,abbr,active) VALUES (?,?,1)',$project_id,$abbr);
		
		$res = $sqlite->query("SELECT id FROM task WHERE project_id = ? AND abbr = ?", $project_id,$abbr);
		$task_id = (int) $sqlite->res2single($res);
		$sqlite->res_close($res);
		
		if(!$task_id) {
			msg("timetrack plugin: failed saving task", -1);
			return false;
		}
		
		$sqlite->query("UPDATE task SET name = ?, active = 1 WHERE id = ?",	$name, $task_id);
		
		return $task_id;

	}
	
	/**
	 * set all tasks not in array to inactive
	 * 
	 * @param array $task_abbrs items of abbrs
	 * @return boolean
	 */
	public function setActiveTasks($project_id,$task_abbrs = array()) {
		$sqlite = $this->getDb();
	
		$task_abbrs = $sqlite->quote_and_join($task_abbrs);
		
		$sqlite->query("UPDATE task SET active = 0 
				WHERE 
					project_id = ? AND 
					abbr NOT IN (".$task_abbrs.")",$project_id);
		
	}
	
	/**
	 * 
	 * @param integer $user_time_id
	 * @param integer $value
	 * @return boolean
	 */
	public function updateUserTime($user_time_id, $value) {
		$sqlite = $this->getDb();
		
		$sqlite->query('UPDATE user_time SET value = ?, update_time = ? WHERE id = ?', $value, time(), $user_time_id);
		return true;
	}
	
	/**
	 * 
	 * @param string $user
	 * @param integer $task_id
	 * @param date $date
	 * @param integer $value
	 * @return boolean
	 */
	public function insertUserTime($user, $task_id, $date, $value){
		$sqlite = $this->getDb();

		$sqlite->query('INSERT INTO user_time (update_time,user_id,task_id, date, value) VALUES (?,?,?,?,?)',
			time(),
			$this->getUserIdByName($user),
			$task_id,
			$date,
			$value);
		return true;
	}
	
	
	
	//////////////////////////////////////////////////////////////////////////
	// QUERY FUNCTIONS
	//////////////////////////////////////////////////////////////////////////
	/**
	 *
	 * @param string $pageid dokuwiki pageid
	 * @param string $abbr id
	 * @param string $name project name
	 * @return boolean|integer projects db id
	 */
	public function getProject($pageid, $abbr = '') {
		$sqlite = $this->getDb();
	
		if(!$abbr) $abbr = noNS($pageid);
		if(!$name) $name = noNS($pageid);
	
		$page_id = $this->getPageId($pageid);
	
		//fetch project_id
		$res = $sqlite->query("SELECT id FROM project WHERE page_id = ? AND abbr = ?", $page_id, $abbr);
		$project_id = (int) $sqlite->res2single($res);
		$sqlite->res_close($res);
	
		if(!$project_id) {
			msg("timetrack plugin: failed loading project", -1);
			return false;
		}
		return $project_id;
	}
	
	public function getPageByProjectId($project_id) {
		$sqlite = $this->getDb();
		
		$res = $sqlite->query("SELECT 
				pa.page
				FROM page AS pa
				JOIN project pr ON pa.id=pr.page_id
				WHERE
					pr.id = ?",
				$project_id);
		$data = $sqlite->res2single($res);
		
		return $data;
	}
	
	public function getProjectTaskIdByPageid($pageid) {
		$sqlite = $this->getDb();
		
		$res = $sqlite->query("SELECT 
				pr.name project_name,
				pr.abbr project_abbr,
				t.name task_name,
				t.abbr task_abbr,
				t.id task_id
				FROM page AS pa
				JOIN project pr ON pa.id=pr.page_id
				JOIN task t ON pr.id=t.project_id
				WHERE
					pa.page = ?", //TODO
				$pageid);
		$data = $sqlite->res2arr($res);
	
		return $data;
	}
	
	public function getProjectIdsByPageId($pageid) {
		$sqlite = $this->getDb();
	
		$res = $sqlite->query("SELECT pr.id
				FROM page AS pa
				JOIN project pr on pa.id=pr.page_id
				WHERE
					pa.page = ?",
				$pageid);
		$data = $sqlite->res2arr($res);
		
		$data = array_map(function($item){return $item['id'];},$data);
	
		return $data;
	}
	
	public function getProjectDetails($project_id) {
		$sqlite = $this->getDb();
	
		$res = $sqlite->query("SELECT
				*
				FROM project AS pr
				WHERE
					pr.id = ?",
				$project_id);
		$data = $sqlite->res2row($res);
	
		return $data;
	}
	

	public function getUserPageTaskData($user, $task_id, $daterange = null) {
		$sqlite = $this->getDb();

		if($daterange === null) $daterange = $this->getDateRangeByYearWeek();
		
		$res = $sqlite->query("SELECT 
					ut.id, 
					ut.date, 
					ut.value 
				FROM 
					task t 
				JOIN 
					user_time ut on t.id=ut.task_id 
				WHERE 
					t.id = ? and
					user_id = ? and 
					date between ? and ?",
				$task_id, 
				$this->getUserIdByName($user), 
				$daterange['start'], 
				$daterange['end']);
		$data = $sqlite->res2arr($res);
		
		$return = array();
		foreach($data as $entry) {
			$return[$entry['date']] = $entry;
		}
		return $return;
	}
	
	
		
	public function isPageTracked($pageid) {
		$sqlite = $this->getDb();
		if(!$sqlite) return false;
		$res = $sqlite->query('SELECT 
				pa.id 
				FROM page pa
				JOIN project pr on pa.id=pr.page_id
				JOIN task t on pr.id=t.project_id
				WHERE pa.page = ? AND t.active = 1
				GROUP BY pa.id',$pageid);
		$db_id = (int) $sqlite->res2single($res);
		$sqlite->res_close($res);
		
		if($db_id) return true;
		
		return false;
	}
	
	public function isUserInDb($user = null) {
		$sqlite = $this->getDb();
		if(!$sqlite) return false;
		if($user === null) {
			$user = $this->getCurrentUser();
		}
		
		$res = $sqlite->query("SELECT u.id 
				FROM user u
				JOIN user_time ut on ut.user_id = u.id
				WHERE u.user = ?", $user);
		$db_user_id = (int) $sqlite->res2single($res);
		$sqlite->res_close($res);
		return $db_user_id ? true : false;
	}
	
	public function getUserData($user, $daterange = null) {
		$sqlite = $this->getDb();
		
		if($daterange === null) $daterange = $this->getDateRangeByYearWeek();
		
		$res = $sqlite->query("SELECT 
				pr.name project_name, 
				pr.id project_id,
				t.name task_name,
				t.id task_id, 
				ut.date, 
				ut.value,
				ut.id id
				FROM page AS pa 
				JOIN project pr ON pa.id=pr.page_id
				JOIN task t ON pr.id=t.project_id 
				JOIN user_time ut ON t.id=ut.task_id 
				WHERE 
					user_id = ? AND 
					date between ? AND ?
				ORDER BY date",
				$this->getUserIdByName($user),
				$daterange['start'],
				$daterange['end']);
		$data = $sqlite->res2arr($res);
		
		return $data;
	}
	
	public function getOverviewData($user, $daterange = null) {
		$sqlite = $this->getDb();
		
		if($daterange === null) $daterange = $this->getDateRangeByYearWeek();
		
		$res = $sqlite->query("SELECT 
				ut.date,
				sum(ut.value) value
				FROM user_time AS ut
				WHERE
					user_id = ? AND
					date between ? AND ?
				GROUP BY date
				ORDER BY date DESC",
				$this->getUserIdByName($user),
				$daterange['start'],
				$daterange['end']);
		$data = $sqlite->res2arr($res);
		
		$return = array();
		foreach($data as $entry) {
			$return[$entry['date']] = $entry;
		}
		return $return;
	}
	

	
	public function getTaskDataById($task_id) {
		$sqlite = $this->getDb();

		$res = $sqlite->query("SELECT 
				pr.name project_name,
				t.name task_name,
				t.id task_id
				FROM project AS pr
					JOIN task t ON pr.id=t.prpject_id
				WHERE
					t.id = ?",
				$page_sub_id);
		$data = $sqlite->res2row($res);
		
		return $data;
	}
	
	public function getHoursNotInTasksOnDate($user, $date, $task_ids) {
		$sqlite = $this->getDb();
		
		$res = $sqlite->query("SELECT
				sum(ut.value)
				FROM user_time AS ut
				WHERE
					user_id = ? AND
					date = ? AND
				 	ut.task_id NOT IN (".$sqlite->quote_and_join($task_ids).")",
				$this->getUserIdByName($user),
				$date);
		$data = $sqlite->res2single($res);
		
		return $data;
	}
	
	
	
	public function getUserDataRecent($user,$daterange) {
		$data = $this->getUserData($user,$daterange);
		
		$return = array();
		foreach($data as $entry) {
			$return[$entry['task_id']][$entry['date']] = $entry;
		}
		return $return;
	}
	
	
	public function getUserTimeData($user,$daterange,$project_ids = null) {
		$sqlite = $this->getDb();
		
		if($project_ids === null) {
			$cond_project = '';
		} else {
			if(!is_array($project_ids)) {
				$project_ids = array($project_ids);
			}
			$cond_project = " pr.id IN (".$sqlite->quote_and_join($project_ids).") AND ";
			
		}

		$res = $sqlite->query("SELECT
				pr.id project_id, 
				pr.name project_name,
				t.name task_name,
				t.id task_id,
				ut.value value,
				ut.date date,
				ut.id id
				FROM page pa
				JOIN project pr ON pa.id=pr.page_id
				JOIN task t ON pr.id=t.project_id
				JOIN user_time ut ON t.id=ut.task_id
				WHERE
					ut.user_id = ? AND
					ut.date between ? AND ? AND
					$cond_project
					t.active = 1",
				$this->getUserIdByName($user),
				$daterange['start'],
				$daterange['end']);
		$data = $sqlite->res2arr($res);
		
		$return = array();
		foreach($data as $entry) {
			$return[$entry['project_id']][$entry['task_id']][$entry['date']] = $entry;
		}

		return $return;
	}

	
	public function getTasks($project_ids = null) {
		$sqlite = $this->getDb();
		
		if($project_ids === null) {
			$cond_project = '';
		} else {
			if(!is_array($project_ids)) {
				$project_ids = array($project_ids);
			}
			$cond_project = " pr.id IN (".$sqlite->quote_and_join($project_ids).") AND ";
				
		}
	
		$res = $sqlite->query("SELECT 
				pr.name project_name,
				t.name task_name,
				t.id task_id,
				pr.id project_id
				FROM page pa
				JOIN project pr ON pa.id=pr.page_id
				JOIN task t ON pr.id=t.project_id
				WHERE
					$cond_project
					t.active = 1
				ORDER BY pr.name,t.name");
		$taskdata = $sqlite->res2arr($res);

		return $taskdata;
	}
	
	public function getRecentTasks($user, $daterange) {
		$sqlite = $this->getDb();
		if(!$sqlite) return false;
	
		$start = new DateTime($daterange['start']);
		$end = new DateTime($daterange['end']);
	
		$days = $this->getConf('days_recent_project_active');
		if($days < 1) $days = 1;
		if($days > 200) $days = 200;
		$interval = new DateInterval("P{$days}D");
	
		$res = $sqlite->query("SELECT
				pr.name project_name,
				t.name task_name,
				t.id task_id,
				pr.id project_id
				FROM page AS pa
					JOIN project pr on pa.id = pr.page_id
					JOIN task t ON pr.id=t.project_id
					JOIN user_time AS ut  ON t.id=ut.task_id
				WHERE
					user_id = ? AND
					date between ? AND ? AND
					ut.value > 0 AND ut.value != ''
				GROUP BY task_id
				ORDER BY pr.name,t.name",
				$this->getUserIdByName($user),
				$start->sub($interval)->format('Y-m-d'),
				$end->add($interval)->format('Y-m-d'));
		$data = $sqlite->res2arr($res);
	
		return $data;
	}
	
	public function getAll() {
		$sqlite = $this->getDb();
		if(!$sqlite) return false;
	
		$res = $sqlite->query("SELECT
				pr.id project_id,
				pr.name project_name,
				t.name task_name,
				us.user as user_name,
				ut.date as date,
				ut.value as hours
				FROM page AS pa
					JOIN project pr on pa.id = pr.page_id
					JOIN task t ON pr.id=t.project_id
					JOIN user_time AS ut  ON t.id=ut.task_id
					JOIN user AS us ON ut.user_id = us.id
				WHERE
					ut.value > 0 AND ut.value != ''
				ORDER BY pr.name,t.name,us.user");
		$data = $sqlite->res2arr($res);
	
		return $data;
	}
	
	/**
	 * 
	 * http://stackoverflow.com/a/15511864/2455349
	 * @param unknown $project_id
	 */
	public function getProjectSumByWeek($project_id) {
		$sqlite = $this->getDb();
		
		$res = $sqlite->query("SELECT
				pr.name project_name,
				t.name task_name,
				t.id task_id,
				strftime('%Y', date(ut.date, '-3 days', 'weekday 4')) || substr('00'||((strftime('%j', date(ut.date, '-3 days', 'weekday 4')) - 1) / 7 + 1), -2, 2) week,
				sum(ut.value) hours
				FROM project pr 
				JOIN task t on pr.id = t.project_id
				LEFT OUTER JOIN user_time ut on t.id = ut.task_id
				WHERE
					pr.id = ?
				GROUP BY t.id,week
				ORDER BY week DESC",
				$project_id);
		$data = $sqlite->res2arr($res);
		
		$return = array();
		foreach($data as $entry) {
			$return[$entry['task_id']][$entry['week']] = $entry;
		}
		return $return;
	}
	
	public function getMinMaxDayByProject($project_id) {
		$sqlite = $this->getDb();
		
		$res = $sqlite->query("SELECT
				MIN(ut.date) min,
				MAX(ut.date) max
				FROM project pr
				JOIN task t on pr.id = t.project_id
				JOIN user_time ut on t.id = ut.task_id
				WHERE
					pr.id = ?",
				$project_id);
		$data = $sqlite->res2row($res);
		
		return $data;
	}
	
	
	public function getMaxUpdateTime($pageid) {
		$sqlite = $this->getDb();
		if(!$sqlite) return false;
		$res = $sqlite->query("SELECT
				MAX(ut.update_time)
				FROM page pa
				JOIN project pr on pa.id=pr.page_id
				JOIN task t on pr.id = t.project_id
				JOIN user_time ut on t.id = ut.task_id
				WHERE
					pa.page = ?",
				$pageid);
		$data = $sqlite->res2single($res);
		
		return $data;
	}

	public function getHoursByPage($pageid) {
		$sqlite = $this->getDb();
	
		$res = $sqlite->query("SELECT
				SUM(ut.value)
				FROM page pa
				JOIN project pr on pa.id=pr.page_id
				JOIN task t on pr.id = t.project_id
				JOIN user_time ut on t.id = ut.task_id
				WHERE
					pa.page = ?",
				$pageid);
		$data = $sqlite->res2single($res);
	
		return $data;
	}
	
	
	//////////////////////////////////////////////////////////////////////////
	// HTML FUNCTIONS
	//////////////////////////////////////////////////////////////////////////
	
	
	public function html_prepareDialog($currenttab, $content, $pageid) {
	
		$tabs = array(
			'overview' => $this->getLang('overview'),
			'recent' => $this->getLang('recent'),
		);
		if(auth_quickaclcheck($pageid) > AUTH_NONE && $this->isPageTracked($pageid)) {
			$tabs['current'] = $this->getLang('current page');
		} else if ($currenttab === 'current') {
			$currenttab = 'overview';
		}
		if(!in_array($currenttab, array_keys($tabs))) return 'currenttab not defined';
	
	
		$html = '<div id="timetrack-dialog-tabs"><ul>';
		$ii = 0;
		foreach($tabs as $tabid => $tab) {
			$html .= '<li data-tabid="'.$tabid.'"><a href="#timetrack-tab-'.$tabid.'" '.($tabid===$currenttab?'selected="selected"':'').' data-index='.$ii++.'>'.hsc($tab).'</a></li>';
		}
		$html .= '</ul><div id="timetrack-tab-'.$currenttab.'">';
		$html .= $content;
		$html .= '</div></div>';
	
		return $html;
	}
	
	public function html_overview($user, $daterange,$pageid) {
		$form = new Form(array(
				'id'=>'timetrack-form'
		));
	
		$dateStart = new DateTime($daterange['start']);
		$dateEnd = new DateTime($daterange['end']);
		$dateInterval = new DateInterval('P1D');
		$dateIntervalWeek = new DateInterval('P7D');
	
		$datePeriod = new DatePeriod($dateStart,$dateInterval,$dateEnd);
	
		$form->setHiddenField('yearweek', $daterange['yearweek']);
		$form->setHiddenField('call', 'plugin_timetrack');
		$form->setHiddenField('cmd', 'overview');
		$form->setHiddenField('pageid',$pageid);
	
	
		$form->addButton('back', sprintf('<< [KW%s]',$dateStart->sub($dateIntervalWeek)->format('W')))->val($dateStart->format('YW'));
		$form->addButton('today', $this->getLang('today') . ' [KW'. date('W') . ']')->val(date('YW'));
		$form->addButton('forward', sprintf('>> [KW%s]',$dateEnd->add($dateIntervalWeek)->format('W')))->val($dateEnd->format('YW'));
		$html = $form->toHTML();
		$html .= '<h3>KW'.$dateStart->add($dateIntervalWeek)->format('W') . '</h3>';
		$r = new Doku_Renderer_xhtml();
	
		$r->table_open();
		$r->tablerow_open();
		$r->tableheader_open();
		$r->cdata($this->getLang('date'));
		$r->tableheader_close();
		$r->tableheader_open();
		$r->cdata($this->getLang('hours'));
		$r->tableheader_close();
		$r->tablerow_close();
	
		$data = $this->getOverviewData($user, $daterange);
		$sum = 0;
		foreach($datePeriod as $date) {
			
			$dateText = $date->format("Y-m-d");
				
			$r->tablerow_open();
			$r->tablecell_open();
			$r->cdata($dateText);
			$r->tablecell_close();
			$r->tablecell_open();
			$r->cdata((int)$data[$dateText]['value']);
			$r->tablecell_close();
			$r->tablerow_close();
			
			$sum += $data[$dateText]['value'];
		}
		
		//display Sum
		$r->tablerow_open();
		$r->tableheader_open();
		$r->cdata($this->getLang('sum'));
		$r->tableheader_close();
		$r->tableheader_open();
		$r->cdata($sum);
		$r->tableheader_close();
		$r->tablerow_close();
	
		$r->table_close();
		$html .= $r->doc;
		return $html;
	}
	
	public function html_week($dbUserValues,$daterange,$dbUserProjects, $cmd,$pageid='') {
	
		$form = new Form(array(
				'id'=>'timetrack-form'
		));
	
	
		$dateStart = new DateTime($daterange['start']);
		$dateEnd = new DateTime($daterange['end']);
		$dateInterval = new DateInterval('P1D');
		$dateIntervalWeek = new DateInterval('P7D');
	
		$datePeriod = new DatePeriod($dateStart,$dateInterval,$dateEnd);
	
		$weekData = $this->getOverviewData($this->getCurrentUser(), $daterange);

		$form->setHiddenField('yearweek', $daterange['yearweek']);
		$form->setHiddenField('call', 'plugin_timetrack');
		$form->setHiddenField('cmd', $cmd);
		$form->setHiddenField('pageid',$pageid);
	
		
		$form->addButton('back', sprintf('<< [KW%s]',$dateStart->sub($dateIntervalWeek)->format('W')))->val($dateStart->format('YW'));
		$form->addButton('today', $this->getLang('today') . ' [KW'. date('W') . ']')->val(date('YW'));
		$form->addButton('forward', sprintf('>> [KW%s]',$dateEnd->add($dateIntervalWeek)->format('W')))->val($dateEnd->format('YW'));
		$form->addHTML('<h3>KW'.$dateStart->add($dateIntervalWeek)->format('W') . '</h3>');
		$form->addTagOpen('table');
		$form->addTagOpen('tr');
		$form->addTagOpen('th');
		$form->addHTML($this->getLang('project'));
		$form->addTagClose('th');
		$form->addTagOpen('th');
		$form->addHTML($this->getLang('task'));
		$form->addTagClose('th');
		foreach($datePeriod as $date) {
			$dateName = $this->getLang('Abbr_' . $date->format('l')) .'<br>'. $date->format("d.m.");
			$form->addTagOpen('th');
			$form->addHTML($dateName);
			$form->addTagClose('th');
		}
		$form->addTagClose('tr');
		
		$project_counts = $this->countIndexValue($dbUserProjects, 'project_id');
		$date_sum_db = array();
		$date_sum_user = array();
		
		foreach($dbUserProjects as $task) {
			$task_id = $task['task_id'];
			$project_id = $task['project_id'];
			$projectDetails = $this->getProjectDetails($project_id);
			
			$form->addTagOpen('tr');

			if($old_project_id != $project_id) {
				$form->addElement(new TagOpenElement('th',array(
					'rowspan' => $project_counts[$project_id]
				)));
				$form->addLabel($task['project_name']);
				$form->addTagClose('th');
			}
			
			$form->addTagOpen('td');
			$form->addLabel($task['task_name']);
			$form->addTagClose('td');
			foreach($datePeriod as $date) {
				$dateText = $date->format("Y-m-d");
				$dateValue = $dbUserValues[$project_id][$task_id][$dateText]['value'];
				
				if(!isset($date_sum_db[$dateText])) $date_sum_db[$dateText] = 0;
				if(!isset($date_sum_user[$dateText])) $date_sum_user[$dateText] = 0;
				if(isset($dbUserValues[$project_id][$task_id][$dateText]['value_db'])) {
					$date_sum_db[$dateText] += $dbUserValues[$project_id][$task_id][$dateText]['value_db'];
					$date_sum_user[$dateText] += $dateValue;
				} else {
					$date_sum_db[$dateText] += $dateValue;
					$date_sum_user[$dateText] += $dateValue;
						
				}
				
				$form->addTagOpen('td');
				$el = $form->addTextInput("UserTime[$project_id][$task_id][$dateText]",'')
				->attr('size',2)
				->attr('data-date',$dateText)
				->useInput(false);
				
				if(($projectDetails['from'] && $projectDetails['from'] > $date->getTimestamp()) || 
					($projectDetails['to'] && $projectDetails['to'] < $date->getTimestamp())) {
						$el->attr('readonly','readonly');
				}
				
				$el->val($dateValue);
				$form->addTagClose('td');
			}
				
			$form->addTagClose('tr');
			
			$old_project_id = $project_id;
		}
// 		dbg([$date_sum_db,$date_sum_user,$dbUserValues]);
		$form->addTagOpen('tr');
		$form->addTag('td')->attr('colspan',2);
		$form->addTag('td');
		$form->addTag('td');
		$form->addTag('td');
		$form->addTag('td');
		$form->addTag('td');
		$form->addTagClose('tr');
		$form->addTagOpen('tr');
		$form->addTagOpen('td')->attr('colspan',2);
		$form->addHTML($this->getLang('sum_day_all'));
		$form->addTagClose('td');

		foreach($datePeriod as $date) {
			$dateText = $date->format("Y-m-d");
			$form->addTagOpen('th')->attrs(array(
					'data-otherhours' => ($weekData[$dateText]['value'] - $date_sum_db[$dateText]),
					'data-date' => $dateText
					
			));
			$form->addHTML($date_sum_user[$dateText] + $weekData[$dateText]['value'] - $date_sum_db[$dateText]);
			$form->addTagClose('th');
		}
		$form->addTagClose('tr');
		
		$form->addTagClose('table');

		return $form->toHTML();
	
	
	}
	
	
	//////////////////////////////////////////////////////////////////////////
	// HELPER FUNCTIONS
	//////////////////////////////////////////////////////////////////////////
	public function countIndexValue($data,$index) {
		$return = array();
		foreach($data as $e) {
			$return[$e[$index]] += 1;
		}
		return $return;
	}
	
	public function getCurrentUser() {
		return $_SERVER['REMOTE_USER'];
	}
	
	public function canAccessProject($project_id) {
	
		$pageid = $this->getPageByProjectId($project_id);
		if(auth_quickaclcheck($pageid) > AUTH_NONE) return true;
	
		return false;
	}
	
	
	public function checkPageInNamespace($pageid,$namespace) {
		$namespace = explode(' ',$namespace);
		$namespace = array_filter($namespace);
		
		$pageid = trim($pageid,' :');
		
		foreach($namespace as $ns) {
				
			$ns  = trim($ns,' :') . ':';
			if (substr($pageid, 0, strlen($ns)) === $ns) {
				return true;
			}
		}
		return false;
	}
	
	//////////////////////////////////////////////////////////////////////////
	// DATE FUNCTIONS
	//////////////////////////////////////////////////////////////////////////
	
	
	public function getDateWeekRangeByDate($date = null) {
		if($date === null) $date = date('Y-m-d');
		$dt = strtotime($datestr);
		$res['start'] = date('N', $dt)==1 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('last monday', $dt));
		$res['end'] = date('N', $dt)==7 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('next sunday', $dt));
		return $res;
	}
	
	/**
	 * return daterange by yearweek
	 * @param string $yearweek 201503
	 * @return array [start],[end]
	 */
	public function getDateRangeByYearWeek($yearweek = null) {
		if($yearweek === null) $yearweek = date('YW');
	
		$year = substr($yearweek,0,4);
		$week = substr($yearweek,4,2);
	
		$ret = array(
				'yearweek' => $yearweek,
				'year' => $year,
				'week' => $week
		);
	
		$days = $this->getConf('weekdays');
		if($days < 1) $days = 1;
		if($days > 7) $days = 7;
		
		$dto = new DateTime();
		$dto->setISODate($year, $week);
		$ret['start'] = $dto->format('Y-m-d');
		$dto->modify("+$days days");
		$ret['end'] = $dto->format('Y-m-d');
		return $ret;
	}
	
	public function th() {
		return 'header';
	}
	public function td($id) {
		return $this->getHoursByPage($id);
	}
	
	
}