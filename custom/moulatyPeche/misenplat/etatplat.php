<?php
/**
 * État du stock des plats - Filtrage par entrepôt, produit ou bon de mise en plat
 * Les plats entièrement sortis ne sont pas considérés
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

global $db, $langs, $user;
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

// ============================================================================
// 🔹 RÉCUPÉRATION DES DONNÉES POUR LES FILTRES
// ============================================================================

// Entrepôts (avec restriction par utilisateur)
$entrepots = [0 => '🧾 Tous les entrepôts'];
$entrepots_accessibles = [];

// Récupérer les entrepôts accessibles par l'utilisateur
$sql_ent_usr = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent_usr = $db->query($sql_ent_usr);
if ($res_ent_usr && $db->num_rows($res_ent_usr) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent_usr)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

// Récupérer tous les entrepôts accessibles
if (!empty($entrepots_accessibles)) {
    $sql_entrepots = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot 
                      WHERE entity = ".$conf->entity." 
                      AND rowid IN (" . implode(',', $entrepots_accessibles) . ")
                      ORDER BY ref ASC";
} else {
    // Si l'utilisateur a accès à tous les entrepôts
    $sql_entrepots = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot 
                      WHERE entity = ".$conf->entity." 
                      ORDER BY ref ASC";
}

$res_entrepots = $db->query($sql_entrepots);
if ($res_entrepots) {
    while ($obj = $db->fetch_object($res_entrepots)) {
        $entrepots[$obj->rowid] = $obj->ref;
    }
}

// Produits (type poisson pour les plats)
$produits = [0 => '🧾 Tous les produits'];
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
$bons_misenplat = [0 => '🧾 Tous les bons'];
$sql_bons = "SELECT b.rowid, b.ref, b.date_creation, e.ref as entrepot_ref
             FROM ".MAIN_DB_PREFIX."pech_bon_misenplat b
             LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = b.fk_entrepot
             WHERE b.statut >= 0";
             
// Appliquer restriction d'accès aux entrepôts
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

// Options de statut des plats
$statut_options = [
    0 => '📦 En stock',
    1 => '📤 Sortis',
    2 => '👁️ Tous'
];

// ============================================================================
// 🔹 EN-TÊTE DE PAGE
// ============================================================================
llxHeader('', $langs->trans("EtatStockPlats"));
print '<link rel="stylesheet" href="../css/selection_form_style.css">';
print '<style>
    .etat-stock-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .filter-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: 1px solid #e1e5e9;
    }
    
    .filter-title {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f3f7;
    }
    
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        color: #4a5568;
        font-size: 14px;
    }
    
    .filter-input, .filter-select {
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s;
        background: white;
    }
    
    .filter-input:focus, .filter-select:focus {
        border-color: #4299e1;
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
        outline: none;
    }
    
    .filter-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 10px 22px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .btn-secondary {
        background: #edf2f7;
        color: #4a5568;
        border: 1px solid #e2e8f0;
    }
    
    .btn-secondary:hover {
        background: #e2e8f0;
    }
    
    .results-container {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: 1px solid #e1e5e9;
        margin-top: 20px;
    }
    
    .results-title {
        font-size: 20px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f3f7;
    }
    
    .plat-card {
        background: #f8fafc;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 15px;
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
    }
    
    .plat-card:hover {
        background: #edf2f7;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .plat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .plat-title {
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .plat-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .badge-stock {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: white;
    }
    
    .badge-sorti {
        background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
        color: white;
    }
    
    .plat-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .info-item {
        background: white;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    
    .info-label {
        font-size: 12px;
        color: #718096;
        margin-bottom: 4px;
        text-transform: uppercase;
        font-weight: 500;
    }
    
    .info-value {
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .plat-totals {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: 10px;
        margin-top: 25px;
    }
    
    .totals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .total-item {
        background: rgba(255, 255, 255, 0.1);
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .total-label {
        font-size: 12px;
        opacity: 0.9;
        margin-bottom: 5px;
        text-transform: uppercase;
    }
    
    .total-value {
        font-size: 24px;
        font-weight: 600;
    }
    
    .no-data {
        text-align: center;
        padding: 50px 20px;
        color: #a0aec0;
    }
    
    .no-data i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }
</style>';

print '<div class="etat-stock-container">';

// ============================================================================
// 🔹 FORMULAIRE DE FILTRAGE
// ============================================================================
print '<div class="filter-section">';
print '<div class="filter-title">';
print '<i class="fa fa-filter"></i> ' . $langs->trans("FiltresStockPlats");
print '</div>';

print '<form method="GET" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';

print '<div class="filter-grid">';

// Entrepôt
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fa fa-warehouse"></i> ' . $langs->trans("Entrepot") . '</label>';
print $form->selectarray('fk_entrepot', $entrepots, $fk_entrepot, 0, 0, 0, '', 0, 0, 0, '', 'filter-select');
print '</div>';

// Produit
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fa fa-cube"></i> ' . $langs->trans("Produit") . '</label>';
print $form->selectarray('fk_product', $produits, $fk_product, 0, 0, 0, '', 0, 0, 0, '', 'filter-select');
print '</div>';

// Bon de mise en plat
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fa fa-file-alt"></i> ' . $langs->trans("BonMisenPlat") . '</label>';
print $form->selectarray('fk_bon_misenplat', $bons_misenplat, $fk_bon_misenplat, 0, 0, 0, '', 0, 0, 0, '', 'filter-select');
print '</div>';

// Statut du plat
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fa fa-info-circle"></i> ' . $langs->trans("StatutPlat") . '</label>';
print $form->selectarray('statut_plat', $statut_options, $statut_plat, 0, 0, 0, '', 0, 0, 0, '', 'filter-select');
print '</div>';

// Date de début
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fa fa-calendar"></i> ' . $langs->trans("DateDebut") . '</label>';
print '<input type="date" name="date_debut" value="' . $date_debut . '" class="filter-input">';
print '</div>';

// Date de fin
print '<div class="filter-group">';
print '<label class="filter-label"><i class="fa fa-calendar"></i> ' . $langs->trans("DateFin") . '</label>';
print '<input type="date" name="date_fin" value="' . $date_fin . '" class="filter-input">';
print '</div>';

print '</div>'; // .filter-grid

// Boutons d'action
print '<div class="filter-actions">';
print '<button type="submit" class="filter-btn btn-primary">';
print '<i class="fa fa-search"></i> ' . $langs->trans("AfficherResultats");
print '</button>';
print '<a href="' . $_SERVER["PHP_SELF"] . '" class="filter-btn btn-secondary">';
print '<i class="fa fa-redo"></i> ' . $langs->trans("Reinitialiser");
print '</a>';
print '</div>';

print '</form>';
print '</div>'; // .filter-section

// ============================================================================
// 🔹 CONSTRUCTION DE LA REQUÊTE SQL AVANCÉE
// ============================================================================
$sql_where = [];

    //$sql_where[] = "m.nombre_plat_sortie < nombre_plat " ;

// Filtre par entrepôt
if ($fk_entrepot > 0) {
    $sql_where[] = "b.fk_entrepot = " . ((int)$fk_entrepot);
}

// Filtre par produit
if ($fk_product > 0) {
    $sql_where[] = "m.fk_product = " . ((int)$fk_product);
}

// Filtre par bon de mise en plat
if ($fk_bon_misenplat > 0) {
    $sql_where[] = "b.rowid = " . ((int)$fk_bon_misenplat);
}

// Filtre par date
if (!empty($date_debut)) {
    $sql_where[] = "DATE(b.date_creation) >= '" . $db->escape($date_debut) . "'";
}
if (!empty($date_fin)) {
    $sql_where[] = "DATE(b.date_creation) <= '" . $db->escape($date_fin) . "'";
}

// Filtre par statut (0 = en stock, 1 = sorti, 2 = tous)
if ($statut_plat == 0) {
    $sql_where[] = "pl.statut = 0"; // En stock
} elseif ($statut_plat == 1) {
    $sql_where[] = "pl.statut = 1"; // Sorti
}
// Si statut_plat = 2 (tous), pas de filtre

// Restriction d'accès aux entrepôts
if (!empty($entrepots_accessibles)) {
    $sql_where[] = "b.fk_entrepot IN (" . implode(',', $entrepots_accessibles) . ")";
}

// ============================================================================
// 🔹 REQUÊTE POUR LE RÉSUMÉ PAR ENTREPÔT/PRODUIT
// ============================================================================
$sql_resume = "
    SELECT 
        b.fk_entrepot,
        e.ref as entrepot_ref,
        m.fk_product,
        p.label as product_label,
        p.ref as product_ref,
        COUNT(pl.rowid) as total_plats,
        SUM(pl.poids) as total_poids,
        SUM(CASE WHEN pl.fk_carton IS NOT NULL THEN 1 ELSE 0 END) as plats_carton,
        SUM(CASE WHEN pl.fk_carton IS NOT NULL THEN pl.poids ELSE 0 END) as poids_carton,
        SUM(CASE WHEN pl.fk_carton IS NULL THEN 1 ELSE 0 END) as plats_tunnel,
        SUM(CASE WHEN pl.fk_carton IS NULL THEN pl.poids ELSE 0 END) as poids_tunnel,
        SUM(CASE WHEN pl.statut = 0 THEN 1 ELSE 0 END) as plats_stock,
        SUM(CASE WHEN pl.statut = 0 THEN pl.poids ELSE 0 END) as poids_stock,
        SUM(CASE WHEN pl.statut = 1 THEN 1 ELSE 0 END) as plats_sortis,
        SUM(CASE WHEN pl.statut = 1 THEN pl.poids ELSE 0 END) as poids_sortis
    FROM " . MAIN_DB_PREFIX . "pech_plat pl
    INNER JOIN " . MAIN_DB_PREFIX . "pech_misenplat m ON m.rowid = pl.fk_misenplat
    INNER JOIN " . MAIN_DB_PREFIX . "pech_bon_misenplat b ON b.rowid = m.fk_bon_misenplat
    INNER JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = b.fk_entrepot
    INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = m.fk_product
";

if (!empty($sql_where)) {
    $sql_resume .= " WHERE " . implode(" AND ", $sql_where);
}

$sql_resume .= " GROUP BY b.fk_entrepot, m.fk_product
                 ORDER BY e.ref ASC, p.label ASC";

// ============================================================================
// 🔹 REQUÊTE POUR LE DÉTAIL DES PLATS
// ============================================================================
$sql_detail = "
    SELECT 
        pl.rowid as plat_id,
        pl.poids,
        pl.prix_moyen,
        pl.frais,
        pl.statut as plat_statut,
        pl.date_creation,
        pl.fk_carton,
        b.ref as bon_ref,
        b.rowid as bon_id,
        b.date_creation as bon_date,
        e.ref as entrepot_ref,
        p.label as product_label,
        p.ref as product_ref,
        m.nombre_plat,
        m.nombre_plat_sortie,
        m.poids_plat,
        (SELECT GROUP_CONCAT(CONCAT(c.ref, ' - ', p2.label) SEPARATOR ', ')
         FROM " . MAIN_DB_PREFIX . "pech_carton c
         LEFT JOIN " . MAIN_DB_PREFIX . "product p2 ON p2.rowid = c.fk_product
         WHERE c.rowid = pl.fk_carton) as carton_info
    FROM " . MAIN_DB_PREFIX . "pech_plat pl
    INNER JOIN " . MAIN_DB_PREFIX . "pech_misenplat m ON m.rowid = pl.fk_misenplat
    INNER JOIN " . MAIN_DB_PREFIX . "pech_bon_misenplat b ON b.rowid = m.fk_bon_misenplat
    INNER JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = b.fk_entrepot
    INNER JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = m.fk_product
";

if (!empty($sql_where)) {
    $sql_detail .= " WHERE " . implode(" AND ", $sql_where);
}

$sql_detail .= " ORDER BY e.ref ASC, p.label ASC, b.date_creation DESC, pl.date_creation DESC";

// ============================================================================
// 🔹 EXÉCUTION ET AFFICHAGE DES RÉSULTATS
// ============================================================================
print '<div class="results-container">';

// Afficher le résumé par entrepôt/produit
$res_resume = $db->query($sql_resume);
$has_data = false;
$total_global_plats = 0;
$total_global_poids = 0;
$total_global_stock = 0;
$total_global_sortis = 0;

if ($res_resume && $db->num_rows($res_resume) > 0) {
    $has_data = true;
    
    print '<div class="results-title">';
    print '<i class="fa fa-chart-bar"></i> ' . $langs->trans("ResumeParEntrepotProduit");
    print '</div>';
    
    $current_entrepot = null;
    
    while ($obj = $db->fetch_object($res_resume)) {
        $total_global_plats += $obj->total_plats;
        $total_global_poids += $obj->total_poids;
        $total_global_stock += $obj->plats_stock;
        $total_global_sortis += $obj->plats_sortis;
        
        // Nouvel entrepôt
        if ($current_entrepot !== $obj->entrepot_ref) {
            if ($current_entrepot !== null) {
                print '</div>'; // Fermer le groupe précédent
            }
            
            $current_entrepot = $obj->entrepot_ref;
            
            print '<div class="plat-card" style="background: #e8f4fd; border-color: #b3d7ff;">';
            print '<div class="plat-header">';
            print '<div class="plat-title">';
            print '<i class="fa fa-warehouse"></i> <strong>' . $obj->entrepot_ref . '</strong>';
            print '</div>';
            print '</div>';
        }
        
        // Carte produit dans l'entrepôt
        print '<div class="plat-card" style="margin-left: 20px; background: white;">';
        print '<div class="plat-header">';
        print '<div class="plat-title">';
        print '<i class="fa fa-cube"></i> ' . $obj->product_label . ' (' . $obj->product_ref . ')';
        print '</div>';
        
        // Badge statut
        if ($obj->plats_stock > 0 && $obj->plats_sortis == 0) {
            print '<span class="plat-badge badge-stock">📦 En stock</span>';
        } elseif ($obj->plats_stock == 0 && $obj->plats_sortis > 0) {
            print '<span class="plat-badge badge-sorti">📤 Entièrement sorti</span>';
        } else {
            print '<span class="plat-badge badge-secondary">🔄 Partiellement sorti</span>';
        }
        
        print '</div>'; // .plat-header
        
        print '<div class="plat-info">';
        
        print '<div class="info-item">';
        print '<div class="info-label">Total Plats</div>';
        print '<div class="info-value">' . $obj->total_plats . '</div>';
        print '</div>';
        
        print '<div class="info-item">';
        print '<div class="info-label">Total Poids</div>';
        print '<div class="info-value">' . price($obj->total_poids) . ' kg</div>';
        print '</div>';
        
        print '<div class="info-item">';
        print '<div class="info-label">En Stock</div>';
        print '<div class="info-value">' . $obj->plats_stock . ' (' . price($obj->poids_stock) . ' kg)</div>';
        print '</div>';
        
        print '<div class="info-item">';
        print '<div class="info-label">Sortis</div>';
        print '<div class="info-value">' . $obj->plats_sortis . ' (' . price($obj->poids_sortis) . ' kg)</div>';
        print '</div>';
        
        print '<div class="info-item">';
        print '<div class="info-label">En Carton</div>';
        print '<div class="info-value">' . $obj->plats_carton . ' (' . price($obj->poids_carton) . ' kg)</div>';
        print '</div>';
        
        print '<div class="info-item">';
        print '<div class="info-label">En Tunnel</div>';
        print '<div class="info-value">' . $obj->plats_tunnel . ' (' . price($obj->poids_tunnel) . ' kg)</div>';
        print '</div>';
        
        print '</div>'; // .plat-info
        print '</div>'; // .plat-card (produit)
    }
    
    if ($current_entrepot !== null) {
        print '</div>'; // Fermer le dernier groupe d'entrepôt
    }
    
    // Totaux globaux
    print '<div class="plat-totals">';
    print '<div class="results-title" style="color: white; border-bottom-color: rgba(255,255,255,0.3);">';
    print '<i class="fa fa-globe"></i> ' . $langs->trans("TotauxGlobaux");
    print '</div>';
    
    print '<div class="totals-grid">';
    
    print '<div class="total-item">';
    print '<div class="total-label">Total Plats</div>';
    print '<div class="total-value">' . $total_global_plats . '</div>';
    print '</div>';
    
    print '<div class="total-item">';
    print '<div class="total-label">Total Poids</div>';
    print '<div class="total-value">' . price($total_global_poids) . ' kg</div>';
    print '</div>';
    
    print '<div class="total-item">';
    print '<div class="total-label">Plats en Stock</div>';
    print '<div class="total-value">' . $total_global_stock . '</div>';
    print '</div>';
    
    print '<div class="total-item">';
    print '<div class="total-label">Plats Sortis</div>';
    print '<div class="total-value">' . $total_global_sortis . '</div>';
    print '</div>';
    
    print '</div>'; // .totals-grid
    print '</div>'; // .plat-totals
}

// ============================================================================
// 🔹 AFFICHAGE DU DÉTAIL DES PLATS (optionnel)
// ============================================================================
if ($has_data && ($fk_product > 0 || $fk_bon_misenplat > 0)) {
    print '<div class="results-title" style="margin-top: 30px;">';
    print '<i class="fa fa-list"></i> ' . $langs->trans("DetailPlats");
    print '</div>';
    
    $res_detail = $db->query($sql_detail);
    if ($res_detail && $db->num_rows($res_detail) > 0) {
        while ($obj = $db->fetch_object($res_detail)) {
            print '<div class="plat-card">';
            print '<div class="plat-header">';
            print '<div class="plat-title">';
            print 'Plat #' . $obj->plat_id . ' - ' . $obj->product_label;
            print '</div>';
            
            // Badge statut
            if ($obj->plat_statut == 0) {
                print '<span class="plat-badge badge-stock">📦 En stock</span>';
            } else {
                print '<span class="plat-badge badge-sorti">📤 Sorti</span>';
            }
            
            print '</div>';
            
            print '<div class="plat-info">';
            
            print '<div class="info-item">';
            print '<div class="info-label">Poids</div>';
            print '<div class="info-value">' . price($obj->poids) . ' kg</div>';
            print '</div>';
            
            print '<div class="info-item">';
            print '<div class="info-label">Prix Moyen</div>';
            print '<div class="info-value">' . price($obj->prix_moyen) . ' ' . $conf->currency . '</div>';
            print '</div>';
            
            print '<div class="info-item">';
            print '<div class="info-label">Frais</div>';
            print '<div class="info-value">' . price($obj->frais) . ' ' . $conf->currency . '</div>';
            print '</div>';
            
            print '<div class="info-item">';
            print '<div class="info-label">Date Création</div>';
            print '<div class="info-value">' . dol_print_date($db->jdate($obj->date_creation), 'dayhour') . '</div>';
            print '</div>';
            
            print '<div class="info-item">';
            print '<div class="info-label">Bon</div>';
            print '<div class="info-value">' . $obj->bon_ref . '</div>';
            print '</div>';
            
            if ($obj->fk_carton) {
                print '<div class="info-item">';
                print '<div class="info-label">Carton</div>';
                print '<div class="info-value">' . $obj->carton_info . '</div>';
                print '</div>';
            }
            
            print '</div>'; // .plat-info
            print '</div>'; // .plat-card
        }
    }
}

if (!$has_data) {
    print '<div class="no-data">';
    print '<i class="fa fa-database"></i>';
    print '<h3>' . $langs->trans("AucuneDonnee") . '</h3>';
    print '<p>' . $langs->trans("ModifiezFiltres") . '</p>';
    print '</div>';
}

print '</div>'; // .results-container
print '</div>'; // .etat-stock-container

// ============================================================================
// 🔹 AJOUT DES TRADUCTIONS NÉCESSAIRES
// ============================================================================
?>


<?php
llxFooter();
$db->close();
?>