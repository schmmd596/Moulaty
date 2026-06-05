<?php
/**
 * État de stock des cartons - Valeurs claires et précises
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

global $db, $langs, $user;

$langs->load("abricot@abricot");
$form = new Form($db);

// Récupération des entrepôts
$entrepots = [0 => '🗂️ Tous les entrepôts'];
$resql = $db->query("SELECT rowid, ref, lieu FROM ".MAIN_DB_PREFIX."entrepot ORDER BY ref ASC");
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $entrepots[$obj->rowid] = $obj->ref.(($obj->lieu)?' - '.$obj->lieu:'');
    }
}

// Récupération des produits (poissons uniquement)
$produits = [0 => '🧾 Tous les produits'];
$sqlp = "SELECT p.rowid, p.label 
         FROM ".MAIN_DB_PREFIX."product p
         INNER JOIN ".MAIN_DB_PREFIX."categorie_product cp ON cp.fk_product = p.rowid
         INNER JOIN ".MAIN_DB_PREFIX."categorie c ON c.rowid = cp.fk_categorie
         WHERE c.label = 'POISSON'
         ORDER BY p.label ASC";

$resqlp = $db->query($sqlp);
if ($resqlp) {
    while ($obj = $db->fetch_object($resqlp)) {
        $produits[$obj->rowid] = $obj->label;
    }
}

// Récupération des filtres
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$fk_product  = GETPOST('fk_product', 'int');
$ref_lot     = GETPOST('ref_lot', 'alpha');

// Entrepôts accessibles par l'utilisateur
$entrepots_accessibles = [];
$sql_ent = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent = $db->query($sql_ent);
if ($res_ent && $db->num_rows($res_ent) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

llxHeader('', 'État de stock des cartons - Valeur réelle du stock', 'stock@abricot');

// Styles CSS pour le tableau
print '
<style>
    body { background-color: #f8fafc; font-family: "Segoe UI", Roboto, sans-serif; }
    .fichecenter { max-width: 1800px; margin: 0 auto; padding: 20px; }
    .filter-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 25px;
        border: 1px solid #e2e8f0;
    }
    .stock-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
        margin-top: 25px;
        border: 1px solid #e2e8f0;
    }
    .stock-table th {
        background: linear-gradient(135deg, #1e3a8a, #3b82f6);
        color: white;
        padding: 16px 12px;
        text-align: left;
        font-weight: 600;
        font-size: 0.9em;
        position: sticky;
        top: 0;
        z-index: 10;
        border-bottom: 2px solid #1d4ed8;
    }
    .stock-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.85em;
        vertical-align: middle;
    }
    .stock-table tr:hover {
        background-color: #f8fafc;
        transform: translateY(-1px);
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .stock-table tr:nth-child(even):not(.lot-header):not(.lot-total):not(.grand-total) {
        background-color: #f8fafc;
    }
    .number-cell {
        text-align: right;
        font-family: "SF Mono", Monaco, monospace;
        font-weight: 500;
        color: #1e293b;
    }
    .product-cell {
        font-weight: 600;
        color: #0f172a;
    }
    .lot-header {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: #1e40af;
        font-weight: 600;
        border-left: 4px solid #3b82f6;
    }
    .lot-header td {
        border-bottom: 2px solid #93c5fd;
    }
    .lot-total {
        background: linear-gradient(135deg, #e7faedff, #e4ebe6ff);
        font-weight: 600;
        border-top: 2px solid #10b981;
        border-bottom: 2px solid #10b981;
    }
    .lot-total td {
        color: #065f46;
    }
    .grand-total {
        background: linear-gradient(135deg, #1e3a8a, #3b82f6);
        color: white;
        font-weight: bold;
        font-size: 0.95em;
    }
    .grand-total td {
        color: white;
        border-top: 2px solid #1d4ed8;
    }
    .stock-value {
        color: #065f46;
        font-weight: bold;
        background: #d1fae5;
        padding: 4px 8px;
        border-radius: 6px;
        display: inline-block;
    }
    .complete-value {
        color: #1e40af;
        font-weight: bold;
        background: #dbeafe;
        padding: 4px 8px;
        border-radius: 6px;
        display: inline-block;
        font-size: 0.9em;
    }
    .frais-info {
        color: #7c2d12;
        font-weight: 600;
        background: #fed7aa;
        padding: 4px 8px;
        border-radius: 6px;
        display: inline-block;
        font-size: 0.8em;
    }
    .export-btn {
        background: linear-gradient(135deg, #10b981, #34d399);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2);
    }
    .export-btn:hover {
        background: linear-gradient(135deg, #059669, #10b981);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3);
        color: white;
        text-decoration: none;
    }
    .no-data {
        text-align: center;
        padding: 60px;
        font-style: italic;
        color: #64748b;
        background: white;
        border-radius: 12px;
        margin-top: 20px;
        border: 2px dashed #cbd5e1;
    }
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .summary-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
        transition: transform 0.3s ease;
    }
    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    .summary-card h4 {
        margin: 0 0 12px 0;
        color: #475569;
        font-size: 0.9em;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .summary-card .value {
        font-size: 1.8em;
        font-weight: bold;
        color: #1e40af;
        margin: 10px 0;
    }
    .summary-card .subtext {
        font-size: 0.8em;
        color: #64748b;
        margin-top: 8px;
    }
    .lot-badge {
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .small-text {
        font-size: 0.8em;
        color: #64748b;
    }
    .export-options {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    .info-panel {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 1px solid #bae6fd;
        border-radius: 12px;
        padding: 20px;
        margin: 20px 0;
        font-size: 0.9em;
    }
    .info-panel h4 {
        color: #0369a1;
        margin: 0 0 15px 0;
        font-size: 1.1em;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .formula-box {
        background: white;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 15px;
        margin: 10px 0;
        font-family: "SF Mono", Monaco, monospace;
        font-size: 0.9em;
        color: #1e293b;
    }
    .calculation-step {
        background: #f8fafc;
        border-left: 4px solid #3b82f6;
        padding: 12px 15px;
        margin: 8px 0;
        border-radius: 0 8px 8px 0;
    }
    .filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: end;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .form-group label {
        font-weight: 600;
        color: #475569;
        font-size: 0.9em;
    }
    .form-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .average-badge {
        background: linear-gradient(135deg, #8b5cf6, #a78bfa);
        color: white;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.75em;
        font-weight: 600;
        display: inline-block;
    }

    
</style>
';

print '<div class="fichecenter">';
print load_fiche_titre('📦 État de stock des cartons - Valeur réelle du stock', '', 'stock@abricot');


// Formulaire de filtres
print '<div class="filter-card">';
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" class="filter-form">';

print '<div class="form-group">';
print '<label>🏠 Entrepôt</label>';
print $form->selectarray('fk_entrepot', $entrepots, $fk_entrepot, 0, '', 0, 0, '', 0, 0, 0, '', '', 'style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1;"');
print '</div>';

print '<div class="form-group">';
print '<label>🧾 Produit</label>';
print $form->selectarray('fk_product', $produits, $fk_product, 0, '', 0, 0, '', 0, 0, 0, '', '', 'style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1;"');
print '</div>';

print '<div class="form-group">';
print '<label>🏷️ Référence lot</label>';
print '<input type="text" name="ref_lot" value="'.dol_escape_htmltag($ref_lot).'" placeholder="Filtrer par référence lot" style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; width: 90%;">';
print '</div>';

print '<div class="form-group">';
print '<label>&nbsp;</label>';
print '<div class="form-actions">';
print '<input type="submit" class="butAction" value="🔍 filtrer" style="background: #319bbbff ;padding: 10px 20px; font-weight: 600; border-radius: 8px;">';
//print '<a href="'.$_SERVER["PHP_SELF"].'" class="butActionDelete" style="padding: 10px 20px; border-radius: 8px;">🗑️ Réinitialiser</a>';
print '</div>';
print '</div>';

print '</form>';

// Boutons d'export
print '<div class="export-options">';
print '<a href="stock_export.php?export=detailed&fk_entrepot='.$fk_entrepot.'&fk_product='.$fk_product.'&ref_lot='.urlencode($ref_lot).'" class="export-btn">📊 Exporter en Excel</a>';
print '</div>';

print '</div>';

// Requête SQL POUR LES CALCULS EXACTS
$sql = "
SELECT 
    -- Informations lot
    l.rowid AS lot_id,
    l.ref AS lot_ref,
    l.date_creation AS lot_date,
    l.total_frais AS lot_frais_total,
    
    -- Informations entrepôt
    e.rowid AS entrepot_id,
    e.ref AS entrepot_ref,
    e.lieu AS entrepot_lieu,
    
    -- Informations produit
    p.rowid AS product_id,
    p.label AS product_label,
    p.ref AS product_ref,
    
    -- Détails lotdet
    ld.rowid AS lotdet_id,
    ld.prix AS prix_unit_lotdet,
    ld.taux_rendement AS taux_rendement,
    
    -- Statistiques cartons par lotdet
    COUNT(c.rowid) AS total_cartons_tout,                    -- Tous les cartons
    SUM(CASE WHEN c.statut = 0 THEN 1 ELSE 0 END) AS cartons_stock,  -- Cartons en stock seulement
    SUM(CASE WHEN c.statut = 1 THEN 1 ELSE 0 END) AS cartons_sortis, -- Cartons sortis seulement
    
    -- Poids
    SUM(CASE WHEN c.statut = 0 THEN c.poids ELSE 0 END) AS poids_stock,
    SUM(CASE WHEN c.statut = 1 THEN c.poids ELSE 0 END) AS poids_sortis,
    
    -- CALCUL 1: Valeur des cartons en STOCK (statut = 0 seulement)
    SUM(CASE WHEN c.statut = 0 THEN c.prix_moyen ELSE 0 END) AS prix_moyen_stock,
    SUM(CASE WHEN c.statut = 0 THEN c.frais ELSE 0 END) AS frais_cartons_stock,
    
    -- CALCUL 2: Valeur de TOUS les cartons (statut 0 + 1)
    SUM(CASE WHEN c.statut IN (0,1) THEN c.prix_moyen ELSE 0 END) AS prix_moyen_tout,
    SUM(CASE WHEN c.statut IN (0,1) THEN c.frais ELSE 0 END) AS frais_cartons_tout
    
FROM ".MAIN_DB_PREFIX."pech_lot AS l
INNER JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = l.fk_entrepot
INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet AS ld ON ld.fk_lot = l.rowid
INNER JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = ld.fk_product
INNER JOIN ".MAIN_DB_PREFIX."pech_carton AS c ON c.fk_lotdet = ld.rowid
";

$where = [];
if ($fk_entrepot > 0) $where[] = "l.fk_entrepot = ".(int)$fk_entrepot;
if ($fk_product > 0) $where[] = "p.rowid = ".(int)$fk_product;
if (!empty($ref_lot)) $where[] = "l.ref LIKE '%".$db->escape($ref_lot)."%'";

// Ajouter restriction sur les entrepôts accessibles
if (!empty($entrepots_accessibles)) {
    $where[] = "l.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
}

// Filtrer uniquement les lots qui ont des cartons en stock
$where[] = "EXISTS (
    SELECT 1 FROM ".MAIN_DB_PREFIX."pech_carton c2 
    WHERE c2.fk_lotdet = ld.rowid AND c2.statut = 0
)";

if (count($where) > 0) {
    $sql .= " WHERE ".implode(" AND ", $where);
}

$sql .= " 
GROUP BY l.rowid, l.ref, l.date_creation, l.total_frais, 
         e.rowid, e.ref, e.lieu,
         p.rowid, p.label, p.ref,
         ld.rowid, ld.prix, ld.taux_rendement
HAVING cartons_stock > 0
ORDER BY e.ref, l.ref, p.label
";

$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    
    // Initialisation des totaux globaux EXACTS
    $totals = [
        'lots' => 0,
        'products' => 0,
        'cartons_stock' => 0,
        'poids_stock' => 0,
        'cartons_sortis' => 0,
        'poids_sortis' => 0,
        'valeur_stock' => 0,      // Valeur des cartons en STOCK seulement
        'valeur_complete' => 0,   // Valeur de TOUS les cartons
        'total_cartons' => 0,     // Tous les cartons (stock + sortis)
        'frais_lots' => 0         // Frais totaux des lots (information)
    ];
    
    // Stockage des données organisées par lot
    $lots_data = [];
    $all_products = [];
    
    while ($obj = $db->fetch_object($resql)) {
        $lot_id = $obj->lot_id;
        
        if (!isset($lots_data[$lot_id])) {
            $lots_data[$lot_id] = [
                'lot_info' => [
                    'id' => $obj->lot_id,
                    'ref' => $obj->lot_ref,
                    'date' => $obj->lot_date,
                    'frais_total' => $obj->lot_frais_total,
                ],
                'entrepot' => [
                    'id' => $obj->entrepot_id,
                    'ref' => $obj->entrepot_ref,
                    'lieu' => $obj->entrepot_lieu,
                ],
                'produits' => [],
                'stats' => [
                    'cartons_stock' => 0,
                    'cartons_sortis' => 0,
                    'total_cartons' => 0,
                    'poids_stock' => 0,
                    'poids_sortis' => 0,
                    'valeur_stock' => 0,      // Cartons en STOCK seulement
                    'valeur_complete' => 0,   // Tous les cartons
                ]
            ];
            $totals['lots']++;
        }
        
        // CALCUL 1: Valeur des cartons EN STOCK (statut = 0 seulement)
        $valeur_stock = $obj->prix_moyen_stock + $obj->frais_cartons_stock;
        
        // CALCUL 2: Valeur de TOUS les cartons (statut 0 + 1)
        $valeur_complete = $obj->prix_moyen_tout + $obj->frais_cartons_tout;
        
        // Mettre à jour les statistiques du lot
        $lots_data[$lot_id]['stats']['cartons_stock'] += $obj->cartons_stock;
        $lots_data[$lot_id]['stats']['cartons_sortis'] += $obj->cartons_sortis;
        $lots_data[$lot_id]['stats']['total_cartons'] += $obj->total_cartons_tout;
        $lots_data[$lot_id]['stats']['poids_stock'] += $obj->poids_stock;
        $lots_data[$lot_id]['stats']['poids_sortis'] += $obj->poids_sortis;
        $lots_data[$lot_id]['stats']['valeur_stock'] += $valeur_stock;
        $lots_data[$lot_id]['stats']['valeur_complete'] += $valeur_complete;
        
        // Ajouter le produit
        $product_data = [
            'id' => $obj->product_id,
            'label' => $obj->product_label,
            'ref' => $obj->product_ref,
            'lotdet_id' => $obj->lotdet_id,
            'prix_unit' => $obj->prix_unit_lotdet,
            'taux_rendement' => $obj->taux_rendement,
            'cartons_stock' => $obj->cartons_stock,
            'poids_stock' => $obj->poids_stock,
            'cartons_sortis' => $obj->cartons_sortis,
            'poids_sortis' => $obj->poids_sortis,
            'total_cartons' => $obj->total_cartons_tout,
            'valeur_stock' => $valeur_stock,
            'valeur_complete' => $valeur_complete,
            'prix_moyen_stock' => $obj->prix_moyen_stock,
            'frais_cartons_stock' => $obj->frais_cartons_stock
        ];
        
        $lots_data[$lot_id]['produits'][] = $product_data;
        
        // Mettre à jour les totaux globaux
        $totals['cartons_stock'] += $obj->cartons_stock;
        $totals['poids_stock'] += $obj->poids_stock;
        $totals['cartons_sortis'] += $obj->cartons_sortis;
        $totals['poids_sortis'] += $obj->poids_sortis;
        $totals['valeur_stock'] += $valeur_stock;
        $totals['valeur_complete'] += $valeur_complete;
        $totals['total_cartons'] += $obj->total_cartons_tout;
        $totals['frais_lots'] += $obj->lot_frais_total;
        
        // Compter les produits uniques
        if (!in_array($obj->product_id, $all_products)) {
            $all_products[] = $obj->product_id;
            $totals['products']++;
        }
    }
    
    // Calculer les valeurs moyennes et ratios
    $totals['valeur_moyenne_carton'] = ($totals['cartons_stock'] > 0) ? 
        ($totals['valeur_stock'] / $totals['cartons_stock']) : 0;
    
    $totals['poids_moyen_carton'] = ($totals['cartons_stock'] > 0) ? 
        ($totals['poids_stock'] / $totals['cartons_stock']) : 0;
    
    $totals['taux_sortie'] = ($totals['total_cartons'] > 0) ? 
        ($totals['cartons_sortis'] / $totals['total_cartons'] * 100) : 0;
    
    // Affichage des cartes de résumé
    print '<div class="summary-cards">';
    
    print '<div class="summary-card">';
    print '<h4>📦 Cartons en stock</h4>';
    print '<div class="value">'.$totals['cartons_stock'].'</div>';
    print '<div class="subtext">'.price($totals['poids_stock']).' kg</div>';
    print '</div>';
    
    print '<div class="summary-card">';
    print '<h4>🚚 Cartons sortis</h4>';
    print '<div class="value">'.$totals['cartons_sortis'].'</div>';
    print '<div class="subtext">'.price($totals['poids_sortis']).' kg</div>';
    print '</div>';
    
    print '<div class="summary-card">';
    print '<h4>💰 Valeur du stock</h4>';
    print '<div class="value">'.number_format($totals['valeur_stock'], 2, '.', ' ').'</div>';
    print '<div class="subtext">'.count($lots_data).' lots actifs</div>';
    print '</div>';
    
    print '<div class="summary-card">';
    print '<h4>💵 Valeur moyenne</h4>';
    print '<div class="value">'.number_format($totals['valeur_moyenne_carton'], 2, '.', ' ').'</div>';
    print '<div class="subtext">Par carton en stock</div>';
    print '</div>';
    
    print '<div class="summary-card">';
    print '<h4>📊 Poids moyen</h4>';
    print '<div class="value">'.price($totals['poids_moyen_carton']).' kg</div>';
    print '<div class="subtext">Par carton en stock</div>';
    print '</div>';
    
    print '<div class="summary-card">';
    print '<h4>📈 Taux de sortie</h4>';
    print '<div class="value">'.round($totals['taux_sortie'], 1).'%</div>';
    print '<div class="subtext">Cartons sortis / Cartons totaux</div>';
    print '</div>';
    
    print '</div>';
    
    // Tableau des données détaillées
    print '<table class="stock-table">';
    print '<thead>';
    print '<tr>';
    print '<th style="width: 12%;">Entrepôt</th>';
    print '<th style="width: 10%;">Réf. Lot</th>';
    print '<th style="width: 8%;">Date</th>';
    print '<th style="width: 15%;">Produit</th>';
    print '<th style="width: 6%;">Stock</th>';
    print '<th style="width: 7%;">Poids (kg)</th>';
    print '<th style="width: 6%;">Sortis</th>';
    print '<th style="width: 6%;">PU Carton</th>';
    print '<th style="width: 9%;">Valeur stock</th>';
    print '<th style="width: 12%;">Valeur complète</th>';
    print '<th style="width: 15%;">Moyenne & Détails</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';
    
    $lot_counter = 0;
    foreach ($lots_data as $lot_id => $lot) {
        $lot_counter++;
        
        // Calculs pour ce lot
        $valeur_moyenne_lot = ($lot['stats']['cartons_stock'] > 0) ? 
            ($lot['stats']['valeur_stock'] / $lot['stats']['cartons_stock']) : 0;
        
        $ratio_stock = ($lot['stats']['total_cartons'] > 0) ? 
            ($lot['stats']['cartons_stock'] / $lot['stats']['total_cartons'] * 100) : 0;
        
        // Ligne d'en-tête du lot
        print '<tr class="lot-header">';
        print '<td><strong>'.$lot['entrepot']['ref'].'</strong>';
        if (!empty($lot['entrepot']['lieu'])) {
            print '<br><span class="small-text">'.$lot['entrepot']['lieu'].'</span>';
        }
        print '</td>';
        print '<td><strong>'.$lot['lot_info']['ref'].'</strong></td>';
        print '<td>'.dol_print_date($lot['lot_info']['date'], '%d/%m/%Y').'</td>';
        print '<td colspan="8">';
        print '<div class="lot-badge">Lot #'.$lot_counter.'</div>';
        print '<span class="small-text" style="margin-left: 10px;">Frais lot: <span class="frais-info">'.price($lot['lot_info']['frais_total']).'</span></span>';
        print '</td>';
        print '</tr>';
        
        // Lignes des produits dans le lot
        $prod_counter = 0;
        foreach ($lot['produits'] as $produit) {
            $prod_counter++;
            
            print '<tr>';
            
            // Première ligne du produit
            if ($prod_counter == 1) {
                print '<td rowspan="'.count($lot['produits']).'"></td>';
                print '<td rowspan="'.count($lot['produits']).'"></td>';
                print '<td rowspan="'.count($lot['produits']).'"></td>';
            }
            
            print '<td class="product-cell">'.$produit['label'];
            if ($produit['ref']) {
                print '<br><span class="small-text">'.$produit['ref'].'</span>';
            }
            print '</td>';
            
            print '<td class="number-cell">'.$produit['cartons_stock'].'</td>';
            print '<td class="number-cell">'.price($produit['poids_stock']).'</td>';
            print '<td class="number-cell">'.$produit['cartons_sortis'].'</td>';
            print '<td class="number-cell"><span class="stock-value">'.number_format($produit['valeur_stock']/($produit['cartons_stock']), 2, '.', ' ').'</span></td>';
            print '<td class="number-cell"><span class="stock-value">'.number_format($produit['valeur_stock'], 2, '.', ' ').'</span></td>';
            
            if ($prod_counter == 1) {
                // Valeur complète du lot
                print '<td rowspan="'.count($lot['produits']).'" style="vertical-align: middle;">';
                print '<div style="text-align: center;">';
                print '<div class="complete-value" style="margin: 5px 0;">'.number_format($lot['stats']['valeur_complete'], 2, '.', ' ').'</div>';
                print '<div class="small-text">Cartons totaux: '.$lot['stats']['total_cartons'].'</div>';
                print '</div>';
                print '</td>';
                
                // Moyenne et détails
                print '<td rowspan="'.count($lot['produits']).'" style="vertical-align: middle;">';
                print '<div style="text-align: center;">';
                print '<div class="average-badge" style="margin: 5px 0; font-size: 0.9em;">'.number_format($valeur_moyenne_lot, 2, '.', ' ').'/carton</div>';
                print '<div class="small-text" style="margin-top: 8px;">';
                print 'Ratio: <strong>'.round($ratio_stock, 1).'%</strong> en stock<br>';
                //print 'Prix unit.: '.price($produit['prix_unit']).'';
                //print '<span class="small-text" style="margin-left: 10px;">Frais lot: <span class="frais-info">'.price($lot['lot_info']['frais_total']).'</span></span>';
        
                print '</div>';
                print '</div>';
                print '</td>';
            }
            
            print '</tr>';
        }
        
        // Ligne de total pour le lot
        print '<tr class="lot-total">';
        print '<td colspan="3"><strong>TOTAL LOT '.$lot['lot_info']['ref'].'</strong></td>';
        print '<td><strong>'.count($lot['produits']).' produit(s)</strong></td>';
        print '<td class="number-cell"><strong>'.$lot['stats']['cartons_stock'].'</strong></td>';
        print '<td class="number-cell"><strong>'.price($lot['stats']['poids_stock']).' kg</strong></td>';
        print '<td class="number-cell"><strong>'.$lot['stats']['cartons_sortis'].'</strong></td>';
        print '<td> </td>';
        print '<td class="number-cell"><strong><span class="stock-value">'.number_format($lot['stats']['valeur_stock'], 2, '.', ' ').'</span></strong></td>';
        print '<td colspan="2" style="text-align: center;" class="small-text">';
        print 'Valeur stock: <strong>'.number_format($lot['stats']['valeur_stock'], 2, '.', ' ').'</strong> | ';
        print 'Valeur complète: <strong>'.number_format($lot['stats']['valeur_complete'], 2, '.', ' ').'</strong>';
        print '</td>';
        print '</tr>';
    }
    
    // Ligne des totaux généraux
    print '<tr class="grand-total">';
    print '<td colspan="4"><strong>TOTAUX GÉNÉRAUX ('.$totals['lots'].' lots, '.$totals['products'].' produits)</strong></td>';
    print '<td class="number-cell"><strong>'.$totals['cartons_stock'].'</strong></td>';
    print '<td class="number-cell"><strong>'.price($totals['poids_stock']).' kg</strong></td>';
    print '<td class="number-cell"><strong>'.$totals['cartons_sortis'].'</strong></td>';
    print '<td class="number-cell"><strong></strong></td>';
    print '<td class="number-cell"><strong><span class="stock-value">'.price($totals['valeur_stock']).'</span></strong></td>';
    print '<td class="number-cell"><strong><span class="complete-value">'.price($totals['valeur_complete']).'</span></strong></td>';
    print '<td style="text-align: center;">';
    print '<div style="display: flex; flex-direction: column; gap: 8px;">';
    print '<div class="average-badge" style="background: rgba(255,255,255,0.2);">'.price($totals['valeur_moyenne_carton']).'/carton</div>';
    print '<div class="small-text" style="color: rgba(255,255,255,0.8);">Poids moyen: '.price($totals['poids_moyen_carton']).' kg</div>';
    print '</div>';
    print '</td>';
    print '</tr>';
    
    print '</tbody>';
    print '</table>';
    
    // Section d'analyse
    print '<div class="info-panel" style="margin-top: 30px;">';
    print '<h4>📊 Analyse des résultats</h4>';
    
    print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 15px;">';
    
    // Valeur totale
    print '<div>';
    print '<strong>💰 Valeur totale du stock :</strong><br>';
    print '<span class="value" style="font-size: 1.2em;">'.price($totals['valeur_stock']).'</span>';
    print '</div>';
    
    // Valeur complète totale
    print '<div>';
    print '<strong>💵 Valeur complète des lots :</strong><br>';
    print '<span class="value" style="font-size: 1.2em;">'.price($totals['valeur_complete']).'</span>';
    print '</div>';
    
   
    
    // Taux de sortie détaillé
    print '<div>';
    print '<strong>📊 Détail stock/sorties :</strong><br>';
    print '<span class="value" style="font-size: 1.2em;">';
    print $totals['cartons_stock'].' en stock ('.round(100 - $totals['taux_sortie'], 1).'%)<br>';
    print $totals['cartons_sortis'].' sortis ('.round($totals['taux_sortie'], 1).'%)';
    print '</span>';
    print '</div>';
    
    print '</div>';
    print '</div>';
    
} else {
    print '<div class="no-data">';
    print '🚫 Aucun lot avec des cartons en stock trouvé pour ces filtres.';
    print '<br><span class="small-text">Essayez de modifier vos critères de recherche</span>';
    print '</div>';
}

print '</div>';

llxFooter();
$db->close();
?>