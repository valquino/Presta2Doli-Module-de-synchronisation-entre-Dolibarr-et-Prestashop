-- Copyright (C) ---Put here your own copyright and developer email---
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


CREATE TABLE llx_mac2sync_shops(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128) NOT NULL, 
	import_key varchar(14), 
	status smallint NOT NULL, 
	prestashop_ws_key varchar(128), 
	prestashop_url varchar(255), 
	DEL_PRODUCT_IN_PRESTA smallint, 
	CREATE_PRODUCT_IN_PRESTA smallint, 
	UPD_PRODUCT_IN_PRESTA integer,
	DEL_CATEGORY_IN_PRESTA smallint,
	CREATE_CATEGORY_IN_PRESTA smallint,
	UPD_CATEGORY_IN_PRESTA integer,
	DEL_CLIENT_IN_PRESTA smallint,
	CREATE_CLIENT_IN_PRESTA smallint,
	UPD_CLIENT_IN_PRESTA integer,
	SYNC_STOCK_IN_PRESTA integer,
	SYNC_VARIANT_IN_PRESTA integer,
	ENTITY_CONNEXION integer,
	SYNC_MULTIPRICES_IN_PRESTA integer,
	PRINCIPAL_LEVEL_MULTIPRICE integer
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
