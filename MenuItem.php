<?php
namespace dokuwiki\plugin\timetrack;

use dokuwiki\Menu\Item\AbstractItem;

/**
 * 
 * Plugin timetrack
 * 
 * @author     peterfromearth <coder@peterfromearth.de>
 * @package dokuwiki\plugin\timetrack
 *
 */
class MenuItem extends AbstractItem {
    protected $type = 'plugin_timetrack';
    protected $method = 'get';
    
    public function getLabel() {
        return plugin_load('action', 'timetrack')->getLang('timetrack');
    }

}
