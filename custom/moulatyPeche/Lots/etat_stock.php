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

// Entrepôts accessibles par l'utilisateur
$entrepots_accessibles = [];
$sql_ent = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent = $db->query($sql_ent);
if ($res_ent && $db->num_rows($res_ent) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

llxHeader('', 'État de stock des cartons');

// Styles CSS pour le tableau
print '
<style>
    body { background-color: #f8fafc; }
    .fichecenter { max-width: 1400px; margin: 0 auto; padding: 20px; }
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
        font-size: 0.95em;
    }
    .stock-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.9em;
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
    .totals-row {
        background: linear-gradient(135deg, #28a745, #20c997) !important;
        color: white;
        font-weight: bold;
        font-size: 1em;
    }
    .status-in {
        background-color: #d4edda;
        color: #155724;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 500;
    }
    .status-out {
        background-color: #f8d7da;
        color: #721c24;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 500;
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
        min-width: 200px;
        background: white;
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 3px 8px rgba(0,0,0,0.08);
    }
    .summary-card h4 {
        margin: 0 0 10px 0;
        color: #555;
        font-size: 0.9em;
    }
    .summary-card .value {
        font-size: 1.8em;
        font-weight: bold;
        color: #0073aa;
    }
    .summary-card .subtext {
        font-size: 0.8em;
        color: #777;
        margin-top: 5px;
    }
</style>
';

print '<div class="fichecenter">';
print load_fiche_titre('📦 État de stock des cartons', '', 'title_generic');

// Formulaire de filtres
print '<div class="filter-card">';
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">';

print '<label style="font-weight: bold;">🏠 Entrepôt :</label>';
print $form->selectarray('fk_entrepot', $entrepots, $fk_entrepot, 0, '', 0, 0, '', 0, 0, 0, '', '', 'minwidth200');

print '<label style="font-weight: bold;">🧾 Produit :</label>';
print $form->selectarray('fk_product', $produits, $fk_product, 0, '', 0, 0, '', 0, 0, 0, '', '', 'minwidth200');

print '<input type="submit" class="butAction" value="🔍 Filtrer" style="padding:6px 15px; font-weight:bold;">';
print '</form>';
print '</div>';

// Requête SQL pour récupérer les données
$sql = "
SELECT 
    p.rowid AS product_id,
    p.label AS product_label,
    e.ref AS entrepot_ref,
    e.lieu AS entrepot_lieu,
    
    -- Cartons en stock
    SUM(CASE WHEN c.statut = 0 THEN 1 ELSE 0 END) AS nb_carton_stock,
    SUM(CASE WHEN c.statut = 0 THEN c.poids ELSE 0 END) AS poids_stock,
    
    -- Cartons sortis
    SUM(CASE WHEN c.statut = 1 THEN 1 ELSE 0 END) AS nb_carton_sortie,
    SUM(CASE WHEN c.statut = 1 THEN c.poids ELSE 0 END) AS poids_sortie,
    
    -- Valeur du stock
    SUM(CASE WHEN c.statut = 0 THEN (c.prix_moyen + c.frais) ELSE 0 END) AS valeur_stock,
    
    -- Total cartons (stock + sortis)
    COUNT(c.rowid) AS total_cartons,
    SUM(c.poids) AS total_poids

FROM ".MAIN_DB_PREFIX."pech_carton AS c
INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet AS ld ON ld.rowid = c.fk_lotdet
INNER JOIN ".MAIN_DB_PREFIX."pech_lot AS l ON l.rowid = ld.fk_lot
INNER JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = c.fk_product
INNER JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = l.fk_entrepot
";

$where = [];
if ($fk_entrepot > 0) $where[] = "l.fk_entrepot = ".(int)$fk_entrepot;
if ($fk_product > 0) $where[] = "c.fk_product = ".(int)$fk_product;

// Ajouter restriction sur les entrepôts accessibles
if (!empty($entrepots_accessibles)) {
    $where[] = "l.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
}

if (count($where) > 0) {
    $sql .= " WHERE ".implode(" AND ", $where);
}

$sql .= " 
GROUP BY p.rowid, p.label, e.rowid, e.ref, e.lieu
ORDER BY e.ref, p.label
";

$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    
    // Initialisation des totaux
    $total_stock_carton = $total_stock_poids = 0;
    $total_sortie_carton = $total_sortie_poids = $total_valeur = 0;
    $total_general_carton = $total_general_poids = 0;
    
    // Stockage des données pour affichage
    $data = [];
    while ($obj = $db->fetch_object($resql)) {
        $data[] = $obj;
        
        // Calcul des totaux
        $total_stock_carton += $obj->nb_carton_stock;
        $total_stock_poids  += $obj->poids_stock;
        $total_sortie_carton += $obj->nb_carton_sortie;
        $total_sortie_poids  += $obj->poids_sortie;
        $total_valeur        += $obj->valeur_stock;
        $total_general_carton += $obj->total_cartons;
        $total_general_poids  += $obj->total_poids;
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
    print '<h4>💰 Valeur du stock</h4>';
    print '<div class="value">'.price($total_valeur).'</div>';
    print '<div class="subtext">Total stock</div>';
    print '</div>';
    
    print '<div class="summary-card">';
    print '<h4>📊 Total général</h4>';
    print '<div class="value">'.$total_general_carton.'</div>';
    print '<div class="subtext">'.price($total_general_poids).' kg</div>';
    print '</div>';
    print '</div>';
    
    // Section header avec bouton d'export
    print '<div class="section-header">';
    print '<h3>📋 Détail par produit et entrepôt</h3>';
    print '<button class="export-btn" onclick="exportToExcel()">📥 Exporter en Excel</button>';
    print '</div>';
    
    // Tableau des données détaillées
    print '<table class="stock-table">';
    print '<thead>';
    print '<tr>';
    print '<th>#</th>';
    print '<th>Produit</th>';
    print '<th>Entrepôt</th>';
    print '<th>📦 Stock</th>';
    print '<th>⚖️ Poids Stock (kg)</th>';
    print '<th>🚚 Sorties</th>';
    print '<th>📤 Poids Sorties (kg)</th>';
    print '<th>💰 Valeur Stock</th>';
    print '<th>📊 Total Cartons</th>';
    print '<th>⚖️ Total Poids (kg)</th>';
    print '<th>État</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';
    
    $counter = 1;
    foreach ($data as $obj) {
        // Calcul des pourcentages
        $pourcentage_stock = $obj->total_cartons > 0 ? ($obj->nb_carton_stock / $obj->total_cartons) * 100 : 0;
        
        print '<tr>';
        print '<td class="number-cell">'.$counter.'</td>';
        print '<td class="product-cell">'.$obj->product_label.'</td>';
        print '<td>'.$obj->entrepot_ref.($obj->entrepot_lieu ? ' - '.$obj->entrepot_lieu : '').'</td>';
        print '<td class="number-cell">'.$obj->nb_carton_stock.'</td>';
        print '<td class="number-cell">'.price($obj->poids_stock).'</td>';
        print '<td class="number-cell">'.$obj->nb_carton_sortie.'</td>';
        print '<td class="number-cell">'.price($obj->poids_sortie).'</td>';
        print '<td class="number-cell">'.price($obj->valeur_stock).'</td>';
        print '<td class="number-cell">'.$obj->total_cartons.'</td>';
        print '<td class="number-cell">'.price($obj->total_poids).'</td>';
        print '<td>';
        if ($pourcentage_stock >= 70) {
            print '<span class="status-in">Stock élevé</span>';
        } elseif ($pourcentage_stock >= 30) {
            print '<span class="status-in">Stock moyen</span>';
        } else {
            print '<span class="status-out">Stock faible</span>';
        }
        print '</td>';
        print '</tr>';
        $counter++;
    }
    
    // Ligne des totaux
    print '<tr class="totals-row">';
    print '<td colspan="3"><strong>TOTAUX GÉNÉRAUX</strong></td>';
    print '<td class="number-cell"><strong>'.$total_stock_carton.'</strong></td>';
    print '<td class="number-cell"><strong>'.price($total_stock_poids).'</strong></td>';
    print '<td class="number-cell"><strong>'.$total_sortie_carton.'</strong></td>';
    print '<td class="number-cell"><strong>'.price($total_sortie_poids).'</strong></td>';
    print '<td class="number-cell"><strong>'.price($total_valeur).'</strong></td>';
    print '<td class="number-cell"><strong>'.$total_general_carton.'</strong></td>';
    print '<td class="number-cell"><strong>'.price($total_general_poids).'</strong></td>';
    print '<td>-</td>';
    print '</tr>';
    
    print '</tbody>';
    print '</table>';
    
    // Script pour l'export Excel
    print '
    <script>
    function exportToExcel() {
        // Création d\'un tableau HTML pour l\'export
        let table = document.querySelector(".stock-table");
        let html = table.outerHTML;
        
        // Création d\'un blob avec les données
        let blob = new Blob([html], {type: "application/vnd.ms-excel"});
        
        // Création d\'un lien de téléchargement
        let link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "etat_stock_cartons_'.date('Y-m-d').'.xls";
        link.click();
    }
    </script>
    ';
    
} else {
    print '<div class="no-data">';
    print 'Aucune donnée trouvée pour ces filtres.';
    print '</div>';
}

print '</div>';
llxFooter();
$db->close();
?>