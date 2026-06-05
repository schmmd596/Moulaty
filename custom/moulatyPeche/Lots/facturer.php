<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

global $db, $langs, $user;

$langs->load("abricot@abricot");
$form = new Form($db);

// ========================================
// RÉCUPÉRATION DU LOT
// ========================================
$id_lot = GETPOST('id_lot', 'int');
if ($id_lot <= 0) accessforbidden("Identifiant du lot invalide");

$sqlLot = "SELECT rowid, ref, commentaire, fk_entrepot 
           FROM ".MAIN_DB_PREFIX."pech_lot 
           WHERE rowid = ".((int)$id_lot);
$resLot = $db->query($sqlLot);
if (!$resLot || $db->num_rows($resLot) == 0) exit("Lot introuvable");
$lot = $db->fetch_object($resLot);

// ========================================
// RÉCUPÉRATION DU FOURNISSEUR
// ========================================
$sql = "SELECT s.rowid AS id_fournisseur, s.nom AS nom_fournisseur
        FROM ".MAIN_DB_PREFIX."societe AS s
        LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields AS sf ON sf.fk_object = s.rowid
        WHERE sf.entrepot = ".((int)$lot->fk_entrepot);
$resql = $db->query($sql);
if (!$resql) exit("Erreur lors de la récupération du fournisseur");
if ($db->num_rows($resql) == 0) exit("Aucun fournisseur trouvé pour cet entrepôt");
$fournisseur = $db->fetch_object($resql);

// ========================================
// CALCUL DU POIDS TOTAL DU LOT
// ========================================
$sqlPoids = "SELECT SUM(nb_carton * poids_carton) as total_poids
             FROM ".MAIN_DB_PREFIX."pech_lotdet
             WHERE fk_lot = ".((int)$id_lot);
$resPoids = $db->query($sqlPoids);
$objPoids = $db->fetch_object($resPoids);
$total_poids = $objPoids->total_poids ?: 0;

// ========================================
// RÉCUPÉRATION DES SERVICES DISPONIBLES
// ========================================
$sqlServices = "SELECT rowid, ref, label, price
                FROM ".MAIN_DB_PREFIX."product 
                WHERE fk_product_type = 1"; // 1 = service
$resServices = $db->query($sqlServices);
$services = [];
while ($obj = $db->fetch_object($resServices)) $services[] = $obj;

// ========================================
// AFFICHAGE DU FORMULAIRE
// ========================================

llxHeader();

// Charger les traductions
$langs->load("womapeche@womapeche");

print '<link rel="stylesheet" href="../fact_style.css">';

print '<div class="facturation-container">';
print '<div class="facturation-header">';
print '<h1><i class="fa fa-file-invoice-dollar"></i> '.$langs->trans("LotInvoicing").' '.$lot->ref.'</h1>';
print '</div>';

print '<form method="POST" action="facturation_process.php?id_lot='.$id_lot.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

// --- Informations du lot ---
print '<div class="facturation-info-section">';
print '<div class="facturation-title">';
print '<i class="fa fa-info-circle"></i> '.$langs->trans("LotInformation");
print '</div>';

print '<div class="facturation-info-grid">';
print '<div class="info-item">';
print '<label>'.$langs->trans("Reference").'</label>';
print '<div class="info-value highlight">'.$lot->ref.'</div>';
print '</div>';

print '<div class="info-item">';
print '<label>'.$langs->trans("Supplier").'</label>';
print '<div class="info-value">'.$fournisseur->nom_fournisseur.'</div>';
print '</div>';

if ($lot->commentaire) {
    print '<div class="info-item full-width">';
    print '<label>'.$langs->trans("Comment").'</label>';
    print '<div class="info-value">'.$lot->commentaire.'</div>';
    print '</div>';
}

print '<div class="info-item">';
print '<label>'.$langs->trans("TotalWeight").'</label>';
print '<div class="info-value highlight">'.price($total_poids).' kg</div>';
print '</div>';
print '</div>';

print '<input type="hidden" name="fk_fournisseur" value="'.$fournisseur->id_fournisseur.'">';
print '</div>';

// ========================================
// TABLE DES SERVICES
// ========================================
print '<div class="facturation-services-section">';
print '<div class="facturation-title">';
print '<i class="fa fa-cogs"></i> '.$langs->trans("PredefinedServices");
print '</div>';

print '<div class="facturation-table-container">';
print '<table class="facturation-table" id="table_services">';
print '<thead>';
print '<tr>';
print '<th><i class="fa fa-cog"></i> '.$langs->trans("Service").'</th>';
print '<th class="center"><i class="fa fa-weight"></i> '.$langs->trans("Quantity").' (kg)</th>';
print '<th class="center"><i class="fa fa-money-bill"></i> '.$langs->trans("UnitPrice").'</th>';
print '<th class="center"><i class="fa fa-calculator"></i> '.$langs->trans("Total").'</th>';
print '<th class="center"><i class="fa fa-actions"></i> '.$langs->trans("Action").'</th>';
print '</tr>';
print '</thead>';
print '<tbody>';

// --- Ligne fixe : service Conjelation ---
print '<tr>';
print '<td>';

$sqlConj = "SELECT rowid, ref, label, price
             FROM ".MAIN_DB_PREFIX."product
             WHERE label = 'CONGÉLATION' AND fk_product_type = 1
             LIMIT 1";
$resConj = $db->query($sqlConj);

if ($resConj && $db->num_rows($resConj) > 0) {
    $conj = $db->fetch_object($resConj);
    print '<input type="hidden" name="service_id[]" value="'.$conj->rowid.'">';
    print '<div class="service-name">';
    print '<i class="fa fa-snowflake"></i> ';
    print '<strong>'.$langs->trans("ServiceOf").' :</strong> '.$conj->label;
    print '</div>';
} else {
    print '<span class="error-text"><i class="fa fa-exclamation-triangle"></i> '.$langs->trans("ServiceNotFound").' "CONGÉLATION"</span>';
}

print '</td>';
print '<td class="center"><input type="text" name="service_qte[]" value="'.$total_poids.'" class="facturation-input qte-input"></td>';
print '<td class="center"><input type="text" name="service_pu[]" value="0" class="facturation-input pu-input"></td>';
print '<td class="center"><input type="text" name="service_total[]" readonly class="facturation-input total-input"></td>';
print '<td class="center"><button type="button" class="facturation-btn danger" onclick="removeRow(this)"><i class="fa fa-trash"></i></button></td>';
print '</tr>';

// --- Ligne sélectionnable : autres services ---
print '<tr>';
print '<td><select name="service_id[]" class="service_select facturation-input" style="width:100%;">';
foreach ($services as $s) {
    if (strcasecmp($s->label, 'CONGÉLATION') !== 0) {
        print '<option value="'.$s->rowid.'" data-price="'.$s->price.'">'.$s->label.'</option>';
    }
}
print '</select></td>';
print '<td class="center"><input type="text" name="service_qte[]" value="0" class="facturation-input qte-input"></td>';
print '<td class="center"><input type="text" name="service_pu[]" value="0" class="facturation-input pu-input"></td>';
print '<td class="center"><input type="text" name="service_total[]" readonly class="facturation-input total-input"></td>';
print '<td class="center"><button type="button" class="facturation-btn danger" onclick="removeRow(this)"><i class="fa fa-trash"></i></button></td>';
print '</tr>';

print '</tbody>';
print '</table>';
print '</div>';

print '<div class="facturation-actions">';
print '<button type="button" class="facturation-btn primary" onclick="addServiceRow()">';
print '<i class="fa fa-plus"></i> '.$langs->trans("AddService");
print '</button>';
print '</div>';
print '</div>';

// --- Total général ---
print '<div class="facturation-total-section">';
print '<div class="total-grid">';
print '<div class="total-label">';
print '<i class="fa fa-receipt"></i> '.$langs->trans("GrandTotal");
print '</div>';
print '<div class="total-value">';
print '<input type="text" id="grand_total" value="0.00" readonly class="grand-total-input">';
print '</div>';
print '</div>';
print '</div>';

// --- Bouton final ---
print '<div class="facturation-submit-section">';
print '<input type="submit" class="facturation-btn success submit-btn" value="'.$langs->trans("CreateInvoice").'" onclick="return confirm(\''.$langs->trans("ConfirmCreateInvoice").'\');">';
print '</div>';

print '</form>';
print '</div>';



llxFooter();
?>

<script>
// ==============================
// Recalcul automatique des totaux
// ==============================
function recalcRow(row, prefix) {
    let qte = parseFloat(row.querySelector(`input[name="${prefix}qte[]"]`)?.value) || 0;
    let pu  = parseFloat(row.querySelector(`input[name="${prefix}pu[]"]`)?.value) || 0;
    let total = qte * pu;
    row.querySelector(`input[name="${prefix}total[]"]`).value = total.toFixed(2);
    recalcGrandTotal();
}

function recalcGrandTotal() {
    let total = 0;
    document.querySelectorAll('input[name="service_total[]"], input[name="total[]"]').forEach(inp => {
        total += parseFloat(inp.value) || 0;
    });
    document.getElementById('grand_total').value = total.toFixed(2);
}

function removeRow(btn) {
    btn.closest('tr').remove();
    recalcGrandTotal();
}

// ==============================
// Gestion dynamique des services
// ==============================
function updateAllListeners() {
    document.querySelectorAll('.service_select').forEach(sel => {
        sel.onchange = e => {
            const price = parseFloat(e.target.selectedOptions[0].dataset.price) || 0;
            const row = e.target.closest('tr');
            row.querySelector('input[name="service_pu[]"]').value = price.toFixed(2);
            recalcRow(row, 'service_');
        };
    });

    document.querySelectorAll('input[name="service_qte[]"], input[name="service_pu[]"]').forEach(inp => {
        inp.oninput = () => {
            const row = inp.closest('tr');
            recalcRow(row, 'service_');
        };
    });
}

// ==============================
// Options des services (PHP → JS)
// ==============================
const serviceOptionsHTML = `<?php
    $options = '';
    foreach ($services as $s) {
        if (strcasecmp($s->label, 'CONGÉLATION') !== 0) {
            $options .= '<option value="'.$s->rowid.'" data-price="'.$s->price.'">'.dol_escape_htmltag($s->label).'</option>';
        }
    }
    echo $options;
?>`;

function addServiceRow() {
    const table = document.getElementById('table_services');
    const row = table.insertRow(-1);

    row.innerHTML = `
        <td><select name="service_id[]" class="service_select" style="width:100%;">${serviceOptionsHTML}</select></td>
        <td><input type="text" name="service_qte[]" value="0" style="width:80px;text-align:right;"></td>
        <td><input type="text" name="service_pu[]" value="0" style="width:80px;text-align:right;"></td>
        <td><input type="text" name="service_total[]" readonly style="width:100px;text-align:right;"></td>
        <td><button type="button" onclick="removeRow(this)">🗑️</button></td>
    `;
    updateAllListeners();
}

// Initialisation
updateAllListeners();
recalcGrandTotal();
</script>

<?php llxFooter(); ?>
