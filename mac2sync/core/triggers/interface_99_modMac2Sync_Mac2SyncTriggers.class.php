<?php
/* Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modMyModule_MyModuleTriggers.class.php
 * \ingroup mymodule
 * \brief   Example trigger.
 *
 * Put detailed description here.
 *
 * \remarks You can create other triggers by copying this one.
 * - File name should be either:
 *      - interface_99_modMyModule_MyTrigger.class.php
 *      - interface_99_all_MyTrigger.class.php
 * - The file must stay in core/triggers
 * - The class name must be InterfaceMytrigger
 * - The constructor method must be named InterfaceMytrigger
 * - The name property name must be MyTrigger
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';


/**
 *  Class of triggers for MyModule module
 */
class InterfaceMac2SyncTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "MyModule triggers.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 'development';
		$this->picto = 'mymodule@mymodule';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
	    //if (empty($conf->mymodule->enabled)) return 0; // If module is not enabled, we do nothing

		//
		global $db;
		include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/class/shops.class.php';
		include_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
		include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/PSWebServicesMethods.php';
		include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/DolibarrMethods.php';
		include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';

		//Récupération des Shops
		$shops = new Shops($db);
		$shops = $shops->fetchAll();

		// foreach($shops as $shop){
		// 	if ($action == 'PRODUCT_CREATE' && $shop->CREATE_PRODUCT_IN_PRESTA){
		// 		mac2sync_log("\n [TRIGGER] Product Create:" .$object->ref);
		// 		// TODO: Pour la synchro du produit dans la catégorie correspondante faire la synchro des categories 
		// 		createProduct($object->ref, $object->label, $object->price, $object->description, $conf->entity, $shop->prestashop_ws_key, $shop->prestashop_url);
		// 	}
			
		// 	if($action == 'PRODUCT_MODIFY' && $shop->UPD_PRODUCT_IN_PRESTA){
		// 		mac2sync_log("[TRIGGER] Product Modify :" . $object->ref);
				
		// 		//Récupère les catégories liées au produit
		// 		$product_categories_id = productGetCategoriesById($object->id);
		// 		$product_categories_prestashop_id = array();

		// 		foreach($product_categories_id as $product_categorie_id){
		// 				$categorie_prestashop_id = getPrestashopLink("category", $product_categorie_id, $shop->id);
		// 				if($categorie_prestashop_id !=FALSE){
		// 					array_push($product_categories_prestashop_id, (int) $categorie_prestashop_id);
		// 					mac2sync_log("Catégorie prestashop id: " .$categorie_prestashop_id);
		// 				}
		// 		}
				
		// 	//Prendre le bon prix en cas de multiprix
		// 	if($conf->global->PRODUIT_MULTIPRICES && $shop->PRINCIPAL_LEVEL_MULTIPRICE != NULL){
		// 		$object->price = $object->multiprices[$shop->PRINCIPAL_LEVEL_MULTIPRICE];
		// 		$object->price_ttc = $object->multiprices_ttc[$shop->PRINCIPAL_LEVEL_MULTIPRICE];
		// 	}
		// 	updateProduct($object->ref, $shop->prestashop_ws_key, $shop->prestashop_url, $object, $product_categories_prestashop_id);
		// 	}


		// 	if($action == 'PRODUCT_PRICE_MODIFY' && $shop->UPD_PRODUCT_IN_PRESTA){
		// 		if($conf->global->PRODUIT_MULTIPRICES){
		// 			updateProductMultiPrice($object->ref, $object->multiprices, $shop->PRINCIPAL_LEVEL_MULTIPRICE, $shop->prestashop_ws_key, $shop->prestashop_url);
		// 			/*foreach($object->multiprices as $level => $price){
		// 				updateProductMultiPriceOld($object->ref, $price, $level, $shop->PRINCIPAL_LEVEL_MULTIPRICE, $shop->prestashop_ws_key, $shop->prestashop_url);
		// 			}*/
		// 		}else{
		// 			updateProductPrice($object->ref, $object->price, $shop->prestashop_ws_key, $shop->prestashop_url);
		// 		}
		// 	}

		// 	if ($action == 'PRODUCT_DELETE' && $shop->DEL_PRODUCT_IN_PRESTA){
		// 		mac2sync_log("[TRIGGER] Product Delete:" .$object->ref);
		// 		deleteProduct($object->ref, $shop->prestashop_ws_key, $shop->prestashop_url);
		// 	}

		// 	// if($action == 'COMPANY_CREATE'  && $shop->CREATE_CLIENT_IN_PRESTA){
		// 	// 	mac2sync_log("[TRIGGER] Client Create:" .$object->name);
		// 	// 	createClient($shop->prestashop_ws_key, $shop->prestashop_url, $object);
		// 	// }

		// 	if($action == 'COMPANY_MODIFY' && $shop->UPD_CLIENT_IN_PRESTA){
		// 		mac2sync_log("[TRIGGER] Client Modify :" . $object->ref);
		// 		updateClient($shop->prestashop_ws_key, $shop->prestashop_url, $object);
		// 	}

		// 	if ($action == 'COMPANY_DELETE' && $shop->DEL_PRODUCT_IN_PRESTA){
		// 		mac2sync_log("[TRIGGER] Client Delete:" .$object->id);
		// 		deleteClient($shop->prestashop_ws_key, $shop->prestashop_url, $object);
		// 	}

		// 	if ($action == 'CATEGORY_CREATE' && $shop->CREATE_CATEGORY_IN_PRESTA && $object->type=='product'){
		// 		mac2sync_log("[TRIGGER] Category Create:" .$object->id);
				
		// 		//DEVCOMM: $object->fk_parent :: arborescence dolibarr
				
		// 		createCategorie($object->label, $object->id, 0, $shop->prestashop_ws_key, $shop->prestashop_url);
		// 	}

		// 	if ($action == 'CATEGORY_MODIFY' && $shop->UPD_CATEGORY_IN_PRESTA && $object->type == 0){
		// 		//KO
		// 		return true;
		// 		mac2sync_log("[TRIGGER] Category Update:" .$object->id);
				
		// 		//$object->label
		// 		//$object->fk_parent :: peut-être changée

		// 		//DEVCOMM: $object->fk_parent :: arborescence dolibarr
		// 		updateCategorie($object->label, $object->id, 0, $shop->prestashop_ws_key, $shop->prestashop_url, $shop->id);
				
		// 		//createCategorie($object->label, $object->id, 0, $shop->prestashop_ws_key, $shop->prestashop_url);
		// 	}

		// 	if($action =='STOCK_MOVEMENT'){
		// 		//var_dump($object);exit();
		// 		//$object->qty //(ex: +1)
		// 		//$object->product_id
		// 		updateStock($object->qty, $object->product_id, $shop->prestashop_ws_key, $shop->prestashop_url);
		// 	}

		// }
		
		return 0;
	}
}
