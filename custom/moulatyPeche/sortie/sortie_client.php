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
// 🔹 Récupération des données
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
// 🔹 Récupération des valeurs POST
// ============================================================================
$fk_entrepot_source = GETPOST('fk_entrepot_source', 'int');
$mode_sortie        = GETPOST('mode_sortie', 'alpha');
$fk_entrepot_dest   = GETPOST('fk_entrepot_dest', 'int');
$fk_client_dest     = GETPOST('fk_client_dest', 'int');

// ============================================================================
// 🔹 Vérification des accès
// ============================================================================
include_once '../user_entrepot_access.php';
if (!empty($fk_entrepot_source)) {
    check_user_entrepot_access($fk_entrepot_source);
}

// ============================================================================
// 🔹 En-tête de page
// ============================================================================
llxHeader('', $langs->trans('NouvelleSortie'));
print '<link rel="stylesheet" href="../selection_form_style.css">';

print '<div class="selection-container">';

// Header avec style CSS
print '<div class="selection-header">';
print '<h1><i class="fa fa-dolly"></i> '.$langs->trans("NouvelleSortie").'</h1>';
print '</div>';

// ============================================================================
// 🔹 Formulaire principal
// ============================================================================
print '<form method="post" action="" id="formSortie">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="type" value="1">';

// ============================================================================
// 🔹 Section Informations de la sortie
// ============================================================================
print '<div class="filter-section">';
print '<div class="filter-title">';
print '<i class="fa fa-info-circle"></i> '.$langs->trans("InformationsSortie");
print '</div>';

print '<table class="filter-table">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("ConfigurationSortie").'</th>
<th colspan=""><a href="sortie_lot.php" class="btn-sortie-lot">';
echo '<i class="fa fa-boxes"></i> ' . $langs->trans("SortieLOT");
echo '</a></th>
</tr>';

echo '
<style>
.btn-sortie-lot {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
}

.btn-sortie-lot:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
    color: white;
    text-decoration: none;
}

.btn-sortie-lot:active {
    transform: translateY(1px);
}
</style>';
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
// 🔹 Section Produits à transférer
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
// 🔹 Styles supplémentaires pour le tableau des produits
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

#tableProduits .product-select {
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
</style>

<script>
// ============================================================================
// 🔹 Variables et fonctions JavaScript
// ============================================================================
var selectProduitHTML = <?php
echo json_encode(
    str_replace("\n", '', $form->selectarray('products[]', array('' => '') + $produits, '', 0, 0, 0, '', 0, 0, 0, '', 'minwidth200 product-select'))
);
?>;

/**
 * Ajoute une nouvelle ligne de produit
 */
function addRow() {
    var table = document.getElementById('tableProduits').getElementsByTagName('tbody')[0];
    var row = table.insertRow(-1);
    
    // Cellule Produit
    var cellProduit = row.insertCell(0);
    cellProduit.innerHTML = selectProduitHTML;
    
    // Cellule Nombre de cartons
    var cellCartons = row.insertCell(1);
    cellCartons.className = 'center';
    cellCartons.innerHTML = '<input type="number" name="nb_carton[]" value="0" step="1" min="0" class="carton-input filter-input" oninput="updateRow(this)">';
    
    // Cellule Poids
    var cellPoids = row.insertCell(2);
    cellPoids.className = 'center';
    cellPoids.innerHTML = '<input type="number" name="poids[]" value="0" step="0.01" min="0" readonly class="poids-input filter-input">';
    
    // Cellule Stock disponible
    var cellStock = row.insertCell(3);
    cellStock.innerHTML = '<input type="text" name="stock_dispo[]" value="" readonly class="stock-info filter-input">';
    
    // Cellule Actions
    var cellActions = row.insertCell(4);
    cellActions.className = 'center';
    cellActions.innerHTML = '<input type="hidden" name="cartons_rowid[]" value=""><button type="button" class="remove-btn" onclick="removeRow(this)"><i class="fa fa-trash"></i> <?php echo $langs->trans("Supprimer"); ?></button>';
    
    // Attacher les événements
    const select = cellProduit.querySelector('.product-select');
    select.addEventListener('change', function() {
        updateStockDisponibilite(this);
    });
}

/**
 * Supprime une ligne de produit
 */
function removeRow(btn) {
    var row = btn.closest('tr');
    row.parentNode.removeChild(row);
}

/**
 * Met à jour les informations de la ligne
 */
function updateRow(element) {
    var row = element.closest('tr');
    var nbCarton = row.querySelector('input[name="nb_carton[]"]');
    var poids = row.querySelector('input[name="poids[]"]');
    var fk_entrepot = <?php echo $fk_entrepot_source ?: 0; ?>;
    var productId = row.querySelector('select[name="products[]"]').value;
    
    if(productId <= 0) return;

    var inputRowid = row.querySelector('input[name="cartons_rowid[]"]');
    var params = 'product_id=' + productId + '&fk_entrepot=' + fk_entrepot + '&mode=carton&nb_carton=' + nbCarton.value;
    
    fetch('ajax2.php?' + params)
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
 * Met à jour le stock disponible
 */
function updateStockDisponibilite(selectElement) {
    var productId = selectElement.value;
    var fk_entrepot = <?php echo (int) $fk_entrepot_source; ?>;
    var row = selectElement.closest('tr');
    
    if (!row) return;

    if (productId > 0) {
        fetch('ajax.php?product_id=' + productId + '&fk_entrepot=' + fk_entrepot)
            .then(response => response.json())
            .then(data => {
                var stockInput = row.querySelector('input[name="stock_dispo[]"]');
                if (stockInput && data.nb_carton_dispo !== undefined && data.poids_dispo !== undefined) {
                    stockInput.value = data.nb_carton_dispo + ' <?php echo $langs->trans("cartons"); ?> / ' + data.poids_dispo + ' kg';
                }
            })
            .catch(err => console.error('Erreur AJAX:', err));
    } else {
        var stockInput = row.querySelector('input[name="stock_dispo[]"]');
        if (stockInput) stockInput.value = '';
    }
}

</script>

<?php
llxFooter();
$db->close();
?>