<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

global $db, $langs, $user;

$langs->load("abricot@abricot");
$form = new Form($db);

$entrepots = [0 => '🗂️ Tous les entrepôts'];
$resql = $db->query("SELECT rowid, ref, lieu FROM ".MAIN_DB_PREFIX."entrepot ORDER BY ref ASC");
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $entrepots[$obj->rowid] = $obj->ref.(($obj->lieu)?' - '.$obj->lieu:'');
    }
}

/*$produits = [0 => '🧾 Tous les produits'];
$resql = $db->query("SELECT rowid, label FROM ".MAIN_DB_PREFIX."product ORDER BY label ASC");
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $produits[$obj->rowid] = $obj->label;
    }
}*/

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

$fk_entrepot = GETPOST('fk_entrepot', 'int');
$fk_product  = GETPOST('fk_product', 'int');

$entrepots_accessibles = [];
$sql_ent = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent = $db->query($sql_ent);
if ($res_ent && $db->num_rows($res_ent) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}
llxHeader('', 'Suivi des cartons');

print '
<style>
    body { background-color: #f8fafc; }
    .fichecenter { max-width: 1200px; margin: 0 auto; }
    .filter-card {
        background: white; padding: 20px; border-radius: 12px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.08); margin-bottom: 25px;
    }
    .cards-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 30px; }
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
        border-radius: 15px; font-size: 1.1em;
    }
    .no-data { text-align: center; font-style: italic; color: #777; margin-top: 20px; }
</style>
';

/*print '<div class="fichecenter">';
print load_fiche_titre("📦 Suivi global des cartons");

print '<div class="filter-card">';
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<label>Entrepôt :</label> '.$form->selectarray('fk_entrepot', $entrepots, $fk_entrepot, 0);
print '<label>Produit :</label> '.$form->selectarray('fk_product', $produits, $fk_product, 0);
print '<input type="submit" value="Afficher">';
print '</form>';
print '</div>';*/

print '<div class="fichecenter">';

print load_fiche_titre('📦 Suivi global des cartons', '', 'title_generic');

print '<div class="ficheaddleft" style="margin: 20px auto; width: fit-content; background: #f8f9fb; border: 1px solid #ddd; border-radius: 8px; padding: 15px 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';

print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">';

print '<label style="font-weight: bold;">🏠 Entrepôt :</label>';
print $form->selectarray('fk_entrepot', $entrepots, $fk_entrepot, 0, '', 0, 0, '', 0, 0, 0, '', '', 'minwidth200');

print '<label style="font-weight: bold;">🧾 Produit :</label>';
print $form->selectarray('fk_product', $produits, $fk_product, 0, '', 0, 0, '', 0, 0, 0, '', '', 'minwidth200');

print '<input type="submit" class="butAction" value="🔍 Afficher" style="padding:6px 15px; font-weight:bold;">';

print '</form>';
print '</div>';

$where = [];
//if ($fk_entrepot > 0) $where[] = "l.fk_entrepot = ".((int)$fk_entrepot);
//if ($fk_product > 0)  $where[] = "c.fk_product = ".((int)$fk_product);

/*$sql = "
SELECT 
    p.rowid AS product_id,
    p.label AS product_label,
    SUM(CASE WHEN c.statut=0 THEN 1 ELSE 0 END) AS nb_carton_stock,
    SUM(CASE WHEN c.statut=0 THEN c.poids ELSE 0 END) AS poids_stock,
    SUM(CASE WHEN c.statut=1 THEN 1 ELSE 0 END) AS nb_carton_sortie,
    SUM(CASE WHEN c.statut=1 THEN c.poids ELSE 0 END) AS poids_sortie,
    SUM(
        CASE WHEN c.statut=0 THEN (
            SELECT SUM(p2.prix_moyen + IFNULL(bm.total_frais / total_plats.total_plats, 0))
            FROM ".MAIN_DB_PREFIX."pech_plat p2
            JOIN ".MAIN_DB_PREFIX."pech_misenplat mp ON mp.rowid = p2.fk_misenplat
            JOIN ".MAIN_DB_PREFIX."pech_bon_misenplat bm ON bm.rowid = mp.fk_bon_misenplat
            JOIN (
                SELECT fk_bon_misenplat, SUM(nombre_plat) AS total_plats
                FROM ".MAIN_DB_PREFIX."pech_misenplat
                GROUP BY fk_bon_misenplat
            ) AS total_plats ON total_plats.fk_bon_misenplat = bm.rowid
            WHERE p2.fk_carton = c.rowid
        ) ELSE 0 END
    ) AS valeur_stock
FROM ".MAIN_DB_PREFIX."pech_carton c
INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
INNER JOIN ".MAIN_DB_PREFIX."pech_lot l ON l.rowid = ld.fk_lot
INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = c.fk_product
";*/

$sql = "
SELECT 
    p.rowid AS product_id,
    p.label AS product_label,

    -- 📦 Nombre et poids des cartons encore en stock
    SUM(CASE WHEN c.statut = 0 THEN 1 ELSE 0 END) AS nb_carton_stock,
    SUM(CASE WHEN c.statut = 0 THEN c.poids ELSE 0 END) AS poids_stock,

    -- 🚚 Nombre et poids des cartons sortis
    SUM(CASE WHEN c.statut = 1 THEN 1 ELSE 0 END) AS nb_carton_sortie,
    SUM(CASE WHEN c.statut = 1 THEN c.poids ELSE 0 END) AS poids_sortie,

    -- 💰 Valeur du stock : somme des prix moyens + frais pour les cartons en stock
    SUM(CASE WHEN c.statut = 0 THEN (c.prix_moyen + c.frais) ELSE 0 END) AS valeur_stock

FROM ".MAIN_DB_PREFIX."pech_carton AS c
INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet AS ld ON ld.rowid = c.fk_lotdet
INNER JOIN ".MAIN_DB_PREFIX."pech_lot AS l ON l.rowid = ld.fk_lot
INNER JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = c.fk_product
";


$where = [];
if ($fk_entrepot > 0) $where[] = "l.fk_entrepot = ".(int)$fk_entrepot;
if ($fk_product > 0) $where[] = "c.fk_product = ".(int)$fk_product;

// Ajouter restriction sur les entrepôts accessibles
if (!empty($entrepots_accessibles)) {
    $where[] = "l.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
}

// Si on a au moins une condition
if (count($where) > 0) {
    $sql .= " WHERE ".implode(" AND ", $where);
}


$sql .= " 
GROUP BY p.rowid, p.label
ORDER BY p.label
";

$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    print '<div class="cards-container">';
    $total_stock_carton = $total_stock_poids = 0;
    $total_sortie_carton = $total_sortie_poids = $total_valeur = 0;

    while ($obj = $db->fetch_object($resql)) {
        print '<div class="product-card">';
        print '<div class="product-title">🧾 '.$obj->product_label.'</div>';
        print '<div class="info-line"><span class="info-label">📦 Cartons en stock</span><span class="info-value">'.$obj->nb_carton_stock.'</span></div>';
        print '<div class="info-line"><span class="info-label">⚖️ Poids stock (kg)</span><span class="info-value">'.price($obj->poids_stock).'</span></div>';
        print '<div class="info-line"><span class="info-label">🚚 Cartons sortis</span><span class="info-value">'.$obj->nb_carton_sortie.'</span></div>';
        print '<div class="info-line"><span class="info-label">📤 Poids sortie (kg)</span><span class="info-value">'.price($obj->poids_sortie).'</span></div>';
        print '<div class="info-line"><span class="info-label">💰 Valeur totale du stock</span><span class="info-value">'.price($obj->valeur_stock).'</span></div>';
        print '</div>';

        $total_stock_carton += $obj->nb_carton_stock;
        $total_stock_poids  += $obj->poids_stock;
        $total_sortie_carton += $obj->nb_carton_sortie;
        $total_sortie_poids  += $obj->poids_sortie;
        $total_valeur        += $obj->valeur_stock;
    }

    print '<div class="total-card">';
    print '<b>🧮 Totaux globaux</b><br>';
    print 'Stock : '.$total_stock_carton.' cartons / '.price($total_stock_poids).' kg<br>';
    print 'Sorties : '.$total_sortie_carton.' cartons / '.price($total_sortie_poids).' kg<br>';
    print 'Valeur totale du stock : <b>'.price($total_valeur).'</b>';
    print '</div>';
    print '</div>';
} else {
    print '<p class="no-data">Aucune donnée trouvée pour ces filtres.</p>';
}

print '</div>';
llxFooter();
$db->close();
?>
