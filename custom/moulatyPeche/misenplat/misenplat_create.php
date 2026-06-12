<?php
/* Copyright (C) 2025
 * Abdou Mahfoudh <superadmin@womapeche.com>
 * All rights reserved.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';

global $db, $langs, $user;

$langs->loadLangs(['womapeche@womapeche', 'main', 'other']);
$form = new Form($db);

// ============================================================================
// Paramètres
// ============================================================================
$selected = GETPOST('selected', 'array');
$fk_entrepot = GETPOST('fk_entrepot', 'int');

// ============================================================================
// Vérification
// ============================================================================
if (empty($selected)) {
    setEventMessages($langs->trans("NoLinesSelected"), null, 'warnings');
    header("Location: ./nouveau.php");
    exit;
}

// ============================================================================
// Récupération et regroupement des produits sélectionnés
// ============================================================================
$sql = "SELECT d.rowid as receptiondet_id, d.fk_product, p.ref as product_ref, p.label as product_label,
               d.poids_net as quantite_totale, d.poids_plater as quantite_plater,
               r.fk_entrepot
        FROM ".MAIN_DB_PREFIX."pech_receptiondet as d
        INNER JOIN ".MAIN_DB_PREFIX."pech_reception as r ON r.rowid = d.fk_reception
        INNER JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = d.fk_product
        WHERE d.rowid IN (".implode(',', array_map('intval', $selected)).")";

$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) {
    setEventMessages($langs->trans("NoDataFoundForSelectedLines"), null, 'warnings');
    header("Location: ./nouveau.php");
    exit;
}

// Regroupement par produit
$produits = [];
$fk_entrepot_from_reception = 0;
while ($obj = $db->fetch_object($resql)) {
    if (empty($fk_entrepot_from_reception) && !empty($obj->fk_entrepot)) {
        $fk_entrepot_from_reception = (int)$obj->fk_entrepot;
    }
    if (!isset($produits[$obj->fk_product])) {
        $produits[$obj->fk_product] = [
            'ref' => $obj->product_ref,
            'label' => $obj->product_label,
            'qte_totale' => 0,
            'qte_plater' => 0
        ];
    }
    $produits[$obj->fk_product]['qte_totale'] += $obj->quantite_totale;
    $produits[$obj->fk_product]['qte_plater'] += $obj->quantite_plater;
}

if (empty($fk_entrepot)) {
    $fk_entrepot = $fk_entrepot_from_reception;
}

// ============================================================================
// Récupération de l'entrepôt
// ============================================================================
$entrepot = new Entrepot($db);
$entrepot->fetch($fk_entrepot);

// ============================================================================
// Affichage
// ============================================================================
llxHeader('', $langs->trans("CreatePlating"));
print '
<style>
.misenplat-container {
    max-width: 1200px;
    margin: 0 auto;
    background: #fff;
}

.misenplat-header {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #28a745;
}

.misenplat-header h1 {
    margin: 0;
    color: #2c3e50;
    font-size: 24px;
    font-weight: 600;
}

.misenplat-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 6px;
    overflow: hidden;
    margin: 15px 0;
}

.misenplat-table th {
    background: #e9ecef;
    color: #495057;
    padding: 12px 8px;
    font-weight: 600;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
}

.misenplat-table th.right {
    text-align: right;
}

.misenplat-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #e9ecef;
}

.misenplat-table td.right {
    text-align: right;
}

.misenplat-table tr:hover {
    background: #f8f9fa;
}

.quantity-input {
    padding: 6px 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    text-align: right;
    font-size: 14px;
}

.quantity-input:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.actions-section {
    text-align: center;
    margin: 25px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #28a745;
    color: white;
}

.btn-primary:hover {
    background: #218838;
    transform: translateY(-1px);
}

.btn-cancel {
    background: #6c757d;
    color: white;
}

.btn-cancel:hover {
    background: #5a6268;
}

.warehouse-info {
    background: #e8f4fd;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 4px solid #007bff;
}

.warehouse-info strong {
    color: #0056b3;
}

@media (max-width: 768px) {
    .misenplat-container {
        padding: 10px;
    }
    
    .misenplat-table {
        font-size: 12px;
    }
    
    .misenplat-table th,
    .misenplat-table td {
        padding: 8px 6px;
    }
    
    .quantity-input {
        width: 70px;
    }
}
</style>
';

print '<div class="misenplat-container">';
print '<div class="misenplat-header">';
print '<h1><i class="fa fa-utensils"></i> '.$langs->trans("CreatePlating").'</h1>';
print '</div>';

// Affichage de l'entrepôt
print '<div class="warehouse-info">';
print '<strong><i class="fa fa-warehouse"></i> '.$langs->trans("Warehouse").' :</strong> '.dol_escape_htmltag($entrepot->ref);
print '</div>';

print '<form method="POST" action="./save.php">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';
foreach ($selected as $value) {
    echo '<input type="hidden" name="selected[]" value="'.(int)$value.'">';
}

// ---------- Input date de création ----------
/*$date_creation_val = dol_print_date(time(), '%Y-%m-%dT%H:%M'); // valeur par défaut = maintenant
if (!empty($bon->date_creation)) {
    $date_creation_val = date('Y-m-d\TH:i', strtotime($bon->date_creation));
}

print '<div class="form-group" style="margin-bottom:15px;">';
print '<label for="date_creation"><strong>'.$langs->trans("CreationDate").' :</strong></label>';
print '<input type="datetime-local" id="date_creation" name="date_creation" value="'.$date_creation_val.'" class="quantity-input">';
print '</div>';
*/
// ---------- Input date de création ----------
print '<div class="form-group" style="margin-bottom:20px;">';
print '<label for="date_creation" style="display:block; margin-bottom:6px; font-weight:600; color:#2c3e50;">';
print '<i class="fa fa-calendar-alt"></i> '.$langs->trans("CreationDate").' :';
print '</label>';
print '<div style="position:relative; display:inline-block;">';
print '<input type="datetime-local" id="date_creation" name="date_creation" value="" required ';
print 'style="padding:10px 12px 10px 40px; border:2px solid #e74c3c; border-radius:6px; ';
print 'font-size:14px; width:250px; transition:all 0.3s ease; background:#fff; ';
print 'color:#333; box-shadow:0 2px 5px rgba(0,0,0,0.05);" ';
print 'oninput="if(this.value){this.style.borderColor=\'#27ae60\'; this.style.boxShadow=\'0 2px 8px rgba(39,174,96,0.2)\';} else {this.style.borderColor=\'#e74c3c\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.05)\';}">';
print '<i class="fa fa-clock" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#7f8c8d;"></i>';
print '</div>';
print '</div>';

print '<table class="misenplat-table">';
print '<thead>';
print '<tr>';
print '<th>'.$langs->trans("Product").'</th>';
print '<th class="right">'.$langs->trans("TotalQuantity").' (kg)</th>';
print '<th class="right">'.$langs->trans("AlreadyPlated").' (kg)</th>';
print '<th class="right">'.$langs->trans("RemainingToPlate").' (kg)</th>';
print '<th class="right">'.$langs->trans("QuantityToPlate").' (kg)</th>';
print '<th class="right">'.$langs->trans("NumberOfPlates").'</th>';
print '</tr>';
print '</thead>';
print '<tbody>';

foreach ($produits as $id_prod => $prod) {
    $reste = max(0, $prod['qte_totale'] - $prod['qte_plater']);

    print '<tr>';
    print '<td><strong>'.dol_escape_htmltag($prod['ref'].' - '.$prod['label']).'</strong></td>';
    print '<td class="right">'.price($prod['qte_totale'], 2, '', 1).' kg</td>';
    print '<td class="right">'.price($prod['qte_plater'], 2, '', 1).' kg</td>';
    print '<td class="right"><strong>'.price($reste, 2, '', 1).' kg</strong></td>';

    print '<td class="right">';
    print '<input type="number" step="0.01" name="qte_plater['.$id_prod.']" value="'.$reste.'" min="0" max="'.$reste.'" class="quantity-input">';
    print '</td>';

    print '<td class="right">';
    print '<input type="number" name="nb_plat['.$id_prod.']" value="'.($reste/10).'" min="0" class="quantity-input">';
    print '</td>';
    print '</tr>';
}

print '</tbody>';
print '</table>';

print '<div class="actions-section">';
print '<input type="submit" class="btn btn-primary" name="action_save" value="'.$langs->trans("SavePlating").'">';
print '&nbsp;&nbsp;';
print '<a class="btn btn-cancel" href="./nouveau.php">'.$langs->trans("Cancel").'</a>';
print '</div>';

print '</form>';
print '</div>';

llxFooter();
$db->close();