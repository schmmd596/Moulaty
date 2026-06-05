<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;

$langs->load("abricot@abricot");
$langs->load("other");

// Récupération de l'ID de la réception depuis l'URL
$fk_reception = GETPOST('id', 'int');

if (empty($fk_reception)) {
    $fk_reception = GETPOST('fk_reception', 'int');
}

// Vérification si la réception existe
if ($fk_reception <= 0) {
    llxHeader('', $langs->trans('Error'));
    print '<div class="error">' . $langs->trans('ErrorNoReceptionSpecified') . '</div>';
    llxFooter();
    exit;
}

// Récupération des informations de base de la réception
$sql_reception = "SELECT r.ref, r.date_creation, f.nom AS fournisseur_nom, e.ref AS entrepot_ref
                  FROM ".MAIN_DB_PREFIX."pech_reception r
                  LEFT JOIN ".MAIN_DB_PREFIX."societe f ON f.rowid = r.fk_fournisseur
                  LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = r.fk_entrepot
                  WHERE r.rowid = " . (int)$fk_reception;
$res_reception = $db->query($sql_reception);

if (!$res_reception || $db->num_rows($res_reception) == 0) {
    llxHeader('', $langs->trans('Error'));
    print '<div class="error">' . $langs->trans('ErrorReceptionNotFound') . '</div>';
    llxFooter();
    exit;
}

$reception = $db->fetch_object($res_reception);

llxHeader('', $langs->trans('PlateWeightFollowup') . ' - ' . $reception->ref);

// Styles CSS améliorés
print '
<style>
    .fichecenter { max-width: 1400px; margin: 0 auto; padding: 20px; }
    .header-info {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        border-left: 5px solid #0073aa;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .header-info strong { color: #495057; }
    .badge {
        background: #e7f1ff;
        color: #0056b3;
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 0.85em;
        font-weight: 500;
        margin: 2px;
        display: inline-block;
        border: 1px solid #b3d4fc;
    }
    .table-container {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        margin-top: 20px;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9em;
    }
    .data-table th {
        background: linear-gradient(135deg, #0073aa, #0056b3);
        color: white;
        padding: 12px 15px;
        text-align: left;
        font-weight: 600;
        border-bottom: 3px solid #004085;
    }
    .data-table td {
        padding: 10px 15px;
        border-bottom: 1px solid #e9ecef;
        vertical-align: middle;
    }
    .data-table tr:hover {
        background-color: #f8fafc;
    }
    .product-row {
        background: white;
        border-left: 4px solid transparent;
    }
    .product-row:hover {
        background: #f1f8ff;
        border-left: 4px solid #0073aa;
    }
    .misenplat-detail {
        background: #f8f9fa !important;
        font-size: 0.85em;
    }
    .misenplat-detail:hover {
        background: #e9ecef !important;
    }
    .total-row {
        background: linear-gradient(135deg, #28a745, #20c997) !important;
        color: white;
        font-weight: bold;
    }
    .summary-row {
        background: #e8f5e8;
        font-style: italic;
    }
    .percentage {
        font-size: 0.8em;
        color: #6c757d;
        font-weight: normal;
    }
    .weight-cell {
        text-align: right;
        font-family: "Consolas", "Monaco", monospace;
        font-weight: 500;
    }
    .center-cell {
        text-align: center;
    }
    .restant-positive {
        color: #dc3545;
        font-weight: 600;
    }
    .restant-zero {
        color: #28a745;
        font-weight: 600;
    }
    .progress-container {
        width: 100px;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        margin: 3px 0;
        overflow: hidden;
        display: inline-block;
        vertical-align: middle;
    }
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #28a745, #20c997);
        border-radius: 4px;
    }
</style>
';

print '<div class="fichecenter">';

// Titre
print load_fiche_titre('🍽️ ' . $langs->trans('PlateWeightFollowup') . ' : ' . $reception->ref, '', 'title_generic');

// Informations de la réception avec style amélioré
print '<div class="header-info">';
print '<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">';
print '<div>';
print '<strong>' . $langs->trans('Supplier') . ' :</strong> ' . $reception->fournisseur_nom . '<br>';
print '<strong>' . $langs->trans('Date') . ' :</strong> ' . dol_print_date($reception->date_creation, '%d/%m/%Y %H:%M') . '<br>';
print '<strong>' . $langs->trans('Warehouse') . ' :</strong> ' . $reception->entrepot_ref . '<br>';
print '</div>';
print '<div style="text-align: right;">';
print '<strong>' . $langs->trans('ReceptionRef') . ' :</strong> ' . $reception->ref . '<br>';
print '<strong>' . $langs->trans('ReceptionID') . ' :</strong> ' . $fk_reception . '<br>';
print '<strong>' . $langs->trans('ReportDate') . ' :</strong> ' . dol_print_date(dol_now(), '%d/%m/%Y %H:%M');
print '</div>';
print '</div>';
print '</div>';

// Requête optimisée sans poids brut et avec entrepôt
$sql = "
SELECT 
    rd.rowid AS receptiondet_id,
    rd.fk_product,
    rd.poids_plater,
    rd.poids_net,
    p.label AS product_name,
    p.ref AS product_ref,
    
    -- Récupérer les bons de mis en plat qui utilisent cette ligne de réception
    (SELECT GROUP_CONCAT(DISTINCT CONCAT(bm.rowid, '|', bm.ref, '|', bm.date_creation) SEPARATOR ';')
     FROM ".MAIN_DB_PREFIX."pech_bon_misenplat bm
     WHERE FIND_IN_SET(rd.rowid, REPLACE(bm.fk_receptiondet, ' ', '')) > 0) AS bon_misenplat_info,
    
    -- Calculer le total des plats formés pour ce produit depuis cette réception
    (SELECT SUM(mp.nombre_plat)
     FROM ".MAIN_DB_PREFIX."pech_bon_misenplat bm2
     INNER JOIN ".MAIN_DB_PREFIX."pech_misenplat mp ON mp.fk_bon_misenplat = bm2.rowid
     WHERE FIND_IN_SET(rd.rowid, REPLACE(bm2.fk_receptiondet, ' ', '')) > 0
     AND mp.fk_product = rd.fk_product) AS total_plats_formes,
    
    -- Calculer le poids TOTAL des mis en plat (poids_plat × nombre_plat)
    (SELECT SUM(mp.poids_plat * mp.nombre_plat)
     FROM ".MAIN_DB_PREFIX."pech_bon_misenplat bm2
     INNER JOIN ".MAIN_DB_PREFIX."pech_misenplat mp ON mp.fk_bon_misenplat = bm2.rowid
     WHERE FIND_IN_SET(rd.rowid, REPLACE(bm2.fk_receptiondet, ' ', '')) > 0
     AND mp.fk_product = rd.fk_product) AS poids_total_misenplat

FROM ".MAIN_DB_PREFIX."pech_receptiondet rd
INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = rd.fk_product
WHERE rd.fk_reception = " . (int)$fk_reception . "
ORDER BY p.label";

$res = $db->query($sql);

if ($res && $db->num_rows($res) > 0) {
    
    print '<div class="table-container">';
    print '<table class="data-table">';
    print '<thead>';
    print '<tr>';
    print '<th>' . $langs->trans('Product') . '</th>';
    print '<th class="center-cell">' . $langs->trans('NetWeight') . ' (kg)</th>';
    print '<th class="center-cell">' . $langs->trans('PlateWeight') . ' (kg)</th>';
    print '<th class="center-cell">' . $langs->trans('Remaining') . ' (kg)</th>';
    print '<th>' . $langs->trans('PlateOrders') . '</th>';
    print '<th class="center-cell">' . $langs->trans('PlatesFormed') . '</th>';
    print '<th class="center-cell">' . $langs->trans('WeightPlated') . ' (kg)</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';
    
    $total_poids_net = 0;
    $total_poids_plater = 0;
    $total_restant = 0;
    $total_poids_misenplat = 0;
    $total_plats_formes = 0;
    
    while ($obj = $db->fetch_object($res)) {
        $poids_net = (float)$obj->poids_net;
        $poids_plater = (float)$obj->poids_plater;
        $poids_total_misenplat = (float)$obj->poids_total_misenplat;
        $total_plats = (int)$obj->total_plats_formes;
        
        // Calculs
        $restant = $poids_net - $poids_plater;  // Ce qui ne sera pas mis en plat
        $restant = max($restant, 0);  // S'assurer que c'est positif
        
        $total_poids_net += $poids_net;
        $total_poids_plater += $poids_plater;
        $total_restant += $restant;
        $total_poids_misenplat += $poids_total_misenplat;
        $total_plats_formes += $total_plats;
        
        // Récupérer les détails des bons de mis en plat
        $bons_details = '';
        if (!empty($obj->bon_misenplat_info)) {
            $bons = explode(';', $obj->bon_misenplat_info);
            foreach ($bons as $bon_info) {
                list($bon_id, $bon_ref, $bon_date) = explode('|', $bon_info);
                $bons_details .= '<span class="badge" title="' . $langs->trans('CreatedOn') . ' ' . dol_print_date($bon_date, '%d/%m/%Y') . '">' . $bon_ref . '</span> ';
            }
        } else {
            $bons_details = '<em>' . $langs->trans('None') . '</em>';
        }
        
        // Pourcentages
        $pourcentage_plater = $poids_net > 0 ? ($poids_plater / $poids_net) * 100 : 0;
        $pourcentage_utilise = $poids_plater > 0 ? ($poids_total_misenplat / $poids_plater) * 100 : 0;
        $pourcentage_restant = $poids_net > 0 ? ($restant / $poids_net) * 100 : 0;
        
        // Ligne produit
        print '<tr class="product-row">';
        print '<td><strong>' . $obj->product_name . '</strong><br>';
        print '<small style="color:#6c757d;">' . $obj->product_ref . '</small></td>';
        
        // Poids net
        print '<td class="weight-cell">' . price($poids_net) . '</td>';
        
        // Poids plater avec barre de progression
        print '<td class="weight-cell">';
        print '<strong>' . price($poids_plater) . '</strong><br>';
        if ($poids_net > 0) {
            print '<div class="progress-container" title="' . round($pourcentage_plater, 1) . '%">';
            print '<div class="progress-bar" style="width: ' . min($pourcentage_plater, 100) . '%"></div>';
            print '</div>';
            print '<span class="percentage">' . round($pourcentage_plater, 1) . '%</span>';
        }
        print '</td>';
        
        // Restant (déchet)
        print '<td class="weight-cell">';
        if ($restant > 0) {
            print '<span class="restant-positive">' . price($restant) . '</span><br>';
            if ($poids_net > 0) {
                print '<span class="percentage">' . round($pourcentage_restant, 1) . '%</span>';
            }
        } else {
            print '<span class="restant-zero">✓ ' . $langs->trans('AllUsed') . '</span>';
        }
        print '</td>';
        
        // Bons de mis en plat
        print '<td>' . $bons_details . '</td>';
        
        // Plats formés
        print '<td class="center-cell"><strong>' . $total_plats . '</strong></td>';
        
        // Poids mis en plat
        print '<td class="weight-cell">';
        if ($poids_total_misenplat > 0) {
            print '<strong>' . price($poids_total_misenplat) . '</strong><br>';
            if ($poids_plater > 0) {
                print '<div class="progress-container" title="' . round($pourcentage_utilise, 1) . '%">';
                print '<div class="progress-bar" style="width: ' . min($pourcentage_utilise, 100) . '%"></div>';
                print '</div>';
                print '<span class="percentage">' . round($pourcentage_utilise, 1) . '%</span>';
            }
        } else {
            print '<em>' . $langs->trans('NotYet') . '</em>';
        }
        print '</td>';
        
        print '</tr>';
        
        // Si des bons de mis en plat existent, afficher les détails des mis en plat
        if (!empty($obj->bon_misenplat_info)) {
            $bons = explode(';', $obj->bon_misenplat_info);
            foreach ($bons as $bon_info) {
                list($bon_id, $bon_ref, $bon_date) = explode('|', $bon_info);
                
                // Récupérer les mis en plat pour ce bon et ce produit
                $sql_misenplat = "SELECT mp.rowid, mp.nombre_plat, mp.poids_plat, mp.date_creation,
                                 e.ref AS entrepot_ref
                                 FROM ".MAIN_DB_PREFIX."pech_misenplat mp
                                 LEFT JOIN ".MAIN_DB_PREFIX."pech_bon_misenplat bm ON bm.rowid = mp.fk_bon_misenplat
                                 LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = bm.fk_entrepot
                                 WHERE mp.fk_bon_misenplat = " . (int)$bon_id . "
                                 AND mp.fk_product = " . (int)$obj->fk_product . "
                                 ORDER BY mp.date_creation";
                
                $res_misenplat = $db->query($sql_misenplat);
                if ($res_misenplat && $db->num_rows($res_misenplat) > 0) {
                    while ($mp = $db->fetch_object($res_misenplat)) {
                        $poids_total_misenplat_detail = $mp->poids_plat * $mp->nombre_plat;
                        
                        print '<tr class="misenplat-detail">';
                        print '<td style="padding-left: 40px;">';
                        print '→ ' . $langs->trans('PlateSetup') . ' #' . $mp->rowid;
                        if (!empty($mp->entrepot_ref)) {
                            print ' (' . $langs->trans('Warehouse') . ': ' . $mp->entrepot_ref . ')';
                        }
                        print '</td>';
                        print '<td class="center-cell">' . dol_print_date($mp->date_creation, '%d/%m/%y') . '</td>';
                        print '<td colspan="2"></td>';
                        print '<td class="center-cell">' . $mp->nombre_plat . ' ' . $langs->trans('Plates') . '</td>';
                        print '<td class="weight-cell">';
                        print price($mp->poids_plat) . ' ' . $langs->trans('KgPerPlate') . '<br>';
                        print '<small>' . $langs->trans('Total') . ': ' . price($poids_total_misenplat_detail) . ' kg</small>';
                        print '</td>';
                        print '<td></td>';
                        print '</tr>';
                    }
                }
            }
        }
    }
    
    // Calcul des totaux
    $pourcentage_total_plater = $total_poids_net > 0 ? ($total_poids_plater / $total_poids_net) * 100 : 0;
    $pourcentage_total_utilise = $total_poids_plater > 0 ? ($total_poids_misenplat / $total_poids_plater) * 100 : 0;
    $pourcentage_total_restant = $total_poids_net > 0 ? ($total_restant / $total_poids_net) * 100 : 0;
    
    // Ligne des totaux
    print '<tr class="total-row">';
    print '<td><strong>' . $langs->trans('Totals') . '</strong></td>';
    print '<td class="weight-cell"><strong>' . price($total_poids_net) . '</strong></td>';
    print '<td class="weight-cell"><strong>' . price($total_poids_plater) . '</strong><br>';
    print '<span class="percentage">' . round($pourcentage_total_plater, 1) . '%</span></td>';
    print '<td class="weight-cell"><strong>' . price($total_restant) . '</strong><br>';
    print '<span class="percentage">' . round($pourcentage_total_restant, 1) . '%</span></td>';
    print '<td>-</td>';
    print '<td class="center-cell"><strong>' . $total_plats_formes . '</strong></td>';
    print '<td class="weight-cell"><strong>' . price($total_poids_misenplat) . '</strong><br>';
    print '<span class="percentage">' . round($pourcentage_total_utilise, 1) . '%</span></td>';
    print '</tr>';
    
    // Ligne de synthèse
    
    
    print '</tbody>';
    print '</table>';
    print '</div>';
    
} else {
    print '<div class="warning">' . $langs->trans('NoReceptionDetailsFound') . '</div>';
}

print '</div>';

// Ajout des traductions manquantes
print '<script>
$(document).ready(function() {
    // Traductions supplémentaires
    var translations = {
        "KgPerPlate": "' . $langs->trans('KgPerPlate') . '",
        "Total": "' . $langs->trans('Total') . '",
        "PlateSetup": "' . $langs->trans('PlateSetup') . '"
    };
});
</script>';

llxFooter();
$db->close();
?>