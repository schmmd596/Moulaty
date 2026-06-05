<?php
/**
 * Nouvelle Sortie - Interface de création de sorties (Transfert Vente)
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $user, $langs, $conf;
$langs->loadLangs(['stocks', 'main', 'womapeche@womapeche']);

$form = new Form($db);

// ============================================================================
// 📊 Récupération des données
// ============================================================================

// --- Entrepôts ---
$sql = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot WHERE entity = ".$conf->entity;
$resql = $db->query($sql);
$entrepots = array();
if ($resql) {
    while ($obj = $db->fetch_object($resql)) $entrepots[$obj->rowid] = $obj->ref;
}

// --- Clients ---
$clients = array();
$sql2 = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE client = 1 AND entity = ".$conf->entity." ORDER BY nom";
$resql2 = $db->query($sql2);
if ($resql2) {
    while ($obj = $db->fetch_object($resql2)) $clients[$obj->rowid] = $obj->nom;
}

// --- Produits ---
$produits = array();
$resql3 = $db->query("SELECT p.rowid, p.label FROM ".MAIN_DB_PREFIX."product p
INNER JOIN ".MAIN_DB_PREFIX."categorie_product cp ON cp.fk_product = p.rowid
INNER JOIN ".MAIN_DB_PREFIX."categorie c ON c.rowid = cp.fk_categorie
WHERE c.label = 'POISSON' ORDER BY p.label ASC");
if ($resql3) {
    while ($obj = $db->fetch_object($resql3)) $produits[$obj->rowid] = $obj->label;
}

// ============================================================================
// 📊 Récupération des valeurs POST
// ============================================================================
$fk_entrepot_source = GETPOST('fk_entrepot_source', 'int');
$mode_sortie        = GETPOST('mode_sortie', 'alpha');
$fk_entrepot_dest   = GETPOST('fk_entrepot_dest', 'int');
$fk_client_dest     = GETPOST('fk_client_dest', 'int');

// ============================================================================
// 🔒 Vérification des accès
// ============================================================================
include_once '../user_entrepot_access.php';
if (!empty($fk_entrepot_source)) {
    check_user_entrepot_access($fk_entrepot_source);
}

// ============================================================================
// 🏁 En-tête de page
// ============================================================================
llxHeader('', $langs->trans('NouvelleSortie'));
print '<link rel="stylesheet" href="../selection_form_style.css">';

print '<div class="selection-container">';

// Header avec style CSS
print '<div class="selection-header">';
print '<h1><i class="fa fa-dolly"></i> '.$langs->trans("NouvelleSortie").' Par LOT</h1>';
print '</div>';

// ============================================================================
// 📝 Formulaire principal
// ============================================================================
print '<form method="post" action="" id="formSortie">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="type" value="1">';

// ============================================================================
// ℹ️ Section Informations de la sortie
// ============================================================================
print '<div class="filter-section">';
print '<div class="filter-title">';
print '<i class="fa fa-info-circle"></i> '.$langs->trans("InformationsSortie");
print '</div>';

print '<table class="filter-table">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("ConfigurationSortie").'</th></tr>';

// --- Entrepôt source ---
print '<tr>';
print '<td width="35%"><label class="selection-label"><i class="fa fa-warehouse"></i> '.$langs->trans("EntrepotSource").'</label></td>';
print '<td>'.$form->selectarray('fk_entrepot_source', $entrepots, $fk_entrepot_source, 1, 0, 0, '', 'filter-input').'</td>';
print '</tr>';

// --- Mode de sortie (fixé à vente) ---
$mode_sortie = '1';
print '<tr>';
print '<td><label class="selection-label"><i class="fa fa-arrow-right-arrow-left"></i> '.$langs->trans("ModeSortie").'</label></td>';
print '<td>';
print '<span class="selection-badge badge-info">';
print '<i class="fa fa-right-left"></i> '.$langs->trans("TransfertVente");
print '</span>';
print '<input type="hidden" name="mode_sortie" value="1">';
print '</td>';
print '</tr>';

// --- Client destination ---
print '<tr>';
print '<td><label class="selection-label"><i class="fa fa-user-tie"></i> '.$langs->trans("ClientDestination").'</label></td>';
print '<td>'.$form->selectarray('fk_client_dest', $clients, $fk_client_dest, 1, 0, 0, '', 'filter-input').'</td>';
print '</tr>';

print '</table>';

// --- Bouton de confirmation ---
print '<div class="selection-actions">';
if (empty($fk_entrepot_source) || empty($fk_client_dest)) {
    print '<button type="submit" class="selection-button selection-button-primary">';
    print '<i class="fa fa-check"></i> '.$langs->trans("ConfirmerSelection");
    print '</button>';
}
print '</div>';

print '</div>'; // .filter-section

// ============================================================================
// 📦 Section Produits à transférer
// ============================================================================
if (!empty($fk_entrepot_source) && isset($mode_sortie) && $mode_sortie==='1' && !empty($fk_client_dest)) {
    
    print '<div class="selection-section">';
    print '<div class="selection-title">';
    print '<i class="fa fa-list"></i> '.$langs->trans("ProduitsTransférer");
    print '</div>';
    
    print '<div class="selection-table-container">';
    print '<table class="selection-table" id="tableProduits">';
    print '<thead>';
    print '<tr>';
    print '<th><i class="fa fa-cube"></i> '.$langs->trans("Produit").'</th>';
    print '<th><i class="fa fa-layer-group"></i> '.$langs->trans("Lot").' <small>(Optionnel)</small></th>';
    print '<th class="center"><i class="fa fa-hashtag"></i> '.$langs->trans("NbCartons").'</th>';
    print '<th class="center"><i class="fa fa-balance-scale"></i> '.$langs->trans("PoidsKg").'</th>';
    print '<th><i class="fa fa-box-open"></i> '.$langs->trans("StockDisponible").'</th>';
    print '<th class="center"><i class="fa fa-gears"></i> '.$langs->trans("Actions").'</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';
    // Les lignes seront ajoutées dynamiquement par JavaScript
    print '</tbody>';
    print '</table>';
    print '</div>';
    
    print '<div class="selection-actions">';
    print '<button type="button" class="selection-button selection-button-success" onclick="addRow()">';
    print '<i class="fa fa-plus"></i> '.$langs->trans("AjouterProduit");
    print '</button>';
    print '<button type="submit" formaction="confirm.php" class="selection-button selection-button-primary">';
    print '<i class="fa fa-check-circle"></i> '.$langs->trans("ValiderSortie");
    print '</button>';
    print '</div>';
    
    print '</div>'; // .selection-section
}

print '</form>';
print '</div>'; // .selection-container

// ============================================================================
// 🎨 Styles supplémentaires pour le tableau des produits
// ============================================================================
?>
<style>
/* Styles spécifiques pour le tableau des produits */
.selection-table-container {
    margin: 20px 0;
}

#tableProduits {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
}

#tableProduits tr {
    background: #f9fafb;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

#tableProduits tr:hover {
    background: #eef2ff;
    box-shadow: 0 3px 8px rgba(0,0,0,0.08);
}

#tableProduits td {
    padding: 12px;
    vertical-align: middle;
    border-top: none;
}

#tableProduits select,
#tableProduits input {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: white;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

#tableProduits select:focus,
#tableProduits input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99,102,241,0.2);
    outline: none;
}

#tableProduits .product-select,
#tableProduits .lot-select {
    min-width: 200px;
}

#tableProduits button.remove-btn {
    background: #ef4444;
    border: none;
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s, transform 0.2s;
    font-size: 13px;
}

#tableProduits button.remove-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.stock-info {
    font-size: 13px;
    color: #6b7280;
    background: #f3f4f6;
    padding: 6px 10px;
    border-radius: 4px;
    border: 1px solid #e5e7eb;
}

.carton-input, .poids-input {
    text-align: center;
}

.lot-select {
    min-width: 150px;
}

.loading-lots {
    color: #6b7280;
    font-style: italic;
    font-size: 12px;
}

.filter-option {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.filter-option input[type="checkbox"] {
    width: auto;
    margin: 0;
}
</style>

<script>
// ============================================================================
// ⚙️ Variables et fonctions JavaScript
// ============================================================================
var selectProduitHTML = <?php
echo json_encode(
    str_replace("\n", '', $form->selectarray('products[]', array('' => '') + $produits, '', 0, 0, 0, '', 0, 0, 0, '', 'minwidth200 product-select'))
);
?>;

var fk_entrepot_source = <?php echo $fk_entrepot_source ?: 0; ?>;

/**
 * Récupère le stock disponible (sans lot)
 */
function getStockWithoutLot(productId, row) {
    if (productId <= 0 || fk_entrepot_source <= 0) {
        return;
    }
    
    var stockInput = row.querySelector('input[name="stock_dispo[]"]');
    if (stockInput) {
        stockInput.value = '<?php echo $langs->trans("Chargement"); ?>...';
    }
    
    fetch('ajax_lot.php?product_id=' + productId + '&fk_entrepot=' + fk_entrepot_source)
        .then(response => response.json())
        .then(data => {
            if (stockInput && data.nb_carton_dispo !== undefined && data.poids_dispo !== undefined) {
                stockInput.value = data.nb_carton_dispo + ' <?php echo $langs->trans("cartons"); ?> / ' + data.poids_dispo + ' kg';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            if (stockInput) {
                stockInput.value = '<?php echo $langs->trans("ErreurChargement"); ?>';
            }
        });
}

/**
 * Récupère les lots disponibles pour un produit
 */
function getLotsForProduct(productId, row) {
    if (productId <= 0 || fk_entrepot_source <= 0) {
        return;
    }
    
    var lotSelect = row.querySelector('.lot-select');
    if (lotSelect) {
        lotSelect.innerHTML = '<option value=""><?php echo $langs->trans("Chargement"); ?>...</option>';
        lotSelect.disabled = true;
    }
    
    fetch('get_lots.php?product_id=' + productId + '&fk_entrepot=' + fk_entrepot_source)
        .then(response => response.json())
        .then(data => {
            if (lotSelect) {
                var options = '<option value=""><?php echo $langs->trans("TousLesLots"); ?> (Stock total)</option>';
                if (data.lots && data.lots.length > 0) {
                    data.lots.forEach(function(lot) {
                        options += '<option value="' + lot.id + '">' + lot.ref +' ('+lot.date+ ')</option>';
                    });
                }
                lotSelect.innerHTML = options;
                lotSelect.disabled = false;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            if (lotSelect) {
                lotSelect.innerHTML = '<option value=""><?php echo $langs->trans("ErreurChargement"); ?></option>';
            }
        });
}

/**
 * Récupère le stock disponible pour un produit et un lot spécifique
 */
function getStockForProductAndLot(productId, lotId, row) {
    if (productId <= 0) {
        return;
    }
    
    var stockInput = row.querySelector('input[name="stock_dispo[]"]');
    if (stockInput) {
        stockInput.value = '<?php echo $langs->trans("Chargement"); ?>...';
    }
    
    var url = 'ajax_lot.php?product_id=' + productId + '&fk_entrepot=' + fk_entrepot_source;
    
    // Si un lot est spécifié, ajouter le paramètre
    if (lotId && lotId > 0) {
        url += '&fk_lot=' + lotId;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (stockInput && data.nb_carton_dispo !== undefined && data.poids_dispo !== undefined) {
                var lotText = '';
                if (lotId && lotId > 0) {
                    lotText = ' (Lot spécifique)';
                } else {
                    lotText = ' (Stock total)';
                }
                stockInput.value = data.nb_carton_dispo + ' <?php echo $langs->trans("cartons"); ?> / ' + data.poids_dispo + ' kg' + lotText;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            if (stockInput) {
                stockInput.value = '<?php echo $langs->trans("ErreurChargement"); ?>';
            }
        });
}

/**
 * Met à jour la sélection des cartons
 */
function updateRow(element) {
    var row = element.closest('tr');
    var nbCarton = row.querySelector('input[name="nb_carton[]"]');
    var poids = row.querySelector('input[name="poids[]"]');
    var productId = row.querySelector('select[name="products[]"]').value;
    var lotId = row.querySelector('.lot-select').value;
    
    if(productId <= 0) return;

    var inputRowid = row.querySelector('input[name="cartons_rowid[]"]');
    var params = 'product_id=' + productId + '&fk_entrepot=' + fk_entrepot_source;
    
    // Ajouter le lot si spécifié
    if (lotId && lotId > 0) {
        params += '&fk_lot=' + lotId;
    }
    
    params += '&mode=carton&nb_carton=' + nbCarton.value;
    
    fetch('ajax_lot2.php?' + params)
    .then(response => response.json())
    .then(data => {
        if(data.nb_carton_calc !== undefined) nbCarton.value = data.nb_carton_calc;
        if(data.poids_calc !== undefined) poids.value = data.poids_calc;
        if(data.cartons_rowid !== undefined && inputRowid) {
            inputRowid.value = data.cartons_rowid.join(',');
        }
    })
    .catch(error => console.error('Erreur:', error));
}

/**
 * Ajoute une nouvelle ligne de produit
 */
function addRow() {
    var table = document.getElementById('tableProduits').getElementsByTagName('tbody')[0];
    var row = table.insertRow(-1);
    row.className = 'product-row';
    
    // Cellule Produit
    var cellProduit = row.insertCell(0);
    cellProduit.innerHTML = selectProduitHTML;
    
    // Cellule Lot (optionnel)
    var cellLot = row.insertCell(1);
    cellLot.innerHTML = '<select class="lot-select filter-input" name="fk_lot[]" disabled>' +
                        '<option value=""><?php echo $langs->trans("SelectionnerProduitDabord"); ?></option>' +
                        '</select>' +
                        '<small class="loading-lots" style="display: none; margin-top: 4px;">Chargement des lots...</small>';
    
    // Cellule Nombre de cartons
    var cellCartons = row.insertCell(2);
    cellCartons.className = 'center';
    cellCartons.innerHTML = '<input type="number" name="nb_carton[]" value="0" step="1" min="0" class="carton-input filter-input" oninput="updateRow(this)" disabled>';
    
    // Cellule Poids
    var cellPoids = row.insertCell(3);
    cellPoids.className = 'center';
    cellPoids.innerHTML = '<input type="number" name="poids[]" value="0" step="0.01" min="0" readonly class="poids-input filter-input">';
    
    // Cellule Stock disponible
    var cellStock = row.insertCell(4);
    cellStock.innerHTML = '<input type="text" name="stock_dispo[]" value="" readonly class="stock-info filter-input">';
    
    // Cellule Actions
    var cellActions = row.insertCell(5);
    cellActions.className = 'center';
    cellActions.innerHTML = '<input type="hidden" name="cartons_rowid[]" value="">' +
                           '<button type="button" class="remove-btn" onclick="removeRow(this)">' +
                           '<i class="fa fa-trash"></i> <?php echo $langs->trans("Supprimer"); ?>' +
                           '</button>';
    
    // Attacher les événements
    const selectProduit = cellProduit.querySelector('.product-select');
    const selectLot = cellLot.querySelector('.lot-select');
    
    // Événement sur changement de produit
    selectProduit.addEventListener('change', function() {
        var productId = this.value;
        var lotSelect = row.querySelector('.lot-select');
        var cartonInput = row.querySelector('input[name="nb_carton[]"]');
        var stockInput = row.querySelector('input[name="stock_dispo[]"]');
        
        if (productId > 0) {
            // Activer les champs
            cartonInput.disabled = false;
            
            // D'abord, afficher le stock total (sans lot)
            getStockWithoutLot(productId, row);
            
            // Ensuite, charger les lots disponibles
            getLotsForProduct(productId, row);
            
            // Réinitialiser les autres champs
            row.querySelector('input[name="poids[]"]').value = 0;
            row.querySelector('input[name="cartons_rowid[]"]').value = '';
        } else {
            // Désactiver tous les champs
            lotSelect.disabled = true;
            cartonInput.disabled = true;
            lotSelect.innerHTML = '<option value=""><?php echo $langs->trans("SelectionnerProduitDabord"); ?></option>';
            stockInput.value = '';
        }
    });
    
    // Événement sur changement de lot
    selectLot.addEventListener('change', function() {
        var productId = row.querySelector('.product-select').value;
        var lotId = this.value;
        
        if (productId > 0) {
            // Mettre à jour le stock selon la sélection du lot
            getStockForProductAndLot(productId, lotId, row);
        } else {
            var stockInput = row.querySelector('input[name="stock_dispo[]"]');
            if (stockInput) stockInput.value = '';
        }
    });
}

/**
 * Supprime une ligne de produit
 */
function removeRow(btn) {
    var row = btn.closest('tr');
    row.parentNode.removeChild(row);
}

// Ajouter une ligne automatiquement si c'est la première
document.addEventListener('DOMContentLoaded', function() {
    var tableBody = document.getElementById('tableProduits').getElementsByTagName('tbody')[0];
    if (tableBody.rows.length === 0) {
        addRow();
    }
});

</script>

<?php
llxFooter();
$db->close();
?>