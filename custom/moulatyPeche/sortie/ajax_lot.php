<?php
require '../../../main.inc.php';
global $db, $user, $conf;

$product_id   = GETPOST('product_id', 'int');
$fk_entrepot  = GETPOST('fk_entrepot', 'int');
$fk_lot       = GETPOST('fk_lot', 'int'); // Paramètre optionnel

$result = array('nb_carton_dispo' => 0, 'poids_dispo' => 0);

if($product_id && $fk_entrepot) {
    
    // Construction de la requête SQL selon la présence du lot
    $sql = "SELECT COUNT(c.rowid) as nb_carton_total, SUM(c.poids) as poids_total
            FROM ".MAIN_DB_PREFIX."pech_carton c
            INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
            INNER JOIN ".MAIN_DB_PREFIX."pech_lot l ON l.rowid = ld.fk_lot";
    
    // Si un lot est spécifié, on ajoute la jointure avec la table lot
    if($fk_lot > 0) {
        $sql .= " WHERE c.fk_product = ".$product_id."
                  AND ld.fk_lot = ".$fk_lot;  // Filtre par lot spécifique
    } else {
        // Si pas de lot, on joint avec la table lot pour filtrer par entrepôt
        $sql .= " 
                  WHERE c.fk_product = ".$product_id."
                    AND l.fk_entrepot = ".$fk_entrepot;  // Filtre par entrepôt seulement
    }
    
    // Conditions communes
    $sql .= " AND c.statut = 0
              AND l.fk_bonentree is not null";
    
    $resql = $db->query($sql);
    if($resql && $obj = $db->fetch_object($resql)) {
        $result['nb_carton_dispo'] = $obj->nb_carton_total ? (int)$obj->nb_carton_total : 0;
        $result['poids_dispo']    = $obj->poids_total ? round($obj->poids_total, 3) : 0;
    }
}

header('Content-Type: application/json');
echo json_encode($result);
exit;
?>