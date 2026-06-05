<?php
/**
 * État du stock des plats - Filtrage par entrepôt, produit ou bon de mise en plat
 * Version améliorée avec tableau structuré et design moderne
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

global $db, $langs, $user, $conf;
$langs->loadLangs(['stocks', 'main', 'womapeche@womapeche', 'abricot@abricot']);

$form = new Form($db);

// ============================================================================
// 🔹 RÉCUPÉRATION DES FILTRES
// ============================================================================
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$fk_product  = GETPOST('fk_product', 'int');
$fk_bon_misenplat = GETPOST('fk_bon_misenplat', 'int');
$date_debut = GETPOST('date_debut', 'alpha');
$date_fin = GETPOST('date_fin', 'alpha');
$statut_plat = GETPOST('statut_plat', 'int'); // 0: stock, 1: sorti, 2: tous
$view_type = GETPOST('view_type', 'alpha') ?: 'summary'; // summary ou details

// ============================================================================
// 🔹 RÉCUPÉRATION DES DONNÉES POUR LES FILTRES
// ============================================================================

// Entrepôts accessibles par l'utilisateur
$entrepots = [0 => $langs->trans("AllWarehouses")];
$entrepots_accessibles = [];

$sql_ent_usr = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent_usr = $db->query($sql_ent_usr);
if ($res_ent_usr && $db->num_rows($res_ent_usr) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent_usr)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

if (!empty($entrepots_accessibles)) {
    $sql_entrepots = "SELECT rowid, ref, label FROM ".MAIN_DB_PREFIX."entrepot 
                      WHERE entity = ".$conf->entity." 
                      AND rowid IN (" . implode(',', $entrepots_accessibles) . ")
                      ORDER BY ref ASC";
} else {
    $sql_entrepots = "SELECT rowid, ref, label FROM ".MAIN_DB_PREFIX."entrepot 
                      WHERE entity = ".$conf->entity." 
                      ORDER BY ref ASC";
}

$res_entrepots = $db->query($sql_entrepots);
if ($res_entrepots) {
    while ($obj = $db->fetch_object($res_entrepots)) {
        $entrepots[$obj->rowid] = $obj->ref . ' - ' . $obj->label;
    }
}

// Produits (type poisson)
$produits = [0 => $langs->trans("AllProducts")];
$sql_produits = "SELECT p.rowid, p.ref, p.label 
                 FROM ".MAIN_DB_PREFIX."product p
                 INNER JOIN ".MAIN_DB_PREFIX."categorie_product cp ON cp.fk_product = p.rowid
                 INNER JOIN ".MAIN_DB_PREFIX."categorie c ON c.rowid = cp.fk_categorie
                 WHERE c.label = 'POISSON'
                 ORDER BY p.label";
$res_produits = $db->query($sql_produits);
if ($res_produits) {
    while ($obj = $db->fetch_object($res_produits)) {
        $produits[$obj->rowid] = $obj->label . ' (' . $obj->ref . ')';
    }
}

// Bons de mise en plat récents
$bons_misenplat = [0 => $langs->trans("AllMisenplatBons")];
$sql_bons = "SELECT b.rowid, b.ref, b.date_creation, e.ref as entrepot_ref
             FROM ".MAIN_DB_PREFIX."pech_bon_misenplat b
             LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = b.fk_entrepot
             WHERE b.statut >= 0";
             
if (!empty($entrepots_accessibles)) {
    $sql_bons .= " AND b.fk_entrepot IN (" . implode(',', $entrepots_accessibles) . ")";
}

$sql_bons .= " ORDER BY b.date_creation DESC LIMIT 100";

$res_bons = $db->query($sql_bons);
if ($res_bons) {
    while ($obj = $db->fetch_object($res_bons)) {
        $bons_misenplat[$obj->rowid] = $obj->ref . ' - ' . dol_print_date($db->jdate($obj->date_creation), 'day') . ' (' . $obj->entrepot_ref . ')';
    }
}

// Options de statut
$statut_options = [
    0 => $langs->trans("InStock"),
    1 => $langs->trans("OutStock"),
    2 => $langs->trans("AllStatus")
];

// ============================================================================
// 🔹 EN-TÊTE DE PAGE AVEC STYLE AMÉLIORÉ
// ============================================================================
llxHeader('', $langs->trans("StockPlatsState"));
print '<link rel="stylesheet" href="../css/selection_form_style.css">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
print '<style>
    .stock-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .filter-panel {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
        color: white;
    }
    
    .filter-title {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .filter-title i {
        font-size: 24px;
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-label {
        font-weight: 500;
        margin-bottom: 8px;
        font-size: 14px;
        opacity: 0.9;
    }
    
    .filter-select, .filter-input {
        padding: 12px 15px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.2);
        background: rgba(255,255,255,0.1);
        color: white;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .filter-select option {
        color: #333;
        background: white;
    }
    
    .filter-select:focus, .filter-input:focus {
        border-color: white;
        background: rgba(255,255,255,0.15);
        box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
        outline: none;
    }
    
    .filter-actions {
        display: flex;
        gap: 12px;
        margin-top: 25px;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
    }
    
    .view-switch {
        display: flex;
        gap: 10px;
        background: rgba(255,255,255,0.1);
        padding: 8px;
        border-radius: 8px;
    }
    
    .view-btn {
        padding: 8px 16px;
        border-radius: 6px;
        background: transparent;
        border: 1px solid transparent;
        color: rgba(255,255,255,0.8);
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .view-btn.active {
        background: white;
        color: #667eea;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .view-btn:hover:not(.active) {
        background: rgba(255,255,255,0.1);
        border-color: rgba(255,255,255,0.3);
    }
    
    .action-buttons {
        display: flex;
        gap: 12px;
    }
    
    .filter-btn {
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: white;
        color: #667eea;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255,255,255,0.2);
    }
    
    .btn-secondary {
        background: rgba(255,255,255,0.1);
        color: white;
        border: 1px solid rgba(255,255,255,0.3);
    }
    
    .btn-secondary:hover {
        background: rgba(255,255,255,0.2);
    }
    
    .results-section {
        background: white;
        border-radius: 12px;
        padding: 0;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        border: 1px solid #e9ecef;
    }
    
    .results-header {
        background: #f8f9fa;
        padding: 20px 25px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .results-title {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .results-stats {
        display: flex;
        gap: 15px;
        font-size: 13px;
        color: #6c757d;
    }
    
    .stat-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .stat-value {
        font-weight: 600;
        color: #495057;
    }
    
    /* Tableau stylisé */
    .stock-table-container {
        overflow-x: auto;
    }
    
    .stock-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }
    
    .stock-table th {
        background: #f1f5f9;
        padding: 15px 20px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }
    
    .stock-table td {
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        color: #475569;
        vertical-align: middle;
    }
    
    .stock-table tbody tr {
        transition: all 0.2s;
    }
    
    .stock-table tbody tr:hover {
        background: #f8fafc;
    }
    
    .stock-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    /* Badges */
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    
    .badge-stock {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: white;
    }
    
    .badge-out {
        background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
        color: white;
    }
    
    .badge-partial {
        background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        color: white;
    }
    
    /* Indicateurs */
    .stock-indicator {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .indicator-bar {
        flex: 1;
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
        position: relative;
    }
    
    .indicator-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s;
    }
    
    .fill-stock {
        background: linear-gradient(90deg, #48bb78, #38a169);
    }
    
    .fill-out {
        background: linear-gradient(90deg, #f56565, #e53e3e);
    }
    
    /* Cartes pour la vue détaillée */
    .cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        padding: 25px;
    }
    
    .plat-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        border: 1px solid #e9ecef;
        transition: all 0.3s;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }
    
    .plat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: #cbd5e1;
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .card-title {
        font-size: 15px;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .card-info {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .info-group {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 4px;
        font-weight: 500;
    }
    
    .info-value {
        font-size: 14px;
        font-weight: 600;
        color: #334155;
    }
    
    /* Totaux */
    .totals-bar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        margin-top: 20px;
        border-radius: 12px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .total-item {
        text-align: center;
        background: rgba(255,255,255,0.1);
        padding: 15px;
        border-radius: 8px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    .total-label {
        font-size: 12px;
        opacity: 0.9;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .total-value {
        font-size: 26px;
        font-weight: 700;
    }
    
    /* États vides */
    .no-data {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }
    
    .no-data-icon {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.3;
    }
    
    .no-data h3 {
        font-size: 18px;
        color: #64748b;
        margin-bottom: 10px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-actions {
            flex-direction: column;
            align-items: stretch;
        }
        
        .view-switch {
            justify-content: center;
        }
        
        .action-buttons {
            justify-content: center;
        }
        
        .cards-container {
            grid-template-columns: 1fr;
        }
        
        .results-header {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
        
        .results-stats {
            justify-content: center;
        }
    }
</style>';

print '<div class="stock-container">';

// ============================================================================
// 🔹 FORMULAIRE DE FILTRAGE AMÉLIORÉ
// ============================================================================
print '<div class="filter-panel">';
print '<div class="filter-title">';
print '<i class="fas fa-filter"></i> ' . $langs->trans("Filters");
print '</div>';

print '<form method="GET" action="' . $_SERVER["PHP_SELF"] . '" id="filterForm">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="view_type" id="viewType" value="' . $view_type . '">';

print '<div class="filter-grid">';

// Entrepôt
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fas fa-warehouse"></i> ' . $langs->trans("Warehouse") . '</label>';
print $form->selectarray('fk_entrepot', $entrepots, $fk_entrepot, 0, 0, 0, '', 0, 0, 0, '', 'class="filter-select"');
print '</div>';

// Produit
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fas fa-cube"></i> ' . $langs->trans("Product") . '</label>';
print $form->selectarray('fk_product', $produits, $fk_product, 0, 0, 0, '', 0, 0, 0, '', 'class="filter-select"');
print '</div>';

// Bon de mise en plat
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fas fa-file-alt"></i> ' . $langs->trans("MisenplatBon") . '</label>';
print $form->selectarray('fk_bon_misenplat', $bons_misenplat, $fk_bon_misenplat, 0, 0, 0, '', 0, 0, 0, '', 'class="filter-select"');
print '</div>';

// Statut du plat
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fas fa-info-circle"></i> ' . $langs->trans("Status") . '</label>';
print $form->selectarray('statut_plat', $statut_options, $statut_plat, 0, 0, 0, '', 0, 0, 0, '', 'class="filter-select"');
print '</div>';

// Date de début
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fas fa-calendar"></i> ' . $langs->trans("DateFrom") . '</label>';
print '<input type="date" name="date_debut" value="' . $date_debut . '" class="filter-input">';
print '</div>';

// Date de fin
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fas fa-calendar"></i> ' . $langs->trans("DateTo") . '</label>';
print '<input type="date" name="date_fin" value="' . $date_fin . '" class="filter-input">';
print '</div>';

print '</div>';

// Boutons d'action et switch de vue
print '<div class="filter-actions">';
print '<div class="view-switch">';
print '<button type="button" class="view-btn ' . ($view_type == 'summary' ? 'active' : '') . '" onclick="setViewType(\'summary\')">';
print '<i class="fas fa-table"></i> ' . $langs->trans("SummaryView");
print '</button>';
print '<button type="button" class="view-btn ' . ($view_type == 'details' ? 'active' : '') . '" onclick="setViewType(\'details\')">';
print '<i class="fas fa-list"></i> ' . $langs->trans("DetailedView");
print '</button>';
print '</div>';

print '<div class="action-buttons">';
print '<button type="submit" class="filter-btn btn-primary">';
print '<i class="fas fa-search"></i> ' . $langs->trans("ShowResults");
print '</button>';
print '<a href="' . $_SERVER["PHP_SELF"] . '" class="filter-btn btn-secondary">';
print '<i class="fas fa-redo"></i> ' . $langs->trans("Reset");
print '</a>';
print '</div>';
print '</div>';

print '</form>';
print '</div>';

// ============================================================================
// 🔹 CONSTRUCTION DES FILTRES SQL
// ============================================================================
$sql_where = [];
$params = [];

// Filtre par entrepôt
if ($fk_entrepot > 0) {
    $sql_where[] = "b.fk_entrepot = ?";
    $params[] = $fk_entrepot;
}

// Filtre par produit
if ($fk_product > 0) {
    $sql_where[] = "m.fk_product = ?";
    $params[] = $fk_product;
}

// Filtre par bon de mise en plat
if ($fk_bon_misenplat > 0) {
    $sql_where[] = "b.rowid = ?";
    $params[] = $fk_bon_misenplat;
}

// Filtres par date
if (!empty($date_debut)) {
    $sql_where[] = "DATE(b.date_creation) >= ?";
    $params[] = $date_debut;
}
if (!empty($date_fin)) {
    $sql_where[] = "DATE(b.date_creation) <= ?";
    $params[] = $date_fin;
}

// Filtre par statut
if ($statut_plat == 0) {
    $sql_where[] = "pl.statut = 0";
} elseif ($statut_plat == 1) {
    $sql_where[] = "pl.statut = 1";
}

// Restriction d'accès aux entrepôts
if (!empty($entrepots_accessibles)) {
    $sql_where[] = "b.fk_entrepot IN (" . implode(',', $entrepots_accessibles) . ")";
}

// ============================================================================
// 🔹 REQUÊTES SQL PRINCIPALES
// ============================================================================

// Requête pour les totaux généraux
$sql_totals = "
    SELECT 
        COUNT(pl.rowid) as total_plats,
        SUM(pl.poids) as total_poids,
        SUM(CASE WHEN pl.statut = 0 THEN 1 ELSE 0 END) as plats_stock,
        SUM(CASE WHEN pl.statut = 0 THEN pl.poids ELSE 0 END) as poids_stock,
        SUM(CASE WHEN pl.statut = 1 THEN 1 ELSE 0 END) as plats_sortis,
        SUM(CASE WHEN pl.statut = 1 THEN pl.poids ELSE 0 END) as poids_sortis,
        COUNT(DISTINCT b.fk_entrepot) as nb_entrepots,
        COUNT(DISTINCT m.fk_product) as nb_produits
    FROM " . MAIN_DB_PREFIX . "pech_plat pl
    INNER JOIN " . MAIN_DB_PREFIX . "pech_misenplat m ON m.rowid = pl.fk_misenplat
    INNER JOIN " . MAIN_DB_PREFIX . "pech_bon_misenplat b ON b.rowid = m.fk_bon_misenplat
";

if (!empty($sql_where)) {
    $sql_totals .= " WHERE " . implode(" AND ", $sql_where);
}

// Requête pour le résumé par entrepôt/produit
$sql_summary = "
    SELECT 
        e.rowid as entrepot_id,
        e.ref as entrepot_ref,
        e.label as entrepot_label,
        p.rowid as product_id,
        p.ref as product_ref,
        p.label as product_label,
        COUNT(pl.rowid) as total_plats,
        SUM(pl.poids) as total_poids,
        SUM(CASE WHEN pl.statut = 0 THEN 1 ELSE 0 END) as plats_stock,
        SUM(CASE WHEN pl.statut = 0 THEN pl.poids ELSE 0 END) as poids_stock,
        SUM(CASE WHEN pl.statut = 1 THEN 1 ELSE 0 END) as plats_sortis,
        SUM(CASE WHEN pl.statut = 1 THEN pl.poids ELSE 0 END) as poids_sortis,
        SUM(CASE WHEN pl.fk_carton IS NOT NULL THEN 1 ELSE 0 END) as plats_carton,
        SUM(CASE WHEN pl.fk_carton IS NOT NULL THEN pl.poids ELSE 0 END) as poids_carton,
        SUM(CASE WHEN pl.fk_carton IS NULL THEN 1 ELSE 0 END) as plats_tunnel,
        SUM(CASE WHEN pl.fk_carton IS NULL THEN pl.poids ELSE 0 END) as poids_tunnel,
        ROUND(AVG(pl.prix_moyen), 2) as prix_moyen_moyen,
        ROUND(AVG(pl.frais), 2) as frais_moyen
    FROM " . MAIN_DB_PREFIX . "pech_plat pl
    INNER JOIN " . MAIN_DB_PREFIX . "pech_misenplat m ON m.rowid = pl.fk_misenplat
    INNER JOIN " . MAIN_DB_PREFIX . "pech_bon_misenplat b ON b.rowid = m.fk_bon_misenplat
    INNER JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = b.fk_entrepot
    INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = m.fk_product
";

if (!empty($sql_where)) {
    $sql_summary .= " WHERE " . implode(" AND ", $sql_where);
}

$sql_summary .= " GROUP BY e.rowid, p.rowid
                  ORDER BY e.ref ASC, p.label ASC";

// Requête pour les détails
$sql_details = "
    SELECT 
        pl.rowid as plat_id,
        pl.poids,
        pl.prix_moyen,
        pl.frais,
        pl.statut as plat_statut,
        pl.date_creation as plat_date,
        pl.fk_carton,
        pl.commentaire as plat_commentaire,
        b.ref as bon_ref,
        b.rowid as bon_id,
        b.date_creation as bon_date,
        e.ref as entrepot_ref,
        e.label as entrepot_label,
        p.label as product_label,
        p.ref as product_ref,
        m.nombre_plat,
        m.nombre_plat_sortie,
        m.poids_plat,
        c.ref as carton_ref,
        prod_carton.label as carton_product_label
    FROM " . MAIN_DB_PREFIX . "pech_plat pl
    INNER JOIN " . MAIN_DB_PREFIX . "pech_misenplat m ON m.rowid = pl.fk_misenplat
    INNER JOIN " . MAIN_DB_PREFIX . "pech_bon_misenplat b ON b.rowid = m.fk_bon_misenplat
    INNER JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = b.fk_entrepot
    INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = m.fk_product
    LEFT JOIN " . MAIN_DB_PREFIX . "pech_carton c ON c.rowid = pl.fk_carton
    LEFT JOIN " . MAIN_DB_PREFIX . "product prod_carton ON prod_carton.rowid = c.fk_product
";

if (!empty($sql_where)) {
    $sql_details .= " WHERE " . implode(" AND ", $sql_where);
}

$sql_details .= " ORDER BY e.ref ASC, p.label ASC, b.date_creation DESC, pl.date_creation DESC";

// ============================================================================
// 🔹 EXÉCUTION DES REQUÊTES ET CALCUL DES TOTAUX
// ============================================================================
$totals = null;
$res_totals = $db->query($sql_totals);
if ($res_totals) {
    $totals = $db->fetch_object($res_totals);
}

// ============================================================================
// 🔹 AFFICHAGE DES RÉSULTATS
// ============================================================================
print '<div class="results-section">';

// En-tête des résultats
print '<div class="results-header">';
print '<div class="results-title">';
if ($view_type == 'summary') {
    print '<i class="fas fa-table"></i> ' . $langs->trans("StockSummary");
} else {
    print '<i class="fas fa-list"></i> ' . $langs->trans("StockDetails");
}
print '</div>';

if ($totals) {
    print '<div class="results-stats">';
    print '<div class="stat-item"><i class="fas fa-cube"></i> <span class="stat-value">' . ($totals->total_plats ?: 0) . '</span> ' . $langs->trans("Plats") . '</div>';
    print '<div class="stat-item"><i class="fas fa-balance-scale"></i> <span class="stat-value">' . price($totals->total_poids ?: 0) . ' kg</span></div>';
    print '<div class="stat-item"><i class="fas fa-warehouse"></i> <span class="stat-value">' . ($totals->nb_entrepots ?: 0) . '</span> ' . $langs->trans("Warehouses") . '</div>';
    print '<div class="stat-item"><i class="fas fa-boxes"></i> <span class="stat-value">' . ($totals->nb_produits ?: 0) . '</span> ' . $langs->trans("Products") . '</div>';
    print '</div>';
}
print '</div>';

// Contenu selon la vue sélectionnée
if ($view_type == 'summary') {
    // VUE SYNTHÈSE : TABLEAU
    $res_summary = $db->query($sql_summary);
    
    if ($res_summary && $db->num_rows($res_summary) > 0) {
        print '<div class="stock-table-container">';
        print '<table class="stock-table">';
        print '<thead>';
        print '<tr>';
        print '<th>' . $langs->trans("Warehouse") . '</th>';
        print '<th>' . $langs->trans("Product") . '</th>';
        print '<th>' . $langs->trans("TotalPlats") . '</th>';
        print '<th>' . $langs->trans("TotalWeight") . ' (kg)</th>';
        print '<th>' . $langs->trans("InStock") . '</th>';
        print '<th>' . $langs->trans("OutStock") . '</th>';
        print '<th>' . $langs->trans("InCarton") . '</th>';
        print '<th>' . $langs->trans("InTunnel") . '</th>';
        print '<th>' . $langs->trans("Status") . '</th>';
        print '<th>' . $langs->trans("AvgPrice") . '</th>';
        print '<th>' . $langs->trans("AvgFees") . '</th>';
        print '</tr>';
        print '</thead>';
        print '<tbody>';
        
        $total_plats = 0;
        $total_poids = 0;
        
        while ($obj = $db->fetch_object($res_summary)) {
            $total_plats += $obj->total_plats;
            $total_poids += $obj->total_poids;
            
            // Calcul des pourcentages
            $pourcent_stock = $obj->total_plats > 0 ? round(($obj->plats_stock / $obj->total_plats) * 100) : 0;
            $pourcent_sortis = $obj->total_plats > 0 ? round(($obj->plats_sortis / $obj->total_plats) * 100) : 0;
            
            // Détermination du statut
            if ($obj->plats_stock == $obj->total_plats) {
                $status_badge = '<span class="status-badge badge-stock"><i class="fas fa-box"></i> ' . $langs->trans("AllInStock") . '</span>';
            } elseif ($obj->plats_sortis == $obj->total_plats) {
                $status_badge = '<span class="status-badge badge-out"><i class="fas fa-truck"></i> ' . $langs->trans("AllOut") . '</span>';
            } else {
                $status_badge = '<span class="status-badge badge-partial"><i class="fas fa-exchange-alt"></i> ' . $langs->trans("Partial") . '</span>';
            }
            
            print '<tr>';
            print '<td><strong>' . $obj->entrepot_ref . '</strong><br><small>' . $obj->entrepot_label . '</small></td>';
            print '<td><strong>' . $obj->product_label . '</strong><br><small>' . $obj->product_ref . '</small></td>';
            print '<td><strong>' . $obj->total_plats . '</strong></td>';
            print '<td><strong>' . price($obj->total_poids) . '</strong></td>';
            print '<td>';
            print '<div class="stock-indicator">';
            print '<span>' . $obj->plats_stock . '</span>';
            print '<div class="indicator-bar">';
            print '<div class="indicator-fill fill-stock" style="width: ' . $pourcent_stock . '%"></div>';
            print '</div>';
            print '</div>';
            print '</td>';
            print '<td>';
            print '<div class="stock-indicator">';
            print '<span>' . $obj->plats_sortis . '</span>';
            print '<div class="indicator-bar">';
            print '<div class="indicator-fill fill-out" style="width: ' . $pourcent_sortis . '%"></div>';
            print '</div>';
            print '</div>';
            print '</td>';
            print '<td>' . $obj->plats_carton . ' (' . price($obj->poids_carton) . ' kg)</td>';
            print '<td>' . $obj->plats_tunnel . ' (' . price($obj->poids_tunnel) . ' kg)</td>';
            print '<td>' . $status_badge . '</td>';
            print '<td>' . price($obj->prix_moyen_moyen) . ' ' . $conf->currency . '</td>';
            print '<td>' . price($obj->frais_moyen) . ' ' . $conf->currency . '</td>';
            print '</tr>';
        }
        
        print '</tbody>';
        print '</table>';
        print '</div>';
        
        // Totaux en bas du tableau
        print '<div class="totals-bar">';
        print '<div class="total-item">';
        print '<div class="total-label">' . $langs->trans("TotalPlats") . '</div>';
        print '<div class="total-value">' . $total_plats . '</div>';
        print '</div>';
        
        print '<div class="total-item">';
        print '<div class="total-label">' . $langs->trans("TotalWeight") . '</div>';
        print '<div class="total-value">' . price($total_poids) . ' kg</div>';
        print '</div>';
        
        if ($totals) {
            print '<div class="total-item">';
            print '<div class="total-label">' . $langs->trans("InStock") . '</div>';
            print '<div class="total-value">' . ($totals->plats_stock ?: 0) . '</div>';
            print '</div>';
            
            print '<div class="total-item">';
            print '<div class="total-label">' . $langs->trans("OutStock") . '</div>';
            print '<div class="total-value">' . ($totals->plats_sortis ?: 0) . '</div>';
            print '</div>';
        }
        print '</div>';
        
    } else {
        print '<div class="no-data">';
        print '<div class="no-data-icon"><i class="fas fa-database"></i></div>';
        print '<h3>' . $langs->trans("NoDataFound") . '</h3>';
        print '<p>' . $langs->trans("ModifyFilters") . '</p>';
        print '</div>';
    }
    
} else {
    // VUE DÉTAILLÉE : CARTES
    $res_details = $db->query($sql_details);
    
    if ($res_details && $db->num_rows($res_details) > 0) {
        print '<div class="cards-container">';
        
        while ($obj = $db->fetch_object($res_details)) {
            print '<div class="plat-card">';
            print '<div class="card-header">';
            print '<div class="card-title">';
            print 'Plat #' . $obj->plat_id . ' - ' . $obj->product_label;
            print '</div>';
            print '<span class="status-badge ' . ($obj->plat_statut == 0 ? 'badge-stock' : 'badge-out') . '">';
            print $obj->plat_statut == 0 ? '<i class="fas fa-box"></i> ' . $langs->trans("InStock") : '<i class="fas fa-truck"></i> ' . $langs->trans("OutStock");
            print '</span>';
            print '</div>';
            
            print '<div class="card-info">';
            
            print '<div class="info-group">';
            print '<div class="info-label">' . $langs->trans("Weight") . '</div>';
            print '<div class="info-value">' . price($obj->poids) . ' kg</div>';
            print '</div>';
            
            print '<div class="info-group">';
            print '<div class="info-label">' . $langs->trans("AvgPrice") . '</div>';
            print '<div class="info-value">' . price($obj->prix_moyen) . ' ' . $conf->currency . '</div>';
            print '</div>';
            
            print '<div class="info-group">';
            print '<div class="info-label">' . $langs->trans("Fees") . '</div>';
            print '<div class="info-value">' . price($obj->frais) . ' ' . $conf->currency . '</div>';
            print '</div>';
            
            print '<div class="info-group">';
            print '<div class="info-label">' . $langs->trans("Date") . '</div>';
            print '<div class="info-value">' . dol_print_date($db->jdate($obj->plat_date), 'dayhour') . '</div>';
            print '</div>';
            
            print '<div class="info-group">';
            print '<div class="info-label">' . $langs->trans("Warehouse") . '</div>';
            print '<div class="info-value">' . $obj->entrepot_ref . '</div>';
            print '</div>';
            
            print '<div class="info-group">';
            print '<div class="info-label">' . $langs->trans("MisenplatBon") . '</div>';
            print '<div class="info-value">' . $obj->bon_ref . '</div>';
            print '</div>';
            
            if ($obj->fk_carton) {
                print '<div class="info-group">';
                print '<div class="info-label">' . $langs->trans("Carton") . '</div>';
                print '<div class="info-value">' . $obj->carton_ref . ' - ' . $obj->carton_product_label . '</div>';
                print '</div>';
            }
            
            if ($obj->plat_commentaire) {
                print '<div class="info-group" style="grid-column: span 2;">';
                print '<div class="info-label">' . $langs->trans("Comments") . '</div>';
                print '<div class="info-value"><small>' . $obj->plat_commentaire . '</small></div>';
                print '</div>';
            }
            
            print '</div>'; // .card-info
            print '</div>'; // .plat-card
        }
        
        print '</div>'; // .cards-container
        
    } else {
        print '<div class="no-data">';
        print '<div class="no-data-icon"><i class="fas fa-database"></i></div>';
        print '<h3>' . $langs->trans("NoDataFound") . '</h3>';
        print '<p>' . $langs->trans("ModifyFilters") . '</p>';
        print '</div>';
    }
}

print '</div>'; // .results-section
print '</div>'; // .stock-container

// ============================================================================
// 🔹 JAVASCRIPT POUR LE SWITCH DE VUE
// ============================================================================
print '<script>
function setViewType(type) {
    document.getElementById("viewType").value = type;
    
    // Mise à jour des boutons actifs
    document.querySelectorAll(".view-btn").forEach(btn => {
        btn.classList.remove("active");
    });
    event.target.classList.add("active");
    
    // Soumission du formulaire
    document.getElementById("filterForm").submit();
}
</script>';

llxFooter();
$db->close();
?>