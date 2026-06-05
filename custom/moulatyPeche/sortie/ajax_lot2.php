<?php
require '../../../main.inc.php';
global $db, $user, $conf;

// Récupération des paramètres
$product_id = GETPOST('product_id', 'int');
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$fk_lot = GETPOST('fk_lot', 'int'); // Paramètre optionnel
$mode = GETPOST('mode', 'alpha'); // 'carton' ou 'quantite'
$nb_carton = GETPOST('nb_carton', 'int');
$poids = GETPOST('poids', 'float');

// Initialisation
$result = array(
    'nb_carton_calc' => 0,
    'poids_calc' => 0,
    'cartons_rowid' => array()
);

if(empty($product_id) || empty($fk_entrepot)) {
    echo json_encode($result);
    exit;
}

// --- Construction de la requête selon la présence du lot ---
$cartons = array();
$sql = "SELECT c.rowid, c.poids
        FROM ".MAIN_DB_PREFIX."pech_carton AS c
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet AS ld ON c.fk_lotdet = ld.rowid
        INNER JOIN ".MAIN_DB_PREFIX."pech_lot AS l ON ld.fk_lot = l.rowid";

// Si un lot est spécifié
if($fk_lot > 0) {
    $sql .= " WHERE c.fk_product = ".$product_id." 
              AND ld.fk_lot = ".$fk_lot;  // Filtre par lot spécifique
} else {
    // Si pas de lot, filtrer par entrepôt
    $sql .= " WHERE c.fk_product = ".$product_id." 
                AND l.fk_entrepot = ".$fk_entrepot;  // Filtre par entrepôt seulement
}

// Conditions communes
$sql .= " AND c.statut = 0
          AND l.fk_bonentree is not null
        ORDER BY c.date_creation ASC";  // FIFO

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
    
    // Prendre les X premiers cartons (FIFO)
    for($i = 0; $i < $nb_carton && $i < $total_cartons; $i++) {
        $poids_calc += $cartons[$i]['poids'];
        $result['cartons_rowid'][] = $cartons[$i]['rowid'];
    }
    
    $result['poids_calc'] = round($poids_calc, 3);
    $result['nb_carton_calc'] = min($nb_carton, $total_cartons);

} elseif($mode == 'quantite' && $poids > 0) {
    $poids_restant = $poids;
    $nb_carton_calc = 0;
    
    // Prendre les cartons nécessaires pour atteindre le poids demandé
    foreach($cartons as $c) {
        if($poids_restant <= 0) break;
        
        $nb_carton_calc++;
        $poids_restant -= $c['poids'];
        $result['cartons_rowid'][] = $c['rowid'];
    }
    
    $result['nb_carton_calc'] = $nb_carton_calc;
    $result['poids_calc'] = $poids - max(0, $poids_restant); // Poids réellement pris
}

header('Content-Type: application/json');
echo json_encode($result);
exit;
?>