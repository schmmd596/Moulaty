<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $langs, $user, $conf;

$langs->loadLangs(['abricot@abricot', 'main', 'womapeche@womapeche']);
$form = new Form($db);

// ============================================================================
// 🔹 PARAMÈTRES
// ============================================================================
$id_sortie = GETPOST('id_sortie', 'int');
if (!$id_sortie) {
    accessforbidden($langs->trans("AucunIdentifiantSortie"));
    exit;
}
if (empty($user->rights->moulatyPeche->read_b)) {
    accessforbidden($langs->trans("AccesReserveAdmin"));
}

// ============================================================================
// 🔹 RÉCUPÉRATION DES DONNÉES PRINCIPALES
// ============================================================================
$sql = "SELECT s.*, 
               es.ref AS ref_source, 
               ed.ref AS ref_dest, 
               c.nom AS client_name
        FROM ".MAIN_DB_PREFIX."pech_sortie s
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot es ON es.rowid = s.fk_entrepot_source
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot ed ON ed.rowid = s.fk_entrepot_dest
        LEFT JOIN ".MAIN_DB_PREFIX."societe c ON c.rowid = s.fk_client
        WHERE s.rowid = ".((int)$id_sortie);

$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) {
    accessforbidden($langs->trans("BonSortieIntrouvable"));
    exit;
}

$sortie = $db->fetch_object($resql);

// ============================================================================
// 🔹 EN-TÊTE DE PAGE
// ============================================================================
llxHeader('', $langs->trans('DetailsBonSortie'));
print '<link rel="stylesheet" href="../bon_style.css">';

print '<div class="selection-container">';

// Header avec style CSS
print '<div class="selection-header">';
print '<h1><i class="fa fa-truck"></i> '.$langs->trans("BonSortie").' : '.dol_escape_htmltag($sortie->ref).'</h1>';
print '</div>';

// ============================================================================
// 🔹 INFORMATIONS PRINCIPALES
// ============================================================================
print '<div class="filter-section">';
print '<div class="filter-title">';
print '<i class="fa fa-info-circle"></i> '.$langs->trans("InformationsGenerales");
print '</div>';

print '<table class="filter-table">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("DetailsSortie").'</th></tr>';

print '<tr>';
print '<td width="30%"><label class="selection-label"><i class="fa fa-tag"></i> '.$langs->trans("Type").'</label></td>';
print '<td>';
print '<span class="selection-badge '.($sortie->type == 0 ? 'badge-info' : 'badge-success').'">';
print $sortie->type == 0 ? $langs->trans("TransfertInterne") : $langs->trans("Vente");
print '</span>';
print '</td>';
print '</tr>';

print '<tr>';
print '<td><label class="selection-label"><i class="fa fa-warehouse"></i> '.$langs->trans("EntrepotSource").'</label></td>';
print '<td><strong>'.dol_escape_htmltag($sortie->ref_source).'</strong></td>';
print '</tr>';

if ($sortie->type == 0) {
    print '<tr>';
    print '<td><label class="selection-label"><i class="fa fa-boxes-stacked"></i> '.$langs->trans("EntrepotDestination").'</label></td>';
    print '<td><strong>'.dol_escape_htmltag($sortie->ref_dest).'</strong></td>';
    print '</tr>';
}

if ($sortie->type == 1) {
    print '<tr>';
    print '<td><label class="selection-label"><i class="fa fa-user-tie"></i> '.$langs->trans("Client").'</label></td>';
    print '<td><strong>'.dol_escape_htmltag($sortie->client_name).'</strong></td>';
    print '</tr>';
}

if (!empty($sortie->commentaire)) {
    print '<tr>';
    print '<td><label class="selection-label"><i class="fa fa-comment"></i> '.$langs->trans("Commentaire").'</label></td>';
    print '<td>'.dol_escape_htmltag($sortie->commentaire).'</td>';
    print '</tr>';
}

print '</table>';

// Informations résumées
print '<div class="selection-actions" style="background: #f8f9fa; justify-content: space-around; margin-top: 15px;">';
print '<div class="info-badge">';
print '<i class="fa fa-weight-scale"></i> ';
print '<strong>'.$langs->trans("PoidsTotal").'</strong>: '.price($sortie->poids_total).' kg';
print '</div>';
print '<div class="info-badge">';
print '<i class="fa fa-box"></i> ';
print '<strong>'.$langs->trans("NbCartons").'</strong>: '.(int)$sortie->nb_carton_total;
print '</div>';
print '<div class="info-badge">';
print '<i class="fa fa-money-bill"></i> ';
print '<strong>'.$langs->trans("TotalFrais").'</strong>: '.price($sortie->total_frais).' '.$conf->currency;
print '</div>';
print '</div>';

print '</div>'; // .filter-section

// ============================================================================
// 🔹 PRODUITS LIÉS À LA SORTIE
// ============================================================================
$sql2 = "SELECT sp.rowid, sp.fk_product, pr.ref, pr.label, sp.nb_carton, sp.poids_total
         FROM ".MAIN_DB_PREFIX."pech_sortiedetprod sp
         LEFT JOIN ".MAIN_DB_PREFIX."product pr ON pr.rowid = sp.fk_product
         WHERE sp.fk_sortie = ".((int)$id_sortie);

$resql2 = $db->query($sql2);

print '<div class="selection-section">';
print '<div class="selection-title">';
print '<i class="fa fa-boxes-stacked"></i> '.$langs->trans("ProduitsSortie");
print '</div>';

$total_sortie = 0;

if ($resql2 && $db->num_rows($resql2) > 0) {
    print '<div class="selection-table-container">';
    print '<table class="selection-table">';
    print '<thead>';
    print '<tr>';
    print '<th><i class="fa fa-cube"></i> '.$langs->trans("Produit").'</th>';
    print '<th class="center"><i class="fa fa-hashtag"></i> '.$langs->trans("NbCartons").'</th>';
    print '<th class="center"><i class="fa fa-balance-scale"></i> '.$langs->trans("PoidsTotalKg").'</th>';
    print '<th class="center"><i class="fa fa-money-bill"></i> '.$langs->trans("ValeurTotale").'</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';

    while ($obj = $db->fetch_object($resql2)) {
        // Récupération des cartons liés (sans frais au départ)
        $sql_cartons = "SELECT c.rowid, c.poids, (c.prix_moyen + c.frais) AS valeur
                        FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton AS sc
                        LEFT JOIN ".MAIN_DB_PREFIX."pech_carton AS c ON c.rowid = sc.fk_carton
                        WHERE sc.fk_sortiedetprod = ".$obj->rowid;
        $res_cartons = $db->query($sql_cartons);

        $valeur = 0;
        if ($res_cartons && $db->num_rows($res_cartons) > 0) {
            while ($c = $db->fetch_object($res_cartons)) {
                $valeur += $c->valeur;
            }
        }

        $total_sortie += $valeur;

        print '<tr>';
        print '<td>';
        print '<strong>'.dol_escape_htmltag($obj->ref).'</strong><br>';
        print '<small class="opacitymedium">'.dol_escape_htmltag($obj->label).'</small>';
        print '</td>';
        print '<td class="center"><strong>'.$obj->nb_carton.'</strong></td>';
        print '<td class="center"><strong>'.price($obj->poids_total).'</strong></td>';
        print '<td class="center"><strong>'.price($valeur).' '.$conf->currency.'</strong></td>';
        print '</tr>';
    }

    print '</tbody>';
    print '<tfoot>';
    print '<tr class="liste_total" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">';
    print '<td colspan="3" class="right"><strong>'.$langs->trans("TotalProduits").' :</strong></td>';
    print '<td class="center"><strong>'.price($total_sortie).' '.$conf->currency.'</strong></td>';
    print '</tr>';
    print '</tfoot>';
    print '</table>';
    print '</div>';
} else {
    print '<div class="selection-empty">';
    print '<i class="fa fa-box-open"></i>';
    print '<p>'.$langs->trans("AucunProduitSortie").'</p>';
    print '</div>';
}

print '</div>'; // .selection-section

// ============================================================================
// 🔹 SERVICES MANUELS
// ============================================================================
$sql = "SELECT rowid, ref, label FROM ".MAIN_DB_PREFIX."product WHERE fk_product_type = 1 AND entity = ".$conf->entity;
$services = array();
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $services[$obj->rowid] = $obj->ref." - ".$obj->label;
    }
}

print '<div class="selection-section">';
print '<div class="selection-title">';
print '<i class="fa fa-cogs"></i> '.$langs->trans("AjouterServicesManuels");
print '</div>';

print '<form method="POST" action="bonsortie_validate.php">';
print '<input type="hidden" name="id_sortie" value="'.$id_sortie.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

$selectServiceHTML = str_replace("\n", "", $form->selectarray('ids[]', $services, '', 0, 0, '', 0, 'minwidth200 service-select'));

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
print '<tfoot>';
print '<tr class="liste_total" style="background: #f6f6f6; font-weight: bold;">';
print '<td colspan="3" class="right"><strong>'.$langs->trans("TotalServices").' :</strong></td>';
print '<td class="center" id="total_service"><strong>0.00</strong></td>';
print '<td></td>';
print '</tr>';
print '</tfoot>';
print '</table>';
print '</div>';

print '<div class="selection-actions">';
print '<button type="button" class="selection-button selection-button-success" onclick="addServiceRow()">';
print '<i class="fa fa-plus"></i> '.$langs->trans("AjouterService");
print '</button>';
print '</div>';

// ============================================================================
// 🔹 TOTAL GÉNÉRAL (PRODUITS + SERVICES)
// ============================================================================
print '<div class="facturation-total-section">';
print '<div class="total-grid">';
print '<div class="total-label">'.$langs->trans("TotalSortieProduitsServices").'</div>';
print '<div class="total-value">';
print '<span id="total_global" class="grand-total-input">'.price($total_sortie).' '.$conf->currency.'</span>';
print '</div>';
print '</div>';
print '</div>';

// ============================================================================
// 🔹 BOUTON VALIDER
// ============================================================================
print '<div class="selection-actions">';
print '<button type="submit" class="selection-button selection-button-primary submit-btn">';
print '<i class="fa fa-check-circle"></i> '.$langs->trans("ValiderBonSortie");
print '</button>';
print '</div>';

print '</form>';
print '</div>'; // .selection-section
print '</div>'; // .selection-container

// ============================================================================
// 🔹 STYLES SUPPLÉMENTAIRES
// ============================================================================
?>
<style>
.info-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    font-weight: 600;
}

.service-select {
    min-width: 250px;
}

.grand-total-input {
    font-size: 18px;
    font-weight: 700;
    color: #2c3e50;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    display: inline-block;
}

.submit-btn {
    padding: 12px 30px;
    font-size: 16px;
}
</style>

<script>
// ============================================================================
// 🔹 VARIABLES ET FONCTIONS JAVASCRIPT
// ============================================================================
var baseTotal = <?php echo json_encode($total_sortie); ?>;
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
    
    document.getElementById("total_service").innerHTML = '<strong>' + total_service.toFixed(2) + '</strong>';
    document.getElementById("total_global").textContent = (baseTotal + total_service).toFixed(2) + ' ' + currency;
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
 * Supprime une ligne de service
 */
function removeServiceRow(btn) {
    var row = btn.closest('tr');
    row.remove();
    updateTotals();
}

/**
 * Validation avant soumission
 */
document.querySelector('form').addEventListener('submit', function(e) {
    var confirmed = confirm("<?php echo $langs->trans('ConfirmerValidationBonSortie'); ?>");
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