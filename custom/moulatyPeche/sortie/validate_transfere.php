<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

global $db, $user;

$id = GETPOST('id', 'int');

if (!$id) {
    setEventMessages("ID de la sortie manquant.", null, 'errors');
    header("Location: detail.php");
    exit;
}

$db->begin();

try {
    // 1️⃣ Récupération de la sortie
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid = ".((int)$id);
    $res = $db->query($sql);
    $sortie = $db->fetch_object($res);

    if (!$sortie) throw new Exception("Sortie introuvable.");
    if ($sortie->statut != 1) throw new Exception("La sortie n'est pas transferable.");

    // 2️⃣ Récupération des lignes produits
    // 2️⃣ Récupération des lignes produits
$sqlProd = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortiedetprod WHERE fk_sortie = ".((int)$id);
$resProd = $db->query($sqlProd);
$produits = array();
$total_cartons = 0;

if ($resProd && $db->num_rows($resProd) > 0) {
    while ($objProd = $db->fetch_object($resProd)) {
        $produits[] = $objProd;
        $total_cartons += (int)$objProd->nb_carton;
    }
} else {
    throw new Exception("Aucun produit dans la sortie.");
}

// 3️⃣ Calcul frais par carton
$total_frais = (float)$sortie->total_frais;
$frais_par_carton = $total_cartons > 0 ? $total_frais / $total_cartons : 0;

    // 4️⃣ Création du nouveau lot
    $resLast = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot ORDER BY rowid DESC LIMIT 1");
    $ref1 = '';
    if ($resLast && $db->num_rows($resLast) > 0) {
        $obj2 = $db->fetch_object($resLast);
        $ref1 = $obj2->ref;
    }
    $nextNumber = (!empty($ref1) && preg_match('/Lot-(\d+)/', $ref1, $m)) ? ((int)$m[1] + 1) : 1;
    $refLot = 'Lot-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

    $sqlLot = "INSERT INTO ".MAIN_DB_PREFIX."pech_lot (ref, fk_user_create, fk_entrepot, commentaire, statut, entity)
               VALUES ('".$db->escape($refLot)."', ".((int)$user->id).", ".((int)$sortie->fk_entrepot_dest).",
               'Créé automatiquement depuis la sortie #".$sortie->ref."', 1, 1)";
    $db->query($sqlLot);
    $lot_id = $db->last_insert_id(MAIN_DB_PREFIX.'pech_lot');



    // 5️⃣ Pour chaque produit
foreach ($produits as $objProd) {

    // 5.1 Création du détail du lot
    $poids_carton = ((float)$objProd->poids_total) / max((int)$objProd->nb_carton, 1);
    $sqlLotDet = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet 
                  (fk_lot, fk_product, fk_misenplat, poids_carton, nb_carton, statut, entity)
                  VALUES (".((int)$lot_id).", ".((int)$objProd->fk_product).", 0,
                  ".$poids_carton.", ".((int)$objProd->nb_carton).", 1, 1)";
    $db->query($sqlLotDet);
    $lotdet_id = $db->last_insert_id(MAIN_DB_PREFIX."pech_lotdet");

    // 5.2 Transfert des cartons liés
    $sqlCarton = "SELECT c.*, sc.fk_carton AS old_carton_id
                  FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton sc
                  LEFT JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.rowid = sc.fk_carton
                  WHERE sc.fk_sortiedetprod = ".((int)$objProd->rowid);
    $resCarton = $db->query($sqlCarton);

    if ($resCarton && $db->num_rows($resCarton) > 0) {
        while ($objCarton = $db->fetch_object($resCarton)) {
            $prix_total_carton = (float)$objCarton->prix_moyen + (float)$objCarton->frais + $frais_par_carton;

            $sqlInsertCarton = "INSERT INTO ".MAIN_DB_PREFIX."pech_carton 
                (fk_lotdet, fk_product, poids, nb_plat, statut, fk_user_create, commentaire, prix_moyen, frais)
                VALUES (".((int)$lotdet_id).", ".((int)$objProd->fk_product).", ".((float)$objCarton->poids).", 
                ".((int)$objCarton->nb_plat).", 0, ".((int)$user->id).", 'Carton transféré avec frais', 
                ".$prix_total_carton.", 0)";
            $db->query($sqlInsertCarton);
        }
    } /*else {
        // Créer un carton global si aucun carton
        $prix_total_carton = $frais_par_carton + ((float)$objProd->poids_total); 
        $sqlInsertCarton = "INSERT INTO ".MAIN_DB_PREFIX."pech_carton 
            (fk_lotdet, fk_product, poids, nb_plat, statut, fk_user_create, commentaire, prix_moyen, frais)
            VALUES (".((int)$lotdet_id).", ".((int)$objProd->fk_product).", ".((float)$objProd->poids_total).",
            0, 0, ".((int)$user->id).", 'Carton global avec frais', ".$prix_total_carton.", ".$frais_par_carton.")";
        $db->query($sqlInsertCarton);
    }*/

    // 5.3 Mouvement de stock
    $product = new Product($db);
    $product->fetch($objProd->fk_product);

    $product->correct_stock($user, $sortie->fk_entrepot_source, -$objProd->poids_total, 0,
        'Transfert interne - Sortie #'.$sortie->ref);
    $product->correct_stock($user, $sortie->fk_entrepot_dest, $objProd->poids_total, 0,
        'Transfert interne - Entrée lot #'.$refLot);
}

      $db->query("UPDATE ".MAIN_DB_PREFIX."pech_sortie SET statut = 2 WHERE rowid = ".((int)$id));


    $db->commit();
    setEventMessages("Sortie validée et transférée avec succès vers l'entrepôt de destination.", null, 'mesgs');
    header("Location: detail.php?id=".$id);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("Erreur : ".$e->getMessage(), null, 'errors');
    header("Location: detail.php?id=".$id);
    exit;
}
?>
