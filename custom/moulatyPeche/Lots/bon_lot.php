<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

global $db, $langs, $user, $conf;

$langs->loadLangs(['abricot@abricot', 'main', 'womapeche@womapeche']);
$form = new Form($db);

if (empty($user->rights->moulatyPeche->read_b)) {
    accessforbidden($langs->trans('AccesReserveAdmin'));
}

// ============================================================================
// 🔹 PARAMÈTRES
// ============================================================================
$id_lot = GETPOST('id_lot', 'int');
$action = GETPOST('action', 'alpha');
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$commentaire = GETPOST('commentaire', 'restricthtml');
$en = new Entrepot($db);
$en->fetch($fk_entrepot);

// ============================================================================
// 🔹 VÉRIFICATION BON D'ENTRÉE EXISTANT
// ============================================================================
$sql = "SELECT ref, fk_bonentree FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".$id_lot;
$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    $bon = $db->fetch_object($resql)->fk_bonentree;
    if ($bon > 0){
        accessforbidden($langs->trans("BonEntreeExisteDeja"));
        exit;
    }
}

// ============================================================================
// 🔹 EN-TÊTE DE PAGE
// ============================================================================
llxHeader('', $langs->trans('CreationBonEntree'));

// Importer le CSS personnalisé
print '<link rel="stylesheet" href="../bon_style.css">';

print '<div class="selection-container">';

// Header avec style CSS
print '<div class="selection-header">';
print '<h1><i class="fa fa-box-open"></i> '.$langs->trans("CreationBonEntreeStock").'</h1>';
print '</div>';

// ============================================================================
// 🔹 FORMULAIRE PRINCIPAL
// ============================================================================
if ($id_lot > 0 && $action == '') {

    // 🔸 Calcul total poids du lot
    $sql = "SELECT SUM(nb_carton * poids_carton) AS total_poids FROM " . MAIN_DB_PREFIX . "pech_lotdet WHERE fk_lot = " . (int)$id_lot;
    $resql = $db->query($sql);
    $total_poids = ($resql && $db->num_rows($resql) > 0) ? $db->fetch_object($resql)->total_poids : 0;

    print '<form method="POST" action="bonentree_create.php?id_lot='.$id_lot.'">';
    print '<input type="hidden" name="action" value="create">';
    print '<input type="hidden" name="token" value="'.newToken().'">';

    // ============================================================================
    // 🔹 INFORMATIONS DU BON D'ENTRÉE
    // ============================================================================
    print '<div class="filter-section">';
    print '<div class="filter-title">';
    print '<i class="fa fa-info-circle"></i> '.$langs->trans("InformationsBonEntree");
    print '</div>';

    print '<table class="filter-table">';
    print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("ConfigurationEntree").'</th></tr>';

    print '<tr>';
    print '<td width="30%"><label class="selection-label"><i class="fa fa-warehouse"></i> '.$langs->trans("Entrepot").'</label></td>';
    print '<td>';
    print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';
    print '<strong>'.dol_escape_htmltag($en->ref).'</strong>';
    print '</td>';
    print '</tr>';

    print '<tr>';
    print '<td><label class="selection-label"><i class="fa fa-comment"></i> '.$langs->trans("Commentaire").'</label></td>';
    print '<td><textarea name="commentaire" rows="2" class="filter-input">'.$commentaire.'</textarea></td>';
    print '</tr>';

    print '</table>';

    // 🔸 Totaux globaux
    print '<div class="selection-actions" style="background: #f8f9fa; justify-content: space-around; margin-top: 15px;">';
    print '<div class="info-badge">';
    print '<i class="fa fa-weight-scale"></i> ';
    print '<strong>'.$langs->trans("PoidsTotalLot").'</strong>: '.price($total_poids).' kg';
    print '</div>';
    print '<div class="info-badge">';
    print '<i class="fa fa-money-bill"></i> ';
    print '<strong>'.$langs->trans("MontantTotalServices").'</strong>: <span id="total_service">0</span> '.$conf->currency;
    print '</div>';
    print '</div>';

    print '</div>'; // .filter-section

    // ============================================================================
    // 🔹 PRODUITS ISSUS DU LOT
    // ============================================================================
    $sql2 = "SELECT p.rowid as fk_product, p.ref, p.label, d.nb_carton, d.poids_carton
             FROM " . MAIN_DB_PREFIX . "pech_lotdet d 
             INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = d.fk_product
             WHERE d.fk_lot = " . (int)$id_lot;
    $resql2 = $db->query($sql2);

    print '<div class="selection-section">';
    print '<div class="selection-title">';
    print '<i class="fa fa-fish"></i> '.$langs->trans("ProduitsIssusLot");
    print '</div>';

    if ($resql2 && $db->num_rows($resql2) > 0) {
        print '<div class="selection-table-container">';
        print '<table class="selection-table">';
        print '<thead>';
        print '<tr>';
        print '<th><i class="fa fa-cube"></i> '.$langs->trans("Produit").'</th>';
        print '<th class="center"><i class="fa fa-hashtag"></i> '.$langs->trans("NbCartons").'</th>';
        print '<th class="center"><i class="fa fa-balance-scale"></i> '.$langs->trans("PoidsCartonKg").'</th>';
        print '<th class="center"><i class="fa fa-calculator"></i> '.$langs->trans("PoidsTotalKg").'</th>';
        print '</tr>';
        print '</thead>';
        print '<tbody>';

        while ($obj = $db->fetch_object($resql2)) {
            $total = $obj->nb_carton * $obj->poids_carton;
            print '<tr>';
            print '<td>';
            print '<strong>'.dol_escape_htmltag($obj->ref).'</strong><br>';
            print '<small class="opacitymedium">'.dol_escape_htmltag($obj->label).'</small>';
            print '</td>';
            print '<td class="center"><strong>'.$obj->nb_carton.'</strong></td>';
            print '<td class="center"><strong>'.price($obj->poids_carton).'</strong></td>';
            print '<td class="center"><strong>'.price($total).'</strong></td>';
            print '</tr>';

            // Champs cachés pour POST
            print '<input type="hidden" name="prod_fk_product[]" value="' . (int)$obj->fk_product . '">';
            print '<input type="hidden" name="prod_nb_carton[]" value="' . dol_escape_htmltag($obj->nb_carton) . '">';
            print '<input type="hidden" name="prod_poids_carton[]" value="' . dol_escape_htmltag($obj->poids_carton) . '">';
        }

        print '</tbody>';
        print '</table>';
        print '</div>';
    } else {
        print '<div class="selection-empty">';
        print '<i class="fa fa-box-open"></i>';
        print '<p>'.$langs->trans("AucunProduitTrouve").'</p>';
        print '</div>';
    }

    print '</div>'; // .selection-section

    // ============================================================================
    // 🔹 PRODUITS CONSOMMÉS
    // ============================================================================
    $sqlConsom = "
        SELECT p.rowid AS product_id, p.ref, p.label, p.pmp
        FROM ".MAIN_DB_PREFIX."product p
        INNER JOIN ".MAIN_DB_PREFIX."categorie_product cp ON cp.fk_product = p.rowid
        INNER JOIN ".MAIN_DB_PREFIX."categorie c ON c.rowid = cp.fk_categorie
        WHERE c.label = 'Consommation'
    ";
    $resConsom = $db->query($sqlConsom);

    $sqlCartons = "SELECT SUM(nb_carton) AS nb_cartons FROM ".MAIN_DB_PREFIX."pech_lotdet WHERE fk_lot = ".(int)$id_lot;
    $nb_cartons_lot = ($db->query($sqlCartons) && $db->fetch_object($db->query($sqlCartons))->nb_cartons) ? $db->fetch_object($db->query($sqlCartons))->nb_cartons : 0;

    print '<div class="selection-section">';
    print '<div class="selection-title">';
    print '<i class="fa fa-cogs"></i> '.$langs->trans("ProduitsConsommes");
    print '</div>';

    print '<div class="selection-table-container">';
    print '<table class="selection-table">';
    print '<thead>';
    print '<tr>';
    print '<th><i class="fa fa-cube"></i> '.$langs->trans("Description").'</th>';
    print '<th class="center"><i class="fa fa-balance-scale"></i> '.$langs->trans("Quantite").'</th>';
    print '<th class="center"><i class="fa fa-tag"></i> '.$langs->trans("PrixUnitaire").'</th>';
    print '<th class="center"><i class="fa fa-calculator"></i> '.$langs->trans("Total").'</th>';
    print '<th class="center"><i class="fa fa-gears"></i> '.$langs->trans("Actions").'</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';

    if($resConsom && $db->num_rows($resConsom) > 0) {
        while($consom = $db->fetch_object($resConsom)) {
            $total = $nb_cartons_lot * $consom->pmp;
            print '<tr>';
            print '<td>';
            print '<strong>'.dol_escape_htmltag($consom->ref).'</strong><br>';
            print '<small class="opacitymedium">'.dol_escape_htmltag($consom->label).'</small>';
            print '<input type="hidden" name="ids[]" value="'.$consom->product_id.'">';
            print '</td>';
            print '<td class="center"><input type="number" name="qte[]" value="'.$nb_cartons_lot.'" min="0" step="1" class="facturation-input qte-input" oninput="updateTotals()"></td>';
            print '<td class="center"><input type="number" name="pu[]" value="'.number_format($consom->pmp, 2, '.', '').'" min="0" step="0.01" class="facturation-input pu-input" oninput="updateTotals()"></td>';
            print '<td class="center"><span class="line_total">'.price($total).'</span></td>';
            print '<td class="center"><button type="button" class="facturation-btn danger" onclick="removeRow(this)"><i class="fa fa-trash"></i></button></td>';
            print '</tr>';
        }
    }
    print '</tbody>';
    print '</table>';
    print '</div>';
    print '</div>'; // .selection-section

    // ============================================================================
    // 🔹 SERVICES MANUELS
    // ============================================================================
    $sql = "SELECT rowid, ref, label, price FROM ".MAIN_DB_PREFIX."product WHERE fk_product_type = 1 AND entity = ".$conf->entity;
    $services = array();
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $services[$obj->rowid] = $obj->ref." - ".$obj->label;
        }
    }

    $selectServiceHTML = str_replace("\n", "", $form->selectarray('ids[]', $services, '', 0, 0, '', 0, 'minwidth200 service-select'));

    print '<div class="selection-section">';
    print '<div class="selection-title">';
    print '<i class="fa fa-hand-holding"></i> '.$langs->trans("AjouterServicesManuels");
    print '</div>';

    print '<div class="selection-table-container">';
    print '<table class="selection-table" id="serviceTable">';
    print '<thead>';
    print '<tr>';
    print '<th><i class="fa fa-cube"></i> '.$langs->trans("Service").'</th>';
    print '<th class="center"><i class="fa fa-balance-scale"></i> '.$langs->trans("Quantite").'</th>';
    print '<th class="center"><i class="fa fa-tag"></i> '.$langs->trans("PrixUnitaire").'</th>';
    print '<th class="center"><i class="fa fa-calculator"></i> '.$langs->trans("Total").'</th>';
    print '<th class="center"><i class="fa fa-gears"></i> '.$langs->trans("Actions").'</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';
    // Les lignes de services seront ajoutées dynamiquement
    print '</tbody>';
    print '</table>';
    print '</div>';

    print '<div class="selection-actions">';
    print '<button type="button" class="selection-button selection-button-success" onclick="addServiceRow()">';
    print '<i class="fa fa-plus"></i> '.$langs->trans("AjouterService");
    print '</button>';
    print '</div>';
    print '</div>'; // .selection-section

    // ============================================================================
    // 🔹 BOUTON DE CRÉATION
    // ============================================================================
    print '<div class="selection-actions">';
    print '<button type="submit" class="selection-button selection-button-primary submit-btn">';
    print '<i class="fa fa-check-circle"></i> '.$langs->trans("CreerBonEntree");
    print '</button>';
    print '</div>';

    print '</form>';
}

print '</div>'; // .selection-container

// ============================================================================
// 🔹 SCRIPT JS
// ============================================================================
?>
<script>
var selectServiceHTML = <?php echo json_encode($selectServiceHTML); ?>;
var currency = <?php echo json_encode($conf->currency); ?>;

/**
 * Met à jour les totaux
 */
function updateTotals() {
    var qtes = document.getElementsByName("qte[]");
    var pus = document.getElementsByName("pu[]");
    var totals = document.getElementsByClassName("line_total");
    var total_service = 0;
    
    for (var i = 0; i < qtes.length; i++) {
        var q = parseFloat(qtes[i].value) || 0;
        var p = parseFloat(pus[i].value) || 0;
        var t = q * p;
        totals[i].textContent = t.toFixed(2);
        total_service += t;
    }
    
    document.getElementById("total_service").textContent = total_service.toFixed(2);
}

/**
 * Ajoute une ligne de service
 */
function addServiceRow() {
    var table = document.getElementById("serviceTable").getElementsByTagName('tbody')[0];
    var row = table.insertRow(-1);
    
    row.innerHTML = `
        <td>${selectServiceHTML}</td>
        <td class="center">
            <input type="number" name="qte[]" value="1" min="0" step="0.01" class="facturation-input qte-input" oninput="updateTotals()">
        </td>
        <td class="center">
            <input type="number" name="pu[]" value="0" min="0" step="0.01" class="facturation-input pu-input" oninput="updateTotals()">
        </td>
        <td class="center">
            <span class="line_total">0</span>
        </td>
        <td class="center">
            <button type="button" class="facturation-btn danger" onclick="removeServiceRow(this)">
                <i class="fa fa-trash"></i>
            </button>
        </td>
    `;
    
    updateTotals();
}

/**
 * Supprime une ligne
 */
function removeServiceRow(btn) {
    var row = btn.closest('tr');
    row.remove();
    updateTotals();
}

/**
 * Supprime une ligne de produit consommé
 */
function removeRow(btn) {
    var row = btn.closest('tr');
    row.remove();
    updateTotals();
}

/**
 * Validation avant soumission
 */
document.querySelector('form').addEventListener('submit', function(e) {
    var confirmed = confirm("<?php echo $langs->trans('ConfirmerCreationBonEntree'); ?>");
    if (!confirmed) {
        e.preventDefault();
        return false;
    }
});

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    updateTotals();
});
</script>

<?php
llxFooter();
?>