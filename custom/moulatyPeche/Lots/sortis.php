<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;

$langs->load("abricot@abricot");

// Récupération de l'ID du lot depuis l'URL
$fk_lot = GETPOST('id', 'int');

if (empty($fk_lot)) {
    $fk_lot = GETPOST('fk_lot', 'int');
}

// Vérification
if ($fk_lot <= 0) {
    llxHeader('', $langs->trans('Error'));
    print '<div class="error">' . $langs->trans('ErrorNoLotSpecified') . '</div>';
    llxFooter();
    exit;
}

// Récupération des informations du lot
$sql_lot = "SELECT l.ref, l.date_creation, e.ref AS entrepot_ref,
                   l.total_frais, l.commentaire, l.source_type,
                   s.nom AS fournisseur_nom
            FROM ".MAIN_DB_PREFIX."pech_lot l
            LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = l.fk_entrepot
            LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = l.fk_fourn
            WHERE l.rowid = " . (int)$fk_lot;
$res_lot = $db->query($sql_lot);

if (!$res_lot || $db->num_rows($res_lot) == 0) {
    llxHeader('', $langs->trans('Error'));
    print '<div class="error">' . $langs->trans('ErrorLotNotFound') . '</div>';
    llxFooter();
    exit;
}

$lot = $db->fetch_object($res_lot);

llxHeader('', $langs->trans('LotExitTracking') . ' - ' . $lot->ref);

// Styles CSS
print '
<style>
    .fichecenter { max-width: 1600px; margin: 0 auto; padding: 20px; }
    .header-card {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 30px;
        border-left: 6px solid #dc3545;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    .header-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    .header-item {
        padding: 15px;
        background: rgba(255,255,255,0.9);
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .header-label {
        font-size: 0.85em;
        color: #6c757d;
        margin-bottom: 5px;
        display: block;
    }
    .header-value {
        font-size: 1.1em;
        font-weight: 600;
        color: #343a40;
    }
    .badge {
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 0.85em;
        font-weight: 500;
        margin: 3px;
        display: inline-block;
    }
    .badge-client {
        background: #e7f1ff;
        color: #0056b3;
        border: 1px solid #b3d4fc;
    }
    .badge-transfer {
        background: #e6fffa;
        color: #0d9488;
        border: 1px solid #99f6e4;
    }
    .badge-exit {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    .table-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        margin-top: 20px;
        border: 1px solid #e9ecef;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9em;
    }
    .data-table th {
        background: linear-gradient(135deg, #02a302ff, #80c823ff);
        color: white;
        padding: 16px 20px;
        text-align: left;
        font-weight: 600;
        font-size: 0.95em;
        border-bottom: 3px solid #bd2130;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .data-table td {
        padding: 14px 20px;
        border-bottom: 1px solid #f1f3f4;
        vertical-align: top;
    }
    .data-table tr:hover {
        background-color: #fff5f5;
    }
    .product-row {
        background: white;
        border-left: 4px solid transparent;
    }
    .product-row:hover {
        background: #fff5f5;
        border-left: 4px solid #dc3545;
    }
    .exit-row {
        background: #f8f9fa;
        font-size: 0.85em;
    }
    .total-row {
        background: linear-gradient(135deg, #0073aa, #0056b3) !important;
        color: white;
        font-weight: bold;
    }
    .summary-row {
        background: #ffe6e6;
        color: #721c24;
    }
    .center-cell {
        text-align: center;
    }
    .right-cell {
        text-align: right;
        font-family: "Consolas", "Monaco", monospace;
        font-weight: 500;
    }
    .lot-ref {
        font-size: 1.8em;
        font-weight: 700;
        color: #3bdc35ff;
        margin-bottom: 10px;
        display: inline-block;
        padding: 5px 15px;
        background: rgba(220,53,69,0.1);
        border-radius: 8px;
        border-left: 4px solid #bddc35ff;
    }
    .type-indicator {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 8px;
        vertical-align: middle;
    }
    .type-client {
        background: #0056b3;
        box-shadow: 0 0 8px rgba(0,86,179,0.5);
    }
    .type-transfer {
        background: #0d9488;
        box-shadow: 0 0 8px rgba(13,148,136,0.5);
    }
    .exit-header {
        background: #e9ecef !important;
        font-weight: 600;
        border-left: 4px solid #6c757d;
    }
    .product-header {
        background: #f3f4f5ff !important;
        font-weight: 600;
        border-left: 4px solid #0056b3;
    }
</style>
';

print '<div class="fichecenter">';

// Titre avec référence du lot
print '<div style="text-align: center; margin-bottom: 10px;">';
print '<div style="font-size: 2em; font-weight: 700; color: #000000ff; text-shadow: 2px 2px 4px rgba(0,0,0,0.1);">';
print '🚚 ' . $langs->trans('LotExitTracking');
print '</div>';
print '<div style="font-size: 1.5em; color: #495057; margin-top: 5px;">';
print '<span class="lot-ref">' . $lot->ref . '</span>';
print '</div>';
print '</div>';

// Informations du lot
print '<div class="header-card">';
print '<div class="header-grid">';
print '<div class="header-item">';
print '<span class="header-label">🏠 ' . $langs->trans('Warehouse') . '</span>';
print '<span class="header-value">' . $lot->entrepot_ref . '</span>';
print '</div>';

print '<div class="header-item">';
print '<span class="header-label">📅 ' . $langs->trans('CreationDate') . '</span>';
print '<span class="header-value">' . dol_print_date($lot->date_creation, '%d/%m/%Y %H:%M') . '</span>';
print '</div>';

print '<div class="header-item">';
print '<span class="header-label">💰 ' . $langs->trans('TotalFees') . '</span>';
print '<span class="header-value">' . price($lot->total_frais) . '</span>';
print '</div>';

if (!empty($lot->fournisseur_nom)) {
    print '<div class="header-item">';
    print '<span class="header-label">👥 ' . $langs->trans('Supplier') . '</span>';
    print '<span class="header-value">' . $lot->fournisseur_nom . '</span>';
    print '</div>';
}
print '</div>';
print '</div>';

// Requête pour regrouper par produit et sortie
$sql = "
SELECT 
    -- Informations produit
    p.rowid AS product_id,
    p.label AS product_label,
    p.ref AS product_ref,
    
    -- Statistiques globales du produit dans le lot
    (SELECT COUNT(*) 
     FROM ".MAIN_DB_PREFIX."pech_carton c
     INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
     WHERE ld.fk_lot = " . (int)$fk_lot . "
     AND c.fk_product = p.rowid) AS total_cartons,
    
    (SELECT SUM(c.poids)
     FROM ".MAIN_DB_PREFIX."pech_carton c
     INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
     WHERE ld.fk_lot = " . (int)$fk_lot . "
     AND c.fk_product = p.rowid) AS total_poids,
    
    (SELECT SUM(c.nb_plat)
     FROM ".MAIN_DB_PREFIX."pech_carton c
     INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
     WHERE ld.fk_lot = " . (int)$fk_lot . "
     AND c.fk_product = p.rowid) AS total_plats

FROM ".MAIN_DB_PREFIX."pech_lotdet ld
INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ld.fk_product
WHERE ld.fk_lot = " . (int)$fk_lot . "
GROUP BY p.rowid, p.label, p.ref
ORDER BY p.label";

$res = $db->query($sql);

if ($res && $db->num_rows($res) > 0) {
    
    print '<div class="table-container">';
    print '<table class="data-table">';
    print '<thead>';
    print '<tr>';
    print '<th>' . $langs->trans('Product') . '</th>';
    print '<th class="center-cell">' . $langs->trans('TotalCartons') . '</th>';
    print '<th class="center-cell">' . $langs->trans('TotalWeight') . ' (kg)</th>';
    print '<th class="center-cell">' . $langs->trans('TotalPlates') . '</th>';
    print '<th>' . $langs->trans('Exits') . '</th>';
    print '<th>' . $langs->trans('CartonsPerExit') . '</th>';
    print '<th>' . $langs->trans('Details') . '</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';
    
    $global_total_cartons = 0;
    $global_total_poids = 0;
    $global_total_plates = 0;
    $global_total_exited = 0;
    
    while ($product = $db->fetch_object($res)) {
        $product_total_cartons = (int)$product->total_cartons;
        $product_total_poids = (float)$product->total_poids;
        $product_total_plates = (int)$product->total_plats;
        
        $global_total_cartons += $product_total_cartons;
        $global_total_poids += $product_total_poids;
        $global_total_plates += $product_total_plates;
        
        // Ligne produit (en-tête)
        print '<tr class="product-header">';
        print '<td>';
        print '<strong>' . $product->product_label . '</strong><br>';
        print '<small style="color:#6c757d;">' . $product->product_ref . '</small>';
        print '</td>';
        print '<td class="center-cell"><strong>' . $product_total_cartons . '</strong></td>';
        print '<td class="right-cell"><strong>' . price($product_total_poids) . '</strong></td>';
        print '<td class="center-cell"><strong>' . $product_total_plates . '</strong></td>';
        print '<td colspan="2">';
        
        // Récupérer les sorties pour ce produit dans ce lot
        $sql_exits = "
        SELECT 
            s.rowid AS exit_id,
            s.ref AS exit_ref,
            s.type AS exit_type,
            s.date_creation AS exit_date,
            s.fk_client,
            s.fk_entrepot_dest,
            s.fk_facture_client,
            
            -- Nombre de cartons de ce produit dans cette sortie
            COUNT(DISTINCT sdc.fk_carton) AS nb_cartons_exit,
            
            -- Poids total des cartons dans cette sortie
            SUM(c.poids) AS poids_exit,
            
            -- Informations client ou entrepôt
            soc.nom AS client_nom,
            e.ref AS entrepot_dest_ref
            
        FROM ".MAIN_DB_PREFIX."pech_sortie s
        INNER JOIN ".MAIN_DB_PREFIX."pech_sortiedetprod sdp ON sdp.fk_sortie = s.rowid
        INNER JOIN ".MAIN_DB_PREFIX."pech_sortiedetcarton sdc ON sdc.fk_sortiedetprod = sdp.rowid
        INNER JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.rowid = sdc.fk_carton
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
        LEFT JOIN ".MAIN_DB_PREFIX."societe soc ON soc.rowid = s.fk_client
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = s.fk_entrepot_dest
        
        WHERE ld.fk_lot = " . (int)$fk_lot . "
        AND c.fk_product = " . (int)$product->product_id . "
        AND sdp.fk_product = " . (int)$product->product_id . "
        
        GROUP BY s.rowid, s.ref, s.type, s.date_creation, 
                 s.fk_client, s.fk_entrepot_dest, s.fk_facture_client,
                 soc.nom, e.ref
        ORDER BY s.date_creation DESC";
        
        $res_exits = $db->query($sql_exits);
        $product_total_exited = 0;
        $product_total_exited_poids = 0;
        
        if ($res_exits && $db->num_rows($res_exits) > 0) {
            print '<div style="margin-top: 5px;">';
            while ($exit = $db->fetch_object($res_exits)) {
                $product_total_exited += $exit->nb_cartons_exit;
                $product_total_exited_poids += $exit->poids_exit;
                
                print '<div style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 6px; border-left: 4px solid ' . ($exit->exit_type == 1 ? '#0056b3' : '#0d9488') . ';">';
                
                // Type et référence
                if ($exit->exit_type == 1) {
                    print '<span class="type-indicator type-client"></span>';
                    print '<span class="badge badge-client">' . $langs->trans('ClientSale') . '</span> ';
                } else {
                    print '<span class="type-indicator type-transfer"></span>';
                    print '<span class="badge badge-transfer">' . $langs->trans('InternalTransfer') . '</span> ';
                }
                print '<strong>' . $exit->exit_ref . '</strong>';
                print '<span class="badge badge-exit">' . $exit->nb_cartons_exit . ' ' . $langs->trans('cartons') . '</span>';
                
                // Destination
                print '<br><small style="color:#6c757d; margin-left: 20px;">';
                if ($exit->exit_type == 1 && !empty($exit->client_nom)) {
                    print '👤 ' . $exit->client_nom;
                } elseif ($exit->exit_type == 0 && !empty($exit->entrepot_dest_ref)) {
                    print '🏠 ' . $langs->trans('To') . ' ' . $exit->entrepot_dest_ref;
                }
                print ' | 📅 ' . dol_print_date($exit->exit_date, '%d/%m/%Y');
                print ' | ⚖️ ' . price($exit->poids_exit) . ' kg';
                print '</small>';
                
                print '</div>';
            }
            print '</div>';
            
            $global_total_exited += $product_total_exited;
            
            // Calculer les cartons restants
            $product_remaining = $product_total_cartons - $product_total_exited;
            $product_remaining_poids = $product_total_poids - $product_total_exited_poids;
            
            // Afficher le résumé pour ce produit
            print '</td>';
            print '<td>';
            print '<div style="padding: 10px; background: #f8f9fa; border-radius: 6px;">';
            
            // Cartons sortis
            print '<div style="color:#dc3545; margin-bottom: 5px;">';
            print '<strong>' . $product_total_exited . '</strong> ' . $langs->trans('exited') . '<br>';
            print '<small>' . price($product_total_exited_poids) . ' kg</small>';
            print '</div>';
            
            // Cartons restants
            if ($product_remaining > 0) {
                print '<div style="color:#28a745; margin-bottom: 5px;">';
                print '<strong>' . $product_remaining . '</strong> ' . $langs->trans('remaining') . '<br>';
                print '<small>' . price($product_remaining_poids) . ' kg</small>';
                print '</div>';
            }
            
            // Pourcentage sorti
            if ($product_total_cartons > 0) {
                $percent_exited = ($product_total_exited / $product_total_cartons) * 100;
                print '<div style="margin-top: 5px; padding-top: 5px; border-top: 1px dashed #dee2e6;">';
                print '<small>' . $langs->trans('Exited') . ': ' . round($percent_exited, 1) . '%</small>';
                print '</div>';
            }
            
            print '</div>';
            print '</td>';
            
        } else {
            // Pas de sorties pour ce produit
            print $langs->trans('NoExitsForProduct');
            print '</td>';
            print '<td>';
            print '<div style="color:#28a745; padding: 10px; background: #f8f9fa; border-radius: 6px;">';
            print '<strong>' . $product_total_cartons . '</strong> ' . $langs->trans('inStock') . '<br>';
            print '<small>' . price($product_total_poids) . ' kg</small>';
            print '</div>';
            print '</td>';
        }
        
        print '</tr>';
    }
    
    // Calcul des totaux globaux
    $global_remaining = $global_total_cartons - $global_total_exited;
    $percent_global_exited = $global_total_cartons > 0 ? ($global_total_exited / $global_total_cartons) * 100 : 0;
    
    // Ligne des totaux
    print '<tr class="total-row">';
    print '<td><strong>' . $langs->trans('Totals') . '</strong></td>';
    print '<td class="center-cell"><strong>' . $global_total_cartons . '</strong></td>';
    print '<td class="right-cell"><strong>' . price($global_total_poids) . ' kg</strong></td>';
    print '<td class="center-cell"><strong>' . $global_total_plates . '</strong></td>';
    print '<td>';
    print '<div style="text-align: center;">';
    print '<strong>' . $global_total_exited . '</strong> ' . $langs->trans('cartonsExited') . '<br>';
    print '<small>' . round($percent_global_exited, 1) . '% ' . $langs->trans('ofTotal') . '</small>';
    print '</div>';
    print '</td>';
    print '<td colspan="2">';
    print '<div style="text-align: center;">';
    print '<strong>' . $global_remaining . '</strong> ' . $langs->trans('cartonsRemaining') . '<br>';
    print '<small>' . (100 - round($percent_global_exited, 1)) . '% ' . $langs->trans('inStock') . '</small>';
    print '</div>';
    print '</td>';
    print '</tr>';
    
    // Synthèse
    print '<tr class="summary-row">';
    print '<td colspan="7" style="padding: 20px;">';
    print '📊 <strong>' . $langs->trans('Summary') . ' :</strong> ';
    
    print $global_total_cartons . ' ' . $langs->trans('cartons') . ' (' . price($global_total_poids) . ' kg) ';
    print $langs->trans('inLot') . ' ' . $lot->ref . ', ';
    print $langs->trans('distributedIn') . ' ' . $db->num_rows($res) . ' ' . $langs->trans('products') . '. ';
    
    if ($global_total_exited > 0) {
        print $global_total_exited . ' ' . $langs->trans('cartonsExited') . ' (' . round($percent_global_exited, 1) . '%) ';
        print $langs->trans('representing') . ' ' . $global_total_exited . ' ' . $langs->trans('cartons') . '. ';
        print $global_remaining . ' ' . $langs->trans('cartonsRemaining') . ' ' . $langs->trans('inStock') . '.';
    } else {
        print $langs->trans('AllCartonsInStock');
    }
    print '</td>';
    print '</tr>';
    
    print '</tbody>';
    print '</table>';
    print '</div>';
    
} else {
    print '<div class="warning">' . $langs->trans('NoProductsInThisLot') . '</div>';
}

print '</div>';

llxFooter();
$db->close();
?>