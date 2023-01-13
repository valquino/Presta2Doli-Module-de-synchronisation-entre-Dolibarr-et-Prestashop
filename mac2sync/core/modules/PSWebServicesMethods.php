<?php

//Nombre d'essai avant abandon
/*$NUM_OF_ATTEMPTS = 3;
$attempts = 0;

do {
    try
    {
        executeCode(); //Code à éxécuter
    } catch (Exception $e) {
        $attempts++;
        sleep(1);
        continue;
    }
    break;
} while($attempts < $NUM_OF_ATTEMPTS);*/

include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/DolibarrMethods.php');
include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

//Vérification du paramétrage du Shop
include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/class/shops.class.php';

class PSWebServicesMethods {


    public function createProduct($ref, $label, $price, $description, $entity, $prestashop_api_key, $prestashop_url, $prestashop_category_id = 1)
    {
        global $db;
        global $conf;

        $shop_id = getShopIdByPrestaInfos($prestashop_url, $prestashop_api_key);
        $shop = new Shops($db);
        $shop->fetch($shop_id);

        if ($shop->ENTITY_CONNEXION == 0 || in_array($conf->entity,explode(',',$shop->ENTITY_CONNEXION))) {

            mac2sync_log("PSWebServices[createProduct] :: " . $ref);

            // Verification de l'existence du produit
            try {
                $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
                $xml = $webService->get([
                    'resource' => 'products',
                    'filter[reference]' => $ref,
                    'display' => 'full'
                ]);

            } catch (PrestaShopWebserviceException $e) {
                mac2sync_log('Erreur récupération du produit : ' . $ref . ' : ' . $e->getMessage());
            }

            // Si produit existant, on UPDATE
            $products = $xml->products->children();
            // var_dump($products);
            // exit;
            if (count($products) > 0) {
                mac2sync_log("PSWebServices[createProduct] :: Produit déjà existant (Prestashop) : " . $ref);
                $product_id = getProductIdByRef($ref);
                $product = new Product($db);
                $product->fetch($product_id);
                $categories_id = array($prestashop_category_id);
                updateProduct($ref, $prestashop_api_key, $prestashop_url, $product, $categories_id);
                return true;
            }

            //Si le produit n'existe pas
            //Est-il un variant ?
            if (isProductVariantByRef($ref)) {
                if ($shop->SYNC_VARIANT_IN_PRESTA == '1') {
                    //Synchroniser les variants en déclinaison
                    createProductDeclinaison($ref, $price, $prestashop_api_key, $prestashop_url);
                    return true;
                } elseif ($shop->SYNC_VARIANT_IN_PRESTA == '2') {
                    //Synchroniser les variants en produit

                } elseif ($shop->SYNC_VARIANT_IN_PRESTA == '0' || $shop->SYNC_VARIANT_IN_PRESTA == NULL) {
                    //Ne pas synchroniser les variants
                    return true;
                }
            }


            if ($description == NULL) {
                $description = " ";
            }

            try {
                //Accès au WebService
                $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
                $blankXml = $webService->get(['url' => $prestashop_url . '/api/products?schema=blank']);
                mac2sync_log('[BLANK XML]' . json_encode($blankXml));
                $products = $blankXml->product->children();
                $products->reference = $ref;
                $products->name = retrieveMultilang($label,$products->name);
                $products->dolibarr_entity = $entity;
                $products->price = (float) $price;
                $products->active = 1;
                $products->description = $description;
                $products->state = 1;
                $products->id_shop_default = 1;
                $products->id_category_default = $prestashop_category_id;
                unset($products->manufacturer_name);
                unset($products->quantity);
                unset($products->id_shop_default);
                unset($products->id_default_image);
                unset($products->associations);
                unset($products->id_default_combination);
                unset($products->position_in_category);
                unset($products->type);
                unset($products->pack_stock_type);
                unset($products->date_add);
                unset($products->date_upd);

                $createdXml = $webService->add([
                    'resource' => 'products',
                    'postXml' => $blankXml->asXML()
                ]);

                $newProductFields = $createdXml->product->children();
                mac2sync_log("PSWebServices[createProduct][CCE]. " . $ref . " ::" . json_encode($products));
                $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
                $xml = $webService->get([
                    'resource' => 'products',
                    'filter[reference]' => $ref,
                    'display' => 'full'
                ]);

                // $xml->products->product->id
                mac2sync_log("PSWebServices[createProduct] :: Produit créé dans Prestashop: " . $ref);

                $idpresta = $xml->products->product->id;
                $sql2 = "SELECT rowid FROM  " . MAIN_DB_PREFIX . "product WHERE ref = '$ref'";
                $res = $db->query($sql2);
                $idDoli = $db->fetch_row($res);
                $idDoli = $idDoli[0];
                mac2sync_log('ID-DOLI' . $idDoli);
                $sql = "INSERT INTO`" . MAIN_DB_PREFIX . "entity_link` (presta_product_id, doli_product_id, doli_entity_id)
                    VALUES ($idpresta,$idDoli,$entity)";
                $db->query($sql);

            } catch (PrestaShopWebserviceException $e) {
                mac2sync_log("PSWebServices[createProduct] :: ERREUR CREATION :: " . $ref);
                mac2sync_log("[EXCEPTION] ::" . $e->getMessage());
                return false;
            }
            return true;
        }
    }

    public function retrieveMultilang($string,$object)
    {
        $languages = [];  
        foreach($object->language as $o){
            $o[0] = $string;
            array_push($languages,$o);
        }

    return $languages[0][1];
    }

    public function createProductDeclinaison($ref, $price, $prestashop_api_key, $prestashop_url)
    {
        //Accès au WebService
        $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
        global $db;
        mac2sync_log("PSWebServices[createProductDeclinaison] :: Produit variant");

        //Verification de l'existence de la déclinaison
        $xml = $webService->get([
            'resource' => 'combinations',
            'filter[reference]' => $ref,
            'display' => 'full'
        ]);

        //Si déclinaison retrouvée : on l'update
        $combinations = $xml->combinations->children();
        if (count($combinations) > 0) {
            mac2sync_log("PSWebServices[createProduct] :: Déclinaison existante dans Prestashop");
            $product_id = getProductIdByRef($ref);
            $product = new Product($db);
            $product->fetch($product_id);
            updateProductDeclinaison($combinations->combination->id, $combinations->combination->id_product, $ref, $product->price, $product->quantity, $prestashop_api_key, $prestashop_url);
            return true;
        }

        //Sinon : on la crée
        //Récupération du produit variant Dolibarr 
        $product_id = getProductIdByRef($ref);
        $id_parent = getProductParentId($product_id);
        $ref_parent = getProductParentRefByParentId($id_parent);
        $product = new Product($db);
        $product->fetch($product_id);
        mac2sync_log("PSWebServices[createProduct] :: Création de la déclinaison à partir du variant Dolibarr: " . $product_id);


        //Formattage des attributs de variants Dolibarr :: <strong>Taille:</strong> Large
        $desc = substr($product->description, 8);
        $desc = explode(":", $desc);
        $attribute_name = $desc[0];
        $attribute_value = substr($desc[1], 10);
        $attribute_id = 0;
        $attribute_value_id = 0;
        mac2sync_log("PSWebServices[createProduct] :: "  . $attribute_name . " : " . $attribute_value);

        //Verification de l'existence du produit parent dans Prestashop / Recuperation du produit
        $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
        $xml = $webService->get([
            'resource' => 'products',
            'filter[reference]' => $ref_parent,
            'display' => 'full'
        ]);


        //Si produit retrouvé
        $products = $xml->products->children();

        if ($products->product->id != NULL) {
            mac2sync_log("PSWebServices[createProduct] :: Produit parent retrouvé");
            //On vérifie que l'attribut existe
            $xml = $webService->get([
                'resource' => 'product_options',
                'filter[name]' => $attribute_name,
                'display' => 'full'
            ]);
            $product_options = $xml->product_options->children();

            //Si l'attribut existe
            if ($product_options->product_option->id != NULL) {
                mac2sync_log("PSWebServices[createProduct] :: Attribut retrouvé : " . $attribute_name);
                //On vérifie que la valeur existe
                $xml = $webService->get([
                    'resource' => 'product_option_values',
                    'filter[name]' => $attribute_value,
                    'filter[id_attribute_group]' => (int) $product_options->product_option->id,
                    'display' => 'full'
                ]);
                $product_option_values = $xml->product_option_values->children();

                if ($product_option_values->product_option_value->id == NULL) {
                    $product_option_id = $product_options->product_option->id;
                    $blankXml = $webService->get(['url' => $prestashop_url . '/api/product_option_values?schema=blank']);
                    $product_option_value = $blankXml->product_option_value->children();
                    $product_option_value->name->language[0][0] = $attribute_value;
                    $product_option_value->id_attribute_group = (int) $product_option_id;

                    $createdXml = $webService->add([
                        'resource' => 'product_option_values',
                        'postXml' => $blankXml->asXML(),
                    ]);
                    $product_option_value = $createdXml->product_option_value->children();
                    $attribute_value_id = $product_option_value->id;
                    mac2sync_log("PSWebServices[createProduct] :: Valeur créee : " . $attribute_value);
                } else {
                    $attribute_value_id = (int) $product_option_values->product_option_value->id;
                    mac2sync_log("PSWebServices[createProduct] :: Valeur retrouvée : " . $attribute_value);
                }
                $attribute_id = (int) $product_options->product_option->id;
            } else {

                $blankXml = $webService->get(['url' => $prestashop_url . '/api/product_options?schema=blank']);
                $product_option = $blankXml->product_option->children();
                $product_option->group_type = "select"; //select, radio, color
                $product_option->name->language[0][0] = $attribute_name;
                $product_option->public_name->language[0][0] = $attribute_name;
                $product_option->is_color_group = 0;
                $createdXml = $webService->add([
                    'resource' => 'product_options',
                    'postXml' => $blankXml->asXML(),
                ]);
                mac2sync_log("PSWebServices[createProduct] :: Attribut créé : " . $attribute_name);

                $product_option = $createdXml->product_option->children();

                $product_option_id = $product_option->id;
                $blankXml = $webService->get(['url' => $prestashop_url . '/api/product_option_values?schema=blank']);
                $product_option_value = $blankXml->product_option_value->children();
                $product_option_value->name->language[0][0] = $attribute_value;
                $product_option_value->id_attribute_group = (int) $product_option_id;

                $createdXml = $webService->add([
                    'resource' => 'product_option_values',
                    'postXml' => $blankXml->asXML(),
                ]);
                $product_option_value = $createdXml->product_option_value->children();
                $attribute_value_id = (int) $product_option_value->id;
                $attribute_id = (int) $product_option_id;
                mac2sync_log("PSWebServices[createProduct] :: Valeur créée : " . $attribute_value);
            }


            /*
                //Verification de l'existence de la déclinaison
                //$webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
                $xml = $webService->get([
                    'resource' => 'combinations',
                    'filter[id_product]' => (int) $products->product->id,
                    //'filter[reference]' => $ref,
                    'display' => 'full'
                    ]);


                //Si déclinaison retrouvée
                $combinations = $xml->combinations->children();
                foreach($combinations as $combination){
                    if($attribute_value_id == (int) $combination->associations->product_option_values->product_option_value->id){
                        //TODO:UPDATE
                        return true;
                    }
                }*/

            //On crée la combinaison (=produit variant) rattaché au produit
            $blankXml = $webService->get(['url' => $prestashop_url . '/api/combinations?schema=blank']);
            $combination = $blankXml->combination->children();
            $combination->id_product = (int) $products->product->id;
            $combination->reference = $ref;
            $combination->minimal_quantity = 1;
            $combination->associations->product_option_values->product_option_value->id = (int) $attribute_value_id;
            //$attribute_id
            //$attribute_value_id
            //$combination->price = 
            //$combination->stock
            //$combination

            $createdXml = $webService->add([
                'resource' => 'combinations',
                'postXml' => $blankXml->asXML(),
            ]);
            mac2sync_log("PSWebServices[createProduct] :: Combinaison créée : " . $attribute_name);

            return true;
        } else {
            //...
        }
    }

    public function deleteProduct($ref, $prestashop_api_key, $prestashop_url)
    {
        require_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        mac2sync_log("PSWebServices[deleteProduct] :: " . $ref);

        try {
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
            $xml = $webService->get([
                'resource' => 'products',
                'filter[reference]' => $ref,
                'display' => 'full'
            ]);
            $products = $xml->products->children();
            $product_id = (int) $products->product->id;

            //[TODO-MAC2): Ajouter : Import key
            $webService->delete([
                'resource' => 'products',
                'id' => $product_id // Here we use hard coded value but of course you could get this ID from a request parameter or anywhere else
            ]);
            mac2sync_log('Produit supprimé : ' . $ref);
        } catch (PrestaShopWebserviceException $e) {
            mac2sync_log('Erreur suppression produit: ' . $ref . ' : ' . $e->getMessage());
            return false;
        }

        return true;
    }

    public function updateProduct($ref, $prestashop_api_key, $prestashop_url, $product, $categories_id)
    {
        require_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
        mac2sync_log("PSWebServices[updateProduct] :: " . $ref);


        try {
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
            $xml = $webService->get([
                'resource' => 'products',
                'filter[reference]' => $ref,
                'display' => 'full'
            ]);

            $products = $xml->products->children();

            //Si le produit n'est pas retrouvé
            if ($products->product->id == NULL) {

                //On vérifie si c'est un variant
                if (isProductVariantByRef($ref)) {
                    mac2sync_log("Produit variant :: " . $ref);
                    //Recherche de la déclinaison par référence
                    $xml = $webService->get([
                        'resource' => 'combinations',
                        'filter[reference]' => $ref,
                        'display' => 'full'
                    ]);
                    $combinations = $xml->combinations->children();

                    //Si la déclinaison produit n'est pas retrouvée
                    if ($combinations->combination->id == NULL) {
                        mac2sync_log("Déclinaison non trouvée");
                        createProductDeclinaison($ref, (float) $combinations->combination->price, $prestashop_api_key, $prestashop_url);
                        return true;
                    } else {
                        //Mise à jour de la déclinaison
                        global $db;
                        $combination_id = (int) $combinations->combination->id;
                        $product_id = (int) $combinations->combination->id_product;
                        $dolibarr_product_id = getProductIdByRef($ref);
                        $product = new Product($db);
                        $product->fetch($dolibarr_product_id);
                        //IF MULTIPRICES
                        $price = $product->multiprices[1];
                        $qty = $product->
                            //ENDIF

                            //$price =  (float) $combinations->combination->price;
                            $qty = (int) $combinations->combination->quantity;

                        updateProductDeclinaison($combination_id, $product_id, $ref,  $price, $qty, $prestashop_api_key, $prestashop_url);
                        return true;
                    }

                    //$combination_id, $product_id, $ref,  $price, $quantity, $prestashop_api_key, $prestashop_url
                    return true;
                } else {
                    createProduct($product->ref, $product->label, $product->price, $product->description, $product->entity, $prestashop_api_key, $prestashop_url);
                    return true;
                }
            }


            $product_id = (int) $products->product->id;

            //Recherche par ID
            $xml_prestashop = $webService->get([
                'resource' => 'products',
                'id' => (int) $product_id,
            ]);
            //[TODO-MAC2]: Ajouter : Import key
            $productFields = $xml_prestashop->product->children();
            unset($productFields->manufacturer_name);
            unset($productFields->quantity);
            unset($productFields->id_shop_default);
            unset($productFields->id_default_image);
            unset($productFields->associations);
            unset($productFields->id_default_combination);
            unset($productFields->position_in_category);
            unset($productFields->type);
            unset($productFields->pack_stock_type);
            unset($productFields->date_add);
            unset($productFields->date_upd);
            $productFields->price = (float) $product->price;
            $productFields->description = $product->description;
            $productFields->name = $product->label;

            $i = 0;
            foreach ($categories_id as $categorie_id) {
                $productFields->associations->categories->category[$i]->id = $categorie_id;
                $i++;
            }
            $updatedXml = $webService->edit([
                'resource' => 'products',
                'id' => (int) $productFields->id,
                'putXml' => $xml_prestashop->asXML(),
            ]);
            $customerFields = $updatedXml->customer->children();
            mac2sync_log('Produit mis à jour : ' . $ref);
        } catch (PrestaShopWebserviceException $e) {
            mac2sync_log('Erreur mise à jour: ' . $ref . ' : ' . $e->getMessage());
            return false;
        }

        return true;
    }

    public function updateProductPrice($ref, $price, $prestashop_api_key, $prestashop_url)
    {
        require_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
        mac2sync_log("PSWebServices[updateProductPrice] :: " . $ref);


        try {
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
            $xml = $webService->get([
                'resource' => 'products',
                'filter[reference]' => $ref,
                'display' => 'full'
            ]);

            $products = $xml->products->children();
            if ($products->product->id == NULL) {
                mac2sync_log("PSWebServices[updateProductPrice] :: Produit non trouvé");
                return true;
            }


            $product_id = (int) $products->product->id;

            //Recherche par ID
            $xml_prestashop = $webService->get([
                'resource' => 'products',
                'id' => (int) $product_id,
            ]);

            //[TODO-MAC2]: Ajouter : Import key
            $productFields = $xml_prestashop->product->children();
            unset($productFields->manufacturer_name);
            unset($productFields->quantity);
            unset($productFields->id_shop_default);
            unset($productFields->id_default_image);
            unset($productFields->associations);
            unset($productFields->id_default_combination);
            unset($productFields->position_in_category);
            unset($productFields->type);
            unset($productFields->pack_stock_type);
            unset($productFields->date_add);
            unset($productFields->date_upd);
            $productFields->price = (float) $price;
            //$productFields->description = $product->description;
            //productFields->name = $product->label;



            $updatedXml = $webService->edit([
                'resource' => 'products',
                'id' => (int) $productFields->id,
                'putXml' => $xml_prestashop->asXML(),
            ]);
            //$customerFields = $updatedXml->customer->children();
            mac2sync_log('Pix principal du roduit mis à jour : ' . $ref);
        } catch (PrestaShopWebserviceException $e) {
            mac2sync_log('Erreur mise à jour du prix principal: ' . $ref . ' : ' . $e->getMessage());
            return false;
        }

        return true;
    }

    public function updateProductDeclinaison($combination_id, $product_id, $ref,  $price, $quantity, $prestashop_api_key, $prestashop_url)
    {
        mac2sync_log("PSWebServices[updateProductDeclinaison] :: " . $ref);
        $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

        $xml = $webService->get([
            'resource' => 'products',
            'filter[id]' => (int) $product_id,
            'display' => 'full'
        ]);

        $product = $xml->products->children();

        $price_impact = ($price - $product->product->price); // Impact sur le prix
        /*var_dump(($price - $product->product->price));
        var_dump((String) $product->product->price);exit();  */
        /*var_dump($product->product->price);
        var_dump($price);
        var_dump($price_impact);exit();*/



        $xml = $webService->get([
            'resource' => 'combinations',
            'filter[id]' => (int) $combination_id,
            //'display' => 'full'
        ]);

        $combination = $xml->combinations->children();
        //$combination = $combinations->combination;

        $combination->price = $price_impact;
        $combination->minimal_quantity = 0;
        $combination->quantity = (int) $quantity;
        $combination->reference = $ref;
        $combination->id = (int) $combination_id;
        $combination->id_product = (int) $product_id;
        $updatedXml = $webService->edit([
            'resource' => 'combinations',
            'id' => (int) $combination->id,
            'putXml' => $xml->asXML(),
        ]);
        //var_dump($updatedXml);exit();

    }

    //Récupère tous les clients
    public function getClients($prestashop_api_key, $prestashop_url)
    {
        require_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
        mac2sync_log("PSWebServices[getClients] :: ALL");

        try {
            //Connexion au WebService
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

            //Call API
            $xml = $webService->get(['resource' => 'customers', 'display' => 'full']);

            //Renvoie les catégories
            return $xml;
        } catch (PrestaShopWebserviceException $ex) {
            return false;
        }
    }

    public function createClient($prestashop_api_key, $prestashop_url, $client)
    {
        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
        require_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/class/shops.class.php';
        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/DolibarrMethods.php');
        global $db;
        mac2sync_log("PSWebServices[createClient] :: " . $client->name);

        //Verification de l'existence du client
        try {
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

            $xml = $webService->get([
                'resource' => 'customers',
                'filter[email]' => $client->email,
                'display' => 'full'
            ]);
        } catch (PrestaShopWebserviceException $e) {
            mac2sync_log('Erreur : ' . $email . ' : ' . $e->getMessage());
        }


        //Si client existant, on UPDATE
        $customers = $xml->customers->children();
        if (count($customers) > 0) {
            $customer_id = getCustomerIdByEmail($client->email);
            $customer = new Societe($db);
            $customer->fetch($customer_id);
            updateClient($prestashop_api_key, $prestashop_url, $customer);
            return true;
        }



        //Accès au WebService
        try {
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
            $blankXml = $webService->get(['url' => $prestashop_url . '/api/customers?schema=blank']);
            $customers = $blankXml->customer->children();
            $customers->firstname = $client->name;
            $customers->lastname = $client->nom;
            $customers->email = $client->email;
            $customers->active = 1;
            $customers->passwd = "MAC22021";

            $createdXml = $webService->add([
                'resource' => 'customers',
                'postXml' => $blankXml->asXML(),
            ]);

            $newCustomerFields = $createdXml->customer->children();

            //Liaison du Client Doli<->Presta par clé
            $shop_id = getShopIdByPrestaInfos($prestashop_url, $prestashop_api_key);
            changeClientImportKey($client->id, (int) $newCustomerFields->id, $shop_id);
            return true;
        } catch (PrestaShopWebserviceException $e) {
            mac2sync_log('Erreur suppression produit: ' . $ref . ' : ' . $e->getMessage());
            return false;
        }
    }

    public function deleteClient($prestashop_api_key, $prestashop_url, $client)
    {
        require_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        mac2sync_log("PSWebServices[deleteClient] :: " . $client->name);

        $import_key = $client->import_key;

        $prestashop_client_id = getObjectIdByImportKey($import_key);

        try {
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
            /* $xml = $webService->get([
                'resource' => 'customers',
                'filter[email]' => $email,
                'display' => 'full'
                ]);
                
                $customers = $xml->customers->children();
                $customer_id = (int) $customers->customer->id;*/

            //[TODO-MAC2): Ajouter : Import key
            $webService->delete([
                'resource' => 'customers',
                'id' => $prestashop_client_id // Here we use hard coded value but of course you could get this ID from a request parameter or anywhere else
            ]);
            mac2sync_log('Client supprimé [ID PRESTA] : ' . $prestashop_client_id);
        } catch (PrestaShopWebserviceException $e) {
            mac2sync_log('Erreur suppression client [ID PRESTA]: ' . $prestashop_client_id . ' : ' . $e->getMessage());
            return false;
        }

        return true;
    }

    public function updateClient($prestashop_api_key, $prestashop_url, $client)
    {
        require_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
        mac2sync_log("PSWebServices[updateClient] :: " . $client->name);

        $import_key = $client->import_key;

        if (strpos(substr($import_key, -4), "-") === false) {
            //echo "id with 4 c"; 
            $prestashop_client_id = substr($import_key, -4);
        } else {
            if (strpos(substr($import_key, -3), "-") === false) {
                //echo "id with 3 c"; 
                $prestashop_client_id = substr($import_key, -3);
            } else {
                if (strpos(substr($import_key, -2), "-") === false) {
                    //echo "id with 2 c"; 
                    $prestashop_client_id = substr($import_key, -2);
                } else {
                    if (strpos(substr($import_key, -1), "-") === false) {
                        //echo "id with 1 c"; 
                        $prestashop_client_id = substr($import_key, -1);
                    }
                }
            }
        }



        try {
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

            //Recherche par ID
            $xml_prestashop = $webService->get([
                'resource' => 'customers',
                'id' => (int) $prestashop_client_id,
            ]);

            if (!isset($client->nom)) {
                $client->nom = $client->name;
            }
            //[TODO-MAC2]: Ajouter : Import key
            $customerFields = $xml_prestashop->customer->children();
            /*unset($productFields->manufacturer_name);
            unset($productFields->quantity);
            unset($productFields->id_shop_default);
            unset($productFields->id_default_image);
            unset($productFields->associations);
            unset($productFields->id_default_combination);
            unset($productFields->position_in_category);
            unset($productFields->type);
            unset($productFields->pack_stock_type);
            unset($productFields->date_add);
            unset($productFields->date_upd);*/
            $customerFields->firstname = $client->name;
            $customerFields->lastname = $client->nom;
            $customerFields->email = $client->email;

            $updatedXml = $webService->edit([
                'resource' => 'customers',
                'id' => (int) $customerFields->id,
                'putXml' => $xml_prestashop->asXML(),
            ]);

            mac2sync_log('Client mis à jour : ' . $email);
        } catch (PrestaShopWebserviceException $e) {
            mac2sync_log('Erreur mise à jour: ' . $email . ' : ' . $e->getMessage());
            return false;
        }

        return true;
    }

    public function getCategories($prestashop_api_key, $prestashop_url)
    {
        require_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
        mac2sync_log("PSWebServices[getCategories] :: ALL");

        try {
            //Connexion au WebService
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

            //Call API
            $xml = $webService->get(['resource' => 'categories', 'display' => 'full']);

            //Renvoie les catégories
            return $xml;
        } catch (PrestaShopWebserviceException $ex) {
            return false;
        }
    }

    //Renvoie le Xml de la catégorie
    public function getCategorie($id, $prestashop_api_key, $prestashop_url)
    {
        require_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';

        mac2sync_log("PSWebServices[getCategorie] :: " . $id);


        try {
            //Connexion au WebService
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

            //Call API
            $xml = $webService->get(['resource' => 'categories', 'filter[id]' => (int) $id, 'display' => 'full']);

            //Renvoie la catégorie
            return $xml;
        } catch (PrestaShopWebserviceException $ex) {
            return false;
        }
    }

    //Renvoie l'ID de la catégorie créée
    public function createCategorie($category_name, $category_doli_id, $category_fk_parent = 0, $prestashop_api_key, $prestashop_url)
    {
        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/DolibarrMethods.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
        require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
        global $db;
        mac2sync_log("PSWebServices[createCategorie] :: " . $category_name);

        //Verification de l'existence de la catégorie (anti-doublons)
        try {
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

            $xml = $webService->get([
                'resource' => 'categories',
                'filter[name]' => $category_name,
                'display' => 'full'
            ]);
        } catch (PrestaShopWebserviceException $e) {
            mac2sync_log('Erreur : ' . $category_name . ' : ' . $e->getMessage());
            return false;
        }

        //Si Catégorie existante: on renvoie l'ID de la catégorie existante
        $categories = $xml->categories->children();
        if (count($categories) > 0) {
            return (int) $categories->category->id;
        }

        /*
        //Si la catégorie est une sous-catégorie :: on recherche l'existence de sa catégorie parente dans Prestashop 
        if($category_fk_parent != 0){
            $category_doli = new Categorie($db);
            $category_doli->fetch($category_fk_parent);
            $category_presta_id = getObjectIdByImportKey($category_doli->import_key);
            $
        }else{
            //Sinon on attribue comme catégorie parente: Accueil
            $category_fk_parent = 2; // Equivalent : Catégorie Accueil (Prestashop)
        }*/



        //Accès au WebService
        try {
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

            $blankXml = $webService->get(['url' => $prestashop_url . '/api/categories?schema=blank']);

            $categories = $blankXml->category->children();
            $categories->name->language[0][0] = $category_name;

            $categories->active = 1;
            $categories->id_parent = 2;
            $categories->link_rewrite->language[0][0] = 0;

            $createdXml = $webService->add([
                'resource' => 'categories',
                'postXml' => $blankXml->asXML(),
            ]);

            $newCategoryFields = $createdXml->category->children();

            return (int) $newCategoryFields->id;
            //return true;
        } catch (PrestaShopWebServiceException $e) {
            mac2sync_log('Erreur suppression produit: ' . $ref . ' : ' . $e->getMessage());
            return false;
        }
    }

    //KO
    public function updateCategorie($category_name, $category_doli_id, $category_fk_parent = 0, $prestashop_api_key, $prestashop_url, $shop_id)
    {
        //KO
        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/DolibarrMethods.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
        require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
        global $db;

        $category_prestashop_id = getPrestashopLink("category", $category_doli_id, $shop_id);

        if ($category_prestashop_id == FALSE) {
            return true;
        }

        //Verification de l'existence de la catégorie (anti-doublons)
        try {
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

            $xml = $webService->get([
                'resource' => 'categories',
                'filter[id]' => $category_prestashop_id,
                'display' => 'full'
            ]);
        } catch (PrestaShopWebserviceException $e) {
            mac2sync_log('Erreur : ' . $category_prestashop_id . ' : ' . $e->getMessage());
        }

        /*if(count($xml->categories) > 0){
                return true;
            }*/


        //Si Catégorie existante: on renvoie l'ID de la catégorie existante
        $categorie = $xml->categories->children();
        $categorie->name = (string) $category_name;
        $categorie->name->language[0][0] = (string) $category_name;
        $categorie->id = (int) $category_prestashop_id;
        $categorie->id_parent = (int) $categorie->category->id_parent;
        $categorie->active = 1;
        $categorie->link_rewrite->language[0][0] = 0;

        $updatedXml = $webService->edit([
            'resource' => 'categories',
            'id' => (int) $category_prestashop_id,
            'putXml' => $xml->asXML(),
        ]);

        //var_dump($updatedXml);exit();

    }

    public function getAllProducts($prestashop_api_key, $prestashop_url)
    {
        mac2sync_log("PSWebServices[getAllProducts] :: ALL");
        try {
            // creating webservice access
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

            // call to retrieve all customers
            $xml = $webService->get(['resource' => 'products']);
        } catch (PrestaShopWebserviceException $ex) {
            // Shows a message related to the error
            //echo 'Other error: <br />' . $ex->getMessage();
            return false;
        }

        $products = $xml->products->product;

        return $products;
    }

    public function getProduct($prestashop_api_key, $prestashop_url, $product_id)
    {
        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/DolibarrMethods.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
        require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
        global $db;
        mac2sync_log("PSWebServices[getProduct] :: " . (int) $product_id);
        try {
            // creating webservice access
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

            $xml = $webService->get(['resource' => 'products', 'filter[id]' => (int) $product_id, 'display' => 'full']);
            //var_dump($xml);
            $product_details = $xml->products->product;
            //var_dump($product_details);
            return $product_details;
        } catch (PrestaShopWebserviceException $ex) {
            //echo 'Other error: <br />' . $ex->getMessage();
            return false;
        }

        /*foreach($xml->products->product as $product){
            $xml = $webService->get(['resource' => 'products', 'filter[id]' => (int) $product['id'], 'display' => 'full']);

            $product_details = $xml->products->product;
            foreach($product_details->associations->categories->category as $category){
                var_dump((int) $category->id);
            }
            //var_dump($xml->products->product->associations->categories);
        }*/
    }

    //KO
    public function updateStock($quantity, $product_id, $prestashop_api_key, $prestashop_url)
    {
        return true;

        //KO



        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/DolibarrMethods.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
        require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
        global $db;
        mac2sync_log("PSWebServices[updateStock] :: " . (int) $product_id);

        $product = new Product($db);
        $product->fetch($product_id);


        try {
            // creating webservice access
            $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

            $xml = $webService->get(['resource' => 'products', 'filter[reference]' => $product->ref, 'display' => 'full']);

            //Vérification de l'existence de la référence (côté Prestashop)

            if (count($xml->products) > 0) {

                $product_details = $xml->products->product;
                $product_id = (int) $product_details->id;
                //var_dump($product_id);exit();

                //Recherche par ID
                $xml_prestashop = $webService->get([
                    'resource' => 'stocks',
                    'filter[id_product]' => (int) $product_id,
                    'display' => 'full',
                ]);

                //var_dump($xml_prestashop);exit();

                //[TODO-MAC2]: Ajouter : Import key
                $productFields = $xml_prestashop->children()->children();
                //var_dump($productFields);exit();
                $id = $productFields->stock_available->id;

                //$xml_stock = $webService->get(['url' => $prestashop_url . '/api/stock_availables?schema=blank']);
                //$productFields = $xml_stock->stock_available;
                //var_dump($xml_stock);exit();
                //$id = (int) $id;
                //var_dump($id);exit();
                $productFields->quantity = $product->stock_reel;
                $productFields->id = $id;
                //var_dump($productFields);exit();
                $productFields->stock_availables += 12;

                $updatedXml = $webService->edit([
                    'resource' => 'stock_availables',
                    'id' => (int) $id,
                    'putXml' => $xml_prestashop->asXML(),
                ]);
                //$customerFields = $updatedXml->customer->children();
                mac2sync_log('Produit mis à jour : ' . $ref);
            }
        } catch (PrestaShopWebserviceException $ex) {
            //echo 'Other error: <br />' . $ex->getMessage();
            return false;
        }
    }

    /*public function updateProductMultiPriceOld($ref, $price, $level, $principal_level_multiprice, $prestashop_api_key, $prestashop_url){

        include_once(DOL_DOCUMENT_ROOT. '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once(DOL_DOCUMENT_ROOT. '/custom/mac2sync/core/modules/DolibarrMethods.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';
        //require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
        global $db;
        mac2sync_log("PSWebServices[updateProductMultiPrice] :: " . $ref);
        mac2sync_log("PSWebServices[updateProductMultiPrice] :: Niveau " . $level);
        mac2sync_log("PSWebServices[updateProductMultiPrice] :: Prix " . $price);

        $date = date("Y-m-d H:i:s");
        $prestashop_level = (int) ($level + 3);
        //var_dump($prestashop_level);

        //Si le prix est NULL, on termine la tâche
        if($price == NULL || $price == ''){
            mac2sync_log("Prix NULL : Pas d'update du prix ");
            return false;
        }
    

            //Accès au WebService
                $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);
                
                //Recherche de la référence
                $xml = $webService->get([
                    'resource' => 'products',
                    'filter[reference]' => $ref,
                    'display' => 'full'
                ]);
                $products = $xml->products->children();


                        //Vérification de l'existence du produit
                        if($products->product->id != NULL) {
                            mac2sync_log("Référence [".$ref."] trouvée");

                            //Récupération de l'ID (prestashop) du produit
                            $product_id = $products->product->id;
                                
                            //Si le prix n'est pas renseigné, on prend le prix principal
                            if($price == 0){ $price = $products->product->price;}

                            //Récupération des multiprix
                            $xml = $webService->get(['resource' => 'specific_prices', 'filter[id_product]' => (int) $product_id, 'filter[id_group]' => (int) $prestashop_level, 'display' => 'full']);
                            $specific_prices = $xml->specific_prices->children();
                            
                            //Vérification de l'existence de ce niveau de prix
                            if(count($specific_prices) > 0) {
                                        mac2sync_log("Niveau de prix:". $level ." existant : update [" .$ref. "]");
                                        $xml = $webService->get([
                                                'resource' => 'specific_prices',
                                                'id' => (int) $specific_prices->specific_price->id,
                                        ]);

                                        $new_specific_price = $xml->specific_price->children();
                                        //var_dump($new_specific_price);exit();
                                        $new_specific_price->price = $price;
                                        $new_specific_price->from = date("Y-m-d H:i:s");

                                        $updatedXml = $webService->edit([
                                            'resource' => 'specific_prices',
                                            'id' => (int) $specific_prices->specific_price->id,
                                            'id_product' => $product_id,
                                            'putXml' => $xml->asXML(),
                                        ]);
                                            
                                        mac2sync_log("Mise à jour du niveau de prix: OK");
                                                    
                            }else{
                                //Création du niveau de prix
                                $blankXml = $webService->get(['url' => $prestashop_url.'/api/specific_prices?schema=blank']);
                                dol_syslog($log_signature . $price);
                                $new_specific_price = $blankXml->specific_price->children();
                                $new_specific_price->id_shop = 1;
                                $new_specific_price->from_quantity = 1;
                                $new_specific_price->id_product = (int) $product_id;
                                $new_specific_price->id_group = (int) $prestashop_level;
                                $new_specific_price->price = $price;
                                $new_specific_price->id_customer = 0;
                                $new_specific_price->from = date("Y-m-d H:i:s");
                                $new_specific_price->to = date("0000-00-00 00:00:00");
                                $new_specific_price->reduction = 0;
                                $new_specific_price->reduction_type = "amount";
                                $new_specific_price->reduction_tax = 1;
                                $new_specific_price->id_currency = 0;
                                $new_specific_price->id_country = 0;
                                $new_specific_price->id_cart = 0;
                                $new_specific_price->id_product_attribute = 0;
                                $new_specific_price->id_shop_group = 0;

                                $createdXml = $webService->add([
                                                'resource' => 'specific_prices',
                                                'postXml' => $blankXml->asXML(),
                                            ]);
                                $new_specific_price = $createdXml->specific_price->children();
                                mac2sync_log("Création du niveau de prix: OK");
                                }  
                        }
                        mac2sync_log("Level: " .$level);
                        mac2sync_log((int) $principal_level_multiprice);
                        if($level == $principal_level_multiprice){
                            $product_id = (int) $product_id;
                            mac2sync_log("Niveau de prix à établir en principal");
                            updateProductPrice($ref, $price, $prestashop_api_key, $prestashop_url);
                        }
    }*/

    public function updateProductMultiPrice($ref, $multiprices, $principal_level_multiprice, $prestashop_api_key, $prestashop_url)
    {
        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/PSWebServiceLibrary.php');
        include_once(DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/DolibarrMethods.php');
        include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/Mac2SyncLogger.php';

        global $db;
        /*mac2sync_log("PSWebServices[updateProductMultiPrice] :: " . $ref);
        mac2sync_log("PSWebServices[updateProductMultiPrice] :: Niveau " . $level);
        mac2sync_log("PSWebServices[updateProductMultiPrice] :: Prix " . $price);*/

        $date = date("Y-m-d H:i:s");

        //Accès au WebService
        $webService = new PrestaShopWebservice($prestashop_url, $prestashop_api_key, false);

        //Recherche de la référence
        $xml = $webService->get([
            'resource' => 'products',
            'filter[reference]' => $ref,
            'display' => 'full'
        ]);
        $products = $xml->products->children();


        //Vérification de l'existence du produit
        if ($products->product->id != NULL) {
            mac2sync_log("Référence [" . $ref . "] trouvée");

            //Récupération de l'ID (prestashop) du produit
            $product_id = $products->product->id;

            //Pour chaque niveau de prix
            foreach ($multiprices as $level => $price) {
                //Niveau de prix : conversion Prestashop
                $prestashop_level = (int) ($level + 3);
                mac2sync_log("Niveau de prix Dolibarr : " . $level);
                mac2sync_log("Niveau de prix Prestashop : " . $prestashop_level);

                //Si le prix est NULL, on termine la tâche
                if ($price == NULL || $price == '') {
                    mac2sync_log("Prix NULL : Pas d'update du prix ");
                    return false;
                }

                //Si le prix n'est pas renseigné, on prend le prix principal
                if ($price == 0) {
                    $price = $products->product->price;
                }

                //Récupération des multiprix
                $xml = $webService->get(['resource' => 'specific_prices', 'filter[id_product]' => (int) $product_id, 'filter[id_group]' => (int) $prestashop_level, 'display' => 'full']);
                $specific_prices = $xml->specific_prices->children();

                //Vérification de l'existence de ce niveau de prix
                if (count($specific_prices) > 0) {
                    mac2sync_log("Niveau de prix:" . $level . " existant : update [" . $ref . "]");
                    $xml = $webService->get([
                        'resource' => 'specific_prices',
                        'id' => (int) $specific_prices->specific_price->id,
                    ]);

                    $new_specific_price = $xml->specific_price->children();
                    //var_dump($new_specific_price);exit();
                    $new_specific_price->price = $price;
                    $new_specific_price->from = date("Y-m-d H:i:s");

                    $updatedXml = $webService->edit([
                        'resource' => 'specific_prices',
                        'id' => (int) $specific_prices->specific_price->id,
                        'id_product' => $product_id,
                        'putXml' => $xml->asXML(),
                    ]);

                    mac2sync_log("Mise à jour du niveau de prix: OK");
                } else {
                    //Création du niveau de prix
                    $blankXml = $webService->get(['url' => $prestashop_url . '/api/specific_prices?schema=blank']);
                    dol_syslog($log_signature . $price);
                    $new_specific_price = $blankXml->specific_price->children();
                    $new_specific_price->id_shop = 1;
                    $new_specific_price->from_quantity = 1;
                    $new_specific_price->id_product = (int) $product_id;
                    $new_specific_price->id_group = (int) $prestashop_level;
                    $new_specific_price->price = $price;
                    $new_specific_price->id_customer = 0;
                    $new_specific_price->from = date("Y-m-d H:i:s");
                    $new_specific_price->to = date("0000-00-00 00:00:00");
                    $new_specific_price->reduction = 0;
                    $new_specific_price->reduction_type = "amount";
                    $new_specific_price->reduction_tax = 1;
                    $new_specific_price->id_currency = 0;
                    $new_specific_price->id_country = 0;
                    $new_specific_price->id_cart = 0;
                    $new_specific_price->id_product_attribute = 0;
                    $new_specific_price->id_shop_group = 0;

                    $createdXml = $webService->add([
                        'resource' => 'specific_prices',
                        'postXml' => $blankXml->asXML(),
                    ]);
                    $new_specific_price = $createdXml->specific_price->children();
                    mac2sync_log("Création du niveau de prix: OK");
                }
                mac2sync_log("Level: " . $level);

                mac2sync_log((string) $principal_level_multiprice);
                if ((int) $level == (int) $principal_level_multiprice) {
                    $product_id = (int) $product_id;
                    mac2sync_log("Niveau de prix à établir en principal");
                    updateProductPrice($ref, $price, $prestashop_api_key, $prestashop_url);
                }
            }
        }
    }
}