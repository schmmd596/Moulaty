<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;

$langs->load("abricot@abricot");

// Récupération de l'ID du bon mis en plat depuis l'URL
$fk_bon_misenplat = GETPOST('id', 'int');

if (empty($fk_bon_misenplat)) {
    $fk_bon_misenplat = GETPOST('fk_bon_misenplat', 'int');
}

// Vérification
if ($fk_bon_misenplat <= 0) {
    llxHeader('', $langs->trans('Error'));
    print '<div class="error">' . $langs->trans('ErrorNoPlateOrderSpecified') . '</div>';
    llxFooter();
    exit;
}

// Récupération des informations du bon mis en plat
$sql_bon = "SELECT bm.ref, bm.date_creation, e.ref AS entrepot_ref, 
                   bm.total_frais, bm.commentaire
            FROM ".MAIN_DB_PREFIX."pech_bon_misenplat bm
            LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = bm.fk_entrepot
            WHERE bm.rowid = " . (int)$fk_bon_misenplat;
$res_bon = $db->query($sql_bon);

if (!$res_bon || $db->num_rows($res_bon) == 0) {
    llxHeader('', $langs->trans('Error'));
    print '<div class="error">' . $langs->trans('ErrorPlateOrderNotFound') . '</div>';
    llxFooter();
    exit;
}

$bon_misenplat = $db->fetch_object($res_bon);

llxHeader('', $langs->trans('PlateToCartonFollowup') . ' - ' . $bon_misenplat->ref);

// Styles CSS améliorés
print '
<style>
    .fichecenter { max-width: 1400px; margin: 0 auto; padding: 20px; }
    .header-card {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 30px;
        border-left: 6px solid #28a745;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        position: relative;
        overflow: hidden;
    }
    .header-card::before {
        content: "📦";
        position: absolute;
        right: 20px;
        top: 20px;
        font-size: 60px;
        opacity: 0.1;
        transform: rotate(15deg);
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
        background: #e7f1ff;
        color: #0056b3;
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 0.85em;
        font-weight: 500;
        margin: 3px;
        display: inline-block;
        border: 1px solid #b3d4fc;
        transition: all 0.2s;
    }
    .badge:hover {
        background: #d4e6ff;
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,86,179,0.2);
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
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        padding: 16px 20px;
        text-align: left;
        font-weight: 600;
        font-size: 0.95em;
        letter-spacing: 0.5px;
        border-bottom: 3px solid #1e7e34;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .data-table td {
        padding: 14px 20px;
        border-bottom: 1px solid #f1f3f4;
        vertical-align: middle;
        transition: background 0.2s;
    }
    .data-table tr:hover {
        background-color: #f8fdf9;
    }
    .product-row {
        background: white;
        border-left: 4px solid transparent;
    }
    .product-row:hover {
        background: #f1f8ff;
        border-left: 4px solid #28a745;
        box-shadow: 0 2px 8px rgba(40,167,69,0.1);
    }
    .total-row {
        background: linear-gradient(135deg, #0073aa, #0056b3) !important;
        color: white;
        font-weight: bold;
        font-size: 1em;
    }
    .summary-row {
        background: #e8f5e8;
        font-style: italic;
        color: #155724;
    }
    .center-cell {
        text-align: center;
    }
    .right-cell {
        text-align: right;
        font-family: "Consolas", "Monaco", monospace;
        font-weight: 500;
    }
    .cartoned-count {
        color: #28a745;
        font-weight: 700;
        font-size: 1.1em;
    }
    .non-cartoned-count {
        color: #fd7e14;
        font-weight: 700;
        font-size: 1.1em;
    }
    .percentage {
        font-size: 0.8em;
        color: #6c757d;
        font-weight: normal;
        display: block;
        margin-top: 3px;
    }
    .progress-container {
        width: 100px;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        margin: 5px auto;
        overflow: hidden;
        display: block;
    }
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #28a745, #20c997);
        border-radius: 4px;
        transition: width 0.5s ease;
    }
    .lot-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    .bon-ref {
        font-size: 1.8em;
        font-weight: 700;
        color: #28a745;
        margin-bottom: 10px;
        display: inline-block;
        padding: 5px 15px;
        background: rgba(40,167,69,0.1);
        border-radius: 8px;
        border-left: 4px solid #28a745;
    }
    .status-indicator {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 8px;
        vertical-align: middle;
    }
    .status-cartoned {
        background: #28a745;
        box-shadow: 0 0 8px rgba(40,167,69,0.5);
    }
    .status-not-cartoned {
        background: #fd7e14;
        box-shadow: 0 0 8px rgba(253,126,20,0.5);
    }
</style>
';

print '<div class="fichecenter">';

// Titre avec référence stylée
print '<div style="text-align: center; margin-bottom: 10px;">';
print '<div style="font-size: 2em; font-weight: 700; color: #28a745; text-shadow: 2px 2px 4px rgba(0,0,0,0.1);">';
print '📦 ' . $langs->trans('PlateToCartonFollowup');
print '</div>';
print '<div style="font-size: 1.5em; color: #495057; margin-top: 5px;">';
print '<span class="bon-ref">' . $bon_misenplat->ref . '</span>';
print '</div>';
print '</div>';

// Informations du bon mis en plat avec style amélioré
print '<div class="header-card">';
print '<div class="header-grid">';
print '<div class="header-item">';
print '<span class="header-label">🏠 ' . $langs->trans('Warehouse') . '</span>';
print '<span class="header-value">' . $bon_misenplat->entrepot_ref . '</span>';
print '</div>';

print '<div class="header-item">';
print '<span class="header-label">📅 ' . $langs->trans('Date') . '</span>';
print '<span class="header-value">' . dol_print_date($bon_misenplat->date_creation, '%d/%m/%Y %H:%M') . '</span>';
print '</div>';

print '<div class="header-item">';
print '<span class="header-label">💰 ' . $langs->trans('TotalFees') . '</span>';
print '<span class="header-value">' . price($bon_misenplat->total_frais) . '</span>';
print '</div>';


$sqlEtat = "
    SELECT 
        CASE
            WHEN SUM(CASE WHEN p.statut = 1 THEN 1 ELSE 0 END) = 0 THEN 0
            WHEN SUM(CASE WHEN p.statut = 1 THEN 1 ELSE 0 END) = COUNT(*) THEN 1
            ELSE 2
        END AS etat
    FROM ".MAIN_DB_PREFIX."pech_plat p
    JOIN ".MAIN_DB_PREFIX."pech_misenplat m ON m.rowid = p.fk_misenplat
    WHERE m.fk_bon_misenplat = ".$fk_bon_misenplat;

$resEtat = $db->query($sqlEtat);
$objEtat = $db->fetch_object($resEtat);

$etat_plat = (int) $objEtat->etat;

print '<div class="header-item">';
print '<span class="header-label">📝 '.$langs->trans('Status').'</span>';
print '<span class="header-value">';

if ($etat_plat === 0) {
    print '<span class="status-indicator status-draft"></span> ';
    print $langs->trans('InTunnel');              // Français = EnTunnel, Arabe = فيالنفق
}
elseif ($etat_plat === 2) {
    print '<span class="status-indicator status-warning"></span> ';
    print $langs->trans('PartiallyCartoned');    // Français = PartiellementCartonnées, Arabe = مجزءةمغلفة
}
elseif ($etat_plat === 1) {
    print '<span class="status-indicator status-cartoned"></span> ';
    print $langs->trans('FullyCartoned');       // Français = ComplètementCartonnées, Arabe = مغلفةكلياً
}

print '</span>';
print '</div>';

if (!empty($bon_misenplat->commentaire)) {
    print '<div class="header-item" style="grid-column: span 2;">';
    print '<span class="header-label">💬 ' . $langs->trans('Comment') . '</span>';
    print '<span class="header-value" style="font-style: italic;">' . $bon_misenplat->commentaire . '</span>';
    print '</div>';
}
print '</div>';
print '</div>';

// Requête simplifiée
$sql = "
SELECT 
    mp.rowid AS misenplat_id,
    mp.fk_congelateur,
    mp.nombre_plat,
    mp.poids_plat,
    p.label AS product_label,
    p.ref AS product_ref,
    
    -- Plats cartonnés (statut 1)
    (SELECT COUNT(*) 
     FROM ".MAIN_DB_PREFIX."pech_plat pl 
     WHERE pl.fk_misenplat = mp.rowid AND pl.statut = 1) AS plats_cartones,
    
    -- Plats non cartonnés (statut 0)
    (SELECT COUNT(*) 
     FROM ".MAIN_DB_PREFIX."pech_plat pl 
     WHERE pl.fk_misenplat = mp.rowid AND pl.statut = 0) AS plats_non_cartones,
    
    -- Cartons utilisés
    (SELECT COUNT(DISTINCT c.rowid) 
     FROM ".MAIN_DB_PREFIX."pech_plat pl
     INNER JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.rowid = pl.fk_carton
     WHERE pl.fk_misenplat = mp.rowid AND pl.statut = 1) AS nb_cartons,
    
    -- Lots utilisés
    (SELECT GROUP_CONCAT(DISTINCT l.ref SEPARATOR ',') 
     FROM ".MAIN_DB_PREFIX."pech_plat pl
     INNER JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.rowid = pl.fk_carton
     INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
     INNER JOIN ".MAIN_DB_PREFIX."pech_lot l ON l.rowid = ld.fk_lot
     WHERE pl.fk_misenplat = mp.rowid AND pl.statut = 1) AS lots_refs

FROM ".MAIN_DB_PREFIX."pech_misenplat mp
INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = mp.fk_product
WHERE mp.fk_bon_misenplat = " . (int)$fk_bon_misenplat . "
ORDER BY mp.date_creation";

$res = $db->query($sql);

if ($res && $db->num_rows($res) > 0) {
    
    print '<div class="table-container">';
    print '<table class="data-table">';
    print '<thead>';
    print '<tr>';
    print '<th>' . $langs->trans('Line') . '</th>';
    print '<th>' . $langs->trans('Product') . '</th>';
    print '<th class="center-cell">' . $langs->trans('PlannedPlates') . '</th>';
    print '<th class="center-cell">' . $langs->trans('CartonedPlates') . '</th>';
    print '<th class="center-cell">' . $langs->trans('NonCartonedPlates') . '</th>';
    print '<th class="center-cell">' . $langs->trans('CartonsUsed') . '</th>';
    print '<th>' . $langs->trans('Lots') . '</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';
    
    $total_planned = 0;
    $total_cartoned = 0;
    $total_non_cartoned = 0;
    $total_cartons = 0;
    
    while ($obj = $db->fetch_object($res)) {
        $planned = (int)$obj->nombre_plat;
        $cartoned = (int)$obj->plats_cartones;
        $non_cartoned = (int)$obj->plats_non_cartones;
        $nb_cartons = (int)$obj->nb_cartons;
        
        $total_planned += $planned;
        $total_cartoned += $cartoned;
        $total_non_cartoned += $non_cartoned;
        $total_cartons += $nb_cartons;
        
        // Pourcentages
        $percent_cartoned = $planned > 0 ? ($cartoned / $planned) * 100 : 0;
        $percent_non_cartoned = $planned > 0 ? ($non_cartoned / $planned) * 100 : 0;
        
        print '<tr class="product-row">';
        
        // Ligne
        print '<td>';
        print '<strong>#' . $obj->misenplat_id . '</strong><br>';
        print '<small style="color:#6c757d;">Congélateur: ' . $obj->fk_congelateur . '</small>';
        print '</td>';
        
        // Produit
        print '<td>';
        print '<strong>' . $obj->product_label . '</strong><br>';
        print '<small style="color:#6c757d;">' . $obj->product_ref . '</small>';
        print '</td>';
        
        // Plats prévus
        print '<td class="center-cell">';
        print '<strong>' . $planned . '</strong><br>';
        print '<small style="color:#6c757d;">' . price($obj->poids_plat) . ' kg/plat</small>';
        print '</td>';
        
        // Plats cartonnés
        print '<td class="center-cell">';
        if ($cartoned > 0) {
            print '<span class="cartoned-count">' . $cartoned . '</span><br>';
            print '<div class="progress-container">';
            print '<div class="progress-bar" style="width: ' . min($percent_cartoned, 100) . '%" title="' . round($percent_cartoned, 1) . '%"></div>';
            print '</div>';
            print '<span class="percentage">' . round($percent_cartoned, 1) . '%</span>';
        } else {
            print '-';
        }
        print '</td>';
        
        // Plats non cartonnés
        print '<td class="center-cell">';
        if ($non_cartoned > 0) {
            print '<span class="non-cartoned-count">' . $non_cartoned . '</span><br>';
            print '<div class="progress-container" style="background:#ffeaa7;">';
            print '<div class="progress-bar" style="background:linear-gradient(90deg, #fd7e14, #ffc107); width: ' . min($percent_non_cartoned, 100) . '%" title="' . round($percent_non_cartoned, 1) . '%"></div>';
            print '</div>';
            print '<span class="percentage">' . round($percent_non_cartoned, 1) . '%</span>';
        } else {
            print '-';
        }
        print '</td>';
        
        // Cartons utilisés
        print '<td class="center-cell">';
        if ($nb_cartons > 0) {
            print '<strong>' . $nb_cartons . '</strong><br>';
            // Estimation: 2 plats par carton
            if ($cartoned > 0) {
                $avg_plats_per_carton = $cartoned / $nb_cartons;
                print '<small style="color:#6c757d;">' . round($avg_plats_per_carton, 1) . ' plats/carton</small>';
            }
        } else {
            print '-';
        }
        print '</td>';
        
        // Lots
        print '<td>';
        if (!empty($obj->lots_refs)) {
            $lots = explode(',', $obj->lots_refs);
            print '<div class="lot-badges">';
            foreach ($lots as $lot_ref) {
                print '<span class="badge" title="' . $langs->trans('Lot') . '">' . $lot_ref . '</span>';
            }
            print '</div>';
        } else {
            print '<span style="color:#6c757d; font-style:italic;">' . $langs->trans('NotInLot') . '</span>';
        }
        print '</td>';
        
        print '</tr>';
    }
    
    // Calcul des totaux
    $percent_total_cartoned = $total_planned > 0 ? ($total_cartoned / $total_planned) * 100 : 0;
    $percent_total_non_cartoned = $total_planned > 0 ? ($total_non_cartoned / $total_planned) * 100 : 0;
    
    // Ligne des totaux
    print '<tr class="total-row">';
    print '<td><strong>' . $langs->trans('Totals') . '</strong></td>';
    print '<td>-</td>';
    print '<td class="center-cell"><strong>' . $total_planned . '</strong></td>';
    print '<td class="center-cell"><strong>' . $total_cartoned . '</strong><br><small>' . round($percent_total_cartoned, 1) . '%</small></td>';
    print '<td class="center-cell"><strong>' . $total_non_cartoned . '</strong><br><small>' . round($percent_total_non_cartoned, 1) . '%</small></td>';
    print '<td class="center-cell"><strong>' . $total_cartons . '</strong></td>';
    print '<td>-</td>';
    print '</tr>';
    
    // Synthèse
    print '<tr class="summary-row">';
    print '<td colspan="7" style="padding: 20px;">';
    print '📊 <strong>' . $langs->trans('Summary') . ' :</strong> ';
    
    if ($total_planned > 0) {
        print $total_cartoned . ' ' . $langs->trans('platesCartoned') . ' (' . round($percent_total_cartoned, 1) . '%) ';
        print $langs->trans('outOf') . ' ' . $total_planned . ' ' . $langs->trans('plates') . '. ';
        print $langs->trans('Using') . ' ' . $total_cartons . ' ' . $langs->trans('cartons') . '.';
        
        if ($total_non_cartoned > 0) {
            print '<br><span style="color:#d35400; margin-top:10px; display:inline-block;">';
            print '⚠ ' . $total_non_cartoned . ' ' . $langs->trans('platesToCarton') . ' ' . $langs->trans('remaining') . ' (' . round($percent_total_non_cartoned, 1) . '%).';
            print '</span>';
        } else {
            print '<br><span style="color:#27ae60; margin-top:10px; display:inline-block;">';
            print '✅ ' . $langs->trans('AllPlatesCartoned');
            print '</span>';
        }
    } else {
        print $langs->trans('NoPlatesInThisOrder');
    }
    print '</td>';
    print '</tr>';
    
    print '</tbody>';
    print '</table>';
    print '</div>';
    
} else {
    print '<div class="warning">' . $langs->trans('NoPlateSetupLinesFound') . '</div>';
}

print '</div>';

llxFooter();
$db->close();
?>