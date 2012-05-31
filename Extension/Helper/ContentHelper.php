<?php
/**
 * @package    Molajo
 * @copyright  2012 Amy Stephen. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */
namespace Molajo\Extension\Helper;

use Molajo\Service\Services;

defined('MOLAJO') or die;

/**
 * Content Helper
 *
 * @package      Molajo
 * @subpackage   Helper
 * @since        1.0
 */
Class ContentHelper
{
	/**
	 * Static instance
	 *
	 * @var     object
	 * @since   1.0
	 */
	protected static $instance;

	/**
	 * getInstance
	 *
	 * @static
	 * @return bool|object
	 * @since   1.0
	 */
	public static function getInstance()
	{
		if (empty(self::$instance)) {
			self::$instance = new ContentHelper();
		}

		return self::$instance;
	}

	/**
	 * Retrieves the Menu Item Route information
	 *
	 * Various registries used to store data definitions. For example: ArticlesContent (and ArticlesCustomfieldsContent,
	 * ArticlesContentMetadata, ArticlesContentParameters), ArticlesComponent (etc.)
	 *
	 * These registries are reused, not rebuilt
	 *
	 * @return boolean
	 * @since    1.0
	 */
	public function getMenuItem()
	{

	}

	/**
	 * Retrieve Route information for a specific Content Item
	 *
	 * Various registries used to store data definitions. For example: ArticlesContent (and ArticlesCustomfieldsContent,
	 * ArticlesContentMetadata, ArticlesContentParameters), ArticlesComponent (etc.)
	 *
	 * These registries are reused, not rebuilt
	 *
	 * @return boolean
	 * @since    1.0
	 */
	public function getRoute()
	{
		/** Retrieve the query results */
		$item = $this->get(
			Services::Registry()->get('Parameters', 'catalog_source_id'),
			'Item',
			ucfirst(strtolower(Services::Registry()->get('Parameters', 'catalog_type')))
		);

		/** 404  */
		if (count($item) == 0) {
			return Services::Registry()->set('Parameters', 'status_found', false);
		}

		/** Save for primary view */
		Services::Registry()->createRegistry('Content');
		Services::Registry()->set('Content', 'query_results', $item);

		/** Route Registry */
		Services::Registry()->set('Parameters', 'content_id', (int)$item->id);
		Services::Registry()->set('Parameters', 'content_title', $item->title);
		Services::Registry()->set('Parameters', 'content_translation_of_id', (int)$item->translation_of_id);
		Services::Registry()->set('Parameters', 'content_language', $item->language);
		Services::Registry()->set('Parameters', 'content_catalog_type_id', (int)$item->catalog_type_id);
		Services::Registry()->set('Parameters', 'content_catalog_type_title', $item->catalog_types_title);
		Services::Registry()->set('Parameters', 'content_modified_datetime', $item->modified_datetime);

		Services::Registry()->set('Parameters', 'extension_instance_id', (int)$item->extension_instances_id);
		Services::Registry()->set('Parameters', 'extension_title', $item->extension_instances_title);
		Services::Registry()->set('Parameters', 'extension_id', (int)$item->extensions_id);
		Services::Registry()->set('Parameters', 'extension_name_path_node', $item->extensions_name);
		Services::Registry()->set('Parameters', 'extension_catalog_type_id',
			(int)$item->extension_instances_catalog_type_id);

		/** Process each field namespace  */
		$customFieldTypes = Services::Registry()->get($item->table_registry_name, 'CustomFieldGroups');

		foreach ($customFieldTypes as $customFieldName) {
			$customFieldName = ucfirst(strtolower($customFieldName));
			Services::Registry()->merge($item->table_registry_name . $customFieldName, $customFieldName);

			/** Save for primary view */
			$array = Services::Registry()->getArray($item->table_registry_name . $customFieldName);
			Services::Registry()->set('Content', $customFieldName, $array);

			Services::Registry()->deleteRegistry($item->table_registry_name . $customFieldName);
		}

		return true;
	}

	/**
	 * Retrieve Route information for a specific Category
	 *
	 * Creates the following Registries (ex. Articles content) containing datasource information for this category.
	 *
	 * ContentCategories, ContentCategoriesCustomfields, ContentCategoriesMetadata, ContentCategoriesParameters
	 *
	 * Merges into Route and Parameters Registries
	 *
	 * @return boolean
	 * @since    1.0
	 */
	public function getRouteCategory()
	{
		/** Retrieve the query results */
		$item = $this->get(
			Services::Registry()->get('Parameters', 'catalog_category_id'),
			'Item',
			'Categories'
		);

		/** Save for primary view */
		Services::Registry()->createRegistry('Category');
		Services::Registry()->set('Category', 'query_results', $item);

		/** Route Registry with Category Data */
		Services::Registry()->set('Parameters', 'category_id', (int)$item->id);
		Services::Registry()->set('Parameters', 'category_title', $item->title);
		Services::Registry()->set('Parameters', 'category_translation_of_id', (int)$item->translation_of_id);
		Services::Registry()->set('Parameters', 'category_language', $item->language);
		Services::Registry()->set('Parameters', 'category_catalog_type_id', (int)$item->catalog_type_id);
		Services::Registry()->set('Parameters', 'category_catalog_type_title', $item->catalog_types_title);
		Services::Registry()->set('Parameters', 'category_modified_datetime', $item->modified_datetime);

		/** Process each field namespace  */
		$customFieldTypes = Services::Registry()->get($item->table_registry_name, 'CustomFieldGroups');

		foreach ($customFieldTypes as $customFieldName) {
			$customFieldName = ucfirst(strtolower($customFieldName));

			/** Save for primary view */
			$array = Services::Registry()->getArray($item->table_registry_name . $customFieldName);
			Services::Registry()->set('Category', $customFieldName, $array);

			Services::Registry()->merge($item->table_registry_name . $customFieldName, $customFieldName);
			Services::Registry()->deleteRegistry($item->table_registry_name . $customFieldName);
		}

		return true;
	}

	/**
	 * Get data for content
	 *
	 * @param $id
	 * @param null $type
	 * @param null $datasource
	 *
	 * @return  array An object containing an array of data
	 * @since   1.0
	 */
	public function get($id, $type = null, $datasource = null)
	{
		if ($type == null) {
			$type = 'Content';
		}

		$controllerClass = 'Molajo\\MVC\\Controller\\ModelController';
		$m = new $controllerClass();
		$m->connect($datasource, $type);

		$m->set('id', (int)$id);
		$m->set('process_triggers', 1);

		$item = $m->getData('item');

		$item->table_registry_name = $m->table_registry_name;
		$item->model_name = $m->get('model_name');

		if (count($item) == 0) {
			return array();
		}

		return $item;
	}
}
