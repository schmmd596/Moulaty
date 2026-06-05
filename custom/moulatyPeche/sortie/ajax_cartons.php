<?php
require '../../../main.inc.php';
global $db, $conf;

$product_id = GETPOST('product_id', 'int');
$fk_entrepot = GETPOST('fk_entrepot', 'int');

$cartons = [];

if ($product_id > 0 && $fk_entrepot > 0) {
    // Requête : on récupère les cartons valides du produit dans l’entrepôt
    $sql = "SELECT c.rowid, c.ref_carton, c.poids, c.date_creation
            FROM ".MAIN_DB_PREFIX."pech_carton AS c
            INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet AS ld ON c.fk_lotdet = ld.rowid
            INNER JOIN ".MAIN_DB_PREFIX."pech_lot AS l ON ld.fk_lot = l.rowid
            WHERE c.fk_product = ".((int)$product_id)."
              AND l.fk_entrepot = ".((int)$fk_entrepot)."
              AND c.statut = 0
              AND c.entity = ".$conf->entity;

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $cartons[] = [
                'rowid' => $obj->rowid,
                'ref_carton' => $obj->date_creation,
                'poids' => $obj->poids
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($cartons);
