<?php

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/mac2sync/class/shops.class.php');
dol_include_once('/mac2sync/lib/mac2sync_shops.lib.php');
include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/PSWebServicesMethods.php';
include_once DOL_DOCUMENT_ROOT . '/custom/mac2sync/core/modules/DolibarrMethods.php';

// Load translation files required by the page
$langs->loadLangs(array("mac2sync@mac2sync", "other"));

// Get parameters
$id = GETPOST('id', 'int');
$ref        = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'shopscard'; // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');
//$lineid   = GETPOST('lineid', 'int');

// Initialize technical objects
$object = new Shops($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->mac2sync->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array('shopscard', 'globalcard')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$search_all = GETPOST("search_all", 'alpha');
$search = array();
foreach ($object->fields as $key => $val)
{
	if (GETPOST('search_'.$key, 'alpha')) $search[$key] = GETPOST('search_'.$key, 'alpha');
}

if (empty($action) && empty($id) && empty($ref)) $action = 'view';

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once.


$permissiontoread = $user->rights->mac2sync->shops->read;
$permissiontoadd = $user->rights->mac2sync->shops->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissiontodelete = $user->rights->mac2sync->shops->delete || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT);
$permissionnote = $user->rights->mac2sync->shops->write; // Used by the include of actions_setnotes.inc.php
$permissiondellink = $user->rights->mac2sync->shops->write; // Used by the include of actions_dellink.inc.php
$upload_dir = $conf->mac2sync->multidir_output[isset($object->entity) ? $object->entity : 1];

// Security check - Protection if external user
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (($object->statut == $object::STATUS_DRAFT) ? 1 : 0);
//$result = restrictedArea($user, $object->element, $object->id, '', '', 'fk_soc', 'rowid', $isdraft);

//if (empty($permissiontoread)) accessforbidden();


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	$error = 0;

	$backurlforlist = dol_buildpath('/mac2sync/shops_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) $backtopage = $backurlforlist;
			else $backtopage = dol_buildpath('/mac2sync/shops_card.php', 1).'?id='.($id > 0 ? $id : '__ID__');
		}
	}

	$triggermodname = 'MAC2SYNC_SHOPS_MODIFY'; // Name of trigger action code to execute when we modify record

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Actions when linking object each other
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';

	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

	// Action to move up and down lines of object
	//include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';

	// Action to build doc
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';


}


/*
 * View
 *
 * Put here all code to build page
 */

$form = new Form($db);
$formfile = new FormFile($db);
$formproject = new FormProjets($db);

$title = $langs->trans("Shops");
$help_url = '';
llxHeader('', $title, $help_url);

// Example : Adding jquery code
print '<script type="text/javascript" language="javascript">
jQuery(document).ready(function() {
	function init_myfunc()
	{
		jQuery("#myid").removeAttr(\'disabled\');
		jQuery("#myid").attr(\'disabled\',\'disabled\');
	}
	init_myfunc();
	jQuery("#mybutton").click(function() {
		init_myfunc();
	});
});
</script>';


// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create')))
{
	$res = $object->fetch_optionals();
	
	$head = shopsPrepareHead($object);
	unset($head[1]);
	unset($head[2]);
	
	//MAC2SYNC-TABS
	$massSyncTab = array("shops_massSync.php?id=". $id, "Import catégories", "massSyncTab");
	array_push($head, $massSyncTab);
	$massSyncMultiTab = array("shops_massSync_multicategorie.php?id=". $id, "Import multicatégories", "massSyncMultiTab");
	array_push($head, $massSyncMultiTab);
	$massSyncMultiTabPresta = array("shops_massSync_multicategorie_presta.php?id=". $id, "Import multi-presta", "massSyncMultiTabPresta");
	array_push($head, $massSyncMultiTabPresta);
	$massSyncTiers = array("shops_massSyncTiers.php?id=". $id, "Import Tiers", "massSyncTiers");
	array_push($head, $massSyncTiers);
	$massSyncTiersPresta = array("shops_massSyncTiersPresta.php?id=". $id, "Import Tiers-presta", "massSyncTiersPresta");
	array_push($head, $massSyncTiersPresta);
	$logs = array("shops_logs.php", "Logs", "logs");
	array_push($head, $logs);
	print dol_get_fiche_head($head, 'massSyncTiersPresta', $langs->trans("Shops"), -1, $object->picto);


	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="'.dol_buildpath('/mac2sync/shops_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	


	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div>';
	print '<b><u>Importer les Tiers Presta -> Doli :</u></b>';
	print '<form action="'.$_SERVER["PHP_SELF"].'" id="syncTiers" method="GET">';

	print '<input type=hidden value="'.$id.'" name="id">';
	print '<input type=hidden value="'.$id.'" name="shop_id">';
	print '<input type=hidden value="SYNC_TIERS" name="action">';
	print '<button onClick="document.getElementById(\'syncTiers\').submit()"><i class="fa fa-sync"></i> Synchroniser</button>';
	print '</form>';
	print '</div>';

	print '<div class="clearboth"></div>';
	print '<br><br>';

	print dol_get_fiche_end();

	//Synchronisation catégorie existante
	if($action == 'SYNC_TIERS'){
		global $db;
		echo "<b><u>Tiers synchronisés: </b></u><br>";
		$prestashop_api_key = $object->prestashop_ws_key;
		$prestashop_url = $object->prestashop_url;
		$object->syncAllTiersPresta($prestashop_api_key, $prestashop_url);
	}
	
}

// End of page
llxFooter();
$db->close();
