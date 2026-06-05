<?php
require '../../../main.inc.php';
header('Content-Type: application/json');

$id = GETPOST('id', 'int');
$response = ['success' => false];

if($id > 0) {
    $sql = "SELECT ef.entrepot, e.ref AS entrepot_label
            FROM ".MAIN_DB_PREFIX."societe_extrafields AS ef
            LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = ef.entrepot
            WHERE ef.fk_object = ".((int)$id);

    $resql = $db->query($sql);
    if($resql && $obj = $db->fetch_object($resql)) {
        if(!empty($obj->entrepot)) {
            $response['success'] = true;
            $response['entrepot_id'] = $obj->entrepot;
            $response['entrepot_label'] = $obj->entrepot_label;
        }
    }
}

echo json_encode($response);
exit;
