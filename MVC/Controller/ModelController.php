<?php
/**
 * @package   Molajo
 * @copyright 2012 Amy Stephen. All rights reserved.
 * @license   GNU General Public License Version 2, or later http://www.gnu.org/licenses/gpl.html
 */
namespace Molajo\MVC\Controller;

use Molajo\Service\Services\Configuration\ConfigurationService;
use Molajo\Service\Services;

defined('MOLAJO') or die;

/**
 * Model Controller
 *
 * The class merely allows all model instantiation as a common gateway
 *
 * There are two basic process flows to the Model within the Molajo Application:
 *
 * 1. The first is directly related to processing the request and using the MVC
 *     architecture to either render output or execute the action action.
 *
 *   -> For rendering, the Parser and Includer gather data needed and execute
 *         the Controller action to activate the MVC.
 *
 *   -> For action actions, the Controller action is initiated in the Application class.
 *
 *  The Controller then interacts with the Model for data requests.
 *
 * 2. The second logic flow routes support queries originating in Service and Helper
 *  classes and pass through this Controller to invoke the Model, as needed.
 *
 * @package     Molajo
 * @subpackage  Model
 * @since       1.0
 */
Class ModelController extends Controller
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
			self::$instance = new ModelController();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since  1.0
	 */
	public function __construct()
	{
		return parent::__construct();
	}

	/**
	 * Prepares data needed for the model using an XML table definition
	 *
	 * @param  string  $table
	 * @param  string  $type
	 *
	 * @return object
	 *
	 * @since   1.0
	 * @throws \RuntimeException
	 */
	public function connect($table = '', $type = null)
	{
		if ($type == null) {
			$type = 'Table';
		}

echo 'In connect: ' . $table . ' type: ' . $type . '<br />';

		if ($table === '') {
			$this->dataSource = $this->default_data_source;

		} else {

			$table_registry_name = ucfirst(strtolower($table)) . ucfirst(strtolower($type));

			if (Services::Registry()->exists($table_registry_name) == true) {
				$this->table_registry_name = $table_registry_name;

			} else {
				$this->table_registry_name = ConfigurationService::getFile($table, $type);
			}
		}

		/* 2. Instantiate Model Class */
		$modelClass = 'Molajo\\MVC\\Model\\EntryModel';

		try {
			$this->model = new $modelClass();
		}
		catch (\Exception $e) {
			throw new \RuntimeException('Model entry failed. Error: ' . $e->getMessage());
		}

		/** 3. Model Properties         */
		$this->model->set('table_registry_name', $this->table_registry_name);

		$this->model->set('model_name',
			Services::Registry()->get($this->table_registry_name, 'model_name'));
		$this->model->set('table_name',
			Services::Registry()->get($this->table_registry_name, 'table_name'));
		$this->model->set('primary_key',
			Services::Registry()->get($this->table_registry_name, 'primary_key'));
		$this->model->set('name_key',
			Services::Registry()->get($this->table_registry_name, 'name_key'));
		$this->model->set('primary_prefix',
			Services::Registry()->get($this->table_registry_name, 'primary_prefix'));
		$this->model->set('get_customfields',
			Services::Registry()->get($this->table_registry_name, 'get_customfields'));
		$this->model->set('get_item_children',
			Services::Registry()->get($this->table_registry_name, 'get_item_children'));
		$this->model->set('use_special_joins',
			Services::Registry()->get($this->table_registry_name, 'use_special_joins'));
		$this->model->set('check_view_level_access',
			Services::Registry()->get($this->table_registry_name, 'check_view_level_access'));
		$this->model->set('check_published',
			Services::Registry()->get($this->table_registry_name, 'check_published'));
		$this->model->set('process_triggers',
			Services::Registry()->get($this->table_registry_name, 'process_triggers'));

		$dbo = Services::Registry()->get($this->table_registry_name, 'data_source', $this->default_data_source);
		$this->model->set('db', Services::$dbo()->get('db'));

		/** 4. Set DB Properties (note: 'mock' DBO's are used for processing non-DB data, like Messages */
		$this->model->set('query', Services::$dbo()->getQuery());
		$this->model->set('nullDate', Services::$dbo()->get('db')->getNullDate());

		if ($dbo == 'JDatabase') {
			$dateClass = 'Joomla\\date\\JDate';
			$dateFromJDate = new $dateClass('now');
			$now = $dateFromJDate->toSql(false, Services::$dbo()->get('db'));
			$this->model->set('now', $now);
		}

		Services::$dbo()->getQuery()->clear();

		return $this;
	}

	/**
	 * Method to execute a model method which interacts with the data source
	 * and returns results
	 *
	 * @param  string  $query_object
	 *
	 * @return  mixed Depends on QueryObject selected
	 *
	 * @since   1.0
	 * @throws \RuntimeException
	 */
	public function getData($query_object = 'loadObjectList')
	{
		if (in_array($query_object, $this->query_objects)) {
		} else {
			echo 'ERROR IN ModelController WILL DIE for $query_object: ' . $query_object . ' defaulting to loadObjectList';
			die;
			$query_object = 'loadObjectList';
		}

		try {
			/** Set ID */
			$this->model->set('id', (int) $this->get('id', 0));

			/** Retrieve Triggers */
			if ((int) Services::Registry()->get($this->table_registry_name, 'process_triggers') == 1) {
				$triggers = Services::Registry()->get($this->table_registry_name, 'triggers', array());

				if (is_array($triggers)) {
				} else {
					if ($triggers == '' || $triggers == false || $triggers == null) {
						$triggers = array();
					} else {
						$temp = $triggers;
						$triggers = array();
						$triggers[] = $temp;
					}
				}
			} else {
				$triggers = array();
			}

			/** Schedule onBeforeRead Event */
			if (count($triggers) > 0) {
				$this->onBeforeReadEvent($triggers);
			}

			/** Run Query */
			$this->query_results = $this->model->$query_object();
			echo '<pre>';
			var_dump($this->query_results);
			echo '</pre>';

			/** Schedule onAfterRead Event */
			if (count($triggers) > 0) {
				$this->onAfterReadEvent($triggers);
			}

			return $this->query_results;

		} catch (\Exception $e) {
			throw new \RuntimeException('ModelController query failed for ' . $query_object . ' Error: ' . $e->getMessage());
			die;
		}
	}

	/**
	 * Schedule onBeforeRead Event
	 *
	 * @return  null
	 * @since   1.0
	 */
	protected function onBeforeReadEvent($triggers = array())
	{
		/** Prepare input */
		if (count($triggers) == 0) {
			return false;
		}

		/** Schedule onBeforeRead Event */
		$arguments = array(
			'table_registry_name' => $this->table_registry_name,
			'parameters' => $this->parameters,
			'model' => $this->model
		);

		$arguments = Services::Event()->schedule('onBeforeRead', $arguments, $triggers);
		if ($arguments == false) {
			return false;
		}

		/** Process results */
		$this->parameters = $arguments['parameters'];
		$this->model = $arguments['model'];

		return;
	}

	/**
	 * Schedule onAfterRead Event
	 *
	 * @return  null
	 * @since   1.0
	 */
	protected function onAfterReadEvent($triggers = array())
	{
		/** Prepare input */
		if (count($triggers) == 0) {
			return false;
		}

		if (strtolower($this->get('model_type', 'Content')) == 'item') {
			if (isset($this->query_results[0])) {
				$this->query_results = $this->query_results[0];
			}

		} else {
			echo 'IN MVC (PROCESS WILL DIE) -- define model_type: ' . $this->parameters->model_type;

			echo 'Model Name ' . $this->get('model_name') . '  $model_type:  ' . $this->get('model_type', 'Content')
				. 'Table Registry Name ' . $this->table_registry_name
				. ' Model query_object: ' . $this->query_results . '<br />';

			echo '<pre>';
			var_dump($triggers);
			echo '</pre>';

			echo '<pre>';
			var_dump($this->parameters);
			echo '</pre>';

		}

		/** Schedule onAfterRead Event */
		$arguments = array(
			'table_registry_name' => $this->table_registry_name,
			'parameters' => $this->parameters,
			'model' => $this->model,
			'query_results' => $this->query_results
		);

		$arguments = Services::Event()->schedule('onAfterRead', $arguments, $triggers);
		if ($arguments == false) {
			return false;
		}

		/** Process results */
		$this->parameters = $arguments['parameters'];
		$this->model = $arguments['model'];
		$this->query_results = array();
		$this->query_results[] = $arguments['query_results'];

		return true;
	}
}
