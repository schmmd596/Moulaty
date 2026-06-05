<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $langs, $user;
$langs->loadLangs(['reception', 'main', 'womapeche@womapeche']);

if (empty($user->rights->moulatyPeche->read_b)) {
    accessforbidden($langs->trans('AccesReserveAdmin'));
}
$form = new Form($db);

// ============================================================================
// 🔹 RÉCUPÉRATION DES SERVICES
// ============================================================================
$sql_service = "SELECT rowid, label, price FROM ".MAIN_DB_PREFIX."product WHERE fk_product_type = 1 ORDER BY label ASC";
$res_service = $db->query($sql_service);
$services = [];
if ($res_service) {
    while ($obj = $db->fetch_object($res_service)) {
        $services[] = [
            'id' => $obj->rowid,
            'label' => $obj->label,
            'price' => (float)$obj->price
        ];
    }
}

// ============================================================================
// 🔹 RÉCUPÉRATION DE LA RÉCEPTION
// ============================================================================
$id_reception = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');

$reception = null;
$lines = [];

// Charger la réception existante et ses lignes
if (!empty($id_reception)) {
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_reception WHERE rowid = ".((int)$id_reception);
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $reception = $db->fetch_object($resql);

        $sqldet = "SELECT * FROM ".MAIN_DB_PREFIX."pech_receptiondet 
                   WHERE fk_reception = ".((int)$id_reception)." 
                   ORDER BY rowid ASC";

        $resdet = $db->query($sqldet);
        if ($resdet) {
            $lines = [];
            while ($obj = $db->fetch_object($resdet)) {
                $lines[] = $obj;
            }
        }
    }
}

// ============================================================================
// 🔹 EN-TÊTE DE PAGE
// ============================================================================
llxHeader('', $langs->trans('CreationBonReception'));

// Importer le CSS personnalisé
print '<link rel="stylesheet" href="../bon_style.css">';

print '<div class="selection-container">';

// Header avec style CSS
print '<div class="selection-header">';
print '<h1><i class="fa fa-file-invoice"></i> '.$langs->trans("BonReceptionReception").'</h1>';
print '</div>';

// ============================================================================
// 🔹 FORMULAIRE PRINCIPAL
// ============================================================================
print '<form method="POST" action="traitement_bonreception.php">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="fk_reception" value="'.$id_reception.'">';

// ============================================================================
// 🔹 INFORMATIONS PRINCIPALES
// ============================================================================
print '<div class="filter-section">';
print '<div class="filter-title">';
print '<i class="fa fa-info-circle"></i> '.$langs->trans("InformationsReception");
print '</div>';

print '<table class="filter-table">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("DetailsReception").'</th></tr>';

// Référence Réception
print '<tr>';
print '<td width="30%"><label class="selection-label"><i class="fa fa-hashtag"></i> '.$langs->trans("ReferenceReception").'</label></td>';
print '<td><input type="text" value="'.dol_escape_htmltag($reception->ref).'" readonly class="filter-input"></td>';
print '</tr>';

// Fournisseur
$fournisseurName = '';
if (!empty($reception->fk_fournisseur)) {
    $soc = new Societe($db);
    if ($soc->fetch($reception->fk_fournisseur) > 0) $fournisseurName = $soc->name;
}
print '<tr>';
print '<td><label class="selection-label"><i class="fa fa-user-tie"></i> '.$langs->trans("Fournisseur").'</label></td>';
print '<td><input type="text" value="'.dol_escape_htmltag($fournisseurName).'" readonly class="filter-input"></td>';
print '</tr>';

// Congélateur
$congelateurName = '';
if (!empty($reception->fk_congelateur)) {
    $soc = new Societe($db);
    if ($soc->fetch($reception->fk_congelateur) > 0) $congelateurName = $soc->name;
}
print '<tr>';
print '<td><label class="selection-label"><i class="fa fa-snowflake"></i> '.$langs->trans("Congelateur").'</label></td>';
print '<td><input type="text" value="'.dol_escape_htmltag($congelateurName).'" readonly class="filter-input"></td>';
print '</tr>';

// Commentaire
print '<tr>';
print '<td><label class="selection-label"><i class="fa fa-comment"></i> '.$langs->trans("Commentaire").'</label></td>';
print '<td><textarea name="comment" class="filter-input" style="height:60px;">'.dol_escape_htmltag($reception->comment).'</textarea></td>';
print '</tr>';

print '</table>';
print '</div>'; // .filter-section

// ============================================================================
// 🔹 LIGNES DE LA RÉCEPTION
// ============================================================================
print '<div class="selection-section">';
print '<div class="selection-title">';
print '<i class="fa fa-list"></i> '.$langs->trans("LignesReception");
print '</div>';

print '<div class="selection-table-container">';
print '<table class="selection-table" id="linesTable">';
print '<thead>';
print '<tr>';
print '<th><i class="fa fa-cube"></i> '.$langs->trans("DescriptionProduit").'</th>';
print '<th class="center"><i class="fa fa-balance-scale"></i> '.$langs->trans("QuantitePoids").'</th>';
print '<th class="center"><i class="fa fa-tag"></i> '.$langs->trans("PrixPU").'</th>';
print '<th class="center"><i class="fa fa-calculator"></i> '.$langs->trans("TotalLigne").'</th>';
print '<th class="center"><i class="fa fa-gears"></i> '.$langs->trans("Actions").'</th>';
print '</tr>';
print '</thead>';
print '<tbody>';

// Modes mapping (for display)
$modes = [1 => $langs->trans("Voiture"), 2 => $langs->trans("PoidsBrut"), 3 => $langs->trans("PoidsNet")];

// Index to keep track of first rendered existing line
$idx = 0;
foreach ($lines as $line) {
    // Determine quantity and PU according to reception_mode
    $qty = 0;
    $pu_val = 0;
    if ($line->reception_mode == 1) {
        $qty = (float)$line->nb_voiture;
        $pu_val = (float)$line->prix_voiture;
    } elseif ($line->reception_mode == 2) {
        $qty = (float)$line->poids_brut;
        $pu_val = (float)$line->pu_brut;
    } else {
        $qty = (float)$line->poids_net;
        $pu_val = (float)$line->pu_poids_net;
    }
    $total_line = round($qty * $pu_val, 2);

    // Get product name if exists
    $productName = '';
    if (!empty($line->fk_product)) {
        $sqlp = "SELECT label FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".((int)$line->fk_product);
        $resp = $db->query($sqlp);
        if ($resp && $db->num_rows($resp) > 0) {
            $objp = $db->fetch_object($resp);
            $productName = $objp->label;
        }
    }

    print '<tr class="productRow">';
    // --- First existing line: leave as original (description text readonly) ---
    if ($idx === 0) {
        $modeLabel = isset($modes[$line->reception_mode]) ? $modes[$line->reception_mode] : '';
        $descriptionValue = trim($productName . ' - ' . $modeLabel);
        print '<td><input type="text" name="description[]" value="'.dol_escape_htmltag($descriptionValue).'" readonly class="facturation-input"></td>';
        print '<td class="center"><input type="number" readonly step="0.01" name="qte[]" class="calc facturation-input qte-input" value="'.price2num($qty, 'MT').'"></td>';
        print '<td class="center"><input type="number" readonly step="0.01" name="PU[]" class="calc facturation-input pu-input" value="'.price2num($pu_val, 'MT').'"></td>';
        print '<td class="center total_line"><strong>'.price($total_line).'</strong></td>';
        print '<td class="center"><button type="button" class="facturation-btn danger" ><i class="fa fa-trash"></i></button></td>';
    }
    print '</tr>';
}
print '</tbody>';
print '</table>';
print '</div>'; // .selection-table-container

print '<div class="selection-actions">';
print '<button type="button" class="selection-button selection-button-success" onclick="addProductRow()">';
print '<i class="fa fa-plus"></i> '.$langs->trans("AjouterLigne");
print '</button>';
print '</div>';

print '</div>'; // .selection-section

// ============================================================================
// 🔹 TOTAL MONTANT
// ============================================================================
print '<div class="facturation-total-section">';
print '<div class="total-grid">';
print '<div class="total-label">'.$langs->trans("MontantTotal").'</div>';
print '<div class="total-value">';
print '<input type="text" id="montant_total" name="montant_total" value="0.00" readonly class="grand-total-input">';
print '</div>';
print '</div>';
print '</div>';

// ============================================================================
// 🔹 LIGNES MANUELLES
// ============================================================================
print '<div class="selection-section">';
print '<div class="selection-title">';
print '<i class="fa fa-edit"></i> '.$langs->trans("LignesManuelles");
print '</div>';
// --- Fournisseur ---

print '<div class="selection-table-container">';
print '<table class="selection-table" id="manualLinesTable">';
print '<tr><td class="titlefieldcreate"><i class="fa fa-user"></i> '.$langs->trans("Fournisseur").' '.$langs->trans("Services").'</td><td>';
print $form->select_company('', 'fk_fournisseur2', 's.fournisseur=1', 1, 1, '', 0, 0, 'class="custom-select"');

print '</td></tr>';
print '<thead>';
print '<tr>';
print '<th><i class="fa fa-cogs"></i> '.$langs->trans("Service").'</th>';
print '<th class="center"><i class="fa fa-balance-scale"></i> '.$langs->trans("QuantitePoids").'</th>';
print '<th class="center"><i class="fa fa-tag"></i> '.$langs->trans("PrixPU").'</th>';
print '<th class="center"><i class="fa fa-calculator"></i> '.$langs->trans("TotalLigne").'</th>';
print '<th class="center"><i class="fa fa-gears"></i> '.$langs->trans("Actions").'</th>';
print '</tr>';
print '</thead>';
print '<tbody>';
print '</tbody>';
print '</table>';
print '</div>';

print '<div class="selection-actions">';
print '<button type="button" class="selection-button selection-button-secondary" onclick="addManualRow()">';
print '<i class="fa fa-plus"></i> '.$langs->trans("AjouterLigneManuelle");
print '</button>';
print '</div>';
print '</div>'; // .selection-section

// ============================================================================
// 🔹 BOUTON DE CRÉATION
// ============================================================================
print '<div class="selection-actions">';
print '<button type="submit" class="selection-button selection-button-primary submit-btn">';
print '<i class="fa fa-check-circle"></i> '.$langs->trans("CreerBonReception");
print '</button>';
print '</div>';

print '</form>';
print '</div>'; // .selection-container

// ============================================================================
// 🔹 SCRIPT JS
// ============================================================================
?>
<script>
/* Services passed from PHP */
const services = <?php echo json_encode($services, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;

/* Helpers: format numbers with 2 decimals */
function formatNum(n){
    return (parseFloat(n) || 0).toFixed(2);
}

/* Add a new product/service row (select) */
function addProductRow(){
    const table = document.getElementById("linesTable").getElementsByTagName('tbody')[0];
    const row = table.insertRow(-1);
    row.className = "productRow";

    let options = '<option value="">-- <?php echo $langs->trans("ChoisirService"); ?> --</option>';
    for (let s of services) {
        options += `<option value="${s.id}" data-price="${s.price}">${s.label}</option>`;
    }

    row.innerHTML = `
        <td><select name="description[]" class="service-select" onchange="updatePU(this)">${options}</select></td>
        <td class="center"><input type="number" step="0.01" name="qte[]" class="calc facturation-input qte-input" value="0"></td>
        <td class="center"><input type="number" step="0.01" name="PU[]" class="calc facturation-input pu-input" value="0.00"></td>
        <td class="center total_line"><strong>0.00</strong></td>
        <td class="center"><button type="button" class="facturation-btn danger" onclick="removeRow(this)"><i class="fa fa-trash"></i></button></td>
    `;
    bindCalculationsRow(row);
}

/* Add a manual line that selects a service */
function addManualRow(){
    const table = document.getElementById("manualLinesTable").getElementsByTagName('tbody')[0];
    const row = table.insertRow(-1);
    row.className = "manualRow";

    let options = '<option value="">-- <?php echo $langs->trans("ChoisirService"); ?> --</option>';
    for (let s of services) {
        options += `<option value="${s.id}" data-price="${s.price}">${s.label}</option>`;
    }

    row.innerHTML = `
        <td><select name="manual_description[]" class="service-select" onchange="updatePU(this)">${options}</select></td>
        <td class="center"><input type="number" step="0.01" name="manual_qte[]" class="manual_calc facturation-input qte-input" value="0"></td>
        <td class="center"><input type="number" step="0.01" name="manual_PU[]" class="manual_calc facturation-input pu-input" value="0.00"></td>
        <td class="center manual_total_line"><strong>0.00</strong></td>
        <td class="center"><button type="button" class="facturation-btn danger" onclick="removeManualRow(this)"><i class="fa fa-trash"></i></button></td>
    `;
    bindManualCalculationsRow(row);
}

/* When a service select changes, update the PU input in the same row */
function updatePU(select){
    const price = parseFloat(select.selectedOptions[0].dataset.price || 0);
    const row = select.closest("tr");
    let puInput = row.querySelector("input[name='PU[]']") || row.querySelector("input[name='manual_PU[]']");
    if (puInput) {
        puInput.value = formatNum(price);
        puInput.dispatchEvent(new Event('input'));
    }
}

/* Remove row and update totals */
function removeRow(btn){
    btn.closest("tr").remove();
    updateTotals();
}

function removeManualRow(btn){
    btn.closest("tr").remove();
}

/* Bind calculations for all existing rows */
function bindCalculations(){
    document.querySelectorAll(".productRow").forEach(row => bindCalculationsRow(row));
    document.querySelectorAll(".manualRow").forEach(row => bindManualCalculationsRow(row));
}

function bindCalculationsRow(row){
    const inputs = row.querySelectorAll("input.calc, select.service-select");
    inputs.forEach(inp => {
        inp.oninput = function(){ recalcRow(row); };
        inp.onchange = function(){ recalcRow(row); };
    });
    const sel = row.querySelector(".service-select");
    if (sel) {
        sel.onchange = function(){ updatePU(sel); };
    }
    recalcRow(row);
}

function bindManualCalculationsRow(row){
    const inputs = row.querySelectorAll(".manual_calc, select.service-select");
    inputs.forEach(inp => {
        inp.oninput = function(){ recalcManualRow(row); };
        inp.onchange = function(){ recalcManualRow(row); };
    });
    const sel = row.querySelector(".service-select");
    if (sel) sel.onchange = function(){ updatePU(sel); };
    recalcManualRow(row);
}

function recalcRow(row){
    const qte = parseFloat(row.querySelector("[name='qte[]']")?.value || 0);
    const pu = parseFloat(row.querySelector("[name='PU[]']")?.value || 0);
    const total = qte * pu;
    const totalCell = row.querySelector(".total_line");
    if (totalCell) totalCell.innerHTML = '<strong>' + formatNum(total) + '</strong>';
    updateTotals();
}

function recalcManualRow(row){
    const q = parseFloat(row.querySelector("[name='manual_qte[]']")?.value || 0);
    const p = parseFloat(row.querySelector("[name='manual_PU[]']")?.value || 0);
    const total = q * p;
    const totalCell = row.querySelector(".manual_total_line");
    if (totalCell) totalCell.innerHTML = '<strong>' + formatNum(total) + '</strong>';
}

/* Update overall totals (summation of productRow totals only) */
function updateTotals(){
    let total = 0;
    document.querySelectorAll(".productRow").forEach(row => {
        const v = parseFloat(row.querySelector(".total_line")?.innerText || 0);
        total += v;
    });
    document.getElementById("montant_total").value = formatNum(total);
}

/* Validation avant soumission */
document.querySelector('form').addEventListener('submit', function(e) {
    var confirmed = confirm("<?php echo $langs->trans('ConfirmerCreationBonReception'); ?>");
    if (!confirmed) {
        e.preventDefault();
        return false;
    }
});

/* Initialize bindings on page load */
document.addEventListener("DOMContentLoaded", function(){
    bindCalculations();
    updateTotals();
});
</script>

<?php
llxFooter();
$db->close();
?>