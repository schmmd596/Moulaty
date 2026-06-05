<?php
/**
 * Fichier : htdocs/custom/peche/fichier_carton_mixte.php
 * Affiche les cartons mixtes d'un lot avec leur composition détaillée
 */

// Dolibarr environment
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

// Load translation files
$langs->load("products");
$langs->load("other");

// Security check (à décommenter en production)
// $result = restrictedArea($user, 'peche', 0, '', '');

// Récupération de l'ID du lot depuis GET ou POST
$idlot = GETPOST('id', 'int');
if (empty($idlot)) {
    $idlot = GETPOST('fk_lot', 'int');
}

$morehtmlref = '';
$morehtmlright = '';

llxHeader('', $langs->trans('CartonsMixtes'), '', '', 0, 0, '', '', $morehtmlref, $morehtmlright);

// Titre principal avec style amélioré
print '<div class="titre">';
print load_fiche_titre($langs->trans('CartonsMixtesDuLot'), '', 'title_setup');
print '</div>';

// Vérification si ID lot est fourni
if (empty($idlot)) {
    print '<div class="error">'.$langs->trans('LotIDNotProvided').'</div>';
    llxFooter();
    exit;
}

// Récupération des informations du lot
$sql = "SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".((int)$idlot);
$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    llxFooter();
    exit;
}

$obj = $db->fetch_object($resql);
if (!$obj) {
    print '<div class="warning">'.$langs->trans('LotNotFound').'</div>';
    llxFooter();
    exit;
}

// En-tête informatif avec style
print '<div class="info-header">';
print '<div class="info-lot">';
print '<span class="info-label">'.$langs->trans('Lot').':</span> ';
print '<span class="info-value">'.$obj->ref.'</span> ';
print '<span class="info-id">(ID: '.$idlot.')</span>';
print '</div>';
print '</div><br>';

// 1. D'abord, trouver le produit MIXTE
$sql = "SELECT rowid, ref, label FROM ".MAIN_DB_PREFIX."product WHERE label LIKE '%MIXTE%' OR ref LIKE '%MIXTE%' LIMIT 1";
$resql = $db->query($sql);
if ($resql) {
    $obj = $db->fetch_object($resql);
    if ($obj) {
        $id_product_mixte = $obj->rowid;
        $product_mixte_ref = $obj->ref;
        $product_mixte_label = $obj->label;
    } else {
        print '<div class="warning">'.$langs->trans('ProductMixteNotFound').'</div>';
        llxFooter();
        exit;
    }
} else {
    dol_print_error($db);
    llxFooter();
    exit;
}

// 2. Récupérer tous les cartons mixtes de ce lot
$sql = "SELECT DISTINCT 
            c.rowid AS carton_id,
            c.ref_carton,
            c.poids,
            c.nb_plat AS total_plats_carton,
            c.date_creation,
            c.prix_moyen,
            c.frais,
            ld.rowid AS lotdet_id,
            p.ref AS produit_ref,
            p.label AS produit_label
        FROM ".MAIN_DB_PREFIX."pech_carton c
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON c.fk_lotdet = ld.rowid
        INNER JOIN ".MAIN_DB_PREFIX."product p ON c.fk_product = p.rowid
        WHERE ld.fk_lot = ".((int)$idlot)."
        AND c.fk_product = ".((int)$id_product_mixte)."
        ORDER BY c.ref_carton, c.date_creation";

$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    llxFooter();
    exit;
}

$num_cartons = $db->num_rows($resql);

if ($num_cartons == 0) {
    print '<div class="info">'.$langs->trans('NoMixedCartonsFoundForThisLot').'</div>';
    llxFooter();
    exit;
}

// Section de statistiques
print '<div class="statistics-box">';
print '<div class="stat-item">';
print '<span class="stat-number">'.$num_cartons.'</span>';
print '<span class="stat-label">'.$langs->trans('MixedCartonsFound').'</span>';
print '</div>';
print '<div class="stat-item">';
print '<span class="stat-label">'.$langs->trans('Product').':</span>';
print '<span class="stat-value">'.$product_mixte_label.' ('.$product_mixte_ref.')</span>';
print '</div>';
print '</div><br>';

// Conteneur principal pour les cartons
print '<div class="cartons-container">';

$carton_counter = 0;
while ($obj = $db->fetch_object($resql)) {
    $carton_counter++;
    
    print '<div class="carton-card">';
    print '<div class="carton-header">';
    print '<div class="carton-title">';
    print '<span class="carton-ref">'.$obj->ref_carton.'</span>';
    print '<span class="carton-id">#'.$carton_counter.'</span>';
    print '</div>';
    print '<div class="carton-badge">'.$langs->trans('Carton').'</div>';
    print '</div>';
    
    print '<div class="carton-details">';
    print '<div class="detail-row">';
    print '<span class="detail-label">'.$langs->trans('Ref').':</span>';
    print '<span class="detail-value">'.$obj->ref_carton.'</span>';
    print '</div>';
    
    print '<div class="detail-row">';
    print '<span class="detail-label">'.$langs->trans('Weight').':</span>';
    print '<span class="detail-value">'.$obj->poids.' kg</span>';
    print '</div>';
    
    print '<div class="detail-row">';
    print '<span class="detail-label">'.$langs->trans('TotalPlates').':</span>';
    print '<span class="detail-value highlight">'.$obj->total_plats_carton.'</span>';
    print '</div>';
    
    if ($obj->prix_moyen > 0) {
        print '<div class="detail-row">';
        print '<span class="detail-label">'.$langs->trans('AveragePrice').':</span>';
        print '<span class="detail-value">'.price($obj->prix_moyen).'</span>';
        print '</div>';
    }
    
    print '<div class="detail-row">';
    print '<span class="detail-label">'.$langs->trans('DateCreation').':</span>';
    print '<span class="detail-value">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</span>';
    print '</div>';
    print '</div>';
    
    // 3. Récupérer la composition détaillée de ce carton mixte
    // CORRECTION : utilisation de pech_misenplat au lieu de product pour le mis en plat
    $sql2 = "SELECT 
                mmm.nb_plat,
                p.ref AS produit_ref,
                p.label AS produit_label,
                misenplat.rowid AS misenplat_id,
                misenplat.fk_product AS misenplat_product_id,
                misenplat.poids_plat,
                mp.label AS misenplat_product_label,
                mp.ref AS misenplat_product_ref
            FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat mmm
            LEFT JOIN ".MAIN_DB_PREFIX."product p ON mmm.fk_product = p.rowid
            LEFT JOIN ".MAIN_DB_PREFIX."pech_misenplat misenplat ON mmm.fk_misenplat = misenplat.rowid
            LEFT JOIN ".MAIN_DB_PREFIX."product mp ON misenplat.fk_product = mp.rowid
            WHERE mmm.fk_lotdet_mixte = ".((int)$obj->lotdet_id)."
            ORDER BY mmm.nb_plat DESC";
    
    $resql2 = $db->query($sql2);
    if ($resql2) {
        $num_composants = $db->num_rows($resql2);
        
        print '<div class="composition-section">';
        print '<div class="composition-header">';
        print '<h4>'.$langs->trans('Composition').'</h4>';
        print '<span class="badge">'.$num_composants.' '.$langs->trans('components').'</span>';
        print '</div>';
        
        if ($num_composants > 0) {
            $total_plats = 0;
            $composants = array();
            
            while ($obj2 = $db->fetch_object($resql2)) {
                $composants[] = $obj2;
                $total_plats += $obj2->nb_plat;
            }
            
            print '<div class="table-responsive">';
            print '<table class="liste composition-table">';
            print '<thead>';
            print '<tr>';
            print '<th class="left">'.$langs->trans('Product').'</th>';
            print '<th class="left">'.$langs->trans('MisEnPlat').'</th>';
            print '<th class="right">'.$langs->trans('PlateWeight').'</th>';
            print '<th class="right">'.$langs->trans('NbPlates').'</th>';
            print '<th class="right">'.$langs->trans('Percentage').'</th>';
            print '</tr>';
            print '</thead>';
            print '<tbody>';
            
            foreach ($composants as $comp) {
                $percentage = ($total_plats > 0) ? round(($comp->nb_plat / $total_plats) * 100, 2) : 0;
                
                print '<tr class="oddeven">';
                print '<td>';
                print '<div class="product-info">';
                print '<div class="product-name">'.($comp->produit_label ?: $langs->trans('UnknownProduct')).'</div>';
                print '<div class="product-ref">'.($comp->produit_ref ?: 'N/A').'</div>';
                print '</div>';
                print '</td>';
                
                print '<td>';
                if ($comp->misenplat_id) {
                    print '<div class="misenplat-info">';
                    print '<div class="misenplat-name">'.($comp->misenplat_product_label ?: $langs->trans('Unknown')).'</div>';
                    print '<div class="misenplat-ref">ID: '.$comp->misenplat_id.'</div>';
                    if ($comp->misenplat_product_ref) {
                        print '<div class="misenplat-ref">Ref: '.$comp->misenplat_product_ref.'</div>';
                    }
                    print '</div>';
                } else {
                    print '<span class="text-muted">'.$langs->trans('NotSpecified').'</span>';
                }
                print '</td>';
                
                print '<td class="right">';
                if ($comp->poids_plat > 0) {
                    print number_format($comp->poids_plat, 3).' kg';
                } else {
                    print '<span class="text-muted">-</span>';
                }
                print '</td>';
                
                print '<td class="right">';
                print '<span class="plate-count">'.$comp->nb_plat.'</span>';
                print '</td>';
                
                print '<td class="right">';
                print '<div class="percentage-bar-container">';
                print '<div class="percentage-bar" style="width: '.min($percentage, 100).'%"></div>';
                print '<span class="percentage-text">'.$percentage.'%</span>';
                print '</div>';
                print '</td>';
                print '</tr>';
            }
            
            print '</tbody>';
            print '<tfoot>';
            print '<tr class="liste_total">';
            print '<td colspan="3" class="right"><strong>'.$langs->trans('Total').':</strong></td>';
            print '<td class="right"><strong class="total-plates">'.$total_plats.'</strong></td>';
            print '<td class="right"><strong>100%</strong></td>';
            print '</tr>';
            print '</tfoot>';
            print '</table>';
            print '</div>';
            
            // Vérification de cohérence
            if ($total_plats != $obj->total_plats_carton) {
                print '<div class="warning-alert">';
                print '<i class="fa fa-exclamation-triangle"></i> ';
                print $langs->trans('WarningPlateCountMismatch', $total_plats, $obj->total_plats_carton);
                print '</div>';
            }
        } else {
            print '<div class="empty-state">';
            print '<i class="fa fa-info-circle"></i> ';
            print $langs->trans('NoCompositionFound');
            print '</div>';
        }
        print '</div>';
    } else {
        dol_print_error($db);
    }
    
    print '</div>'; // Fermeture carton-card
}

print '</div>'; // Fermeture cartons-container

// Résumé global du lot avec style amélioré
print '<div class="summary-section">';
print '<div class="summary-header">';
print '<h3>'.$langs->trans('SummaryLotMixedCartons').'</h3>';
print '</div>';

// Compter le nombre total de plats par produit dans tous les cartons mixtes
$sql_summary = "SELECT 
                    p.label AS produit_label,
                    p.ref AS produit_ref,
                    SUM(mmm.nb_plat) AS total_plats_produit,
                    COUNT(DISTINCT c.rowid) AS nb_cartons_utilisant,
                    COUNT(DISTINCT mmm.fk_misenplat) AS nb_misenplats
                FROM ".MAIN_DB_PREFIX."pech_carton c
                INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON c.fk_lotdet = ld.rowid
                INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat mmm ON mmm.fk_lotdet_mixte = ld.rowid
                INNER JOIN ".MAIN_DB_PREFIX."product p ON mmm.fk_product = p.rowid
                WHERE ld.fk_lot = ".((int)$idlot)."
                AND c.fk_product = ".((int)$id_product_mixte)."
                GROUP BY p.rowid, p.label, p.ref
                ORDER BY total_plats_produit DESC";

$resql_summary = $db->query($sql_summary);
if ($resql_summary) {
    print '<div class="table-responsive">';
    print '<table class="liste summary-table">';
    print '<thead>';
    print '<tr>';
    print '<th class="left">'.$langs->trans('Product').'</th>';
    print '<th class="right">'.$langs->trans('TotalPlatesInAllMixedCartons').'</th>';
    print '<th class="right">'.$langs->trans('PercentageTotal').'</th>';
    print '<th class="right">'.$langs->trans('NumberOfCartons').'</th>';
    print '<th class="right">'.$langs->trans('NumberOfMisenplats').'</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';
    
    $total_general = 0;
    $produits_summary = array();
    
    while ($obj_sum = $db->fetch_object($resql_summary)) {
        $produits_summary[] = $obj_sum;
        $total_general += $obj_sum->total_plats_produit;
    }
    
    foreach ($produits_summary as $produit) {
        $percentage_total = ($total_general > 0) ? round(($produit->total_plats_produit / $total_general) * 100, 2) : 0;
        
        print '<tr class="oddeven">';
        print '<td>';
        print '<div class="product-summary">';
        print '<div class="product-name">'.$produit->produit_label.'</div>';
        print '<div class="product-ref">'.$produit->produit_ref.'</div>';
        print '</div>';
        print '</td>';
        
        print '<td class="right">';
        print '<strong>'.$produit->total_plats_produit.'</strong>';
        print '</td>';
        
        print '<td class="right">';
        print '<div class="total-percentage">';
        print '<div class="percentage-bar-large" style="width: '.min($percentage_total * 2, 100).'%"></div>';
        print '<span>'.$percentage_total.'%</span>';
        print '</div>';
        print '</td>';
        
        print '<td class="right">'.$produit->nb_cartons_utilisant.'</td>';
        print '<td class="right">'.$produit->nb_misenplats.'</td>';
        print '</tr>';
    }
    
    print '</tbody>';
    print '<tfoot>';
    print '<tr class="liste_total">';
    print '<td><strong>'.$langs->trans('GrandTotal').'</strong></td>';
    print '<td class="right"><strong class="grand-total">'.$total_general.'</strong></td>';
    print '<td class="right"><strong>100%</strong></td>';
    print '<td class="right"><strong>'.$num_cartons.'</strong></td>';
    print '<td class="right">-</td>';
    print '</tr>';
    print '</tfoot>';
    print '</table>';
    print '</div>';
}

print '</div>'; // Fermeture summary-section

// Boutons d'action avec style
print '<div class="action-buttons">';
print '<a href="detail_lot.php?id='.$idlot.'" class="button butAction">';
print '<i class="fa fa-arrow-left"></i> '.$langs->trans('BackToLot');
print '</a>';

print '</div>';

// Ajout de styles CSS pour améliorer l'apparence
print '<style>
    .info-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .info-lot {
        font-size: 1.2em;
    }
    
    .info-label {
        font-weight: bold;
        opacity: 0.9;
    }
    
    .info-value {
        font-size: 1.3em;
        font-weight: bold;
    }
    
    .info-id {
        background: rgba(255,255,255,0.2);
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.9em;
    }
    
    .statistics-box {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .stat-item {
        background: white;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        min-width: 200px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .stat-number {
        font-size: 2.5em;
        font-weight: bold;
        color: #667eea;
        line-height: 1;
    }
    
    .stat-label {
        color: #666;
        margin-top: 5px;
        text-align: center;
    }
    
    .stat-value {
        font-weight: bold;
        color: #333;
        margin-top: 5px;
    }
    
    .cartons-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    @media (max-width: 768px) {
        .cartons-container {
            grid-template-columns: 1fr;
        }
    }
    
    .carton-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .carton-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    
    .carton-header {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .carton-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .carton-ref {
        font-size: 1.3em;
        font-weight: bold;
    }
    
    .carton-id {
        background: rgba(255,255,255,0.2);
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.9em;
    }
    
    .carton-badge {
        background: rgba(255,255,255,0.2);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.9em;
        font-weight: bold;
    }
    
    .carton-details {
        padding: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        align-items: center;
    }
    
    .detail-row:last-child {
        margin-bottom: 0;
    }
    
    .detail-label {
        color: #666;
        font-weight: 500;
    }
    
    .detail-value {
        font-weight: bold;
        color: #333;
    }
    
    .highlight {
        color: #667eea;
        font-size: 1.1em;
    }
    
    .composition-section {
        padding: 20px;
    }
    
    .composition-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .composition-header h4 {
        margin: 0;
        color: #333;
    }
    
    .badge {
        background: #667eea;
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.9em;
    }
    
    .composition-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .composition-table th {
        background: #f8f9fa;
        padding: 12px;
        border-bottom: 2px solid #dee2e6;
    }
    
    .composition-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
    
    .product-info, .misenplat-info {
        line-height: 1.4;
    }
    
    .product-name, .misenplat-name {
        font-weight: 500;
        color: #333;
    }
    
    .product-ref, .misenplat-ref {
        font-size: 0.85em;
        color: #666;
    }
    
    .plate-count {
        font-weight: bold;
        color: #667eea;
    }
    
    .percentage-bar-container {
        position: relative;
        width: 80px;
        height: 24px;
        background: #f0f0f0;
        border-radius: 12px;
        margin-left: auto;
        display: inline-block;
    }
    
    .percentage-bar {
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        background: linear-gradient(90deg, #a8edea 0%, #fed6e3 100%);
        border-radius: 12px;
        transition: width 0.5s ease;
    }
    
    .percentage-text {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9em;
        color: #333;
        z-index: 1;
    }
    
    .warning-alert {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
        padding: 12px;
        border-radius: 6px;
        margin-top: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .empty-state {
        text-align: center;
        padding: 30px;
        color: #666;
        background: #f8f9fa;
        border-radius: 8px;
        border: 2px dashed #dee2e6;
    }
    
    .summary-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }
    
    .summary-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .summary-header h3 {
        margin: 0;
        color: #333;
    }
    
    .summary-table th {
        background: #f8f9fa;
        padding: 15px;
        font-weight: 600;
    }
    
    .summary-table td {
        padding: 15px;
        vertical-align: middle;
    }
    
    .product-summary {
        line-height: 1.4;
    }
    
    .total-percentage {
        position: relative;
        width: 120px;
        height: 24px;
        background: #f0f0f0;
        border-radius: 12px;
        margin-left: auto;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .percentage-bar-large {
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        opacity: 0.2;
    }
    
    .total-percentage span {
        position: relative;
        z-index: 1;
        font-weight: bold;
    }
    
    .grand-total {
        font-size: 1.2em;
        color: #667eea;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
        justify-content: center;
        padding: 20px;
        background: white;
        border-radius: 10px; 
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .text-muted {
        color: #999 !important;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
</style>';

llxFooter();
$db->close();
?>