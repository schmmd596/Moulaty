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
if ($id_lot <= 0) accessforbidden($langs->trans("InvalidLotId"));

$sqlLot = "SELECT l.*, s.nom AS fournisseur_nom
           FROM ".MAIN_DB_PREFIX."pech_lot l
           LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = l.fk_fourn
           WHERE l.rowid = ".((int)$id_lot);
$resLot = $db->query($sqlLot);
if (!$resLot || $db->num_rows($resLot) == 0) exit($langs->trans("LotNotFound"));
$lot = $db->fetch_object($resLot);

// Vérifier que c'est un lot fournisseur direct
if ($lot->source_type != 1) {
    setEventMessages($langs->trans("NotDirectSupplierLot"), null, 'errors');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}

// ========================================
// RÉCUPÉRATION DES LIGNES DU LOT
// ========================================
$sqlLines = "SELECT ld.*, p.ref AS product_ref, p.label AS product_label
             FROM ".MAIN_DB_PREFIX."pech_lotdet ld
             LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ld.fk_product
             WHERE ld.fk_lot = ".$id_lot;
$resLines = $db->query($sqlLines);
$lines = [];
while ($obj = $db->fetch_object($resLines)) $lines[] = $obj;

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

print '<link rel="stylesheet" href="../fact_style.css">';

print '<div class="facturation-container">';
print '<div class="facturation-header">';
print '<h1><i class="fa fa-file-invoice-dollar"></i> '.$langs->trans("SupplierInvoicePreparation").' '.$lot->ref.'</h1>';
print '</div>';

print '<form method="POST" action="facture_fournisseur_create.php?id_lot='.$id_lot.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="fk_fournisseur" value="'.$lot->fk_fourn.'">';

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
print '<div class="info-value">'.($lot->fournisseur_nom ?: $langs->trans("NotSpecified")).'</div>';
print '</div>';

if ($lot->commentaire) {
    print '<div class="info-item full-width">';
    print '<label>'.$langs->trans("Comment").'</label>';
    print '<div class="info-value">'.$lot->commentaire.'</div>';
    print '</div>';
}
print '</div>';
print '</div>';

// --- Table des produits du lot ---
print '<div class="facturation-services-section">';
print '<div class="facturation-title">';
print '<i class="fa fa-cubes"></i> '.$langs->trans("ProductsToInvoice");
print '</div>';

print '<div class="facturation-table-container">';
print '<table class="facturation-table" id="table_lines">';
print '<thead>';
print '<tr>';
print '<th><i class="fa fa-cube"></i> '.$langs->trans("Product").'</th>';
print '<th class="center"><i class="fa fa-box"></i> '.$langs->trans("NbCartons").'</th>';
print '<th class="center"><i class="fa fa-money-bill"></i> '.$langs->trans("UnitPricePerCarton").'</th>';
print '<th class="center"><i class="fa fa-calculator"></i> '.$langs->trans("LineTotal").'</th>';
print '</tr>';
print '</thead>';
print '<tbody>';
foreach ($lines as $line) {
    $pu_carton = $line->prix;  // PU par carton
    //print 'rrr '.$line->prix;
    print '<tr>';
    print '<td><div class="service-name">'.$line->product_ref.' - '.$line->product_label.'</div></td>';
    print '<td class="center"><input type="text" value="'.$line->nb_carton.'" readonly class="facturation-input qte-input"></td>';
    print '<td class="center"><input type="text" value="'.price($pu_carton).'" readonly class="facturation-input pu-input"></td>';
    print '<td class="center"><input type="text" value="'.price($line->prix * $line->nb_carton).'" readonly class="facturation-input total-input line_total"></td>';
    print '</tr>';
}
print '</tbody>';
print '</table>';
print '</div>';
print '</div>';

// --- Services supplémentaires ---
print '<div class="facturation-services-section">';
print '<div class="facturation-title">';
print '<i class="fa fa-plus-circle"></i> '.$langs->trans("AddAdditionalServices");
print '</div>';

print '<div class="facturation-table-container">';
print '<table class="facturation-table" id="table_services">';
print '<thead>';
print '<tr>';
print '<th><i class="fa fa-cog"></i> '.$langs->trans("Service").'</th>';
print '<th class="center"><i class="fa fa-weight"></i> '.$langs->trans("Quantity").'</th>';
print '<th class="center"><i class="fa fa-money-bill"></i> '.$langs->trans("UnitPrice").'</th>';
print '<th class="center"><i class="fa fa-calculator"></i> '.$langs->trans("Total").'</th>';
print '<th class="center"><i class="fa fa-actions"></i> '.$langs->trans("Action").'</th>';
print '</tr>';
print '</thead>';
print '<tbody>';
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
print '<i class="fa fa-file-invoice"></i> '.$langs->trans("InvoiceTotal");
print '</div>';
print '<div class="total-value">';
print '<span id="grand_total" class="grand-total-input">0.00</span>';
print '</div>';
print '</div>';
print '</div>';

// --- Bouton final ---
print '<div class="facturation-submit-section">';
print '<input type="submit" class="facturation-btn success submit-btn" value="'.$langs->trans("ValidateInvoice").'" onclick="return confirm(\''.$langs->trans("ConfirmCreateInvoice").'\');">';
print '</div>';

print '</form>';
print '</div>';
?>

<script>
function recalcGrandTotal() {
    let total = 0;
    document.querySelectorAll('#table_lines .line_total').forEach(inp => total += parseFloat(inp.value) || 0);
    document.querySelectorAll('input[name="service_total[]"]').forEach(inp => total += parseFloat(inp.value) || 0);
    document.getElementById('grand_total').innerText = total.toFixed(2);
}

function recalcRow(row) {
    let qte = parseFloat(row.querySelector('input[name="service_qty[]"]').value) || 0;
    let pu = parseFloat(row.querySelector('input[name="service_price[]"]').value) || 0;
    row.querySelector('input[name="service_total[]"]').value = (qte * pu).toFixed(2);
    recalcGrandTotal();
}

function removeRow(btn) {
    if (document.querySelectorAll('#table_services tbody tr').length > 0) {
        btn.closest('tr').remove();
        recalcGrandTotal();
    }
}

const serviceOptionsHTML = `<?php
    $options = '';
    foreach ($services as $s) {
        $options .= '<option value="'.$s->rowid.'" data-price="'.$s->price.'">'.dol_escape_htmltag($s->label).'</option>';
    }
    echo $options;
?>`;

function addServiceRow() {
    const tbody = document.querySelector('#table_services tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><select name="service_id[]" class="service_select facturation-input" style="width:100%;">${serviceOptionsHTML}</select></td>
        <td class="center"><input type="number" name="service_qty[]" value="1" class="facturation-input qte-input"></td>
        <td class="center"><input type="number" name="service_price[]" value="0" class="facturation-input pu-input"></td>
        <td class="center"><input type="number" name="service_total[]" readonly class="facturation-input total-input"></td>
        <td class="center"><button type="button" class="facturation-btn danger" onclick="removeRow(this)"><i class="fa fa-trash"></i></button></td>
    `;
    tbody.appendChild(row);
    updateListeners();
    // Trigger change to set initial price
    const select = row.querySelector('.service_select');
    if (select.options.length > 0) {
        select.dispatchEvent(new Event('change'));
    }
}

function updateListeners() {
    document.querySelectorAll('input[name="service_qty[]"]').forEach(inp => {
        inp.oninput = () => recalcRow(inp.closest('tr'));
    });
    document.querySelectorAll('.service_select').forEach(sel => {
        sel.onchange = e => {
            let price = parseFloat(e.target.selectedOptions[0].dataset.price) || 0;
            let row = e.target.closest('tr');
            row.querySelector('input[name="service_price[]"]').value = price;
            recalcRow(row);
        };
    });
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    updateListeners();
    recalcGrandTotal();
});
</script>

<?php llxFooter(); ?>