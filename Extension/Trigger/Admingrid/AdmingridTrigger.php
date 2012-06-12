<?php
/**
 * @package    Molajo
 * @copyright  2012 Amy Stephen. All rights reserved.
 * @license    GNU General Public License Version 2, or later http://www.gnu.org/licenses/gpl.html
 */
namespace Molajo\Extension\Trigger\Admingrid;

use Molajo\Extension\Trigger\Content\ContentTrigger;
use Molajo\Service\Services;

defined('MOLAJO') or die;

/**
 * @package     Molajo
 * @subpackage  Trigger
 * @since       1.0
 */
class AdmingridTrigger extends ContentTrigger
{
	/**
	 * Static instance
	 *
	 * @var    object
	 * @since  1.0
	 */
	protected static $instance;

	/**
	 * getInstance
	 *
	 * @static
	 * @return bool|object
	 * @since  1.0
	 */
	public static function getInstance()
	{
		if (empty(self::$instance)) {
			self::$instance = new AdmingridTrigger();
		}

		return self::$instance;
	}

	/**
	 * Before-read processing
	 *
	 * Prepares data for the Administrator Grid  - position AdmingridTrigger last
	 *
	 * @return boolean
	 * @since   1.0
	 */
	public function onAfterRoute()
	{
		/** Is this an Administrative Grid Request?  */
		if (strtolower($this->get('template_view_path_node')) == 'admingrid') {
		} else {
			return true;
		}
		$model_type = $this->get('model_type');
		$model_name = $this->get('model_name');

		//todo: move this query out of here, into the ModelController
		/** Initialization */
		$controllerClass = 'Molajo\\MVC\\Controller\\ModelController';
		$m = new $controllerClass();
		$results = $m->connect($model_type, $model_name);
		if ($results == false) {
			return false;
		}

		$table_name = $m->get('table_name');

		$primary_prefix = $m->get('primary_prefix');
		$primary_key = $m->get('primary_key');
		$name_key = $m->get('name_key');

		/** 1. Prepare Submenu Data */
		$grid_submenu_items = explode(',', $this->get('grid_submenu_items', 'items,categories,drafts'));

		$query_results = array();

		$url = Services::Registry()->get('Configuration', 'application_base_url');
		if (Services::Registry()->get('Configuration', 'url_sef') == 1) {
			$url .= '/' . $this->get('catalog_url_sef_request');
			$connector = '?';
		} else {
			$url .= '/' . $this->get('catalog_url_request');
			$connector = '&';
		}
		Services::Registry()->set('Trigger', 'PageURL', $url);
		Services::Registry()->set('Trigger', 'PageURLConnector', $connector);

		if (count($grid_submenu_items) == 0 || $grid_submenu_items == null) {
		} else {

			foreach ($grid_submenu_items as $submenu) {
				$row = new \stdClass();
				$row->link = $url . $connector. 'submenu=' . $submenu;
				$row->link_text = Services::Language()->translate('SUBMENU_' . strtoupper($submenu));
				$query_results[] = $row;
			}
		}
		Services::Registry()->set('Trigger', 'AdminSubmenu', $query_results);

		/** 2. Toolbar Data */
		$grid_toolbar_buttons = explode(',', $this->get('grid_toolbar_buttons',
				'new,edit,publish,feature,archive,checkin,restore,delete,trash,options')
		);

		$permissions = Services::Authorisation()->authoriseTaskList(
			$grid_toolbar_buttons,
			$this->get('extension_catalog_id')
		);

		$query_results = array();

		foreach ($grid_toolbar_buttons as $buttonname) {

			if ($permissions[$buttonname] == true) {

				$row = new \stdClass();
				$row->name = Services::Language()->translate(strtoupper('TASK_' . strtoupper($buttonname) . '_BUTTON'));
				$row->action = $buttonname;
				$row->link = $url . '&action=' . $row->action;

				$query_results[] = $row;
			}
		}

		if (Services::Registry()->get('Trigger', 'grid_search', 1) == 1) {
			$row = new \stdClass();
			$row->name = Services::Language()->translate(strtoupper('TASK_' . 'SEARCH' . '_BUTTON'));
			$row->action = 'search';
			$row->link = $url . '&action=search';

			$query_results[] = $row;
		}

		Services::Registry()->set('Trigger', 'AdminToolbar', $query_results);

		/** 3. Filter Lists */
		$grid_list = explode(',', $this->get(
			'grid_list', 'catalog_type_id,created_by,featured,status')
		);

		$lists = array();

		if (is_array($grid_list) && count($grid_list) > 0) {

			/** Build each list and store in registry along with current selection */
			foreach ($grid_list as $list) {

				$fieldValue = Services::Text()->getList($list, $this->parameters);

				if ($fieldValue == false) {
				} else {

					ksort($fieldValue);

					Services::Registry()->set('Trigger', 'list_' . $list, $fieldValue);

					/** todo: Retrieves the user selected field from the session */
					$selectedValue = '';
					Services::Registry()->set('Trigger', 'list_' . $list . '_selected', $selectedValue);

					if ($selectedValue == '') {
					} else {
						$m->model->query->where($m->model->db->qn($primary_prefix)
							. '.' . $m->model->db->qn($list)
							. ' = ' . $m->model->db->q($selectedValue));
					}

        			/** Store the name of each filter list in an array */
					$lists[] = strtolower($list);
				}
			}
		}

		Services::Registry()->set('Trigger', 'GridFilters', $lists);

		/** 4. Grid Options */
		$grid_columns = explode(',', $this->get('grid_columns',
				'id,featured,title,created_by,start_publishing_datetime,ordering')
		);
		Services::Registry()->set('Trigger', 'GridTableColumns', $grid_columns);

		foreach ($grid_columns as $column) {
			$m->model->query->select($m->model->db->qn($primary_prefix)	. '.' . $m->model->db->qn($column));
		}

		Services::Registry()->set('Trigger', 'GridTableRows', $this->get('grid_rows', 5));

		$ordering = $this->get('grid_ordering', 'start_publishing_datetime');
		Services::Registry()->set('Trigger', 'GridTableOrdering', $ordering);
		$this->set('model_ordering', $ordering);

		$direction = $this->get('grid_ordering_direction', 'DESC');
		Services::Registry()->set('Trigger', 'GridTableOrderingDirection', $direction);
		$this->set('model_direction', $direction);

		$m->model->query->from($m->model->db->qn($table_name) . ' as ' . $m->model->db->qn($primary_prefix));
		$m->model->query->where($m->model->db->qn('a.catalog_type_id') . ' = ' . $this->get('criteria_catalog_type_id'));
		//$m->model->query->order($m->model->db->qn($ordering));

		/** 5. Grid Pagination */
		$query_results = array();
		$current = 0;

		$row = new \stdClass();
		$row->link = $url.$connector.'&start='.$current + 5;
		$row->class = ' page-prev';
		$row->link_text = ' Prev';

		$query_results[] = $row;

		$row = new \stdClass();
		$row->link = $url.$connector.'&start='.$current + 10;
		$row->class = '';
		$row->link_text = ' 2';

		$query_results[] = $row;

		$row = new \stdClass();
		$row->link = $url.$connector.'&start='.$current + 15;
		$row->class = ' page-next';
		$row->link_text = ' Next';

		$query_results[] = $row;

		Services::Registry()->set('Trigger', 'GridPagination', $query_results);

		$offset = $this->get('grid_offset', 0);
		Services::Registry()->set('Trigger', 'GridPaginationOffset', $direction);
		$this->set('model_offset', $offset);

		$count = $this->get('grid_count', 5);
		Services::Registry()->set('Trigger', 'GridPaginationCount', $count);
		$this->set('model_count', $count);

		/** 6. Grid Batch */
		Services::Registry()->set('Trigger', 'GridBatch', $this->get('grid_batch', 1));

		/** Run the query and store results */
		$m->model->db->setQuery($m->model->query->__toString(), $offset, $count);

		$query_results = $m->model->db->loadObjectList();
		Services::Registry()->set('Trigger', 'GridQueryResults', $query_results);

		Services::Registry()->set('Parameters', 'model_name', 'Triggerdata');
		Services::Registry()->set('Parameters', 'model_type', 'dbo');
		Services::Registry()->set('Parameters', 'model_query_object', 'getTriggerdata');
		Services::Registry()->set('Parameters', 'model_parameter', 'GridQueryResults');

		Services::Registry()->set('Trigger', '*');

		return true;
	}
}