<?php
/* Copyright (C) 2017  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        class/shops.class.php
 * \ingroup     mac2sync
 * \brief       This file is a CRUD class file for Shops (Create/Read/Update/Delete)
 */

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/class/shops.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/class/links.class.php';
include_once DOL_DOCUMENT_ROOT . '/api/class/api.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/api_products.class.php';
include_once DOL_DOCUMENT_ROOT . '/categories/class/api_categories.class.php';
/*******************************************************************************************/

/**
 * Class for Shops
 */
class Mac2SyncProducts extends Shops
{
	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * CALL TO THE API
	 * $api_endpoint : products, languages, customers...
	 * $display : Full, [id], [name, value...]
	 * $id : ID linked to the data to return
	 * $filter : filter by ID [id1|id2|id3...]
	 */
	public function getRessourcesFromPrestashop($api_endpoint, $display = null, $id = null, $filter = null, $limit = null, $debug = false)
	{

		try {
			// creating webservice access
			$webService = new PrestaShopWebservice($this->prestashop_url, $this->prestashop_ws_key, $debug);
		
			// type of data to retrieve
			$params = array(
				'resource' 	=> 	$api_endpoint
				// 'Output-Format'=>"JSON"
			);

			// Fields to return
			if(!empty($display)) { 
				$params['display'] = $display;	
			}

			// Specific result by ID
			if(!empty($id)) { 
				$params['id'] = $id;	
			}

			// Specific result by filter
			if(!empty($filter)) { 
				$params['filter[id]'] = $filter;	
			}
			
			
			$xml = $webService->get($params);
		} catch (PrestaShopWebserviceException $ex) {
			// Shows a message related to the error
			echo 'Error: <br />' . $ex->getMessage();
		}

		unset($params);
		unset($webService);

		return $xml;
		
	}

	/**
	 * GET PRESTASHOP LOCALES FOR LANGUAGES
	 * Return an array of the locales of the lang 
	 * or the locale only if $language_id is given
	 * [1, fr_FR]
	 * [3, en_US]
	 * [4, ...] 
	 */
	public function getPrestashopLanguages()
	{
		$languages = $this->getRessourcesFromPrestashop('languages', '[id, locale]');

		$prestashop_languages = $languages->children()->children(); 
		$prestashop_language_locales = [];

		foreach($prestashop_languages as $prestashop_language){
			$prestashop_language_locale_normalized = str_replace('-', '_', $prestashop_language->locale->__toString());
			$prestashop_language_locales[intval($prestashop_language->id)] = $prestashop_language_locale_normalized;
		}

		return $prestashop_language_locales;
	}

	/**
	 * GET PRESTASHOP CATEGORIES
	 * Return an array of the category in format
	 * 	[
	 * 		id => categorie id
	 * 		name => categorie name
	 * 		parent_id => parent id of the category
	 * 		parent_name => parent name of the category
	 * 	]
	 */
	public function getPrestashopCategoriesFullArbo()
	{
		$categories = $this->getRessourcesFromPrestashop('categories', '[id, id_parent, name]');

		$prestashop_products_categories = $categories->children()->children(); 
		// var_dump($prestashop_products_categories); die;
		$ps_categories_array = [];

		foreach($prestashop_products_categories as $ps_categorie){
			$ps_categories_array[] = array(
				'id'			=> (int) $ps_categorie->id,
				'name' 			=> (string) $ps_categorie->name->language,
				'parent_id'		=> (int) $ps_categorie->id_parent
			);
		}

		// Get parent name from parent id
		$parent_categorie_names_array = $this->getPrestashopCategoriesParent($ps_categories_array);

		// Add category parent name to its child category
		$ps_categories_full_arbo = [];

		foreach($ps_categories_array as $ps_categorie_array) {
				$ps_categorie_array['parent_name'] = $parent_categorie_names_array[(int) $ps_categorie_array['parent_id']];
				$ps_categories_full_arbo[] = $ps_categorie_array;
			}

		return $ps_categories_full_arbo;
	}

	/**
	 * GET DOLIBARR CATEGORIES
	 * Return an array of the category in format
	 * 	[
	 * 		id => categorie id
	 * 		name => categorie name
	 * 		parent_id => parent id of the category
	 * 		parent_name => parent name of the category
	 * 	]
	 */
	public function getDolibarrCategoriesFullArbo()
	{
		// Dolibarr Categories
		$dolibarr_categories = new Categorie($this->db);
		$dolibarr_categories = $dolibarr_categories->get_full_arbo('product');
		// At least 1 category exist in Dolibarr
		if(count($dolibarr_categories) > 0) {
			$dlb_categories_array = [];
			foreach($dolibarr_categories as $key => $dolibarr_categorie) {
				// Get parent category name and category 
				$dlb_categories_fulllabel = explode(' >> ', $dolibarr_categorie['fulllabel']);
				$parent_category_name = $dlb_categories_fulllabel[0];
				$category_name = $dlb_categories_fulllabel[1];
				
				// If fulllbal has only one index = $dlb_categories_fulllabel[1] is null
				// So the category has no parent category
				if(!$category_name) {
					$category_name = $parent_category_name;
					$parent_category_name = null;
				}

				$dlb_categories_array[$key]['id'] = $dolibarr_categorie['id'];
				$dlb_categories_array[$key]['name'] = $category_name;
				$dlb_categories_array[$key]['parent_id'] = $dolibarr_categorie['fk_parent'];
				$dlb_categories_array[$key]['parent_name'] = $parent_category_name;
			}
		} else {
			$dlb_categories_array[0]['id'] = 0;
			$dlb_categories_array[0]['name'] = 'Racine';
			$dlb_categories_array[0]['parent_id'] = 0;
			$dlb_categories_array[0]['parent_name'] = null;
		}

		return $dlb_categories_array;
	}

	/**
	 * GET PARENT CATEGORY FROM ONE OR MULTIPLES CATEGORIES IDs
	 * Return an array in format [parent_id => parent_name]
	 */
	public function getPrestashopCategoriesParent($prestashop_categories)
	{
		$parent_ids = [];
		foreach($prestashop_categories as $key => $ps_categorie) {
			$parent_ids[] = $ps_categorie['parent_id'];
		}
		$parent_ids = array_unique($parent_ids);
		$parent_ids = implode('|',$parent_ids);
		$parent_ids = '['.$parent_ids.']';
		$prestashop_categorie_parents = $this->getRessourcesFromPrestashop('categories', '[id, name]', '', $parent_ids);
		$ps_categorie_parent_names_array = [];
		
		foreach($prestashop_categorie_parents->children()->children() as $ps_categorie_parent) {
			$ps_categorie_parent_names_array[(int) $ps_categorie_parent->id] = (string) $ps_categorie_parent->name->language;
		}

		return $ps_categorie_parent_names_array;
	}

	/**
	 * SYNC CATEGORIES BETWEEN PRESTASHOP AND DOLIBARR
	 */
	public function syncCategories()
	{
		global $user;

		// Get categories from Prestashop
		$prestashop_categories = $this->getPrestashopCategoriesFullArbo();
		// var_dump($prestashop_categories); die;

		

		// Compare Prestashop categories and Dolibarr Categories
		$root = 'Racine';
		$home = 'Accueil';

		$dlb_categories_array = $this->getDolibarrCategoriesFullArbo();

		foreach($prestashop_categories as $ps_key => $prestashop_categorie) {
			foreach($dlb_categories_array as $dlb_key => $dlb_categorie) {
				if($prestashop_categorie['name'] !== $root && $prestashop_categorie['name'] !== $home) {
					// checking if the Prestashop category name or parent category name exist in Dolibarr
					$is_category_exist = ($prestashop_categorie['name'] === $dlb_categorie['name']);
					$is_parent_category_exist = ($prestashop_categorie['parent_name'] === $dlb_categorie['parent_name']);

					// Prestashop category linked to its parent doesn't exist in Dolibarr
					if(!$is_category_exist || (!$is_parent_category_exist && $prestashop_categorie['parent_name'] !== $root)) {
						
						// Checking if the parent exist
						$new_dlb_parent_categorie = new Categorie($this->db);
						$new_dlb_parent_categorie->label = $prestashop_categorie['parent_name'];

						// If parent is root then the parent already exist in Dolibarr
						// If parent is home, we force it to be root
						if($prestashop_categorie['parent_name'] === $root || $prestashop_categorie['parent_name'] === $home) {
							$prestashop_categorie['parent_name'] === $root;
							$dlb_root_category_id = 0; // Default ID for root or home category

						// The parent is not the root category, we try to found it.
						} else {
							// var_dump($prestashop_categorie['parent_name']); 
							$new_dlb_parent_categorie->fk_parent = 0;
							$dlb_parent_categorie_exist = $new_dlb_parent_categorie->already_exists();
						
							// Parent not found in Dolibarr, so we create it
							if(!$dlb_parent_categorie_exist) {
								$dlb_parent_categorie_id = $new_dlb_parent_categorie->create($user);
								if($dlb_parent_categorie_id < 0) {
									// Parent category not created. There is an error.
									var_dump('La catégorie parente n\'a pas été crée');
								}
								var_dump('Catégorie parente ID' . $dlb_parent_categorie_id . 'Créée avec succès !'); 

							// The Prestashop parent category already exist. We retrieve its ID from Dolibarr 
							} else {
								// Return an array of all categories in Dolibarr with the researched name
								$dlb_categories_searched = $new_dlb_parent_categorie->rechercher(0, $new_dlb_parent_categorie->label, 0);
								foreach($dlb_categories_searched as $dlb_categorie_found) {
									// We looking for a parent category which has not a parent (no parent = parent is root).
									if($dlb_categorie_found->label === $new_dlb_parent_categorie->label && ($dlb_categorie_found->fk_parent === null || $dlb_categorie_found->fk_parent === 0)) {
										// We get the parent category ID
										$dlb_parent_categorie_id = $dlb_categorie_found->id;
									}
								}
							}
						}

						// Now we are checking if the Prestashop category (child of parent) exist in Dolibarr 
						$new_dlb_categorie = new Categorie($this->db);
						$new_dlb_categorie->label = $prestashop_categorie['name'];

						// var_dump($new_dlb_categorie);

						// If parent is not root we retrieve its ID from Dolibarr
						if(!$dlb_root_category_id) {
							$new_dlb_categorie->fk_parent = $dlb_parent_categorie_id;
						} else {
							$new_dlb_categorie->fk_parent = $dlb_root_category_id;
						}

						$dlb_categorie_already_exist = $new_dlb_categorie->already_exists();

						// Category not found
						if(!$dlb_categorie_already_exist) {
							$dlb_categorie_id = $new_dlb_categorie->create($user);

							if($dlb_categorie_id < 0) {
								// Category not created. There is an error.
								var_dump('La catégorie n\'a pas été crée');
							}
							// var_dump('Catégorie ID' . $dlb_categorie_id . ' créée avec succès !');
						}
					}
				}

				unset($is_category_exist, $is_parent_category_exist, $new_dlb_parent_categorie, $new_dlb_categorie, $dlb_root_category_id, $dlb_parent_categorie_id);
			}
		}
	}

	/**
	 * SYNC PRODUCTS BETWEEN PRESTASHOP AND DOLIBARR
	 */
	public function syncProducts()
	{
		global $db; global $langs; global $user; global $id;

		////////////////////////////////////////////////////////////////////////
		// CATEGORIES SYNC FROM PRESTASHOP TO DOLIBARR
		////////////////////////////////////////////////////////////////////////
		$this->syncCategories();
		
		// Init error count
		$error = 0;

		// Init synced products
		$added_products = [];

		////////////////////////////////////////////////////////////////////////
		// GET PRODUCTS DATA FROM PRESTASHOP
		////////////////////////////////////////////////////////////////////////
		$prestashop_products = $this->getRessourcesFromPrestashop('products', '[id, name, description, reference, price, on_sale, id_category_default, categories[id]]');

		// We also retrieve languages locales from Prestashop		
		$prestashop_languages = $this->getPrestashopLanguages();

		// And Dolibarr Default lang
		$dolibarr_lang_default = $langs->getDefaultLang();

		// Dolibarr all langs available may serve later...
		// $dolibarr_langs_available = $langs->get_available_languages(DOL_DOCUMENT_ROOT, 0, 2);

		foreach($prestashop_products->children()->children() as $ps_product) {
			$ps_product_attributes = $ps_product->children();
			
			// if the product has not multilanguales informations
			if(!$ps_product_attributes->name->language) {
				$multilingual = false;
				$ps_product_label = $ps_product_attributes->name;
				$ps_product_description = $ps_product_attributes->description;
			} else {
				$multilingual = true;
				$ps_product_labels = $ps_product_attributes->name->language;
				$ps_product_descriptions = $ps_product_attributes->description->language;
				$md = 0; // Init key for multilingual infos

				// Create an array associating each product name to its description regarding the language
				// array format : 
				// [lang_id => [
				//     product name => product description
				// ]]
				foreach($ps_product_labels as $labels) {
					$ps_product_language_locale_id = intval($labels->attributes()['id']);

					// We store the id of the language from Prestashop linked to the the default dolibarr language
					$ps_product_language_locale = $prestashop_languages[$ps_product_language_locale_id];

					if($ps_product_language_locale === $dolibarr_lang_default) {
						$prestashop_default_lang_id_for_dolibarr = $ps_product_language_locale_id;
					}

					// $prestashop_language_locale_default = $prestashop_languages[$ps_product_language_locale_id];
					$ps_product_multilangs[$ps_product_language_locale_id] = array(
						$labels->__toString() => $ps_product_descriptions[$md]->__toString()
					);
					$md++;
				}
			}

			////////////////////////////////////////////////////////////////////////
			// CREATE THE PRODUCT IN DOLIBARR
			////////////////////////////////////////////////////////////////////////

			// We will use the default language id previously stored to create the default product info
			// wich will be inserted first in Dolibarr
			$new_dolibarr_product = new Product($db);

			// Is a multilingual product?
			if($multilingual) {
				$new_dolibarr_product->label = array_key_first($ps_product_multilangs[$prestashop_default_lang_id_for_dolibarr]);
				$new_dolibarr_product->description = $ps_product_multilangs[$prestashop_default_lang_id_for_dolibarr][$new_dolibarr_product->label];
			} else {
				$new_dolibarr_product->label = $ps_product_label;
				$new_dolibarr_product->description = $ps_product_description;
			}

			$new_dolibarr_product->ref = $ps_product_attributes->reference;
			$new_dolibarr_product->price_ttc = $ps_product_attributes->price;
			$new_dolibarr_product->price = $ps_product_attributes->price;
			$new_dolibarr_product->price_min_ttc = $ps_product_attributes->price;
			$new_dolibarr_product->price_min = $ps_product_attributes->price;
			$new_dolibarr_product->status = $ps_product_attributes->on_sale;
			$new_dolibarr_product->status_buy = $ps_product_attributes->on_sale;
			$new_dolibarr_product->price_base_type = 'HT';

			// Create the product
			$new_dolibarr_product_id = $new_dolibarr_product->create($user);

			// Product successfuly created
			if($new_dolibarr_product_id > 0) {

				////////////////////////////////////////////////////////////////////////
				// SET DOLIBARR CATEGORIES
				////////////////////////////////////////////////////////////////////////

				// Root name category in Dolibarr which will be skipped for control
				$root = 'Racine';

				// Init array de categories ids of the product
				$ps_product_categories_ids = [];

				// IDs of root categories ('Racine' and 'Home' categories) stored in Prestashop
				// Theses IDs are used for any Prestashop installation
				$root_categories_ids = [1,2];

				// Create an array of the Prestashop product containing the category id and the parent category id
				$k = 'parent_id';

				foreach($ps_product_attributes->associations->categories->category as $ps_product_categorie) {
					// If the Prestashop category is not root
					if(!in_array((int) $ps_product_categorie->id, $root_categories_ids)) {
						$ps_product_categories_ids[$k] = (int) $ps_product_categorie->id;
						$k = 'id';
					}
				}

				// If prestashop categories ids was found for the product
				if(!empty($ps_product_categories_ids)) {
					// Get Prestashop categorie name and parent name from theses ids
					$prestashop_categories = $this->getPrestashopCategoriesFullArbo();

					foreach ($prestashop_categories as $prestashop_category) {
						
						if(!array_diff_assoc($ps_product_categories_ids, $prestashop_category)) {
							$ps_product_category_parent_name = $prestashop_category['parent_name'];
							$ps_product_category_name = $prestashop_category['name'];
							break;
						} 
					}

						
					if(empty($ps_product_category_parent_name) && empty($ps_product_category_name)) {
						// Prestashop product has no category 
					} else {
						// Search equivalent Dolibarr category id with the same name and same parent
						$dlb_categorie_full_arbo = $this->getDolibarrCategoriesFullArbo();

						foreach($dlb_categorie_full_arbo as $dlb_cat_arbo) {

							if($dlb_cat_arbo['parent_name'] === $ps_product_category_parent_name && $dlb_cat_arbo['name'] === $ps_product_category_name) {
								// Same category name with the same parent name found in Dolibarr. We use the associated category id to set the category to the product
								$new_dolibarr_product->setCategories($dlb_cat_arbo['id']);
								$dolibarr_categorie_found = true;
								break;
							} 
						}

						// If no category was found
						if(!isset($dolibarr_categorie_found)) {
							// Error
							var_dump('Aucune catégorie correspondante n\'a été trouvée dans Dolibarr. Veuillez synchroniser les catégories avant de synchroniser les produits');
						}
					}
				}

				////////////////////////////////////////////////////////////////////////
				// SET DOLIBARR MULTILANGS INFOS
				////////////////////////////////////////////////////////////////////////

				// Insert multilangs info of the prestashop product in Dolibarr
				foreach($ps_product_multilangs as $ps_lang_locale_id => $ps_product_multilang_info) {

					// id of the language from Prestashop linked to the the default dolibarr language
					$ps_product_language_locale = $prestashop_languages[$ps_lang_locale_id];

					if($ps_product_language_locale !== $dolibarr_lang_default) {
						$new_dolibarr_product->multilangs[$ps_product_language_locale]['label'] = array_key_first($ps_product_multilang_info);
						$new_dolibarr_product_label = $new_dolibarr_product->multilangs[$ps_product_language_locale]['label'];
						$new_dolibarr_product->multilangs[$ps_product_language_locale]['description'] = $ps_product_multilang_info[$new_dolibarr_product_label];
						$new_dolibarr_product->setMultiLangs($user);
					}
				}

				// Serve to count and display the total de product added
				$added_products[] = $new_dolibarr_product->label;
			} else {
				$error++;
				// Error, the product was not created
				// If $new_dolibarr_product_id = -1 means the product already exist with the provided ref
				// var_dump($new_dolibarr_product_id);
			}
			
			unset($new_dolibarr_product, $k, $ps_product_category_parent_name, $ps_product_category_name);
		}

		if($error > 0) {
			//
		} 

		$nbr_added_products = count($added_products);

		if($nbr_added_products > 0) {
			$result = 'Nombre de produits synchronisés :'. $nbr_added_products . "\n";

			foreach($added_products as $added_product) {
				$result .= $added_product. "\n";
			} 
		} else {
			$result = 'Nombre de produits synchronisés : 0' . "\n";
			$result .= 'Tous les produits ont déjà été synchronisés.'; 
		}

		return $result;
	}
}

/**
 * Class ShopsLine. You can also remove this and generate a CRUD class for lines objects.
 */
class Mac2SyncProductsLine extends ShopsLine
{
	// To complete with content of an object ShopsLine
	// We should have a field rowid, fk_shops and position

	/**
	 * @var int  Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}
}
