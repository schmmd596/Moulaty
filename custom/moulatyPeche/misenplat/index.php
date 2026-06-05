<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

global $db, $langs, $user;

$langs->load("abricot@abricot");
$form = new Form($db);

// ============================================================================
// 🔹 Charger les entrepôts
// ============================================================================
$entrepots = array(0 => 'Tous les entrepôts');
$sql = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot ORDER BY ref ASC";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $entrepots[$obj->rowid] = $obj->ref;
    }
}

// ============================================================================
// 🔹 Charger les produits
// ============================================================================
/*$produits = array(0 => 'Tous les produits');
$sql = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."product ORDER BY label ASC";
$resql = $db->query($sql);
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
// ============================================================================
// 🔹 Récupération des filtres
// ============================================================================
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$fk_product  = GETPOST('fk_product', 'int');

llxHeader();
print load_fiche_titre("📊 Suivi des plats par entrepôt et produit");

// ============================================================================
// 🔹 Formulaire de filtrage
// ============================================================================
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" style="
    background:#f8f9fa;
    border:1px solid #ddd;
    border-radius:8px;
    padding:15px 20px;
    margin:20px auto;
    width:fit-content;
    box-shadow:0 2px 5px rgba(0,0,0,0.1);
">';

print '<div style="display:flex;gap:20px;align-items:flex-end;flex-wrap:wrap;">';

// --- Sélecteur entrepôt ---
print '<div>';
print '<label style="font-weight:600;color:#333;">Entrepôt :</label><br>';
print $form->selectarray('fk_entrepot', $entrepots, $fk_entrepot, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200 maxwidth250');
print '</div>';

// --- Sélecteur produit ---
print '<div>';
print '<label style="font-weight:600;color:#333;">Produit :</label><br>';
print $form->selectarray('fk_product', $produits, $fk_product, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200 maxwidth250');
print '</div>';

// --- Bouton ---
print '<div>';
print '<input type="submit" class="button" value="Afficher" style="
    background:#0078d7;
    color:white;
    font-weight:600;
    border:none;
    border-radius:6px;
    padding:8px 18px;
    cursor:pointer;
    transition:0.2s;
">';
print '</div>';

print '</div>';
print '</form>';

print '<style>
    .button:hover {
        background:#005fa3 !important;
        transform:translateY(-1px);
    }
    select {
        padding:5px 8px;
        border-radius:4px;
        border:1px solid #ccc;
        background-color:white;
    }
</style>';

// ============================================================================
// 🔹 Construction de la requête principale
// ============================================================================
/*$where = array();
if ($fk_entrepot > 0) $where[] = "b.fk_entrepot = ".(int)$fk_entrepot;
if ($fk_product > 0) $where[] = "m.fk_product = ".(int)$fk_product;
*/
$entrepots_accessibles = [];
$sql_ent = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent = $db->query($sql_ent);
if ($res_ent && $db->num_rows($res_ent) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

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

/*if (count($where) > 0) {
    $sql .= "".implode(" AND ", $where);
}

$sql .= " GROUP BY p.label, e.ref ORDER BY e.ref, p.label ASC";
*/
$where = [];
if ($fk_entrepot > 0) $where[] = "b.fk_entrepot = ".(int)$fk_entrepot;
if ($fk_product > 0) $where[] = "m.fk_product = ".(int)$fk_product;

// Ajouter restriction sur les entrepôts accessibles
if (!empty($entrepots_accessibles)) {
    $where[] = "b.fk_entrepot IN (".implode(',', $entrepots_accessibles).")";
}

// Si on a au moins une condition
if (count($where) > 0) {
    $sql .= " WHERE ".implode(" AND ", $where);
}

$sql .= " GROUP BY p.label, e.ref ORDER BY e.ref, p.label ASC";
// ============================================================================
// 🔹 Exécution et affichage
// ============================================================================
// Totaux globaux initialisés
$grand_total_plats    = 0;
$grand_total_poids    = 0.0;
$grand_tunnel_plats   = 0;
$grand_tunnel_poids   = 0.0;
$grand_carton_plats   = 0;
$grand_carton_poids   = 0.0;

$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    print '<div style="display:flex;flex-wrap:wrap;gap:20px;margin-top:20px;">';
    while ($obj = $db->fetch_object($resql)) {
        // Mise à jour des totaux globaux
        $grand_total_plats  += (int)$obj->total_plat;
        $grand_total_poids  += (float)$obj->total_poids;
        $grand_tunnel_plats += (int)$obj->nb_tunnel;
        $grand_tunnel_poids += (float)$obj->poids_tunnel;
        $grand_carton_plats += (int)$obj->nb_carton;
        $grand_carton_poids += (float)$obj->poids_carton;

        print '<div class="box" style="flex:1;min-width:280px;padding:15px;border-radius:10px;background:#fff;border:1px solid #ddd;box-shadow:0 2px 5px rgba(0,0,0,0.05);">';
        print '<h3 style="margin-bottom:10px;">📦 <strong>'.dol_escape_htmltag($obj->product_label).'</strong></h3>';
        print '<p><strong>Entrepôt :</strong> '.dol_escape_htmltag($obj->entrepot_label).'</p>';
        print '<hr>';
        print '<p>🟢 <strong>Total :</strong> '.$obj->total_plat.' plats — '.price($obj->total_poids).' kg</p>';
        print '<p>🟡 <strong>En Tunnel :</strong> '.$obj->nb_tunnel.' plats — '.price($obj->poids_tunnel).' kg</p>';
        print '<p>🔴 <strong>En Carton :</strong> '.$obj->nb_carton.' plats — '.price($obj->poids_carton).' kg</p>';
        print '</div>';
    }
    print '</div>';

    // Affichage récapitulatif global en bas
    print '<div style="margin-top:25px;padding:15px;border-radius:10px;background: #74d0faff;border:1px solid #e0e6ea;">';
    print '<h3 style="margin:0 0 10px 0;">🔢 Totaux globaux</h3>';
    print '<div style="display:flex;gap:20px;flex-wrap:wrap;">';
    print '<div style="min-width:200px;padding:10px;background:#e8f7e8;border-radius:8px;">';
    print '<strong>🟢 Total général :</strong><br>';
    print $grand_total_plats.' plats<br>';
    print price($grand_total_poids).' kg';
    print '</div>';
    print '<div style="min-width:200px;padding:10px;background:#fff7e6;border-radius:8px;">';
    print '<strong>🟡 En Tunnel :</strong><br>';
    print $grand_tunnel_plats.' plats<br>';
    print price($grand_tunnel_poids).' kg';
    print '</div>';
    print '<div style="min-width:200px;padding:10px;background:#ffeaea;border-radius:8px;">';
    print '<strong>🔴 En Carton :</strong><br>';
    print $grand_carton_plats.' plats<br>';
    print price($grand_carton_poids).' kg';
    print '</div>';
    print '</div>';
    print '</div>';

} else {
    print '<p class="opacitymedium center" style="margin-top:20px;">Aucune donnée trouvée pour ce filtre.</p>';
}

llxFooter();
$db->close();
?>
