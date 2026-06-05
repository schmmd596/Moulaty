<?php
/**
 * Confirmation Sortie - Interface de confirmation des sorties
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $langs, $user, $conf;
$langs->loadLangs(['stocks', 'main', 'womapeche@womapeche']);

$form = new Form($db);

// ============================================================================
// 🔹 RÉCUPÉRATION DES PARAMÈTRES
// ============================================================================
$type_sortie = GETPOST('mode_sortie', 'int');
$fk_entrepot_source = GETPOST('fk_entrepot_source', 'int');
$fk_entrepot_dest = GETPOST('fk_entrepot_dest', 'int');
$fk_client = GETPOST('fk_client_dest', 'int');
$id_sortie = GETPOST('id', 'int');

// ============================================================================
// 🔹 MODE MODIFICATION SI ID EXISTANT
// ============================================================================
$isEditMode = false;
$sortieData = null;

if ($id_sortie > 0) {
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid = ".(int)$id_sortie;
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $sortieData = $db->fetch_object($resql);
        $isEditMode = true;

        if ($sortieData->statut != 0) {
            print '<div class="error">❌ '.$langs->trans("SortieNonModifiable").'</div>';
            llxFooter(); $db->close(); exit;
        }

        // Préremplissage
        $type_sortie = $sortieData->type;
        $fk_entrepot_source = $sortieData->fk_entrepot_source;
        $fk_entrepot_dest = $sortieData->fk_entrepot_dest;
        $fk_client = $sortieData->fk_client;
    }
}

// ============================================================================
// 🔹 VALIDATION ENTREPÔTS
// ============================================================================
if ($fk_entrepot_source == $fk_entrepot_dest) {
    setEventMessages($langs->trans("ErreurEntrepotsIdentiques"), null, 'errors');
    $previous = $_SERVER['HTTP_REFERER'] ?? 'sortie_card.php';
    header("Location: $previous");
    exit;
}

// ============================================================================
// 🔹 PRÉPARATION DES OBJETS
// ============================================================================
$entrepot_src = new Entrepot($db);
$entrepot_src->fetch($fk_entrepot_source);

// ============================================================================
// 🔹 EN-TÊTE DE PAGE
// ============================================================================
llxHeader('', $isEditMode ? $langs->trans('ModificationBonSortie') : $langs->trans('ConfirmationBonSortie'));
print '<link rel="stylesheet" href="../selection_form_style.css">';

print '<div class="selection-container">';

// Header avec style CSS
print '<div class="selection-header">';
print '<h1><i class="fa fa-'.($isEditMode ? 'edit' : 'check-circle').'"></i> ';
print $isEditMode ? $langs->trans("ModificationBonSortie") : $langs->trans("ConfirmationBonSortie");
print '</h1>';
print '</div>';

// ============================================================================
// 🔹 FORMULAIRE PRINCIPAL
// ============================================================================
print '<form method="POST" action="confirm_create.php">';
print '<input type="hidden" name="action" value="'.($isEditMode ? 'update_sortie' : 'confirm_sortie').'">';
if ($isEditMode) print '<input type="hidden" name="id" value="'.$id_sortie.'">';
print '<input type="hidden" name="type" value="'.$type_sortie.'">';
print '<input type="hidden" name="fk_entrepot_source" value="'.$fk_entrepot_source.'">';
if ($type_sortie == 0) {
    print '<input type="hidden" name="fk_entrepot_dest" value="'.$fk_entrepot_dest.'">';
} elseif ($type_sortie == 1) {
    print '<input type="hidden" name="fk_client" value="'.$fk_client.'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';

// ============================================================================
// 🔹 INFORMATIONS GÉNÉRALES
// ============================================================================
print '<div class="filter-section">';
print '<div class="filter-title">';
print '<i class="fa fa-info-circle"></i> '.$langs->trans("InformationsGenerales");
print '</div>';

print '<table class="filter-table">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("DetailsSortie").'</th></tr>';

// Entrepôt source
print '<tr>';
print '<td width="35%"><label class="selection-label"><i class="fa fa-warehouse"></i> '.$langs->trans("EntrepotSource").'</label></td>';
print '<td><strong>'.dol_escape_htmltag($entrepot_src->ref).'</strong></td>';
print '</tr>';

// Entrepôt destination ou client selon le type
if ($type_sortie == 0 && $fk_entrepot_dest) {
    $entrepot_dest = new Entrepot($db);
    $entrepot_dest->fetch($fk_entrepot_dest);
    print '<tr>';
    print '<td><label class="selection-label"><i class="fa fa-boxes-stacked"></i> '.$langs->trans("EntrepotDestination").'</label></td>';
    print '<td><strong>'.dol_escape_htmltag($entrepot_dest->ref).'</strong></td>';
    print '</tr>';
}

if ($type_sortie == 1 && $fk_client) {
    $thirdparty = new Societe($db);
    $thirdparty->fetch($fk_client);
    print '<tr>';
    print '<td><label class="selection-label"><i class="fa fa-user-tie"></i> '.$langs->trans("Client").'</label></td>';
    print '<td><strong>'.dol_escape_htmltag($thirdparty->name).'</strong></td>';
    print '</tr>';
}

// Type de sortie
print '<tr>';
print '<td><label class="selection-label"><i class="fa fa-arrow-right-arrow-left"></i> '.$langs->trans("TypeSortie").'</label></td>';
print '<td>';
print '<span class="selection-badge '.($type_sortie == 0 ? 'badge-info' : 'badge-success').'">';
print '<i class="fa fa-'.($type_sortie == 0 ? 'right-left' : 'truck').'"></i> ';
print $type_sortie == 0 ? $langs->trans("TransfertEntrepots") : $langs->trans("SortieClient");
print '</span>';
print '</td>';
print '</tr>';

print '</table>';
print '</div>'; // .filter-section

// ============================================================================
// 🔹 SECTION PRODUITS
// ============================================================================
print '<div class="selection-section">';
print '<div class="selection-title">';
print '<i class="fa fa-cubes"></i> '.$langs->trans("Produits");
print '</div>';

// Récupération des données produits
$products = $nb_carton = $poids = $pus = $cartons_rowids = [];

if ($isEditMode) {
    $sql = "SELECT fk_product, nb_carton, poids_total, pu 
            FROM ".MAIN_DB_PREFIX."pech_sortiedetprod 
            WHERE fk_sortie = ".(int)$id_sortie;
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $products[] = $obj->fk_product;
            $nb_carton[] = $obj->nb_carton;
            $poids[] = $obj->poids_total;
            $pus[] = $obj->pu;
        }
    }
} else {
    $products = GETPOST('products', 'array');
    $nb_carton = GETPOST('nb_carton', 'array');
    $poids = GETPOST('poids', 'array');
    $cartons_rowids = GETPOST('cartons_rowid', 'array');
}

$totalCartons = 0;
$totalPoids = 0;

print '<div class="selection-table-container">';
print '<table class="selection-table">';
print '<thead>';
print '<tr>';
print '<th><i class="fa fa-cube"></i> '.$langs->trans("Produit").'</th>';
print '<th class="center"><i class="fa fa-hashtag"></i> '.$langs->trans("Cartons").'</th>';
print '<th class="center"><i class="fa fa-balance-scale"></i> '.$langs->trans("PoidsTotal").'kg</th>';
print '</tr>';
print '</thead>';
print '<tbody>';

if (!empty($products)) {
    foreach ($products as $i => $pid) {
        $prod = new Product($db);
        $prod->fetch($pid);
        $poidsTotal = isset($poids[$i]) ? (float)$poids[$i] : 0;
        $nbCarton = isset($nb_carton[$i]) ? (int)$nb_carton[$i] : 0;
        $cartons_rowid = isset($cartons_rowids[$i]) ? $cartons_rowids[$i] : '';

        $totalCartons += $nbCarton;
        $totalPoids += $poidsTotal;

        print '<tr>';
        print '<td>';
        print '<strong>'.dol_escape_htmltag($prod->ref).'</strong><br>';
        print '<small class="opacitymedium">'.dol_escape_htmltag($prod->label).'</small>';
        print '<input type="hidden" name="products[]" value="'.$pid.'">';
        print '</td>';
        print '<td class="center"><strong>'.$nbCarton.'</strong>';
        print '<input type="hidden" name="nb_carton[]" value="'.$nbCarton.'">';
        print '</td>';
        print '<td class="center"><strong>'.price($poidsTotal).'</strong>';
        print '<input type="hidden" name="poids[]" value="'.$poidsTotal.'">';
        print '<input type="hidden" name="cartons_rowid[]" value="'.$cartons_rowid.'">';
        print '</td>';
        print '</tr>';
    }

    // Ligne total
    print '<tr class="liste_total" style="background: linear-gradient(135deg, #cfd6f7ff 0%, #dfc6f8ff 100%); color: white; font-weight: bold;">';
    print '<td class="right"><strong>'.$langs->trans("Total").'</strong></td>';
    print '<td class="center"><strong>'.$totalCartons.'</strong></td>';
    print '<td class="center"><strong>'.price($totalPoids).'</strong></td>';
    print '</tr>';
} else {
    print '<tr>';
    print '<td colspan="3" class="selection-empty">';
    print '<i class="fa fa-exclamation-circle"></i>';
    print '<p>'.$langs->trans("AucunProduitSelectionne").'</p>';
    print '</td>';
    print '</tr>';
}

print '</tbody>';
print '</table>';
print '</div>'; // .selection-table-container
print '</div>'; // .selection-section

// ============================================================================
// 🔹 BOUTON CONFIRMATION
// ============================================================================
print '<div class="selection-actions">';
print '<button type="submit" class="selection-button selection-button-success" style="padding: 12px 30px; font-size: 16px;">';
print '<i class="fa fa-'.($isEditMode ? 'save' : 'check').'"></i> ';
print $isEditMode ? $langs->trans("MettreAJourSortie") : $langs->trans("ConfirmerSortie");
print '</button>';
print '</div>';

print '</form>';
print '</div>'; // .selection-container

// ============================================================================
// 🔹 STYLES SUPPLÉMENTAIRES
// ============================================================================
?>
<style>
.liste_total {
    font-weight: 600;
    font-size: 15px;
}

.liste_total td {
    padding: 15px 12px;
    border-top: 2px solid #dee2e6;
}

.selection-table small.opacitymedium {
    color: #6c757d;
    font-size: 12px;
}

.selection-button-success {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    border: none;
}

.selection-button-success:hover {
    background: linear-gradient(135deg, #219a52 0%, #27ae60 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
}
</style>

<script>
// Validation avant soumission
document.querySelector('form').addEventListener('submit', function(e) {
    const products = document.querySelectorAll('input[name="products[]"]');
    let hasProducts = false;
    
    products.forEach(input => {
        if (input.value && input.value > 0) {
            hasProducts = true;
        }
    });
    
    if (!hasProducts) {
        alert("<?php echo $langs->trans('VeuillezSelectionnerProduit'); ?>");
        e.preventDefault();
        return false;
    }
    
    const totalCartons = <?php echo $totalCartons; ?>;
    const totalPoids = <?php echo $totalPoids; ?>;
    
    if (totalCartons <= 0 || totalPoids <= 0) {
        alert("<?php echo $langs->trans('QuantitesInvalides'); ?>");
        e.preventDefault();
        return false;
    }
    
    // Confirmation avant validation
    const message = "<?php echo $isEditMode ? $langs->trans('ConfirmerMiseAJour') : $langs->trans('ConfirmerCreation'); ?>";
    if (!confirm(message)) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php
llxFooter();
$db->close();
?>