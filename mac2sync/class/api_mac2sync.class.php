<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2021 SuperAdmin
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

use LDAP\Result;
use Luracast\Restler\RestException;

dol_include_once('/mac2sync/class/shops.class.php');

require_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
// require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

require_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/class/societe.class.php';

require_once DOL_DOCUMENT_ROOT . '/societe/class/api_thirdparties.class.php';

require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.product.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/variants/class/ProductAttribute.class.php';
require_once DOL_DOCUMENT_ROOT . '/variants/class/ProductAttributeValue.class.php';
require_once DOL_DOCUMENT_ROOT . '/variants/class/ProductCombination.class.php';
require_once DOL_DOCUMENT_ROOT . '/variants/class/ProductCombination2ValuePair.class.php';
require_once DOL_DOCUMENT_ROOT . '/api/class/api_access.class.php';

/**
 * \file    mac2sync/class/api_mac2sync.class.php
 * \ingroup mac2sync
 * \brief   File for API management of shops.
 */

/**
 * API class for mac2sync shops
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Mac2SyncApi extends DolibarrApi
{
	/**
	 * @var Shops $shops {@type Shops}
	 */
	public $shops;

	/**
	 * @var Product $product {@type Product}
	 */
	public $product;

	/**
	 * @var CommandeMac2Sync $commande {@type CommandeMac2Sync}
	 */
	public $commande;

	/**
	 * @var FactureMac2Sync $commande {@type FactureMac2Sync}
	 */
	public $invoice;

	/**
	 * @var ExpeditionMac2sync $shipment {@type ExpeditionMac2sync}
	 */
	public $shipment;

	/**
	 * @var SocieteMac2sync $company {@type Societe}
	 */
	public $company;

	/**
	 * @var array   $FIELDS     Mandatory fields, checked when create and update object
	 */
	static $FIELDS = array(
		'socid',
		'date'
	);

	/**
	 * Constructor
	 *
	 * @url     GET /
	 *
	 */
	public function __construct()
	{
		global $db, $conf;
		$this->db = $db;
		$this->shops = new Shops($this->db);
		$this->product = new Product($this->db);
		$this->commande = new CommandeMac2Sync($this->db);
		$this->invoice = new FactureMac2Sync($this->db);
		$this->shipment = new ExpeditionMac2sync($this->db);
		$this->company = new SocieteMac2sync($this->db);
	}

	/**
	 * Get properties of a shops object
	 *
	 * Return an array with shops informations
	 *
	 * @param 	int 	$id ID of shops
	 * @return 	array|mixed data without useless information
	 *
	 * @url	GET shopss/{id}
	 *
	 * @throws RestException 401 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function get($id)
	{
		// if (!DolibarrApiAccess::$user->rights->mac2sync->read) {
		// 	throw new RestException(401);
		// }

		$result = $this->shops->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Shops not found');
		}

		// if (!DolibarrApi::_checkAccessToResource('shops', $this->shops->id, 'mac2sync_shops')) {
		// 	throw new RestException(401, 'Access to instance id='.$this->shops->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		// }

		return $this->_cleanObjectDatas($this->shops);
	}


	/**
	 * List shopss
	 *
	 * Get a list of shopss
	 *
	 * @param string	       $sortfield	        Sort field
	 * @param string	       $sortorder	        Sort order
	 * @param int		       $limit		        Limit for list
	 * @param int		       $page		        Page number
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @return  array                               Array of order objects
	 *
	 * @throws RestException
	 *
	 * @url	GET /shopss/
	 */
	public function index($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '')
	{
		global $db, $conf;

		$obj_ret = array();
		$tmpobject = new Shops($this->db);

		// if (!DolibarrApiAccess::$user->rights->mac2sync->shops->read) {
		// 	throw new RestException(401);
		// }

		$socid = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : '';

		$restrictonsocid = 0; // Set to 1 if there is a field socid in table of object

		// If the internal user must only see his customers, force searching by him
		$search_sale = 0;
		if ($restrictonsocid && !DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) $search_sale = DolibarrApiAccess::$user->id;

		$sql = "SELECT t.rowid";
		if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) $sql .= ", sc.fk_soc, sc.fk_user"; // We need these fields in order to filter by sale (including the case where the user can only see his prospects)
		$sql .= " FROM " . MAIN_DB_PREFIX . $tmpobject->table_element . " as t";

		if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) $sql .= ", " . MAIN_DB_PREFIX . "societe_commerciaux as sc"; // We need this table joined to the select in order to filter by sale
		$sql .= " WHERE 1 = 1";

		// Example of use $mode
		//if ($mode == 1) $sql.= " AND s.client IN (1, 3)";
		//if ($mode == 2) $sql.= " AND s.client IN (2, 3)";

		if ($tmpobject->ismultientitymanaged) $sql .= ' AND t.entity IN (' . getEntity($tmpobject->element) . ')';
		if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) $sql .= " AND t.fk_soc = sc.fk_soc";
		if ($restrictonsocid && $socid) $sql .= " AND t.fk_soc = " . $socid;
		if ($restrictonsocid && $search_sale > 0) $sql .= " AND t.rowid = sc.fk_soc"; // Join for the needed table to filter by sale
		// Insert sale filter
		if ($restrictonsocid && $search_sale > 0) {
			$sql .= " AND sc.fk_user = " . $search_sale;
		}
		if ($sqlfilters) {
			if (!DolibarrApi::_checkFilters($sqlfilters)) {
				throw new RestException(503, 'Error when validating parameter sqlfilters ' . $sqlfilters);
			}
			$regexstring = '\(([^:\'\(\)]+:[^:\'\(\)]+:[^:\(\)]+)\)';
			$sql .= " AND (" . preg_replace_callback('/' . $regexstring . '/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters) . ")";
		}

		$sql .= $this->db->order($sortfield, $sortorder);
		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		$result = $this->db->query($sql);
		$i = 0;
		if ($result) {
			$num = $this->db->num_rows($result);
			while ($i < $num) {
				$obj = $this->db->fetch_object($result);
				$tmp_object = new Shops($this->db);
				if ($tmp_object->fetch($obj->rowid)) {
					$obj_ret[] = $this->_cleanObjectDatas($tmp_object);
				}
				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieving shops list: ' . $this->db->lasterror());
		}
		if (!count($obj_ret)) {
			throw new RestException(404, 'No shops found');
		}
		return $obj_ret;
	}

	/**
	 * Create shops object
	 *
	 * @param array $request_data   Request datas
	 * @return int  ID of shops
	 *
	 * @throws RestException
	 *
	 * @url	POST shopss/
	 */
	public function post($request_data = null)
	{
		if (!DolibarrApiAccess::$user->rights->mac2sync->write) {
			throw new RestException(401);
		}
		// Check mandatory fields
		$result = $this->_validate($request_data);

		foreach ($request_data as $field => $value) {
			$this->shops->$field = $value;
		}
		if ($this->shops->create(DolibarrApiAccess::$user) < 0) {
			throw new RestException(500, "Error creating Shops", array_merge(array($this->shops->error), $this->shops->errors));
		}
		return $this->shops->id;
	}

	/**
	 * Update shops
	 *
	 * @param int   $id             Id of shops to update
	 * @param array $request_data   Datas
	 * @return int
	 *
	 * @throws RestException
	 *
	 * @url	PUT shopss/{id}
	 */
	public function put($id, $request_data = null)
	{
		if (!DolibarrApiAccess::$user->rights->mac2sync->write) {
			throw new RestException(401);
		}

		$result = $this->shops->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Shops not found');
		}

		if (!DolibarrApi::_checkAccessToResource('shops', $this->shops->id, 'mac2sync_shops')) {
			throw new RestException(401, 'Access to instance id=' . $this->shops->id . ' of object not allowed for login ' . DolibarrApiAccess::$user->login);
		}

		foreach ($request_data as $field => $value) {
			if ($field == 'id') continue;
			$this->shops->$field = $value;
		}

		if ($this->shops->update(DolibarrApiAccess::$user, false) > 0) {
			return $this->get($id);
		} else {
			throw new RestException(500, $this->shops->error);
		}
	}

	/**
	 * Delete shops
	 *
	 * @param   int     $id   Shops ID
	 * @return  array
	 *
	 * @throws RestException
	 *
	 * @url	DELETE shopss/{id}
	 */
	public function delete($id)
	{
		if (!DolibarrApiAccess::$user->rights->mac2sync->delete) {
			throw new RestException(401);
		}
		$result = $this->shops->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Shops not found');
		}

		if (!DolibarrApi::_checkAccessToResource('shops', $this->shops->id, 'mac2sync_shops')) {
			throw new RestException(401, 'Access to instance id=' . $this->shops->id . ' of object not allowed for login ' . DolibarrApiAccess::$user->login);
		}

		if (!$this->shops->delete(DolibarrApiAccess::$user)) {
			throw new RestException(500, 'Error when deleting Shops : ' . $this->shops->error);
		}

		return array(
			'success' => array(
				'code' => 200,
				'message' => 'Shops deleted'
			)
		);
	}




	private function _fetch($id, $ref = '', $ref_ext = '', $barcode = '', $includestockdata = 0, $includesubproducts = false, $includeparentid = false, $includeifobjectisused = false, $includetrans = false)
	{
		if (empty($id) && empty($ref) && empty($ref_ext) && empty($barcode)) {
			throw new RestException(400, 'bad value for parameter id, ref, ref_ext or barcode');
		}

		$id = (empty($id) ? 0 : $id);

		if (!DolibarrApiAccess::$user->rights->produit->lire) {
			throw new RestException(403);
		}

		$result = $this->product->fetch($id, $ref, $ref_ext, $barcode, 0, 0, ($includetrans ? 0 : 1));
		if (!$result) {
			throw new RestException(404, 'Product not found');
		}

		// if (!DolibarrApi::_checkAccessToResource('product', $this->product->id)) {
		// 	throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		// }

		if ($includestockdata && DolibarrApiAccess::$user->rights->stock->lire) {
			$this->product->load_stock();

			if (is_array($this->product->stock_warehouse)) {
				foreach ($this->product->stock_warehouse as $keytmp => $valtmp) {
					if (is_array($this->product->stock_warehouse[$keytmp]->detail_batch)) {
						foreach ($this->product->stock_warehouse[$keytmp]->detail_batch as $keytmp2 => $valtmp2) {
							unset($this->product->stock_warehouse[$keytmp]->detail_batch[$keytmp2]->db);
						}
					}
				}
			}
		}

		if ($includesubproducts) {
			$childsArbo = $this->product->getChildsArbo($id, 1);

			$keys = array('rowid', 'qty', 'fk_product_type', 'label', 'incdec', 'ref', 'fk_association', 'rang');
			$childs = array();
			foreach ($childsArbo as $values) {
				$childs[] = array_combine($keys, $values);
			}

			$this->product->sousprods = $childs;
		}

		if ($includeparentid) {
			$prodcomb = new ProductCombination($this->db);
			$this->product->fk_product_parent = null;
			if (($fk_product_parent = $prodcomb->fetchByFkProductChild($this->product->id)) > 0) {
				$this->product->fk_product_parent = $fk_product_parent;
			}
		}

		if ($includeifobjectisused) {
			$this->product->is_object_used = ($this->product->isObjectUsed() > 0);
		}

		return $this->_cleanObjectDatasProduct($this->product);
	}

	protected function _cleanObjectDatasProduct($object)
	{
		// phpcs:enable
		$object = parent::_cleanObjectDatas($object);

		unset($object->statut);

		unset($object->regeximgext);
		unset($object->price_by_qty);
		unset($object->prices_by_qty_id);
		unset($object->libelle);
		unset($object->product_id_already_linked);
		unset($object->reputations);
		unset($object->db);
		unset($object->name);
		unset($object->firstname);
		unset($object->lastname);
		unset($object->civility_id);
		unset($object->contact);
		unset($object->contact_id);
		unset($object->thirdparty);
		unset($object->user);
		unset($object->origin);
		unset($object->origin_id);
		unset($object->fourn_pu);
		unset($object->fourn_price_base_type);
		unset($object->fourn_socid);
		unset($object->ref_fourn);
		unset($object->ref_supplier);
		unset($object->product_fourn_id);
		unset($object->fk_project);

		unset($object->mode_reglement_id);
		unset($object->cond_reglement_id);
		unset($object->demand_reason_id);
		unset($object->transport_mode_id);
		unset($object->cond_reglement);
		unset($object->shipping_method_id);
		unset($object->model_pdf);
		unset($object->note);

		unset($object->nbphoto);
		unset($object->recuperableonly);
		unset($object->multiprices_recuperableonly);
		unset($object->tva_npr);
		unset($object->lines);
		unset($object->fk_bank);
		unset($object->fk_account);

		unset($object->supplierprices);	// Mut use another API to get them

		if (empty(DolibarrApiAccess::$user->rights->stock->lire)) {
			unset($object->stock_reel);
			unset($object->stock_theorique);
			unset($object->stock_warehouse);
		}

		return $object;
	}


	/////////  PRODUCTS    ////////

	/**
	 * List products
	 * 
	 * Get a list of products
	 *
	 *
	 * @param  string $sortfield  			Sort field
	 * @param  string $sortorder  			Sort order
	 * @param  int    $limit      			Limit for list
	 * @param  int    $page       			Page number
	 * @param  int    $mode       			Use this param to filter list (0 for all, 1 for only product, 2 for only service)
	 * @param  int    $category   			Use this param to filter list by category
	 * @param  string $sqlfilters 			Other criteria to filter answers separated by a comma. Syntax example "(t.tobuy:=:0) and (t.tosell:=:1)"
	 * @param  bool   $ids_only   			Return only IDs of product instead of all properties (faster, above all if list is long)
	 * @param  int    $variant_filter   	Use this param to filter list (0 = all, 1=products without variants, 2=parent of variants, 3=variants only)
	 * @param  bool   $pagination_data   	If this parameter is set to true the response will include pagination data. Default value is false. Page starts from 0
	 * @param  int    $includestockdata		Load also information about stock (slower)
	 * @return array                		Array of product objects
	 * 
	 * @url	GET /products/
	 * 
	 */
	public function getProducts($sortfield = "t.ref", $sortorder = 'ASC', $limit = 100, $page = 0, $mode = 0, $category = 0, $sqlfilters = '', $ids_only = false, $variant_filter = 0, $pagination_data = false, $includestockdata = 0)
	{
		global $db, $conf;

		// if (!DolibarrApiAccess::$user->rights->produit->lire) {
		// 	throw new RestException(403);
		// }

		$obj_ret = array();

		// $socid = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : '';

		$sql = "SELECT t.rowid, t.ref, t.ref_ext";
		$sql .= " FROM " . MAIN_DB_PREFIX . "product as t";
		if ($category > 0) {
			$sql .= ", " . MAIN_DB_PREFIX . "categorie_product as c";
		}
		// $sql .= ' WHERE t.entity IN ('.getEntity('product').')';  // When commented retrieve all products from all entity

		if ($variant_filter == 1) {
			$sql .= ' AND t.rowid not in (select distinct fk_product_parent from ' . MAIN_DB_PREFIX . 'product_attribute_combination)';
			$sql .= ' AND t.rowid not in (select distinct fk_product_child from ' . MAIN_DB_PREFIX . 'product_attribute_combination)';
		}
		if ($variant_filter == 2) {
			$sql .= ' AND t.rowid in (select distinct fk_product_parent from ' . MAIN_DB_PREFIX . 'product_attribute_combination)';
		}
		if ($variant_filter == 3) {
			$sql .= ' AND t.rowid in (select distinct fk_product_child from ' . MAIN_DB_PREFIX . 'product_attribute_combination)';
		}

		// Select products of given category
		if ($category > 0) {
			$sql .= " AND c.fk_categorie = " . ((int) $category);
			$sql .= " AND c.fk_product = t.rowid";
		}
		if ($mode == 1) {
			// Show only products
			$sql .= " AND t.fk_product_type = 0";
		} elseif ($mode == 2) {
			// Show only services
			$sql .= " AND t.fk_product_type = 1";
		}
		// Add sql filters
		if ($sqlfilters) {
			$errormessage = '';
			if (!DolibarrApi::_checkFilters($sqlfilters, $errormessage)) {
				throw new RestException(503, 'Error when validating parameter sqlfilters -> ' . $errormessage);
			}
			//var_dump($sqlfilters);exit;
			$regexstring = '\(([^:\'\(\)]+:[^:\'\(\)]+:[^\(\)]+)\)';	// We must accept datc:<:2020-01-01 10:10:10
			$sql .= " AND (" . preg_replace_callback('/' . $regexstring . '/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters) . ")";
		}

		//this query will return total products with the filters given
		$sqlTotals =  str_replace('SELECT t.rowid, t.ref, t.ref_ext', 'SELECT count(t.rowid) as total', $sql);

		$sql .= $this->db->order($sortfield, $sortorder);
		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}

		$result = $this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);
			$min = min($num, ($limit <= 0 ? $num : $limit));
			$i = 0;
			while ($i < $min) {
				$obj = $this->db->fetch_object($result);
				if (!$ids_only) {
					$product_static = new Product($this->db);
					if ($product_static->fetch($obj->rowid)) {
						if ($includestockdata && DolibarrApiAccess::$user->rights->stock->lire) {
							$product_static->load_stock();

							if (is_array($product_static->stock_warehouse)) {
								foreach ($product_static->stock_warehouse as $keytmp => $valtmp) {
									if (is_array($product_static->stock_warehouse[$keytmp]->detail_batch)) {
										foreach ($product_static->stock_warehouse[$keytmp]->detail_batch as $keytmp2 => $valtmp2) {
											unset($product_static->stock_warehouse[$keytmp]->detail_batch[$keytmp2]->db);
										}
									}
								}
							}
						}


						$obj_ret[] = $this->_cleanObjectDatas($product_static);
					}
				} else {
					$obj_ret[] = $obj->rowid;
				}
				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieve product list : ' . $this->db->lasterror());
		}
		if (!count($obj_ret)) {
			throw new RestException(404, 'No product found');
		}

		//if $pagination_data is true the response will contain element data with all values and element pagination with pagination data(total,page,limit)
		if ($pagination_data) {
			$totalsResult = $this->db->query($sqlTotals);
			$total = $this->db->fetch_object($totalsResult)->total;

			$tmp = $obj_ret;
			$obj_ret = array();

			$obj_ret['data'] = $tmp;
			$obj_ret['pagination'] = array(
				'total' => (int) $total,
				'page' => $page, //count starts from 0
				'page_count' => ceil((int) $total / $limit),
				'limit' => $limit
			);
		}

		return $obj_ret;
	}

	/**
	 * Get properties of a product object by id
	 *
	 * Return an array with product information.
	 *
	 * @param  int    $id                  ID of product
	 * @param  int    $includestockdata    Load also information about stock (slower)
	 * @param  bool   $includesubproducts  Load information about subproducts
	 * @param  bool   $includeparentid     Load also ID of parent product (if product is a variant of a parent product)
	 * @param  bool   $includetrans		   Load also the translations of product label and description
	 * @return array|mixed                 Data without useless information
	 *
	 * @throws RestException 401
	 * @throws RestException 403
	 * @throws RestException 404
	 */
	public function getProduct($id, $includestockdata = 0, $includesubproducts = false, $includeparentid = false, $includetrans = false)
	{
		return $this->_fetch($id, '', '', '', $includestockdata, $includesubproducts, $includeparentid, false, $includetrans);
	}

	/**
	 * Update product.
	 * Price will be updated by this API only if option is set on "One price per product". See other APIs for other price modes.
	 *
	 * @param  int   $id           Id of product to update
	 * @param  array $request_data Datas
	 * @return int
	 *
	 * @throws RestException 401
	 * @throws RestException 404
	 * 
	 * @url	PUT /products/{id}
	 */
	public function updateProduct($id, $request_data = null)
	{
		global $conf;

		// if (!DolibarrApiAccess::$user->rights->produit->creer) {
		// 	throw new RestException(401);
		// }

		$result = $this->product->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Product not found');
		}

		// if (!DolibarrApi::_checkAccessToResource('mac2syncapi/product', $this->product->id)) {
		// 	throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		// }

		$oldproduct = dol_clone($this->product, 0);

		foreach ($request_data as $field => $value) {
			if ($field == 'id') {
				continue;
			}
			if ($field == 'stock_reel') {
				throw new RestException(400, 'Stock reel cannot be updated here. Use the /stockmovements endpoint instead');
			}
			$this->product->$field = $value;
		}

		$updatetype = false;
		if ($this->product->type != $oldproduct->type && ($this->product->isProduct() || $this->product->isService())) {
			$updatetype = true;
		}

		$result = $this->product->update($id, DolibarrApiAccess::$user, 1, 'update', $updatetype);

		// If price mode is 1 price per product
		if ($result > 0 && !empty($conf->global->PRODUCT_PRICE_UNIQ)) {
			// We update price only if it was changed
			$pricemodified = false;
			if ($this->product->price_base_type != $oldproduct->price_base_type) {
				$pricemodified = true;
			} else {
				if ($this->product->tva_tx != $oldproduct->tva_tx) {
					$pricemodified = true;
				}
				if ($this->product->tva_npr != $oldproduct->tva_npr) {
					$pricemodified = true;
				}
				if ($this->product->default_vat_code != $oldproduct->default_vat_code) {
					$pricemodified = true;
				}

				if ($this->product->price_base_type == 'TTC') {
					if ($this->product->price_ttc != $oldproduct->price_ttc) {
						$pricemodified = true;
					}
					if ($this->product->price_min_ttc != $oldproduct->price_min_ttc) {
						$pricemodified = true;
					}
				} else {
					if ($this->product->price != $oldproduct->price) {
						$pricemodified = true;
					}
					if ($this->product->price_min != $oldproduct->price_min) {
						$pricemodified = true;
					}
				}
			}

			if ($pricemodified) {
				$newvat = $this->product->tva_tx;
				$newnpr = $this->product->tva_npr;
				$newvatsrccode = $this->product->default_vat_code;

				$newprice = $this->product->price;
				$newpricemin = $this->product->price_min;
				if ($this->product->price_base_type == 'TTC') {
					$newprice = $this->product->price_ttc;
					$newpricemin = $this->product->price_min_ttc;
				}

				$result = $this->product->updatePrice($newprice, $this->product->price_base_type, DolibarrApiAccess::$user, $newvat, $newpricemin, 0, $newnpr, 0, 0, array(), $newvatsrccode);
			}
		}

		if ($result <= 0) {
			throw new RestException(500, "Error updating product", array_merge(array($this->product->error), $this->product->errors));
		}

		return $this->getProduct($id);
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Clean sensible object datas
	 *
	 * @param   Object  $object     Object to clean
	 * @return  Object              Object with cleaned properties
	 */
	protected function _cleanObjectDatas($object)
	{
		// phpcs:enable
		$object = parent::_cleanObjectDatas($object);

		unset($object->rowid);
		unset($object->canvas);

		/*unset($object->name);
		unset($object->lastname);
		unset($object->firstname);
		unset($object->civility_id);
		unset($object->statut);
		unset($object->state);
		unset($object->state_id);
		unset($object->state_code);
		unset($object->region);
		unset($object->region_code);
		unset($object->country);
		unset($object->country_id);
		unset($object->country_code);
		unset($object->barcode_type);
		unset($object->barcode_type_code);
		unset($object->barcode_type_label);
		unset($object->barcode_type_coder);
		unset($object->total_ht);
		unset($object->total_tva);
		unset($object->total_localtax1);
		unset($object->total_localtax2);
		unset($object->total_ttc);
		unset($object->fk_account);
		unset($object->comments);
		unset($object->note);
		unset($object->mode_reglement_id);
		unset($object->cond_reglement_id);
		unset($object->cond_reglement);
		unset($object->shipping_method_id);
		unset($object->fk_incoterms);
		unset($object->label_incoterms);
		unset($object->location_incoterms);
		*/

		// If object has lines, remove $db property
		if (isset($object->lines) && is_array($object->lines) && count($object->lines) > 0) {
			$nboflines = count($object->lines);
			for ($i = 0; $i < $nboflines; $i++) {
				$this->_cleanObjectDatas($object->lines[$i]);

				unset($object->lines[$i]->lines);
				unset($object->lines[$i]->note);
			}
		}

		return $object;
	}

	/**
	 * Validate fields before create or update object
	 *
	 * @param	array		$data   Array of data to validate
	 * @return	array
	 *
	 * @throws	RestException
	 */
	private function _validate($data)
	{
		$shops = array();
		foreach ($this->shops->fields as $field => $propfield) {
			if (in_array($field, array('rowid', 'entity', 'date_creation', 'tms', 'fk_user_creat')) || $propfield['notnull'] != 1) continue; // Not a mandatory field
			if (!isset($data[$field]))
				throw new RestException(400, "$field field missing");
			$shops[$field] = $data[$field];
		}
		return $shops;
	}

	/**
	 * Update stock
	 *
	 * @param int   $id             Id of product to update
	 * @param int   $stock             Id of product to update
	 * @param array $request_data   Datas
	 * @return int
	 *
	 * @throws RestException
	 *
	 * @url	PUT stock/{id}/{stock}
	 */
	public function updateStock($id, $stock, $request_data = null)
	{
		/*if (!DolibarrApiAccess::$user->rights->mac2sync->write) {
			throw new RestException(401);
		}*/
		dol_include_once('/product/class/product.class.php');
		global $db;
		$sql = "UPDATE llx_product_stock SET reel='" . $stock . "' WHERE fk_product=" . $id;

		if ($db->query($sql) === TRUE) {
			echo "Record updated successfully";
		} else {
			echo "Error updating record: " . $db->error;
		}

		$product = new Product($db);
		$product->fetch($id);
		return $product->stock_reel;

		/*if (!$result) {
			throw new RestException(404, 'Shops not found');
		}*/
	}


	/////////  ORDERS   //////


	/**
	 * List orders
	 *
	 * Get a list of orders
	 *
	 * @param string	       $sortfield	        Sort field
	 * @param string	       $sortorder	        Sort order
	 * @param int		       $limit		        Limit for list
	 * @param int		       $page		        Page number
	 * @param string   	       $thirdparty_ids	    Thirdparty ids to filter orders of (example '1' or '1,2,3') {@pattern /^[0-9,]*$/i}
	 * @param string           $sqlfilters          Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
	 * @return  array                               Array of order objects
	 * 
	 * @url	GET /orders
	 *
	 * @throws RestException 404 Not found
	 * @throws RestException 503 Error
	 */
	public function getOrders($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $thirdparty_ids = '', $sqlfilters = '')
	{
		global $db, $conf;

		if (!DolibarrApiAccess::$user->rights->commande->lire) {
			throw new RestException(401);
		}

		$obj_ret = array();

		// case of external user, $thirdparty_ids param is ignored and replaced by user's socid
		$socids = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : $thirdparty_ids;

		// If the internal user must only see his customers, force searching by him
		$search_sale = 0;
		if (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socids) {
			$search_sale = DolibarrApiAccess::$user->id;
		}

		$sql = "SELECT t.rowid";
		if ((!DolibarrApiAccess::$user->rights->societe->client->voir && !$socids) || $search_sale > 0) {
			$sql .= ", sc.fk_soc, sc.fk_user"; // We need these fields in order to filter by sale (including the case where the user can only see his prospects)
		}
		$sql .= " FROM ".MAIN_DB_PREFIX."commande as t";

		if ((!DolibarrApiAccess::$user->rights->societe->client->voir && !$socids) || $search_sale > 0) {
			$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc"; // We need this table joined to the select in order to filter by sale
		}

		// $sql .= ' WHERE t.entity IN ('.getEntity('commande').')';
		if ((!DolibarrApiAccess::$user->rights->societe->client->voir && !$socids) || $search_sale > 0) {
			$sql .= " WHERE t.fk_soc = sc.fk_soc";
		}
		if ($socids) {
			$sql .= " AND t.fk_soc IN (".$this->db->sanitize($socids).")";
		}
		if ($search_sale > 0) {
			$sql .= " AND t.rowid = sc.fk_soc"; // Join for the needed table to filter by sale
		}
		// Insert sale filter
		if ($search_sale > 0) {
			$sql .= " AND sc.fk_user = ".((int) $search_sale);
		}
		// Add sql filters
		if ($sqlfilters) {
			$errormessage = '';
			if (!DolibarrApi::_checkFilters($sqlfilters, $errormessage)) {
				throw new RestException(503, 'Error when validating parameter sqlfilters -> '.$errormessage);
			}
			$regexstring = '\(([^:\'\(\)]+:[^:\'\(\)]+:[^\(\)]+)\)';
			$sql .= " Where (".preg_replace_callback('/'.$regexstring.'/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters).")";
		}
		$sql .= $this->db->order($sortfield, $sortorder);
		if ($limit) {
			if ($page < 0) {
				$page = 0;
			}
			$offset = $limit * $page;

			$sql .= $this->db->plimit($limit + 1, $offset);
		}
		dol_syslog("API Rest request");
		$result = $this->db->query($sql);

		if ($result) {
			$num = $this->db->num_rows($result);
			$min = min($num, ($limit <= 0 ? $num : $limit));
			$i = 0;
			while ($i < $min) {
				$obj = $this->db->fetch_object($result);
				$commande_static = new Commande($this->db);
				if ($commande_static->fetch($obj->rowid)) {
					// Add external contacts ids
					$commande_static->contacts_ids = $commande_static->liste_contact(-1, 'external', 1);
					$obj_ret[] = $this->_cleanObjectDatas($commande_static);
				}
				$i++;
			}
		} else {
			throw new RestException(503, 'Error when retrieve commande list : '.$this->db->lasterror());
		}
		if (!count($obj_ret)) {
			throw new RestException(404, 'No order found');
		}
		return $obj_ret;
	}

	/**
	 * Create a sale order
	 *
	 * Exemple: { "socid": 2, "date": 1595196000, "type": 0, "lines": [{ "fk_product": 2, "qty": 1 }] }
	 * 
	 * @param int  	$entity         Id of entity where order is created
	 * @param array $request_data   Request data
	 * @return  int     ID of order
	 * 
	 * @url	POST /orders/{entity}
	 */
	public function createOrders($entity, $request_data = null)
	{
		if (!DolibarrApiAccess::$user->rights->commande->creer) {
			throw new RestException(401, "Insuffisant rights");
		}

		// Check mandatory fields
		$result = $this->_validateOrder($request_data);

		foreach ($request_data as $field => $value) {
			$this->commande->$field = $value;
		}
		/*if (isset($request_data["lines"])) {
		  $lines = array();
		  foreach ($request_data["lines"] as $line) {
			array_push($lines, (object) $line);
		  }
		  $this->commande->lines = $lines;
		}*/

		if ($this->commande->create(DolibarrApiAccess::$user, $entity) < 0) {
			throw new RestException(500, "Error creating order", array_merge(array($this->commande->error), $this->commande->errors));
		}

		return $this->commande->id;
	}


	/**
	 * Add a line to given order
	 *
	 * @param int   $id             Id of order to update
	 * @param array $request_data   OrderLine data
	 *
	 * @url	POST {id}/lines
	 *
	 * @return int
	 */
	public function createOrderLine($id, $request_data = null)
	{
		if (!DolibarrApiAccess::$user->rights->commande->creer) {
			throw new RestException(401);
		}
		$result = $this->commande->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Order not found');
		}

		if (!DolibarrApi::_checkAccessToResource('commande', $this->commande->id)) {
			throw new RestException(401, 'Access not allowed for login ' . DolibarrApiAccess::$user->login);
		}

		$request_data = (object) $request_data;

		$request_data->desc = checkVal($request_data->desc, 'restricthtml');
		$request_data->label = checkVal($request_data->label);

		$updateRes = $this->commande->addline(
			$request_data->desc,
			$request_data->subprice,
			$request_data->qty,
			$request_data->tva_tx,
			$request_data->localtax1_tx,
			$request_data->localtax2_tx,
			$request_data->fk_product,
			$request_data->remise_percent,
			$request_data->info_bits,
			$request_data->fk_remise_except,
			$request_data->price_base_type ? $request_data->price_base_type : 'HT',
			$request_data->subprice,
			$request_data->date_start,
			$request_data->date_end,
			$request_data->product_type,
			$request_data->rang,
			$request_data->special_code,
			$request_data->fk_parent_line,
			$request_data->fk_fournprice,
			$request_data->pa_ht,
			$request_data->label,
			$request_data->array_options,
			$request_data->fk_unit,
			$request_data->origin,
			$request_data->origin_id,
			$request_data->multicurrency_subprice,
			$request_data->ref_ext
		);

		if ($updateRes > 0) {
			return $updateRes;
		} else {
			throw new RestException(400, $this->commande->error);
		}
	}

	/**
	 * Validate an order
	 *
	 * If you get a bad value for param notrigger check, provide this in body
	 * {
	 *   "idwarehouse": 0,
	 *   "notrigger": 0
	 * }
	 *
	 * @param   string 	$refClient      Order ref_client
	 * @param   int 	$idwarehouse    Warehouse ID
	 * @param   int 	$notrigger      1=Does not execute triggers, 0= execute triggers
	 *
	 * @url POST    orders/{refClient}/validate
	 *
	 * @throws RestException 304
	 * @throws RestException 401
	 * @throws RestException 404
	 * @throws RestException 500 System error
	 *
	 * @return  array
	 */
	public function validateOrders($refClient, $idwarehouse = 0, $notrigger = 0)
	{
		// if (!DolibarrApiAccess::$user->rights->commande->creer) {
		// 	throw new RestException(401);
		// }
		$sql = "SELECT * FROM " . MAIN_DB_PREFIX . "commande WHERE ref_client = '$refClient'";
		$res = $this->db->query($sql);
		$num = $res->num_rows;

		$i = 0;
		while ($i < $num) {
			$obj = $this->db->fetch_object($res);
			$result = $this->commande->fetch($obj->rowid);
			if (!$result) {
				throw new RestException(404, 'Order not found');
			}

			$result = $this->commande->fetch_thirdparty(); // do not check result, as failure is not fatal (used only for mail notification substitutes)

			// if (!DolibarrApi::_checkAccessToResource('commande', $this->commande->id)) {
			// 	throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
			// }

			$result = $this->commande->valid(DolibarrApiAccess::$user, $idwarehouse, $notrigger);
			if ($result == 0) {
				throw new RestException(304, 'Error nothing done. May be object is already validated');
			}
			if ($result < 0) {
				throw new RestException(500, 'Error when validating Order: ' . $this->commande->error);
			}
			$result = $this->commande->fetch($refClient);

			$this->commande->fetchObjectLinked();
			$i++;
			// return $this->_cleanObjectDatas_Order($this->commande);
		}
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Clean sensible object datas
	 *
	 * @param   Object  $object     Object to clean
	 * @return  Object              Object with cleaned properties
	 */
	protected function _cleanObjectDatas_Order($object)
	{
		// phpcs:enable
		$object = parent::_cleanObjectDatas($object);

		unset($object->note);
		unset($object->address);
		unset($object->barcode_type);
		unset($object->barcode_type_code);
		unset($object->barcode_type_label);
		unset($object->barcode_type_coder);

		return $object;
	}


	/**
	 * Validate fields before create or update object
	 *
	 * @param   array           $data   Array with data to verify
	 * @return  array
	 * @throws  RestException
	 */
	private function _validateOrder($data)
	{
		$commande = array();
		foreach (Mac2SyncApi::$FIELDS as $field) {
			if (!isset($data[$field])) {
				throw new RestException(400, $field . " field missing");
			}
			$commande[$field] = $data[$field];
		}
		return $commande;
	}

	/**
	 * Create the shipment of an order
	 *
	 * @param int   $orderId      		Id of the order
	 * @param int   $entity        		Id of the entity
	 * @param int	$warehouse_id 	Id of a warehouse
	 *
	 * @url     POST /shipment/createfromorder/{orderId}/{entity}/{warehouse_id}
	 *
	 * @return int
	 *
	 * @throws RestException 401
	 * @throws RestException 404
	 * @throws RestException 500 System error
	 */
	public function createOrderShipment($orderId, $warehouse_id,$entity)
	{
		require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
		if (!DolibarrApiAccess::$user->rights->expedition->creer) {
			throw new RestException(401);
		}
		if ($warehouse_id <= 0) {
			throw new RestException(404, 'Warehouse not found');
		}
		$result = $this->commande->fetch($orderId);
		if (!$result) {
			throw new RestException(404, 'Order not found');
		}
		
		$this->shipment ;
		$this->shipment->socid = $this->commande->socid;
		$result = $this->shipment->create(DolibarrApiAccess::$user,$entity);
		if ($result <= 0) {
			throw new RestException(500, 'Error on creating expedition :'.$this->db->lasterror());
		}
		foreach ($this->commande->lines as $line) {
			$result = $this->shipment->create_line($warehouse_id, $line->id, $line->qty);
			if ($result <= 0) {
				throw new RestException(500, 'Error on creating expedition lines:'.$this->db->lasterror());
			}
		}
		$res = $this->shipment->add_object_linked('commande',$orderId);
		return $shipment->id;
	}

	/////////  INVOICES  //////////
	
	/**
	 * Create an invoice using an existing order.
	 *
	 *
	 * @param int   $orderid     Id of the order
	 * @param int   $entity      Id of the entity
	 *
	 * @url     POST /createfromorder/{orderid}/{entity}
	 *
	 * @return int
	 * @throws RestException 400
	 * @throws RestException 401
	 * @throws RestException 404
	 * @throws RestException 405
	 */
	public function createInvoiceFromOrder($orderid, $entity)
	{

		require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';

		if (!DolibarrApiAccess::$user->rights->commande->lire) {
			throw new RestException(401);
		}
		if (!DolibarrApiAccess::$user->rights->facture->creer) {
			throw new RestException(401);
		}
		if (empty($orderid)) {
			throw new RestException(400, 'Order ID is mandatory');
		}

		$order = new Commande($this->db);
		$result = $order->fetch($orderid);
		if (!$result) {
			throw new RestException(404, 'Order not found');
		}

		$result = $this->invoice->createFromOrder($order, DolibarrApiAccess::$user, $entity);
		if ($result < 0) {
			throw new RestException(405, $this->invoice->error);
		}
		$this->invoice->fetchObjectLinked();
		return $this->_cleanObjectDataInvoice($this->invoice);
	}


	/**
	 * Validate an order
	 *
	 * If you get a bad value for param notrigger check, provide this in body
	 * {
	 *   "idwarehouse": 0,
	 *   "notrigger": 0
	 * }
	 *
	 * @param   string 	$refClient      Invoice ref_client
	 * @param   int 	$idwarehouse    Warehouse ID
	 * @param   int 	$notrigger      1=Does not execute triggers, 0= execute triggers
	 *
	 * @url POST    /invoices/{refClient}/validate
	 *
	 * @throws RestException 304
	 * @throws RestException 401
	 * @throws RestException 404
	 * @throws RestException 500 System error
	 *
	 * @return  array
	 */
	public function validateInvoices($refClient, $idwarehouse = 0, $notrigger = 0)
	{
		// if (!DolibarrApiAccess::$user->rights->commande->creer) {
		// 	throw new RestException(401);
		// }
		$sql = "SELECT * FROM " . MAIN_DB_PREFIX . "facture WHERE ref_client = '$refClient'";
		$res = $this->db->query($sql);
		$num = $res->num_rows;

		$i = 0;
		while ($i < $num) {
			$obj = $this->db->fetch_object($res);
			$result = $this->invoice->fetch($obj->rowid);
			if (!$result) {
				throw new RestException(404, 'Order not found');
			}

			$result = $this->invoice->fetch_thirdparty(); // do not check result, as failure is not fatal (used only for mail notification substitutes)

			// if (!DolibarrApi::_checkAccessToResource('commande', $this->commande->id)) {
			// 	throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
			// }

			$result = $this->invoice->validate(DolibarrApiAccess::$user, $idwarehouse, $notrigger);
			if ($result == 0) {
				throw new RestException(304, 'Error nothing done. May be object is already validated');
			}
			if ($result < 0) {
				throw new RestException(500, 'Error when validating Order: ' . $this->commande->error);
			}
			$result = $this->invoice->fetch($refClient);

			// $this->invoice->fetchObjectLinked();
			$i++;
			// return $this->_cleanObjectDatas_Order($this->commande);
		}
	}
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Clean sensible object datas
	 *
	 * @param   Object  $object     Object to clean
	 * @return  Object              Object with cleaned properties
	 */
	protected function _cleanObjectDataInvoice($object)
	{
		// phpcs:enable
		$object = parent::_cleanObjectDatas($object);

		unset($object->note);
		unset($object->address);
		unset($object->barcode_type);
		unset($object->barcode_type_code);
		unset($object->barcode_type_label);
		unset($object->barcode_type_coder);

		return $object;
	}

	//////  CLIENTS		/////////


	/**
	 * Create thirdparty object
	 *
	 * @param int  	$entity         Id of entity where client is created
	 * @param array $request_data   Request datas
	 * @return int  ID of thirdparty
	 * 
	 * @url	POST /thirdparties/{entity}
	 */
	public function createClient($entity,$request_data = null)
	{
		if (!DolibarrApiAccess::$user->rights->societe->creer) {
			throw new RestException(401);
		}
		// Check mandatory fields
		$result = $this->_validateClient($request_data);

		foreach ($request_data as $field => $value) {
			$this->company->$field = $value;
		}
		if ($this->company->create(DolibarrApiAccess::$user,$entity) < 0) {
			throw new RestException(500, 'Error creating thirdparty', array_merge(array($this->company->error), $this->company->errors));
		}

		return $this->company->id;
	}

	/**
	 * Validate fields before create or update object
	 *
	 * @param array $data   Datas to validate
	 * @return array
	 *
	 * @throws RestException
	 */
	private function _validateClient($data)
	{
		$thirdparty = array();
		foreach (Thirdparties::$FIELDS as $field) {
			if (!isset($data[$field])) {
				throw new RestException(400, "$field field missing");
			}
			$thirdparty[$field] = $data[$field];
		}
		return $thirdparty;
	}

	/**
	 * Get properties of a thirdparty object by email.
	 *
	 * Return an array with thirdparty informations
	 *
	 * @param string    $email  Email of third party to load
	 * @return array|mixed Cleaned Societe object
	 *
	 * @url  GET thirdparties/email/{email}
	 *
	 * @throws RestException
	 */
	public function getByEmail($email)
	{
		return $this->_fetchClient('', '', '', '', '', '', '', '', '', '', $email);
	}

	/**
	 * Fetch properties of a thirdparty object.
	 *
	 * Return an array with thirdparty informations
	 *
	 * @param    int	$rowid      Id of third party to load (Use 0 to get a specimen record, use null to use other search criterias)
	 * @param    string	$ref        Reference of third party, name (Warning, this can return several records)
	 * @param    string	$ref_ext    External reference of third party (Warning, this information is a free field not provided by Dolibarr)
	 * @param    string	$barcode    Barcode of third party to load
	 * @param    string	$idprof1		Prof id 1 of third party (Warning, this can return several records)
	 * @param    string	$idprof2		Prof id 2 of third party (Warning, this can return several records)
	 * @param    string	$idprof3		Prof id 3 of third party (Warning, this can return several records)
	 * @param    string	$idprof4		Prof id 4 of third party (Warning, this can return several records)
	 * @param    string	$idprof5		Prof id 5 of third party (Warning, this can return several records)
	 * @param    string	$idprof6		Prof id 6 of third party (Warning, this can return several records)
	 * @param    string	$email   		Email of third party (Warning, this can return several records)
	 * @param    string	$ref_alias  Name_alias of third party (Warning, this can return several records)
	 * @return array|mixed cleaned Societe object
	 *
	 * @throws RestException
	 */
	private function _fetchClient($rowid, $ref = '', $ref_ext = '', $barcode = '', $idprof1 = '', $idprof2 = '', $idprof3 = '', $idprof4 = '', $idprof5 = '', $idprof6 = '', $email = '', $ref_alias = '')
	{
		global $conf;

		if (!DolibarrApiAccess::$user->rights->societe->lire) {
			throw new RestException(401);
		}

		if ($rowid === 0) {
			$result = $this->company->initAsSpecimen();
		} else {
			$result = $this->company->fetch($rowid, $ref, $ref_ext, $barcode, $idprof1, $idprof2, $idprof3, $idprof4, $idprof5, $idprof6, $email, $ref_alias);
		}
		if (!$result) {
			throw new RestException(404, 'Thirdparty not found');
		}

		// if (!DolibarrApi::_checkAccessToResource('societe', $this->company->id)) {
		// 	throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		// }

		if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
			$filterabsolutediscount = "fk_facture_source IS NULL"; // If we want deposit to be substracted to payments only and not to total of final invoice
			$filtercreditnote = "fk_facture_source IS NOT NULL"; // If we want deposit to be substracted to payments only and not to total of final invoice
		} else {
			$filterabsolutediscount = "fk_facture_source IS NULL OR (description LIKE '(DEPOSIT)%' AND description NOT LIKE '(EXCESS RECEIVED)%')";
			$filtercreditnote = "fk_facture_source IS NOT NULL AND (description NOT LIKE '(DEPOSIT)%' OR description LIKE '(EXCESS RECEIVED)%')";
		}

		$absolute_discount = $this->company->getAvailableDiscounts('', $filterabsolutediscount);
		$absolute_creditnote = $this->company->getAvailableDiscounts('', $filtercreditnote);
		$this->company->absolute_discount = price2num($absolute_discount, 'MT');
		$this->company->absolute_creditnote = price2num($absolute_creditnote, 'MT');

		return $this->_cleanObjectDatas($this->company);
	}


		// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 * Clean sensible object datas
	 *
	 * @param   Object  $object     Object to clean
	 * @return  array|mixed         Object with cleaned properties
	 */
	protected function _cleanObjectDatasClients($object)
	{
		// phpcs:enable
		$object = parent::_cleanObjectDatas($object);

		unset($object->nom); // ->name already defined and nom deprecated
		unset($object->name_bis); // ->name_alias already defined
		unset($object->note); // ->note_private and note_public already defined
		unset($object->departement);
		unset($object->departement_code);
		unset($object->pays);
		unset($object->particulier);
		unset($object->prefix_comm);

		unset($object->commercial_id); // This property is used in create/update only. It does not exists in read mode because there is several sales representatives.

		unset($object->total_ht);
		unset($object->total_tva);
		unset($object->total_localtax1);
		unset($object->total_localtax2);
		unset($object->total_ttc);

		unset($object->lines);
		unset($object->thirdparty);

		unset($object->fk_delivery_address); // deprecated feature

		unset($object->skype);
		unset($object->twitter);
		unset($object->facebook);
		unset($object->linkedin);
		unset($object->instagram);
		unset($object->snapchat);
		unset($object->googleplus);
		unset($object->youtube);
		unset($object->whatsapp);

		return $object;
	}

	//////  EXPEDITION   //////////

	
	//  /**
	//  * Create a shipment using an existing order.
	//  *
	//  * @param int   $orderId       Id of the order
	//  * @param int   $entity      Id of the entity
	//  * 
	//  * @url     POST /shipment/createfromorder/{orderId}/{entity}
	//  *
	//  * @return int
	//  * @throws RestException 400
	//  * @throws RestException 401
	//  * @throws RestException 404
	//  * @throws RestException 405
	//  */
	// public function createShipmentFromOrder($orderId,$entity)
	// {

	// 	require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';

	// 	if(! DolibarrApiAccess::$user->rights->expedition->lire) {
	// 		throw new RestException(401);
	// 	}
	// 	if(! DolibarrApiAccess::$user->rights->expedition->creer) {
	// 		throw new RestException(401);
	// 	}
	// 	if(empty($orderId)) {
	// 		throw new RestException(400, 'Order ID is mandatory');
	// 	}

	// 	$order = new Commande($this->db);
	// 	$result = $order->fetch($orderId);

	// 	if( ! $result ) {
	// 		throw new RestException(404, 'Order not found');
	// 	}

	// 	$result = $this->shipment->createFromOrder($order, DolibarrApiAccess::$user, $entity);

	// 	if( $result < 0) {
	// 		throw new RestException(405, $this->shipment->error);
	// 	}
	// 	$this->shipment->fetchObjectLinked();
	// 	return $this->_cleanObjectDatas($this->shipment);
	// }

}


