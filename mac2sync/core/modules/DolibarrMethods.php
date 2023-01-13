<?php

/****** Fichier de Méthodes Dolibarr *********/
/****** MAC2 WEBINTELLIGENCE **************/


//Récupérer ...
function isProductVariantByRef($ref)
{
    require_once(DOL_DOCUMENT_ROOT . '/product/class/product.class.php');
    global $db;
    $sql = "SELECT rowid FROM llx_product WHERE ref='" . $ref . "'";
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $product_id = $row['rowid'];
        }
    }
    $product = new Product($db);
    $product->fetch($product_id);
    return $product->isVariant();
}

//Récupérer ...
function getProductIdByRef($ref)
{
    require_once(DOL_DOCUMENT_ROOT . '/product/class/product.class.php');
    global $db;
    $sql = "SELECT rowid FROM llx_product WHERE ref='" . $ref . "'";
    $result = $db->query($sql);
    if ($resul) {
        while ($row = $result->fetch_assoc()) {
            $product_id = $row['rowid'];
        }
    }
    return $product_id;
}

//
function getProductParentId($product_id)
{
    global $db;
    //Récupération de l'ID du produit parent
    $sql = "SELECT fk_product_parent FROM " . MAIN_DB_PREFIX . "product_attribute_combination WHERE fk_product_child = " . $product_id;
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $id_parent = $row['fk_product_parent'];
            //row['variation_price] POUR UPDATE LE PRIX
        }
    }
    return $id_parent;
}


function getProductParentRefByParentId($id_parent)
{
    global $db;
    $sql = "SELECT ref FROM " . MAIN_DB_PREFIX . "product WHERE rowid = " . $id_parent;
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $ref_parent = $row['ref'];
            //row['variation_price] POUR UPDATE LE PRIX
        }
    }
    return $ref_parent;
}


function getCustomerIdByEmail($email)
{
    global $db;
    $sql = "SELECT rowid FROM llx_societe WHERE email='" . $email . "'";
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $customer_id = $row['rowid'];
        }
    }
    return $customer_id;
}

function getShopIdByPrestaInfos($prestashop_url, $prestashop_api_key)
{
    global $db;
    $sql = "SELECT * FROM llx_mac2sync_shops WHERE prestashop_ws_key='" . $prestashop_api_key . "' AND prestashop_url='" . $prestashop_url . "'";
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            return $row['rowid'];
        }
    }
}

function getProductCategoryIdByName($name)
{
    global $db;
    $sql = "SELECT * FROM llx_categorie WHERE label='" . $name . "'";
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            return $row['rowid'];
        }
    } else {
        return FALSE;
    }
}

function changeClientImportKey($dolibarr_client_id, $prestashop_client_id, $shop_id)
{
    global $db;
    $import_key = "PS-" . $shop_id . "-" . $prestashop_client_id;
    $sql = "UPDATE llx_societe SET import_key='" . $import_key . "' WHERE rowid=" . $dolibarr_client_id;
    if ($db->query($sql) === TRUE) {
        //echo "Record updated successfully";
    } else {
        //echo "Error updating record: " . $db->error;
    }
}

function changeCategoryImportKey($dolibarr_cate_id, $prestashop_cate_id, $shop_id)
{
    global $db;
    $import_key = "PS-" . $shop_id . "-" . $prestashop_cate_id;
    $sql = "UPDATE llx_categorie SET import_key='" . $import_key . "' WHERE rowid=" . $dolibarr_cate_id;
    if ($db->query($sql) === TRUE) {
        //echo "Record updated successfully";
    } else {
        //echo "Error updating record: " . $db->error;
    }
}

function getObjectIdByImportKey($import_key)
{
    if (strpos(substr($import_key, -4), "-") === false) {
        //echo "id with 4 c"; 
        $object_id = substr($import_key, -4);
    } else {
        if (strpos(substr($import_key, -3), "-") === false) {
            //echo "id with 3 c"; 
            $object_id = substr($import_key, -3);
        } else {
            if (strpos(substr($import_key, -2), "-") === false) {
                //echo "id with 2 c"; 
                $object_id = substr($import_key, -2);
            } else {
                if (strpos(substr($import_key, -1), "-") === false) {
                    //echo "id with 1 c"; 
                    $object_id = substr($import_key, -1);
                }
            }
        }
    }
    return $object_id;
}

function productGetCategoriesById($product_id)
{
    global $db;
    $sql = "SELECT fk_categorie FROM llx_categorie_product WHERE fk_product=" . $product_id;
    $categories = array();
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            array_push($categories, $row['fk_categorie']);
        }
    }
    return $categories;
}

function getCategorieImportKeyById($categorie_id)
{
    global $db;
    $sql = "SELECT * FROM llx_categorie WHERE rowid=" . $categorie_id;
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $import_key = $row['import_key'];
            return $import_key;
        }
    }
    return 0;
}

/*function getCategorieRefExtById($categorie_id){
    global $db;
     $sql = "SELECT * FROM llx_categorie WHERE rowid=" . $categorie_id;
     $result = $db->query($sql);
                            if($result->num_rows > 0){
                                while($row = $result->fetch_assoc()) { 
                                    $ref_ext = $row['ref_ext'];
                                    return $ref_ext;
                                }
                            }
    return FALSE;
}*/

/*function getDolibarrProductCategoryIdByPrestashopId($prestashop_categorie_id, $shop_id){
    global $db;
    $import_key = "MAC2SYNC-".$shop_id;
     $sql = "SELECT * FROM llx_categorie WHERE ref_ext=" . $prestashop_categorie_id. " AND import_key='".$import_key."'";
     $result = $db->query($sql);
                            if($result->num_rows > 0){
                                while($row = $result->fetch_assoc()) { 
                                    $id = $row['rowid'];
                                    return (int) $id;
                                }
                            }
    return FALSE;
}*/

/*function getCategorieIdByRefExt($categorie_id){
    global $db;
     $sql = "SELECT * FROM llx_categorie WHERE ref_ext=" . $categorie_id;
     $result = $db->query($sql);
                            if($result->num_rows > 0){
                                while($row = $result->fetch_assoc()) { 
                                    $id = $row['rowid'];
                                    return $id;
                                }
                            }
    return FALSE;
}*/


function getPrestashopLink($type, $dolibarr_id, $shop_id)
{
    //mac2sync(" :::: function : getPrestashopLink ($type, $dolibarr_id, SHOP:$shop_id) :::: )
    global $db;
    $sql = "SELECT * FROM llx_mac2sync_links WHERE ";
    $sql .= "type = '$type' AND ";
    $sql .= "dolibarr_id = '" . $dolibarr_id . "' AND ";
    $sql .= "shop_id = '" . $shop_id . "'";
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $id = $row['prestashop_id'];
            return $id;
        }
    }
    return FALSE;
}

function getDolibarrLink($type, $prestashop_id, $shop_id)
{
    global $db;
    $sql = "SELECT * FROM llx_mac2sync_links WHERE ";
    $sql .= "type = '$type' AND ";
    $sql .= "prestashop_id = '" . $prestashop_id . "' AND ";
    $sql .= "shop_id = '" . $shop_id . "'";
    $result = $db->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $id = $row['dolibarr_id'];
            return $id;
        }
    }
    return FALSE;
}

function getDolibarrCategoryArboText($categorie)
{
    require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
    global $db;
    //Initialisation d'une variable de fin de boucle
    $to_the_root = FALSE;
    $arbo = "";

    //Renommage de la variable afin de mieux l'identifier
    $categorie_to_show = $categorie;
    $categorie_to_show_parent_id = $categorie['fk_parent'];
    while ($to_the_root != TRUE) {

        if ($categorie_to_show_parent_id != 0) {
            $categorie_parent = new Categorie($db);
            $categorie_parent->fetch($categorie_to_show_parent_id);
            $arbo = $categorie_parent->label . " -> " . $arbo;
            $categorie_to_show_parent_id = (int) $categorie_parent->fk_parent;
        } else {
            print "Racine -> ";
            $to_the_root = TRUE;
        }
    }

    print $arbo;
    print $categorie['label'];
}

function getPrestashopCategoryArboText($categorie, $prestashop_url, $prestashop_api_key)
{
    include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/PSWebServicesMethods.php';
    //Initialisation d'une variable de fin de boucle
    $to_the_root = FALSE;
    $arbo = "";

    //Renommage de la variable afin de mieux l'identifier
    $categorie_to_show = $categorie;
    $categorie_to_show_parent_id = (int) $categorie_to_show->id_parent;

    while ($to_the_root != TRUE) {

        if ($categorie_to_show_parent_id == 0 && $categorie_to_show->id != 1) {
            print "Racine -> " . $category->name->language;
            $to_the_root = TRUE;
        } else {
            $categorie_to_show_parent = getCategorie((int) $categorie_to_show_parent_id, $prestashop_api_key, $prestashop_url);
            $categorie_to_show_parent_id_parent = (int) $categorie_to_show_parent->categories->category->id_parent;
            $categorie_to_show_parent_name = (string) $categorie_to_show_parent->categories->category->name->language;
            if ($categorie_to_show_parent_id == 0) {
                $arbo = $categorie_to_show_parent_name;
            } else {
                $arbo = $categorie_to_show_parent_name . " -> " . $arbo;
            }

            if ($categorie_to_show_parent_id_parent != 0) {
                $categorie_to_show_parent_id =  $categorie_to_show_parent_id_parent;
            } else {
                $to_the_root = TRUE;
            }
        }
    }

    return $arbo . $categorie->name->language;
}
