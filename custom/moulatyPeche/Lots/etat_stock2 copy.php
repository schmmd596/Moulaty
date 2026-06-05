<?php
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

llxHeader('', 'État de stock des cartons - Détail par lot');

// Styles CSS pour le tableau
print '
<style>
    body { background-color: #f8fafc; }
    .fichecenter { max-width: 1600px; margin: 0 auto; padding: 20px; }
    .filter-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        margin-bottom: 25px;
    }
    .stock-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        margin-top: 20px;
    }
    .stock-table th {
        background: linear-gradient(135deg, #0073aa, #00aaff);
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        font-size: 0.9em;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .stock-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.85em;
        vertical-align: top;
    }
    .stock-table tr:hover {
        background-color: #f8fafc;
    }
    .stock-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .number-cell {
        text-align: right;
        font-family: monospace;
        font-weight: 500;
    }
    .product-cell {
        font-weight: 600;
        color: #222;
    }
    .lot-cell {
        font-weight: 600;
        color: #2c5282;
        background-color: #ebf8ff;
        border-left: 3px solid #4299e1;
    }
    .totals-row {
        background: linear-gradient(135deg, #28a745, #20c997) !important;
        color: white;
        font-weight: bold;
        font-size: 0.9em;
    }
    .section-total {
        background: #e6fffa !important;
        font-weight: 600;
        border-top: 2px solid #81e6d9;
    }
    .status-in {
        background-color: #d4edda;
        color: #155724;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: 500;
        display: inline-block;
    }
    .status-out {
        background-color: #f8d7da;
        color: #721c24;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: 500;
        display: inline-block;
    }
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .export-btn {
        background: #28a745;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
    }
    .export-btn:hover {
        background: #218838;
    }
    .no-data {
        text-align: center;
        padding: 40px;
        font-style: italic;
        color: #777;
        background: white;
        border-radius: 10px;
        margin-top: 20px;
    }
    .summary-cards {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .summary-card {
        flex: 1;
        min-width: 180px;
        background: white;
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 3px 8px rgba(0,0,0,0.08);
    }
    .summary-card h4 {
        margin: 0 0 10px 0;
        color: #555;
        font-size: 0.85em;
    }
    .summary-card .value {
        font-size: 1.5em;
        font-weight: bold;
        color: #0073aa;
    }
    .summary-card .subtext {
        font-size: 0.75em;
        color: #777;
        margin-top: 5px;
    }
    .lot-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .lot-badge {
        background: #4299e1;
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.75em;
        font-weight: 500;
    }
    .collapse-btn {
        background: none;
        border: none;
        color: #4299e1;
        cursor: pointer;
        font-size: 1.2em;
        padding: 0 5px;
    }
    .product-details {
        margin-left: 20px;
        padding-left: 15px;
        border-left: 2px solid #cbd5e0;
    }
    .small-text {
        font-size: 0.8em;
        color: #666;
    }
    .lot-summary {
        background: #f7fafc;
        border-radius: 8px;
        padding: 10px;
        margin: 5px 0;
        border: 1px solid #e2e8f0;
    }
</style>
';

print '<div class="fichecenter">';
print load_fiche_titre('📦 État de stock des cartons - Détail par lot', '', 'title_generic');

// Formulaire de filtres
print '<div class="filter-card">';
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">';

print '<label style="font-weight: bold;">🏠 Entrepôt :</label>';
print $form->selectarray('fk_entrepot', $entrepots, $fk_entrepot, 0, '', 0, 0, '', 0, 0, 0, '', '', 'minwidth150');

print '<label style="font-weight: bold;">🧾 Produit :</label>';
print $form->selectarray('fk_product', $produits, $fk_product, 0, '', 0, 0, '', 0, 0, 0, '', '', 'minwidth150');

print '<label style="font-weight: bold;">🏷️ Réf. Lot :</label>';
print '<input type="text" name="ref_lot" value="'.dol_escape_htmltag($ref_lot).'" placeholder="Référence lot" style="padding: 6px; border: 1px solid #ccc; border-radius: 4px; width: 150px;">';

print '<input type="submit" class="butAction" value="🔍 Filtrer" style="padding:6px 15px; font-weight:bold;">';
print '</form>';
print '</div>';

// Requête SQL pour récupérer les données détaillées par lot
$sql = "
SELECT 
    -- Informations lot
    l.rowid AS lot_id,
    l.ref AS lot_ref,
    l.date_creation AS lot_date,
    l.total_frais AS lot_frais,
    
    -- Informations entrepôt
    e.rowid AS entrepot_id,
    e.ref AS entrepot_ref,
    e.lieu AS entrepot_lieu,
    
    -- Informations produit
    p.rowid AS product_id,
    p.label AS product_label,
    
    -- Détails lotdet
    ld.rowid AS lotdet_id,
    ld.prix AS prix_unit_lotdet,
    ld.taux_rendement AS taux_rendement,
    
    -- Statistiques cartons par lotdet
    COUNT(c.rowid) AS total_cartons_lotdet,
    SUM(CASE WHEN c.statut = 0 THEN 1 ELSE 0 END) AS cartons_stock_lotdet,
    SUM(CASE WHEN c.statut = 0 THEN c.poids ELSE 0 END) AS poids_stock_lotdet,
    SUM(CASE WHEN c.statut = 1 THEN 1 ELSE 0 END) AS cartons_sortie_lotdet,
    SUM(CASE WHEN c.statut = 1 THEN c.poids ELSE 0 END) AS poids_sortie_lotdet,
    
    -- Valeurs financières par lotdet
    SUM(CASE WHEN c.statut = 0 THEN c.prix_moyen ELSE 0 END) AS total_prix_moyen,
    SUM(CASE WHEN c.statut = 0 THEN c.frais ELSE 0 END) AS total_frais_cartons,
    
    -- Prix par carton (moyenne)
    AVG(CASE WHEN c.statut = 0 THEN (c.prix_moyen + c.frais) ELSE NULL END) AS prix_moyen_carton
    
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

// Filtrer uniquement les lots qui ont des cartons en stock (statut = 0)
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
         p.rowid, p.label,
         ld.rowid, ld.prix, ld.taux_rendement
HAVING cartons_stock_lotdet > 0  -- N'afficher que les lots avec des cartons en stock
ORDER BY e.ref, l.ref, p.label
";

$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    
    // Initialisation des totaux
    $total_stock_carton = $total_stock_poids = 0;
    $total_sortie_carton = $total_sortie_poids = 0;
    $total_valeur = 0;
    $total_general_carton = $total_general_poids = 0;
    
    // Stockage des données organisées par lot
    $lots_data = [];
    $current_lot = null;
    
    while ($obj = $db->fetch_object($resql)) {
        // Organiser par lot
        $lot_key = $obj->lot_id;
        
        if (!isset($lots_data[$lot_key])) {
            $lots_data[$lot_key] = [
                'lot_info' => [
                    'id' => $obj->lot_id,
                    'ref' => $obj->lot_ref,
                    'date' => $obj->lot_date,
                    'frais' => $obj->lot_frais,
                ],
                'entrepot' => [
                    'id' => $obj->entrepot_id,
                    'ref' => $obj->entrepot_ref,
                    'lieu' => $obj->entrepot_lieu,
                ],
                'produits' => []
            ];
        }
        
        // Ajouter les détails produit au lot
        $lots_data[$lot_key]['produits'][] = [
            'product_id' => $obj->product_id,
            'product_label' => $obj->product_label,
            'lotdet_id' => $obj->lotdet_id,
            'prix_unit' => $obj->prix_unit_lotdet,
            'taux_rendement' => $obj->taux_rendement,
            'total_cartons' => $obj->total_cartons_lotdet,
            'cartons_stock' => $obj->cartons_stock_lotdet,
            'poids_stock' => $obj->poids_stock_lotdet,
            'cartons_sortie' => $obj->cartons_sortie_lotdet,
            'poids_sortie' => $obj->poids_sortie_lotdet,
            'total_prix_moyen' => $obj->total_prix_moyen,
            'total_frais_cartons' => $obj->total_frais_cartons,
            'prix_moyen_carton' => $obj->prix_moyen_carton,
            'valeur_stock' => ($obj->total_prix_moyen + $obj->total_frais_cartons)
        ];
        
        // Calcul des totaux
        $total_stock_carton += $obj->cartons_stock_lotdet;
        $total_stock_poids  += $obj->poids_stock_lotdet;
        $total_sortie_carton += $obj->cartons_sortie_lotdet;
        $total_sortie_poids  += $obj->poids_sortie_lotdet;
        $total_valeur        += ($obj->total_prix_moyen + $obj->total_frais_cartons);
        $total_general_carton += $obj->total_cartons_lotdet;
        $total_general_poids  += ($obj->poids_stock_lotdet + $obj->poids_sortie_lotdet);
    }
    
    // Affichage des cartes de résumé
    print '<div class="summary-cards">';
    print '<div class="summary-card">';
    print '<h4>📦 Cartons en stock</h4>';
    print '<div class="value">'.$total_stock_carton.'</div>';
    print '<div class="subtext">'.price($total_stock_poids).' kg</div>';
    print '</div>';
    
    print '<div class="summary-card">';
    print '<h4>🚚 Cartons sortis</h4>';
    print '<div class="value">'.$total_sortie_carton.'</div>';
    print '<div class="subtext">'.price($total_sortie_poids).' kg</div>';
    print '</div>';
    
    print '<div class="summary-card">';
    print '<h4>💰 Valeur totale stock</h4>';
    print '<div class="value">'.price($total_valeur).'</div>';
    print '<div class="subtext">'.count($lots_data).' lots actifs</div>';
    print '</div>';
    
    print '<div class="summary-card">';
    print '<h4>📊 Total général</h4>';
    print '<div class="value">'.$total_general_carton.'</div>';
    print '<div class="subtext">'.price($total_general_poids).' kg</div>';
    print '</div>';
    print '</div>';
    
    // Section header avec bouton d'export
    /*print '<div class="section-header">';
    print '<h3>📋 Détail des cartons par lot</h3>';
    print '<button class="export-btn" onclick="exportToExcel()">📥 Exporter en Excel</button>';
    print '</div>';*/
    // Dans la section header avec le bouton d'export, remplacez par :
// Boutons d'export
print '<div class="export-options">';
//print '<a href="stock_export.php?export=simple&fk_entrepot='.$fk_entrepot.'&fk_product='.$fk_product.'&ref_lot='.urlencode($ref_lot).'" class="export-btn">📥 Export Excel simple</a>';
print '<a href="stock_export.php?export=detailed&fk_entrepot='.$fk_entrepot.'&fk_product='.$fk_product.'&ref_lot='.urlencode($ref_lot).'" class="export-btn detailed">📊 Export Excel détaillé</a>';
//print '<a href="stock_export.php?export=full&fk_entrepot='.$fk_entrepot.'&fk_product='.$fk_product.'&ref_lot='.urlencode($ref_lot).'" class="export-btn server">📤 Export complet (serveur)</a>';
print '</div>';
    
    // Tableau des données détaillées
    print '<table class="stock-table">';
    print '<thead>';
    print '<tr>';
    print '<th>Entrepôt</th>';
    print '<th>🏷️ Réf. Lot</th>';
    print '<th>📅 Date Lot</th>';
    print '<th>Produit</th>';
    print '<th>📦 Stock</th>';
    print '<th>⚖️ Poids Stock (kg)</th>';
    print '<th>🚚 Sorties</th>';
    print '<th>📤 Poids Sorties (kg)</th>';
    print '<th>💰 Prix unit. (lot)</th>';
    print '<th>💵 Prix/carton</th>';
    print '<th>💰 Valeur stock</th>';
    print '<th>📈 Taux rend.</th>';
    print '<th>🏷️ ID LotDet</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';
    
    $lot_counter = 0;
    foreach ($lots_data as $lot) {
        $lot_counter++;
        $lot_total_stock = 0;
        $lot_total_poids = 0;
        $lot_total_valeur = 0;
        $lot_total_cartons = 0;
        
        // Calcul des totaux par lot
        foreach ($lot['produits'] as $produit) {
            $lot_total_stock += $produit['cartons_stock'];
            $lot_total_poids += $produit['poids_stock'];
            $lot_total_valeur += $produit['valeur_stock'];
            $lot_total_cartons += $produit['total_cartons'];
        }
        
        // Ligne d'en-tête du lot
        print '<tr class="lot-cell">';
        print '<td><strong>'.$lot['entrepot']['ref'].'</strong>';
        if (!empty($lot['entrepot']['lieu'])) {
            print '<br><span class="small-text">'.$lot['entrepot']['lieu'].'</span>';
        }
        print '</td>';
        print '<td><strong>'.$lot['lot_info']['ref'].'</strong></td>';
        print '<td>'.dol_print_date($lot['lot_info']['date'], '%d/%m/%Y').'</td>';
        print '<td colspan="9"><span class="lot-badge">Lot #'.$lot_counter.'</span> ';
        print 'Total lot: <strong>'.$lot_total_stock.'</strong> cartons en stock, ';
        print 'Valeur: <strong>'.price($lot_total_valeur).'</strong>';
        print '</td>';
        print '</tr>';
        
        // Lignes des produits dans le lot
        $prod_counter = 0;
        foreach ($lot['produits'] as $produit) {
            $prod_counter++;
            $row_class = $prod_counter % 2 == 0 ? '' : 'style="background-color: #f0f9ff;"';
            
            print '<tr '.$row_class.'>';
            
            // Première ligne du produit
            if ($prod_counter == 1) {
                print '<td rowspan="'.count($lot['produits']).'"></td>';
                print '<td rowspan="'.count($lot['produits']).'"></td>';
                print '<td rowspan="'.count($lot['produits']).'"></td>';
            }
            
            print '<td class="product-cell">'.$produit['product_label'].'</td>';
            print '<td class="number-cell">'.$produit['cartons_stock'].'</td>';
            print '<td class="number-cell">'.price($produit['poids_stock']).'</td>';
            print '<td class="number-cell">'.$produit['cartons_sortie'].'</td>';
            print '<td class="number-cell">'.price($produit['poids_sortie']).'</td>';
            print '<td class="number-cell">'.price($produit['prix_unit']).'</td>';
            print '<td class="number-cell">'.price($produit['prix_moyen_carton']).'</td>';
            print '<td class="number-cell"><strong>'.price($produit['valeur_stock']).'</strong></td>';
            print '<td class="number-cell">'.($produit['taux_rendement'] ? $produit['taux_rendement'].'%' : '-').'</td>';
            print '<td class="number-cell small-text">#'.$produit['lotdet_id'].'</td>';
            print '</tr>';
        }
        
        // Ligne de total pour le lot
        print '<tr class="section-total">';
        print '<td colspan="3"><strong>TOTAL LOT '.$lot['lot_info']['ref'].'</strong></td>';
        print '<td><strong>'.count($lot['produits']).' produit(s)</strong></td>';
        print '<td class="number-cell"><strong>'.$lot_total_stock.'</strong></td>';
        print '<td class="number-cell"><strong>'.price($lot_total_poids).'</strong></td>';
        print '<td class="number-cell">-</td>';
        print '<td class="number-cell">-</td>';
        print '<td class="number-cell">-</td>';
        print '<td class="number-cell">-</td>';
        print '<td class="number-cell"><strong>'.price($lot_total_valeur).'</strong></td>';
        print '<td class="number-cell">-</td>';
        print '<td class="number-cell">-</td>';
        print '</tr>';
    }
    
    // Ligne des totaux généraux
    print '<tr class="totals-row">';
    print '<td colspan="4"><strong>TOTAUX GÉNÉRAUX</strong> ('.count($lots_data).' lots)</td>';
    print '<td class="number-cell"><strong>'.$total_stock_carton.'</strong></td>';
    print '<td class="number-cell"><strong>'.price($total_stock_poids).'</strong></td>';
    print '<td class="number-cell"><strong>'.$total_sortie_carton.'</strong></td>';
    print '<td class="number-cell"><strong>'.price($total_sortie_poids).'</strong></td>';
    print '<td class="number-cell">-</td>';
    print '<td class="number-cell">-</td>';
    print '<td class="number-cell"><strong>'.price($total_valeur).'</strong></td>';
    print '<td class="number-cell">-</td>';
    print '<td class="number-cell">-</td>';
    print '</tr>';
    
    print '</tbody>';
    print '</table>';
    
    // Script pour l'export Excel
    print '
<script>
// Export client-side (optionnel - pour garder une version)
function exportToExcelClient() {
    let table = document.querySelector(".stock-table");
    let html = table.outerHTML;
    
    html = html.replace(/<th/g, \'<th style="background-color: #0073aa; color: white; padding: 8px;"\');
    html = html.replace(/<td/g, \'<td style="padding: 6px; border: 1px solid #ddd;"\');
    
    let blob = new Blob([html], {type: "application/vnd.ms-excel"});
    let link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "etat_stock_" + new Date().toISOString().slice(0,10) + ".xls";
    link.click();
}
</script>
';
    
} else {
    print '<div class="no-data">';
    print 'Aucun lot avec des cartons en stock trouvé pour ces filtres.';
    print '</div>';
}

print '</div>';
llxFooter();
$db->close();
?>

