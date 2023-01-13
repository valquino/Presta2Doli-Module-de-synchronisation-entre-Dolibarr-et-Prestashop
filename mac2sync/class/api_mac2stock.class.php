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

use Luracast\Restler\RestException;

dol_include_once('/product/class/product.class.php');



/**
 * \file    mac2sync/class/api_mac2stock.class.php
 * \ingroup mac2sync
 * \brief   File for API management of stock.
 */

/**
 * API class for mac2sync stock
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Mac2StockApi extends DolibarrApi
{
	/**
	 * @var Product $product {@type Product}
	 */
	public $product;

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
		$this->product = new Product($this->db);
	}

	/**
	 * Update stock of product
	 *
	 * Return an array with shops informations
	 *
	 * @param 	int 	$id ID of shops
	 * @return 	array|mixed data without useless information
	 *
	 * @url	GET update/{id}
	 *
	 * @throws RestException 401 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function updateStock($id)
	{
		if (!DolibarrApiAccess::$user->rights->mac2sync->read) {
			throw new RestException(401);
		}

		$result = $this->product->fetch($id);
		if (!$result) {
			throw new RestException(404, 'Product not found');
		}

		/*if (!DolibarrApi::_checkAccessToResource('shops', $this->shops->id, 'mac2sync_shops')) {
			throw new RestException(401, 'Access to instance id='.$this->shops->id.' of object not allowed for login '.DolibarrApiAccess::$user->login);
		}*/

		return true;
		return $this->_cleanObjectDatas($this->shops);
	}



}
