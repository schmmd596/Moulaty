<?php
/**
 * Script pour afficher l'origine des cartons par lot pour une sortie
 */

require '../../../main.inc.php';

global $db, $user, $langs;

// Chargement des traductions
$langs->load("moulatyPeche@moulatyPeche");
$langs->load("products");
$langs->load("stocks");

$id = GETPOST('id', 'int');

if (!$id) {
    setEventMessages($langs->trans("ErrorMissingID"), null, 'errors');
    header("Location: list.php");
    exit;
}

// Récupération des informations de la sortie
$sql = "SELECT s.*, e1.ref as entrepot_source, e2.ref as entrepot_dest,
               u.lastname as user_lastname, u.firstname as user_firstname
        FROM ".MAIN_DB_PREFIX."pech_sortie s
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot e1 ON e1.rowid = s.fk_entrepot_source
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot e2 ON e2.rowid = s.fk_entrepot_dest
        LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = s.fk_user
        WHERE s.rowid = ".((int)$id);
$res = $db->query($sql);
$sortie = $db->fetch_object($res);

if (!$sortie) {
    setEventMessages($langs->trans("ErrorSortieNotFound"), null, 'errors');
    header("Location: list.php");
    exit;
}

$title = $langs->trans("LotOriginForSortie", $sortie->ref);
llxHeader('', $title);

// Debug: Vérifier la requête
error_log("DEBUG: ID sortie = " . $id);

// Styles CSS améliorés
print '
<style>
    /* Styles généraux améliorés */
    .fichecenter { 
        max-width: 1800px; 
        margin: 0 auto; 
        padding: 20px; 
        background: #f8f9fa;
    }
    
    /* En-tête stylisée */
    .custom-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        position: relative;
        overflow: hidden;
    }
    
    .custom-header::before {
        content: "";
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: rgba(255,255,255,0.1);
        transform: rotate(30deg);
    }
    
    .header-content {
        position: relative;
        z-index: 2;
    }
    
    .ref-sortie {
        font-size: 2.5em;
        font-weight: 800;
        margin-bottom: 10px;
        color: white;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }
    
    .title-page {
        font-size: 1.8em;
        font-weight: 600;
        margin-bottom: 20px;
        color: #333;
        text-align: center;
        padding: 15px;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        border-radius: 10px;
        border-left: 6px solid #667eea;
    }
    
    /* Cartes d\'information */
    .info-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        border: 1px solid #e0e0e0;
        transition: transform 0.3s ease;
    }
    
    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.15);
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .info-item {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
        border-left: 4px solid #667eea;
    }
    
    .info-label {
        font-size: 0.9em;
        color: #666;
        margin-bottom: 5px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-value {
        font-size: 1.2em;
        font-weight: 600;
        color: #333;
    }
    
    /* Badges améliorés */
    .badge-custom {
        padding: 8px 16px;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.85em;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    
    .badge-custom:hover {
        transform: scale(1.05);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .badge-draft {
        background: linear-gradient(135deg, #ffd166, #ff9e00);
        color: #333;
    }
    
    .badge-validated {
        background: linear-gradient(135deg, #06d6a0, #04a777);
        color: white;
    }
    
    .badge-canceled {
        background: linear-gradient(135deg, #ef476f, #d90429);
        color: white;
    }
    
    .badge-lot {
        background: linear-gradient(135deg, #118ab2, #e5eef1ff);
        color: white;
    }
    
    .badge-carton {
        background: linear-gradient(135deg, #7209b7, #3a0ca3);
        color: white;
    }
    
    /* Tableaux améliorés */
    .table-container {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        margin-bottom: 30px;
        border: 1px solid #e0e0e0;
    }
    
    .custom-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .custom-table thead th {
        background: linear-gradient(135deg, #4cc9f0, #4361ee);
        color: white;
        padding: 18px 20px;
        font-weight: 600;
        font-size: 0.95em;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .custom-table tbody td {
        padding: 16px 20px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
        transition: background-color 0.3s ease;
    }
    
    .custom-table tbody tr:hover td {
        background-color: #f8f9ff;
    }
    
    .custom-table tbody tr:nth-child(even) {
        background-color: #fafafa;
    }
    
    /* Boutons améliorés */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 25px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        text-decoration: none;
        box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .btn-custom:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(102,126,234,0.6);
        color: white;
    }
    
    .btn-back {
        background: linear-gradient(135deg, #6c757d, #495057);
    }
    
    .btn-back:hover {
        box-shadow: 0 8px 25px rgba(108,117,125,0.6);
    }
    
    /* Icônes */
    .icon {
        font-size: 1.2em;
        vertical-align: middle;
        margin-right: 8px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .custom-table thead th,
        .custom-table tbody td {
            padding: 12px 15px;
            font-size: 0.9em;
        }
        
        .ref-sortie {
            font-size: 2em;
        }
    }
    
    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade-in {
        animation: fadeIn 0.6s ease-out;
    }
    
    /* Barres de progression */
    .progress-bar {
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin: 10px 0;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #4cc9f0, #4361ee);
        border-radius: 4px;
        transition: width 1s ease-in-out;
    }
</style>
';

// Entête personnalisée
print '<div class="fichecenter animate-fade-in">';

print '<div class="custom-header">';
print '<div class="header-content">';
print '<div class="ref-sortie">📦 ' . $sortie->ref . '</div>';
print '<div style="font-size: 1.2em; opacity: 0.9;">' . $langs->trans("LotOrigin") . '</div>';
print '</div>';
print '</div>';

// Card d'information de la sortie
print '<div class="info-card">';
print '<h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-bottom: 20px;">';
print '📋 ' . $langs->trans("SortieInformation");
print '</h3>';

print '<div class="info-grid">';

print '<div class="info-item">';
print '<div class="info-label">' . $langs->trans("Ref") . '</div>';
print '<div class="info-value">' . $sortie->ref . '</div>';
print '</div>';

print '<div class="info-item">';
print '<div class="info-label">' . $langs->trans("Date") . '</div>';
print '<div class="info-value">' . dol_print_date($sortie->date_creation, 'dayhour') . '</div>';
print '</div>';

print '<div class="info-item">';
print '<div class="info-label">' . $langs->trans("WarehouseSource") . '</div>';
print '<div class="info-value">🏭 ' . $sortie->entrepot_source . '</div>';
print '</div>';

print '<div class="info-item">';
print '<div class="info-label">' . $langs->trans("User") . '</div>';
print '<div class="info-value">👤 ' . dolGetFirstLastname($sortie->user_firstname, $sortie->user_lastname) . '</div>';
print '</div>';

print '<div class="info-item">';
print '<div class="info-label">' . $langs->trans("Status") . '</div>';
print '<div class="info-value">';
if ($sortie->statut == 0) {
    print '<span class="badge-custom badge-draft">⏳ ' . $langs->trans("StatusDraft") . '</span>';
} elseif ($sortie->statut == 1) {
    print '<span class="badge-custom badge-validated">✅ ' . $langs->trans("StatusValidated") . '</span>';
} else {
    print '<span class="badge-custom badge-canceled">❌ ' . $langs->trans("Transferé") . '</span>';
}
print '</div>';
print '</div>';

print '<div class="info-item">';
print '<div class="info-label">' . $langs->trans("TotalWeight") . '</div>';
print '<div class="info-value">⚖️ ' . price($sortie->poids_total, 0, '', 1, 3) . ' kg</div>';
print '</div>';

print '<div class="info-item">';
print '<div class="info-label">' . $langs->trans("TotalCartons") . '</div>';
print '<div class="info-value">📦 ' . $sortie->nb_carton_total . '</div>';
print '</div>';

print '</div>';
print '</div>';

// Debug: Afficher l'ID et compter les cartons
$sql_debug = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."pech_sortiedetprod WHERE fk_sortie = ".((int)$id);
$res_debug = $db->query($sql_debug);
if ($res_debug) {
    $debug = $db->fetch_object($res_debug);
    error_log("DEBUG: Total produits dans sortie = " . $debug->total);
}

// Requête pour regrouper par lot (identique à votre code original)

// Requête pour regrouper par lot
$sql = "
    SELECT 
        l.rowid as lot_id,
        l.ref as lot_ref,
        l.date_creation as lot_date,
        e.ref as entrepot_lot,
        
        COUNT(DISTINCT sc.fk_carton) as nb_cartons_sortie,
        COUNT(DISTINCT c.rowid) as nb_cartons_total,
        SUM(sc.poids) as total_poids_sortie,
        SUM(c.poids) as total_poids_lot,
        
        GROUP_CONCAT(DISTINCT p.ref ORDER BY p.ref SEPARATOR ', ') as produits,
        GROUP_CONCAT(DISTINCT CONCAT('<span class=\"badge badge-status4\">', c.ref_carton, '</span> (', FORMAT(c.poids, 3), ' kg)') ORDER BY c.ref_carton SEPARATOR ' ') as details_cartons
        
    FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton sc
    LEFT JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.rowid = sc.fk_carton
    LEFT JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
    LEFT JOIN ".MAIN_DB_PREFIX."pech_lot l ON l.rowid = ld.fk_lot
    LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ld.fk_product
    LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = l.fk_entrepot
    
    WHERE sc.fk_sortiedetprod IN (
        SELECT rowid FROM ".MAIN_DB_PREFIX."pech_sortiedetprod WHERE fk_sortie = ".((int)$id)."
    )
    
    GROUP BY l.rowid, l.ref, l.date_creation, e.ref
    ORDER BY l.ref";

$res = $db->query($sql);

error_log("DEBUG: Requête SQL exécutée, nombre de résultats = " . $db->num_rows($res));

if ($db->num_rows($res) > 0) {
    $total_cartons = 0;
    $total_poids = 0;
    
    // Section 1: Répartition par lot
   
    
    // Section 2: Détail par produit
    $sql_detail = "
        SELECT 
            p.rowid as product_id,
            p.ref as product_ref,
            p.label as product_label,
            l.rowid as lot_id,
            l.ref as lot_ref,
            COUNT(DISTINCT sc.fk_carton) as nb_cartons,
            SUM(sc.poids) as total_poids
            
        FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton sc
        LEFT JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.rowid = sc.fk_carton
        LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = c.fk_product
        LEFT JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
        LEFT JOIN ".MAIN_DB_PREFIX."pech_lot l ON l.rowid = ld.fk_lot
        
        WHERE sc.fk_sortiedetprod IN (
            SELECT rowid FROM ".MAIN_DB_PREFIX."pech_sortiedetprod WHERE fk_sortie = ".((int)$id)."
        )
        
        GROUP BY p.rowid, p.ref, p.label, l.rowid, l.ref
        ORDER BY p.ref, l.ref";
    
    $res_detail = $db->query($sql_detail);
    
    if ($db->num_rows($res_detail) > 0) {
        print '<div class="table-container">';
        print '<div class="title-page">📦 ' . $langs->trans("DetailByProduct") . '</div>';
        
        print '<table class="custom-table">';
        print '<thead>';
        print '<tr>';
        print '<th width="30%">' . $langs->trans("Product") . '</th>';
        print '<th width="30%">' . $langs->trans("Lot") . '</th>';
        print '<th width="20%" style="text-align: center;">' . $langs->trans("NumberOfCartons") . '</th>';
        print '<th width="20%" style="text-align: right;">' . $langs->trans("TotalWeight") . '</th>';
        print '</tr>';
        print '</thead>';
        print '<tbody>';
        
        $current_product = null;
        $product_subtotal = 0;
        $product_subtotal_cartons = 0;
        
        $products = array();
        while ($obj = $db->fetch_object($res_detail)) {
            if (!isset($products[$obj->product_id])) {
                $products[$obj->product_id] = array(
                    'ref' => $obj->product_ref,
                    'label' => $obj->product_label,
                    'rows' => 0,
                    'subtotal_cartons' => 0,
                    'subtotal_weight' => 0,
                    'lots' => array()
                );
            }
            $products[$obj->product_id]['rows']++;
            $products[$obj->product_id]['subtotal_cartons'] += $obj->nb_cartons;
            $products[$obj->product_id]['subtotal_weight'] += $obj->total_poids;
            $products[$obj->product_id]['lots'][] = $obj;
        }
        
        foreach ($products as $product_id => $product_data) {
            $first_row = true;
            
            foreach ($product_data['lots'] as $lot) {
                print '<tr>';
                
                // Colonne Produit
                if ($first_row) {
                    print '<td rowspan="' . $product_data['rows'] . '" style="vertical-align: top;">';
                    print '<div style="font-weight: 700; color: #2d00f8ff; margin-bottom: 5px;">';
                    print '📦 ' . $product_data['ref'];
                    print '</div>';
                    print '<div style="color: #666; font-size: 0.9em;">';
                    print $product_data['label'];
                    print '</div>';
                    print '</td>';
                    $first_row = false;
                }
                
                // Colonne Lot
                print '<td>';
                if ($lot->lot_id) {
                    print '<a href="' . DOL_URL_ROOT . '/moulatyPeche/Lot/detail_lot.php?id=' . $lot->lot_id . '" class="badge-custom badge-lot" style="text-decoration: none; display: inline-block;">';
                    print '📁 ' . $lot->lot_ref;
                    print '</a>';
                } else {
                    print '<span class="badge-custom" style="background: #6c757d;">-</span>';
                }
                print '</td>';
                
                // Autres colonnes
                print '<td style="text-align: center; font-weight: 600; color: #4361ee;">' . $lot->nb_cartons . '</td>';
                print '<td style="text-align: right; font-weight: 600;">' . price($lot->total_poids, 0, '', 1, 3) . ' kg</td>';
                
                print '</tr>';
            }
            
            // Sous-total pour ce produit
            print '<tr style="background: #f8f9fa; font-weight: 600;">';
            print '<td colspan="2" style="text-align: right; color: #333; padding: 15px;">';
            print '📊 ' . $langs->trans("SubtotalForProduct") . ' ' . $product_data['ref'];
            print '</td>';
            print '<td style="text-align: center; color: #4361ee; padding: 15px;">';
            print $product_data['subtotal_cartons'];
            print '</td>';
            print '<td style="text-align: right; color: #333; padding: 15px;">';
            print price($product_data['subtotal_weight'], 0, '', 1, 3) . ' kg';
            print '</td>';
            print '</tr>';
            $product_subtotal_cartons += $product_data['subtotal_cartons'];
            $total_poids += $product_data['subtotal_weight'];
        }
        
        // Total général
        print '<tr style="background: linear-gradient(135deg, #2d3436, #636e72); color: white; font-weight: 700;">';
        print '<td colspan="2" style="text-align: right; padding: 20px;">';
        print '📊 ' . $langs->trans("Total");
        print '</td>';
        print '<td style="text-align: center; padding: 20px; font-size: 1.2em;">';
        print $product_subtotal_cartons;
        print '</td>';
        print '<td style="text-align: right; padding: 20px; font-size: 1.2em;">';
        print price($total_poids, 0, '', 1, 3) . ' kg';
        print '</td>';
        print '</tr>';
        
        print '</tbody>';
        print '</table>';
        print '</div>';
    }
    
} else {
    // Aucun résultat - message d'erreur amélioré
    print '<div class="info-card" style="text-align: center; padding: 50px 30px;">';
    print '<div style="font-size: 4em; color: #e0e0e0; margin-bottom: 20px;">📭</div>';
    print '<h3 style="color: #666; margin-bottom: 15px;">' . $langs->trans("NoCartonsFound") . '</h3>';
    print '<p style="color: #888; max-width: 600px; margin: 0 auto; line-height: 1.6;">';
    print $langs->trans("NoCartonsLinkedToLotsDesc");
    print '</p>';
    print '<div style="margin-top: 30px;">';
    print '<a href="detail.php?id=' . $id . '" class="btn-custom btn-back">';
    print '← ' . $langs->trans("BackToSortie");
    print '</a>';
    print '</div>';
    print '</div>';
}

// Boutons d'action
if ($db->num_rows($res) > 0) {
    print '<div style="text-align: center; margin: 40px 0;">';
    print '<a href="detail.php?id=' . $id . '" class="btn-custom btn-back">';
    print '← ' . $langs->trans("BackToSortie");
    print '</a>';
    print '</div>';
}

print '</div>';

llxFooter();
$db->close();
?>
