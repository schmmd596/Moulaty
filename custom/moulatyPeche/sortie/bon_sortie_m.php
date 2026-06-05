<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

// ============================================================================
// 🔹 INCLUSION DES FONCTIONS DE DEVISE
// ============================================================================
require_once '../functions.php';

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
// 🔹 RÉCUPÉRATION DE LA DEVISE DE L'ENTREPÔT
// ============================================================================
$id_devise_entrepot = getDeviseEntrepot($db, $sortie->fk_entrepot_source);
$code_devise_entrepot = getCodeDeviseFromId($db, $id_devise_entrepot);
$code_devise = $code_devise_entrepot;
$nom_devise_entrepot = getNomMonnaie($db, $id_devise_entrepot);

// Récupérer le taux de change


// ============================================================================
// 🔹 EN-TÊTE DE PAGE
// ============================================================================
llxHeader('', $langs->trans('DetailsBonSortie'));
print '<link rel="stylesheet" href="../bon_style.css">';

print '<div class="selection-container">';

// Header avec style CSS et info devise
print '<div class="selection-header">';
print '<h1><i class="fa fa-truck"></i> '.$langs->trans("BonSortie").' : '.dol_escape_htmltag($sortie->ref).'</h1>';
$sqlRate = "
    SELECT 
        mcr.rate,
        mcr.date_sync
    FROM ".MAIN_DB_PREFIX."multicurrency_rate AS mcr
    WHERE mcr.fk_multicurrency = ".(int) $id_devise_entrepot."
    ORDER BY mcr.date_sync DESC
    LIMIT 1
";
$resRate = $db->query($sqlRate);
$exchangeRate = 1;
$exchangeDate = '';
if ($resRate && $db->num_rows($resRate) > 0) {
    $rateRow = $db->fetch_object($resRate);
    $exchangeRate = (float) $rateRow->rate;
    $exchangeDate = $db->jdate($rateRow->date_sync);
}
// Affichage devise + taux de change

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
// 🔹 PRODUITS LIÉS À LA SORTIE (Montants en MRO)
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

$total_sortie_mro = 0; // Total en MRO
$total_sortie_devise = 0; // Total dans la devise de l'entrepôt

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
        // Récupération des cartons liés (valeur en MRO)
        $sql_cartons = "SELECT c.rowid, c.poids, (c.prix_moyen + c.frais) AS valeur
                        FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton AS sc
                        LEFT JOIN ".MAIN_DB_PREFIX."pech_carton AS c ON c.rowid = sc.fk_carton
                        WHERE sc.fk_sortiedetprod = ".$obj->rowid;
        $res_cartons = $db->query($sql_cartons);

        $valeur_mro = 0;
        if ($res_cartons && $db->num_rows($res_cartons) > 0) {
            while ($c = $db->fetch_object($res_cartons)) {
                $valeur_mro += $c->valeur;
            }
        }

        $total_sortie_mro += $valeur_mro;
        
        // Calculer la valeur dans la devise de l'entrepôt
        $valeur_devise = $valeur_mro;
        if ($code_devise_entrepot !== 'MRO') {
            $valeur_devise = $valeur_mro * $exchangeRate;
        }

        print '<tr>';
        print '<td>';
        print '<strong>'.dol_escape_htmltag($obj->ref).'</strong><br>';
        print '<small class="opacitymedium">'.dol_escape_htmltag($obj->label).'</small>';
        print '</td>';
        print '<td class="center"><strong>'.$obj->nb_carton.'</strong></td>';
        print '<td class="center"><strong>'.price($obj->poids_total).'</strong></td>';
        print '<td class="center">';
        if ($code_devise_entrepot !== 'MRO') {
            print '<br><strong>('.price(round($valeur_devise, 2)).' '.$code_devise_entrepot.')</strong>';
        }
        print '<br><small class="text-muted">'.price($valeur_mro).' '.$conf->currency.'</small>';
        
        print '</td>';
        print '</tr>';
    }

    // Calcul du total dans la devise de l'entrepôt
    $total_sortie_devise = $total_sortie_mro;
    if ($code_devise_entrepot !== 'MRO') {
        $total_sortie_devise = $total_sortie_mro * $exchangeRate;
    }

    print '</tbody>';
    print '<tfoot>';
    print '<tr class="liste_total" style="background: linear-gradient(135deg, #667eea 0%, #e2dbe9ff 100%); color: white;">';
    print '<td colspan="3" class="right"><strong>'.$langs->trans("TotalProduits").' :</strong></td>';
    print '<td class="center">';
    if ($code_devise_entrepot !== 'MRO') {
        print '<strong>'.price(round($total_sortie_devise, 2)).' '.$code_devise_entrepot.'</strong>';
    }
    print '<br><small class="text-muted">'.price($total_sortie_mro).' '.$conf->currency.'</small>';
    
    print '</td>';
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
// 🔹 SERVICES MANUELS (Prix saisis en devise entrepôt)
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
print '<small class="text-muted" style="margin-left: 10px;"><i class="fa fa-info-circle"></i> ';
print $langs->trans("LesPrixSontSaisisEnDevise").' <strong>'.$code_devise_entrepot.'</strong>';
print '</small>';
print '</div>';

print '<form method="POST" action="bonsortie_validate_m.php">';
print '<input type="hidden" name="id_sortie" value="'.$id_sortie.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

$selectServiceHTML = str_replace("\n", "", $form->selectarray('ids[]', $services, '', 0, 0, '', 0, 'minwidth200 service-select'));

print '<div class="selection-table-container">';
print '<table class="selection-table" id="serviceTable">';
print '<thead>';
print '<tr>';
print '<th><i class="fa fa-cube"></i> '.$langs->trans("Service").'</th>';
print '<th class="center"><i class="fa fa-balance-scale"></i> '.$langs->trans("Quantite").'</th>';
print '<th class="center"><i class="fa fa-tag"></i> '.$langs->trans("PrixUnitaire").' <small>('.$code_devise_entrepot.')</small></th>';
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
print '<td class="center">';
print '<span id="total_service_devise" class="currency-total">0.00 '.$code_devise_entrepot.'</span><br>';
print '<small id="total_service_mro" class="text-muted">0.00 '.$conf->currency.'</small>';
print '</td>';
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
// 🔹 TOTAL GÉNÉRAL (PRODUITS + SERVICES) - Affichage en 2 devises
// ============================================================================
// Calcul initial pour affichage
$total_global_mro = $total_sortie_mro;
$total_global_devise = $total_sortie_devise;

print '<div class="facturation-total-section">';
print '<div class="total-grid">';
print '<div class="total-label">'.$langs->trans("TotalSortieProduitsServices").'</div>';
print '<div class="total-value">';
print '<div class="currency-total" id="total_global_devise">'.price(round($total_global_devise, 2)).' '.$code_devise_entrepot.'</div>';
print '<small class="text-muted" id="total_global_mro">'.price($total_global_mro).' '.$conf->currency.'</small>';
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

.currency-total {
    font-size: 18px;
    font-weight: 700;
    color: #2c3e50;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    display: inline-block;
    margin-bottom: 5px;
}

.submit-btn {
    padding: 12px 30px;
    font-size: 16px;
}

.currency-rate-box {
    display: inline-block;
    margin-left: 20px;
    padding: 5px 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.currency-rate-box .badge {
    margin-right: 10px;
}

.rate-value, .rate-date {
    margin-left: 10px;
    color: #6c757d;
    font-size: 0.9em;
}

.text-muted {
    color: #6c757d !important;
    font-size: 0.9em;
}
</style>

<script>
// ============================================================================
// 🔹 VARIABLES ET FONCTIONS JAVASCRIPT
// ============================================================================
var baseTotalMRO = <?php echo json_encode($total_sortie_mro); ?>;
var baseTotalDevise = <?php echo json_encode($total_sortie_devise); ?>;
var selectServiceHTML = <?php echo json_encode($selectServiceHTML); ?>;
var currencyMRO = <?php echo json_encode($conf->currency); ?>;
var currencyEntrepot = <?php echo json_encode($code_devise_entrepot); ?>;
var exchangeRate = <?php echo json_encode($exchangeRate); ?>;

/**
 * Met à jour les totaux avec conversion de devise
 */
function updateTotals() {
    var qtes = document.getElementsByName("qte[]");
    var pus = document.getElementsByName("pu[]");
    var totalsDevise = document.getElementsByClassName("line_total_devise");
    var totalsMRO = document.getElementsByClassName("line_total_mro");
    
    var total_service_devise = 0;
    var total_service_mro = 0;
    
    for (var i = 0; i < qtes.length; i++) {
        var q = parseFloat(qtes[i].value) || 0;
        var p = parseFloat(pus[i].value) || 0; // Prix en devise entrepôt
        
        // Total en devise entrepôt
        var tDevise = q * p;
        
        // Conversion en MRO
        var tMRO = tDevise;
        if (currencyEntrepot !== 'MRO') {
            tMRO = tDevise / exchangeRate;
        }
        
        if (totalsDevise[i]) totalsDevise[i].textContent = tDevise.toFixed(2);
        if (totalsMRO[i]) totalsMRO[i].textContent = tMRO.toFixed(2);
        
        total_service_devise += tDevise;
        total_service_mro += tMRO;
    }
    
    // Mise à jour des totaux services
    document.getElementById("total_service_devise").innerHTML = 
        '<strong>' + total_service_devise.toFixed(2) + ' ' + currencyEntrepot + '</strong>';
    document.getElementById("total_service_mro").innerHTML = 
        total_service_mro.toFixed(2) + ' ' + currencyMRO;
    
    // Mise à jour des totaux globaux
    var totalGlobalDevise = baseTotalDevise + total_service_devise;
    var totalGlobalMRO = baseTotalMRO + total_service_mro;
    
    document.getElementById("total_global_devise").textContent = 
        totalGlobalDevise.toFixed(2) + ' ' + currencyEntrepot;
    document.getElementById("total_global_mro").textContent = 
        totalGlobalMRO.toFixed(2) + ' ' + currencyMRO;
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
            <div class="small-text">${currencyEntrepot}</div>
        </td>
        <td class="center">
            <span class="line_total_devise line_total">0</span> ${currencyEntrepot}<br>
            <small class="line_total_mro text-muted">0 ${currencyMRO}</small>
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