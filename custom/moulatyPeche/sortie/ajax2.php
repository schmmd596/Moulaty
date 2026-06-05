<?php
require '../../../main.inc.php';
global $db, $user, $conf;

// Récupération des paramètres
$product_id = GETPOST('product_id', 'int');
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$mode = GETPOST('mode', 'alpha'); // 'carton' ou 'quantite'
$nb_carton = GETPOST('nb_carton', 'int');
$poids = GETPOST('poids', 'float');

// Initialisation
$result = array(
    'nb_carton_calc' => 0,
    'poids_calc' => 0,
    'cartons_rowid' => array() // <-- ajout pour stocker les rowid
);

if(empty($product_id) || empty($fk_entrepot)) {
    echo json_encode($result);
    exit;
}

// --- Récupération des cartons disponibles les plus anciens dans cet entrepôt ---
$cartons = array();
$sql = "SELECT c.rowid, c.poids
        FROM ".MAIN_DB_PREFIX."pech_carton AS c
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet AS ld ON c.fk_lotdet = ld.rowid
        INNER JOIN ".MAIN_DB_PREFIX."pech_lot AS l ON ld.fk_lot = l.rowid
        WHERE c.fk_product = ".$product_id." 
          AND l.fk_entrepot = ".$fk_entrepot." 
          AND c.statut = 0
          AND fk_bonentree is not null
        ORDER BY c.date_creation ASC";
$resql = $db->query($sql);
if($resql) {
    while($obj = $db->fetch_object($resql)) {
        $cartons[] = array(
            'rowid' => $obj->rowid,
            'poids' => $obj->poids
        );
    }
}

$total_cartons = count($cartons);

if($mode == 'carton' && $nb_carton > 0) {
    $poids_calc = 0;
    for($i=0; $i<$nb_carton && $i<$total_cartons; $i++) {
        $poids_calc += $cartons[$i]['poids'];
        $result['cartons_rowid'][] = $cartons[$i]['rowid']; // <-- on ajoute le rowid
    }
    $result['poids_calc'] = round($poids_calc, 3);
    $result['nb_carton_calc'] = min($nb_carton, $total_cartons);

} elseif($mode == 'quantite' && $poids > 0) {
    $poids_restant = $poids;
    $nb_carton_calc = 0;
    foreach($cartons as $c) {
        if($poids_restant <= 0) break;
        $nb_carton_calc++;
        $poids_restant -= $c['poids'];
        $result['cartons_rowid'][] = $c['rowid']; // <-- on ajoute le rowid
    }
    $result['nb_carton_calc'] = $nb_carton_calc;
    $result['poids_calc'] = $poids;
}

echo json_encode($result);
exit;
