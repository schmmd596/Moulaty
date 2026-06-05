<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $conf, $langs, $user;

$langs->load("products");
$langs->load("abricot@abricot");

$lot_id = GETPOST('id', 'int');
$form = new Form($db);

if (empty($lot_id)) {
    print '<div class="error">' . $langs->trans("LotNotSpecified") . '</div>';
    exit;
}

// Récupération des informations du lot
$sql_lot = "SELECT l.ref, l.date_creation, e.ref as entrepot_ref
            FROM ".MAIN_DB_PREFIX."pech_lot l
            LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = l.fk_entrepot
            WHERE l.rowid = " . (int)$lot_id;
$res_lot = $db->query($sql_lot);
$lot = $res_lot ? $db->fetch_object($res_lot) : null;

/*
|--------------------------------------------------------------------------
| REQUÊTE : reconstruction métier complète
|--------------------------------------------------------------------------
| Lot -> LotDet -> Carton -> Plat -> Mise en plat -> Bon
*/
$sql = "
    SELECT
        bmp.rowid AS bon_id,
        bmp.ref   AS bon_ref,
        bmp.date_creation AS bon_date,

        mp.rowid  AS misenplat_id,

        prod.rowid AS product_id,
        prod.ref   AS product_ref,
        prod.label AS product_label,

        c.rowid AS carton_id,

        p.rowid AS plat_id,
        p.poids AS poids_plat

    FROM ".MAIN_DB_PREFIX."pech_lot l
    JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.fk_lot = l.rowid
    JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.fk_lotdet = ld.rowid
    JOIN ".MAIN_DB_PREFIX."pech_plat p ON p.fk_carton = c.rowid
    JOIN ".MAIN_DB_PREFIX."pech_misenplat mp ON mp.rowid = p.fk_misenplat
    JOIN ".MAIN_DB_PREFIX."pech_bon_misenplat bmp ON bmp.rowid = mp.fk_bon_misenplat
    LEFT JOIN ".MAIN_DB_PREFIX."product prod ON prod.rowid = ld.fk_product

    WHERE l.rowid = ".((int)$lot_id)."
    ORDER BY bmp.rowid, mp.rowid, prod.ref
";

$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}

/*
|--------------------------------------------------------------------------
| STRUCTURATION DES DONNÉES
|--------------------------------------------------------------------------
*/
$data = [];

while ($obj = $db->fetch_object($resql)) {

    // ---- BON ----
    if (!isset($data[$obj->bon_id])) {
        $data[$obj->bon_id] = [
            'ref' => $obj->bon_ref,
            'date' => $obj->bon_date,
            'misenplats' => []
        ];
    }

    // ---- MISE EN PLAT ----
    if (!isset($data[$obj->bon_id]['misenplats'][$obj->misenplat_id])) {
        $data[$obj->bon_id]['misenplats'][$obj->misenplat_id] = [
            'produits' => []
        ];
    }

    // ---- PRODUIT ----
    if (!isset($data[$obj->bon_id]['misenplats'][$obj->misenplat_id]['produits'][$obj->product_id])) {
        $data[$obj->bon_id]['misenplats'][$obj->misenplat_id]['produits'][$obj->product_id] = [
            'ref' => $obj->product_ref,
            'label' => $obj->product_label,
            'nb_cartons' => 0,
            'nb_plats' => 0,
            'poids_plats' => 0,
            'cartons' => [],
            'plats' => []
        ];
    }

    $produit =& $data[$obj->bon_id]['misenplats'][$obj->misenplat_id]['produits'][$obj->product_id];

    // ---- CARTON (1 fois seulement) ----
    if (!isset($produit['cartons'][$obj->carton_id])) {
        $produit['cartons'][$obj->carton_id] = true;
        $produit['nb_cartons']++;
    }

    // ---- PLAT (1 fois seulement) ----
    if (!isset($produit['plats'][$obj->plat_id])) {
        $produit['plats'][$obj->plat_id] = true;
        $produit['nb_plats']++;
        $produit['poids_plats'] += (float) $obj->poids_plat;
    }
}

/*
|--------------------------------------------------------------------------
| AFFICHAGE AMÉLIORÉ
|--------------------------------------------------------------------------
*/
llxHeader('', $langs->trans("LotMisenplatDetail"));

print '<style>
    .lot-header-container {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .lot-title {
        font-size: 24px;
        font-weight: 600;
        margin: 0 0 10px 0;
    }
    
    .lot-title i {
        margin-right: 10px;
    }
    
    .lot-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    
    .lot-info-item {
        background: rgba(255,255,255,0.1);
        padding: 12px;
        border-radius: 6px;
        backdrop-filter: blur(10px);
    }
    
    .lot-info-label {
        font-size: 12px;
        opacity: 0.9;
        margin-bottom: 5px;
    }
    
    .lot-info-value {
        font-size: 16px;
        font-weight: 500;
    }
    
    .bon-card {
        background: white;
        border-radius: 10px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        overflow: hidden;
        border: 1px solid #e8e8e8;
    }
    
    .bon-header {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        color: white;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .bon-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }
    
    .bon-title i {
        margin-right: 10px;
    }
    
    .bon-date {
        font-size: 14px;
        opacity: 0.9;
    }
    
    .misenplat-section {
        padding: 20px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .misenplat-section:last-child {
        border-bottom: none;
    }
    
    .misenplat-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .misenplat-id {
        background: #e74c3c;
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        margin-right: 15px;
    }
    
    .misenplat-label {
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .produits-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .produits-table thead th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #e9ecef;
        font-size: 14px;
    }
    
    .produits-table tbody td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        vertical-align: middle;
    }
    
    .produits-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    .produits-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .produit-ref {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .produit-label {
        color: #6c757d;
        font-size: 13px;
    }
    
    .stat-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin: 2px;
    }
    
    .stat-cartons {
        background: #3498db;
        color: white;
    }
    
    .stat-plats {
        background: #2ecc71;
        color: white;
    }
    
    .stat-poids {
        background: #f39c12;
        color: white;
    }
    
    .no-data {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }
    
    .no-data i {
        font-size: 48px;
        color: #adb5bd;
        margin-bottom: 15px;
    }
    
    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #6c757d;
        color: white;
        padding: 10px 20px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 500;
        margin-top: 20px;
        transition: all 0.3s;
    }
    
    .back-button:hover {
        background: #5a6268;
        color: white;
        text-decoration: none;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .total-row {
        background: #f8f9fa !important;
        font-weight: 600;
    }
    
    .total-row td {
        border-top: 2px solid #dee2e6 !important;
    }
</style>';

print '<div class="lot-header-container">';
print '<h1 class="lot-title"><i class="fa fa-box"></i> ' . $langs->trans("LotMisenplatDetail") . '</h1>';

if ($lot) {
    print '<div class="lot-info-grid">';
    print '<div class="lot-info-item">';
    print '<div class="lot-info-label">' . $langs->trans("Lot") . '</div>';
    print '<div class="lot-info-value">' . dol_escape_htmltag($lot->ref) . '</div>';
    print '</div>';
    
    if ($lot->entrepot_ref) {
        print '<div class="lot-info-item">';
        print '<div class="lot-info-label"><i class="fa fa-warehouse"></i> ' . $langs->trans("Entrepot") . '</div>';
        print '<div class="lot-info-value">' . dol_escape_htmltag($lot->entrepot_ref) . '</div>';
        print '</div>';
    }
    
    if ($lot->date_creation) {
        print '<div class="lot-info-item">';
        print '<div class="lot-info-label"><i class="fa fa-calendar"></i> ' . $langs->trans("DateCreation") . '</div>';
        print '<div class="lot-info-value">' . dol_print_date($db->jdate($lot->date_creation), 'dayhour') . '</div>';
        print '</div>';
    }
    print '</div>';
}
print '</div>';

if (empty($data)) {
    print '<div class="bon-card">';
    print '<div class="no-data">';
    print '<i class="fa fa-inbox"></i>';
    print '<h3>' . $langs->trans("NoMisenplatData") . '</h3>';
    print '<p>' . $langs->trans("NoMisenplatFoundForThisLot") . '</p>';
    print '</div>';
    print '</div>';
} else {
    foreach ($data as $bon_id => $bon) {
        print '<div class="bon-card">';
        print '<div class="bon-header">';
        print '<div>';
        print '<h2 class="bon-title"><i class="fa fa-file-alt"></i> ' . $langs->trans("BonMisenplat") . ' : ' . dol_escape_htmltag($bon['ref']) . '</h2>';
        if ($bon['date']) {
            print '<div class="bon-date"><i class="fa fa-calendar-alt"></i> ' . dol_print_date($db->jdate($bon['date']), 'day') . '</div>';
        }
        print '</div>';
        print '<div style="background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px; font-size: 14px;">';
        print count($bon['misenplats']) . ' ' . $langs->trans("Misenplats");
        print '</div>';
        print '</div>';
        
        foreach ($bon['misenplats'] as $mp_id => $mp) {
            print '<div class="misenplat-section">';
            print '<div class="misenplat-header">';
            print '<div class="misenplat-id">MP #' . $mp_id . '</div>';
            print '<div class="misenplat-label">' . $langs->trans("Misenplat") . '</div>';
            print '</div>';
            
            if (empty($mp['produits'])) {
                print '<p style="text-align: center; color: #6c757d; padding: 20px;">' . $langs->trans("NoProducts") . '</p>';
            } else {
                print '<table class="produits-table">';
                print '<thead>';
                print '<tr>';
                print '<th width="40%">' . $langs->trans("Product") . '</th>';
                print '<th width="20%" align="center">' . $langs->trans("Cartons") . '</th>';
                print '<th width="20%" align="center">' . $langs->trans("Plats") . '</th>';
                print '<th width="20%" align="right">' . $langs->trans("TotalWeight") . '</th>';
                print '</tr>';
                print '</thead>';
                print '<tbody>';
                
                $total_cartons = 0;
                $total_plats = 0;
                $total_poids = 0;
                
                foreach ($mp['produits'] as $prod) {
                    $total_cartons += $prod['nb_cartons'];
                    $total_plats += $prod['nb_plats'];
                    $total_poids += $prod['poids_plats'];
                    
                    print '<tr>';
                    print '<td>';
                    print '<div class="produit-ref">' . dol_escape_htmltag($prod['ref']) . '</div>';
                    if (!empty($prod['label'])) {
                        print '<div class="produit-label">' . dol_escape_htmltag($prod['label']) . '</div>';
                    }
                    print '</td>';
                    print '<td align="center">';
                    print '<span class="stat-badge stat-cartons">' . $prod['nb_cartons'] . ' ' . $langs->trans("Cartons") . '</span>';
                    print '</td>';
                    print '<td align="center">';
                    print '<span class="stat-badge stat-plats">' . $prod['nb_plats'] . ' ' . $langs->trans("Plats") . '</span>';
                    print '</td>';
                    print '<td align="right">';
                    print '<span class="stat-badge stat-poids">' . price($prod['poids_plats'], 0, '', 1, 2) . ' kg</span>';
                    print '</td>';
                    print '</tr>';
                }
                
                // Ligne totale
                print '<tr class="total-row">';
                print '<td><strong>' . $langs->trans("Total") . '</strong></td>';
                print '<td align="center"><strong>' . $total_cartons . '</strong></td>';
                print '<td align="center"><strong>' . $total_plats . '</strong></td>';
                print '<td align="right"><strong>' . price($total_poids, 0, '', 1, 2) . ' kg</strong></td>';
                print '</tr>';
                
                print '</tbody>';
                print '</table>';
            }
            
            print '</div>'; // Fin misenplat-section
        }
        
        print '</div>'; // Fin bon-card
    }
}

// Bouton retour
print '<div style="text-align: center; margin-top: 30px;">';
print '<a href="detail_lot.php?id=' . $lot_id . '" class="back-button">';
print '<i class="fa fa-arrow-left"></i> ' . $langs->trans("BackToLotDetail");
print '</a>';
print '</div>';

llxFooter();
$db->close();