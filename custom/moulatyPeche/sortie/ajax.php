<?php
require '../../../main.inc.php';
global $db, $user, $conf;

$product_id   = GETPOST('product_id', 'int');
$fk_entrepot  = GETPOST('fk_entrepot', 'int');

$result = array('nb_carton_dispo' => 0, 'poids_dispo' => 0);

if($product_id && $fk_entrepot) {

    // On joint les tables pour filtrer par entrepôt et produit
    $sql = "SELECT COUNT(c.rowid) as nb_carton_total, SUM(c.poids) as poids_total
            FROM ".MAIN_DB_PREFIX."pech_carton c
            INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
            INNER JOIN ".MAIN_DB_PREFIX."pech_lot l ON l.rowid = ld.fk_lot
            WHERE c.fk_product = ".$product_id."
              AND l.fk_entrepot = ".$fk_entrepot."
              AND c.statut = 0
              AND fk_bonentree is not null";
              

    $resql = $db->query($sql);
    if($resql && $obj = $db->fetch_object($resql)) {
        $result['nb_carton_dispo'] = $obj->nb_carton_total ? $obj->nb_carton_total : 0;
        $result['poids_dispo']    = $obj->poids_total ? $obj->poids_total : 0;
    }
}

echo json_encode($result);
