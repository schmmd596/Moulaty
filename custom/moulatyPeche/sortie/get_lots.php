<?php
/**
 * Récupère les lots disponibles pour un produit dans un entrepôt
 */

require '../../../main.inc.php';
global $db, $user, $conf;

$product_id = GETPOST('product_id', 'int');
$fk_entrepot = GETPOST('fk_entrepot', 'int');

$result = array('lots' => array());

if($product_id && $fk_entrepot) {
    $sql = "SELECT DISTINCT l.rowid as id, l.ref, l.date_creation
            FROM ".MAIN_DB_PREFIX."pech_lot l
            INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.fk_lot = l.rowid
            INNER JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.fk_lotdet = ld.rowid
            WHERE c.fk_product = ".$product_id."
              AND l.fk_entrepot = ".$fk_entrepot."
              AND c.statut = 0
              AND l.fk_bonentree is not null
            ORDER BY l.date_creation DESC";
    
    $resql = $db->query($sql);
    if($resql) {
        while($obj = $db->fetch_object($resql)) {
            $result['lots'][] = array(
                'id' => $obj->id,
                'ref' => $obj->ref,
                'date' => $obj->date_creation
                
            );
        }
    }
}

header('Content-Type: application/json');
echo json_encode($result);
exit;