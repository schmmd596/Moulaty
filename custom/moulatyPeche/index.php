<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

global $db, $langs, $user;
$langs->load("main");
$langs->load("products");
$langs->load("abricot@abricot");

$form = new Form($db);

// ====================
// 🔹 CHARGER LES ENTREPÔTS
// ====================
$entrepots = [0 => $langs->trans('AllWarehouses')];
$resql = $db->query("SELECT rowid, ref, lieu FROM ".MAIN_DB_PREFIX."entrepot ORDER BY ref ASC");
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $entrepots[$obj->rowid] = $obj->ref.(($obj->lieu)?' - '.$obj->lieu:'');
    }
}

// ====================
// 🔹 CHARGER LES PRODUITS
// ====================
$produits = [0 => $langs->trans('AllProducts')];
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

// ====================
// 🔹 FILTRES
// ====================
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$fk_product  = GETPOST('fk_product', 'int');

// ====================
// 🔹 ENTREPÔTS ACCESSIBLES
// ====================
$entrepots_accessibles = [];
$sql_ent = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent = $db->query($sql_ent);
if ($res_ent && $db->num_rows($res_ent) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

// ====================
// 🔹 HEADER
// ====================
llxHeader('', $langs->trans('GlobalDashboard'));
print '
<style>
.fichecenter { max-width: 1200px; margin: 0 auto; }
.filter-card {
    background: white; padding: 20px; border-radius: 12px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08); margin-bottom: 25px;
}
.cards-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
.product-card {
    background: white; border-radius: 15px; padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.product-title { font-weight: 600; color: #222; font-size: 1.1em; margin-bottom: 10px; }
.info-line { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f0; }
.info-label { color: #555; font-size: 0.9em; }
.info-value { font-weight: 600; color: #0073aa; }
.total-card {
    grid-column: 1 / -1;
    background: linear-gradient(135deg, #0073aa, #00aaff);
    color: white; text-align: center; padding: 20px;
    border-radius: 15px; font-size: 1.1em; margin-top: 15px;
}
.section-title { font-size: 1.3em; margin-top: 40px; color:#0073aa; font-weight:600; }
.no-data { text-align: center; font-style: italic; color: #777; margin-top: 20px; }
.butAction { padding:6px 15px; font-weight:bold; background:#0078d7; color:white; border:none; border-radius:6px; cursor:pointer; }
.butAction:hover { background:#005fa3; }

  .dashboard-container {
        background: #fff;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }
    .dashboard-header {
        display: flex;
        justify-content: space-between;
                align-items: center;
        border-bottom: 2px solid #e5e5e5;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .dashboard-header h2 {
        font-size: 20px;
        color: #333;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .dashboard-header h2 i {
        color: #0055a4;
        font-size: 22px;
    }
    .filter-form {
        text-align: right;
        margin-bottom: 15px;
    }
    .filter-form select, 
    .filter-form .button {
        padding: 6px 10px;
        border-radius: 5px;
        border: 1px solid #ccc;
    }
    .filter-form .button {
        background-color: #0055a4;
        color: #fff;
        font-weight: 500;
        border: none;
        transition: 0.2s;
    }
    .filter-form .button:hover {
        background-color: #003f7d;
    }
    table.liste {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    table.liste th {
        background-color: #f5f6f8;
        color: #333;
        text-align: center;
        padding: 8px;
        border-bottom: 2px solid #ddd;
        font-weight: 600;
    }
    table.liste td {
        padding: 8px;
        border-bottom: 1px solid #eee;
        text-align: center;
        color: #333;
    }
    table.liste tr:hover {
        background-color: #f9f9f9;
    }
    .summary-box {
        display: flex;
        justify-content: space-around;
        text-align: center;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    .summary-card {
        background-color: #f8f9fa;
        border: 1px solid #e3e3e3;
        border-radius: 8px;
        padding: 15px;
        min-width: 200px;
        margin: 5px;
        flex: 1;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.03);
    }
    .summary-card h4 {
        font-size: 15px;
        color: #555;
        margin-bottom: 8px;
    }
    .summary-card p {
        font-size: 16px;
        font-weight: bold;
        color: #000;
        margin: 0;
    }
    h3.section-title {
        margin-top: 35px;
        font-size: 17px;
        font-weight: 600;
        color: #222;
        border-left: 4px solid #0055a4;
        padding-left: 8px;
    }
        hr.styled {
    border: 0;
    height: 2px;
    background: linear-gradient(to right, #0078d7, #00c6ff);
    border-radius: 5px;
    margin: 20px 0;
}
</style>
';

print '<div class="fichecenter">';
print load_fiche_titre($langs->trans('GlobalDashboard'), '', 'title_generic');

// ====================
// 🔹 FORMULAIRE DE FILTRE
// ====================
print '<div class="filter-card">';
print '<form method="GET" action="">';
print '<label style="font-weight:600; color:#333; margin-right:10px;">'.$langs->trans('Warehouse').' :</label> '
    .$form->selectarray('fk_entrepot', $entrepots, $fk_entrepot, 0);

print '&nbsp;&nbsp;&nbsp;';

print '<label style="font-weight:600; color:#333; margin-right:10px;">'.$langs->trans('Product').' :</label> '
    .$form->selectarray('fk_product', $produits, $fk_product, 0);
print ' <input type="submit" class="butAction" value="'.$langs->trans('Show').'">';
print '</form>';
print '</div>';

print '<hr class="styled">';

// =========================
// STATISTIQUES GLOBALES
// =========================
print '<div class="section-title">'.$langs->trans('ReceptionTracking').'</div>';

$sql = "SELECT COUNT(r.rowid) AS nb_receptions, 
               SUM(r.montant) AS montant_total, 
               SUM(r.poids) AS poids_total
        FROM ".MAIN_DB_PREFIX."pech_reception r
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = r.fk_entrepot
        WHERE r.etat = 1";
if ($fk_entrepot > 0) $sql .= " AND r.fk_entrepot = ".$fk_entrepot;

if (!empty($entrepots_accessibles)) {
    $sql .= " AND r.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
}

$res = $db->query($sql);

if ($res && $db->num_rows($res) > 0) {
    $obj = $db->fetch_object($res);
    print '<div class="summary-box">';
    print '<div class="summary-card"><h4>'.$langs->trans('NumberOfReceptions').'</h4><p>'.$obj->nb_receptions.'</p></div>';
    print '<div class="summary-card"><h4>'.$langs->trans('TotalAmount').'</h4><p>'.number_format($obj->montant_total, 2, ',', ' ').' '.$langs->trans('Currency').'</p></div>';
    print '<div class="summary-card"><h4>'.$langs->trans('TotalWeight').'</h4><p>'.number_format($obj->poids_total, 2, ',', ' ').' kg</p></div>';
    print '</div>';
} else {
    print '<p style="text-align:center;color:#777;">'.$langs->trans('NoDataFound').'</p>';
}

// =========================
// DÉTAIL PAR ENTREPÔT
// =========================
$sql2 = "SELECT e.ref AS entrepot, COUNT(r.rowid) AS nb_receptions,
                SUM(r.montant) AS montant_total, SUM(r.poids) AS poids_total
         FROM ".MAIN_DB_PREFIX."pech_reception r
         LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = r.fk_entrepot
         WHERE r.etat = 1";
if ($fk_entrepot > 0) $sql2 .= " AND r.fk_entrepot = ".$fk_entrepot;
if (!empty($entrepots_accessibles)) {
    $sql2 .= " AND r.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
}
$sql2 .= " GROUP BY e.ref ORDER BY e.ref ASC";

$res2 = $db->query($sql2);

print '<h3 class="section-title">'.$langs->trans('DetailByWarehouse').'</h3>';
print '<table class="liste">';
print '<tr><th>'.$langs->trans('Warehouse').'</th><th>'.$langs->trans('NumberOfReceptions').'</th><th>'.$langs->trans('TotalAmount').' ('.$langs->trans('Currency').')</th><th>'.$langs->trans('TotalWeight').' (kg)</th></tr>';

if ($res2 && $db->num_rows($res2) > 0) {
    while ($obj = $db->fetch_object($res2)) {
        print '<tr>';
        print '<td><strong>'.$obj->entrepot.'</strong></td>';
        print '<td>'.$obj->nb_receptions.'</td>';
        print '<td>'.number_format($obj->montant_total, 2, ',', ' ').'</td>';
        print '<td>'.number_format($obj->poids_total, 2, ',', ' ').'</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="4">'.$langs->trans('NoDataAvailable').'</td></tr>';
}
print '</table>';

print '</div>'; // end dashboard-container

print '<hr class="styled">';

// ====================
// 🔹 SECTION 1 : Suivi des Plats
// ====================
print '<div class="section-title">'.$langs->trans('DishTracking').'</div>';

$where = [];
if ($fk_entrepot > 0) $where[] = "b.fk_entrepot = ".(int)$fk_entrepot;
if ($fk_product > 0) $where[] = "m.fk_product = ".(int)$fk_product;
if (!empty($entrepots_accessibles)) $where[] = "b.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";

$sql = "
SELECT 
    p.label AS product_label,
    e.ref AS entrepot_label,
    COUNT(pl.rowid) AS total_plat,
    SUM(pl.poids) AS total_poids,
    SUM(CASE WHEN pl.fk_carton IS NOT NULL THEN 1 ELSE 0 END) AS nb_carton,
    SUM(CASE WHEN pl.fk_carton IS NOT NULL THEN pl.poids ELSE 0 END) AS poids_carton,
    SUM(CASE WHEN pl.fk_carton IS NULL THEN 1 ELSE 0 END) AS nb_tunnel,
    SUM(CASE WHEN pl.fk_carton IS NULL THEN pl.poids ELSE 0 END) AS poids_tunnel
FROM ".MAIN_DB_PREFIX."pech_plat pl
INNER JOIN ".MAIN_DB_PREFIX."pech_misenplat m ON m.rowid = pl.fk_misenplat
INNER JOIN ".MAIN_DB_PREFIX."pech_bon_misenplat b ON b.rowid = m.fk_bon_misenplat
INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = m.fk_product
INNER JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = b.fk_entrepot
";

if (count($where) > 0) $sql .= " WHERE ".implode(" AND ", $where);
$sql .= " GROUP BY p.label, e.ref ORDER BY e.ref, p.label ASC";

$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    print '<div class="cards-container">';
    $total_plats = $total_poids = $total_tunnel = $total_tunnel_poids = $total_carton = $total_carton_poids = 0;
    while ($obj = $db->fetch_object($resql)) {
        $total_plats += $obj->total_plat;
        $total_poids += $obj->total_poids;
        $total_tunnel += $obj->nb_tunnel;
        $total_tunnel_poids += $obj->poids_tunnel;
        $total_carton += $obj->nb_carton;
        $total_carton_poids += $obj->poids_carton;

        print '<div class="product-card">';
        print '<div class="product-title">📦 '.dol_escape_htmltag($obj->product_label).'</div>';
        print '<div class="info-line"><span class="info-label">'.$langs->trans('Warehouse').'</span><span class="info-value">'.dol_escape_htmltag($obj->entrepot_label).'</span></div>';
        print '<div class="info-line"><span class="info-label">🟢 '.$langs->trans('Total').'</span><span class="info-value">'.$obj->total_plat.' '.$langs->trans('Dishes').' / '.price($obj->total_poids).' kg</span></div>';
        print '<div class="info-line"><span class="info-label">🟡 '.$langs->trans('InTunnel').'</span><span class="info-value">'.$obj->nb_tunnel.' '.$langs->trans('Dishes').' / '.price($obj->poids_tunnel).' kg</span></div>';
        print '<div class="info-line"><span class="info-label">🔴 '.$langs->trans('InCarton').'</span><span class="info-value">'.$obj->nb_carton.' '.$langs->trans('Dishes').' / '.price($obj->poids_carton).' kg</span></div>';
        print '</div>';
    }
    // Totaux globaux
    print '<div class="total-card">';
    print '<b>🧮 '.$langs->trans('GlobalTotals').'</b><br>';
    print $langs->trans('TotalDishes').' : '.$total_plats.' / '.price($total_poids).' kg<br>';
    print $langs->trans('Tunnel').' : '.$total_tunnel.' / '.price($total_tunnel_poids).' kg<br>';
    print $langs->trans('Carton').' : '.$total_carton.' / '.price($total_carton_poids).' kg';
    print '</div>';
    print '</div>';
} else {
    print '<p class="no-data">'.$langs->trans('NoDataFoundForFilters').'</p>';
}

print '<hr class="styled">';

// ====================
// 🔹 SECTION 2 : Suivi des Cartons
// ====================
print '<div class="section-title">'.$langs->trans('CartonTracking').'</div>';

$where = [];
if ($fk_entrepot > 0) $where[] = "l.fk_entrepot = ".(int)$fk_entrepot;
if ($fk_product > 0) $where[] = "c.fk_product = ".(int)$fk_product;
if (!empty($entrepots_accessibles)) $where[] = "l.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";

$sql = "
SELECT 
    p.rowid AS product_id,
    p.label AS product_label,
    SUM(CASE WHEN c.statut = 0 THEN 1 ELSE 0 END) AS nb_carton_stock,
    SUM(CASE WHEN c.statut = 0 THEN c.poids ELSE 0 END) AS poids_stock,
    SUM(CASE WHEN c.statut = 1 THEN 1 ELSE 0 END) AS nb_carton_sortie,
    SUM(CASE WHEN c.statut = 1 THEN c.poids ELSE 0 END) AS poids_sortie,
    SUM(CASE WHEN c.statut = 0 THEN (c.prix_moyen + c.frais) ELSE 0 END) AS valeur_stock
FROM ".MAIN_DB_PREFIX."pech_carton AS c
INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet AS ld ON ld.rowid = c.fk_lotdet
INNER JOIN ".MAIN_DB_PREFIX."pech_lot AS l ON l.rowid = ld.fk_lot
INNER JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = c.fk_product
";

if (count($where) > 0) $sql .= " WHERE ".implode(" AND ", $where);
$sql .= " GROUP BY p.rowid, p.label ORDER BY p.label";

$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    print '<div class="cards-container">';
    $total_stock_carton = $total_stock_poids = 0;
    $total_sortie_carton = $total_sortie_poids = $total_valeur = 0;

    while ($obj = $db->fetch_object($resql)) {
        print '<div class="product-card">';
        print '<div class="product-title">🧾 '.$obj->product_label.'</div>';
        print '<div class="info-line"><span class="info-label">'.$langs->trans('CartonsInStock').'</span><span class="info-value">'.$obj->nb_carton_stock.'</span></div>';
        print '<div class="info-line"><span class="info-label">'.$langs->trans('StockWeight').' (kg)</span><span class="info-value">'.price($obj->poids_stock).'</span></div>';
        print '<div class="info-line"><span class="info-label">'.$langs->trans('CartonsOut').'</span><span class="info-value">'.$obj->nb_carton_sortie.'</span></div>';
        print '<div class="info-line"><span class="info-label">'.$langs->trans('OutWeight').' (kg)</span><span class="info-value">'.price($obj->poids_sortie).'</span></div>';
        print '<div class="info-line"><span class="info-label">'.$langs->trans('StockValue').'</span><span class="info-value">'.price($obj->valeur_stock).'</span></div>';
        print '</div>';

        $total_stock_carton += $obj->nb_carton_stock;
        $total_stock_poids  += $obj->poids_stock;
        $total_sortie_carton += $obj->nb_carton_sortie;
        $total_sortie_poids  += $obj->poids_sortie;
        $total_valeur        += $obj->valeur_stock;
    }

    print '<div class="total-card">';
    print '<b>🧮 '.$langs->trans('GlobalTotals').'</b><br>';
    print $langs->trans('Stock').' : '.$total_stock_carton.' / '.price($total_stock_poids).' kg<br>';
    print $langs->trans('Out').' : '.$total_sortie_carton.' / '.price($total_sortie_poids).' kg<br>';
    print $langs->trans('Value').' : '.price($total_valeur);
    print '</div>';
    print '</div>';
} else {
    print '<p class="no-data">'.$langs->trans('NoDataFoundForFilters').'</p>';
}

print '<hr class="styled">';

// ====================
// 🔹 SECTION 3 : Sorties
// ====================
print '<div class="section-title">'.$langs->trans('OutBySourceWarehouse').'</div>';

$sql = "
SELECT 
    e.rowid AS entrepot_id,
    e.ref AS entrepot_ref,
    COUNT(DISTINCT s.rowid) AS nb_sorties,
    SUM(d.nb_carton) AS total_cartons,
    SUM(d.poids_total) AS total_poids,
    SUM(f.total_ttc) AS total_factures
FROM ".MAIN_DB_PREFIX."pech_sortie s
INNER JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = s.fk_entrepot_source
LEFT JOIN ".MAIN_DB_PREFIX."pech_sortiedetprod d ON s.rowid = d.fk_sortie
LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = s.fk_facture_client
WHERE s.statut = 2
";

if ($fk_entrepot > 0) $sql .= " AND s.fk_entrepot_source = ".((int)$fk_entrepot);

$sql .= " GROUP BY e.rowid, e.ref ORDER BY e.ref ASC";

$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    print '<div class="cards-container">';
    $total_sorties_global = $total_cartons_global = $total_poids_global = $total_factures_global = 0;

    while ($obj = $db->fetch_object($resql)) {
        print '<div class="product-card">';
        print '<div class="product-title">🏭 '.$obj->entrepot_ref.'</div>';
        print '<div class="info-line"><span class="info-label">'.$langs->trans('Outs').'</span><span class="info-value">'.$obj->nb_sorties.'</span></div>';
        print '<div class="info-line"><span class="info-label">'.$langs->trans('TotalCartons').'</span><span class="info-value">'.price($obj->total_cartons).'</span></div>';
        print '<div class="info-line"><span class="info-label">'.$langs->trans('TotalWeight').' (kg)</span><span class="info-value">'.price($obj->total_poids).'</span></div>';
        print '<div class="info-line"><span class="info-label">'.$langs->trans('InvoicesAmount').'</span><span class="info-value">'.price($obj->total_factures).'</span></div>';
        print '</div>';

        $total_sorties_global += $obj->nb_sorties;
        $total_cartons_global += $obj->total_cartons;
        $total_poids_global += $obj->total_poids;
        $total_factures_global += $obj->total_factures;
    }

    print '<div class="total-card">';
    print '<b>🧮 '.$langs->trans('GlobalTotals').'</b><br>';
    print $langs->trans('Outs').' : '.$total_sorties_global.'<br>';
    print $langs->trans('Cartons').' : '.price($total_cartons_global).'<br>';
    print $langs->trans('Weight').' : '.price($total_poids_global).' kg<br>';
    print $langs->trans('Invoices').' : '.price($total_factures_global);
    print '</div>';

    print '</div>';
} else {
    print '<p class="no-data">'.$langs->trans('NoOutFoundForFilters').'</p>';
}

print '</div>'; // fichecenter
llxFooter();
$db->close();
?>