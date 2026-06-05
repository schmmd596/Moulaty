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

if (empty($user->rights->moulatyPeche->read_r)) {
    accessforbidden('Accès réservé à l’administrateur.');
}

$db->begin();

try {
    // 1️⃣ Récupération de la sortie
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid = ".((int)$id);
    $res = $db->query($sql);
    $sortie = $db->fetch_object($res);

    if (!$sortie) throw new Exception("Sortie introuvable.");
    if ($sortie->statut != 0) throw new Exception("La sortie n'est plus modifiable.");
    //if ($sortie->type != 1) throw new Exception("Ce script ne traite que les transferts internes.");

    // 2️⃣ Lignes produits
    $sqlProd = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortiedetprod WHERE fk_sortie = ".((int)$id);
    $resProd = $db->query($sqlProd);
    if ($db->num_rows($resProd) == 0) throw new Exception("Aucun produit dans la sortie.");

    // 3️⃣ Création du nouveau lot
    $resLast = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot ORDER BY rowid DESC LIMIT 1");
    $ref1 = '';
    if ($resLast && $db->num_rows($resLast) > 0) {
        $obj2 = $db->fetch_object($resLast);
        $ref1 = $obj2->ref;
    }
    $nextNumber = (!empty($ref1) && preg_match('/Lot-(\d+)/', $ref1, $m)) ? ((int)$m[1] + 1) : 1;
    $refLot = 'Lot-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

    /*$sqlLot = "INSERT INTO ".MAIN_DB_PREFIX."pech_lot (ref, fk_user_create, fk_entrepot, commentaire, statut, entity)
               VALUES ('".$db->escape($refLot)."', ".((int)$user->id).", ".((int)$sortie->fk_entrepot_dest).",
               'Créé automatiquement depuis la sortie #".$sortie->ref."', 1, 1)"; // statut = 0 pour en stock
    $db->query($sqlLot);
    $lot_id = $db->last_insert_id(MAIN_DB_PREFIX.'pech_lot');*/

    // 4️⃣ Pour chaque produit
    while ($objProd = $db->fetch_object($resProd)) {

        // 4.1 Détail du lot
        /*$sqlLotDet = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet (fk_lot, fk_product, fk_misenplat, poids_carton, nb_carton, statut, entity)
                      VALUES (".((int)$lot_id).", ".((int)$objProd->fk_product).", 0,
                      ".((float)$objProd->poids_total) / (int)$objProd->nb_carton.", ".((int)$objProd->nb_carton).", 1, 1)"; // statut = 0
        $db->query($sqlLotDet);
        $lotdet_id = $db->last_insert_id('".MAIN_DB_PREFIX."pech_lotdet');

        // 4.2 Transfert des cartons liés*/
        $sqlCarton = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton WHERE fk_sortiedetprod = ".((int)$objProd->rowid);
        $resCarton = $db->query($sqlCarton);
        if ($db->num_rows($resCarton) > 0) {
            /*while ($objCarton = $db->fetch_object($resCarton)) {
                // Nouveau carton pour le lot : statut = 0 (en stock)
                $sqlInsertCarton = "INSERT INTO ".MAIN_DB_PREFIX."pech_carton 
                    (fk_lotdet, fk_product, poids, nb_plat, statut, fk_user_create, commentaire)
                    VALUES (".((int)$lotdet_id).", ".((int)$objProd->fk_product).", ".((float)$objCarton->poids).", 
                    0, 0, ".((int)$user->id).", 'Carton transféré automatiquement')";
                $db->query($sqlInsertCarton);
            }*/

            // Mise à jour des anciens cartons : statut = 1 (sortie)
            $db->query("UPDATE ".MAIN_DB_PREFIX."pech_carton SET statut = 1 WHERE rowid IN (
                SELECT fk_carton FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton WHERE fk_sortiedetprod = ".((int)$objProd->rowid)."
            )");
        } else {
            // Si aucun carton n'existe dans la sortie, on crée un carton global
            $sqlInsertCarton = "INSERT INTO ".MAIN_DB_PREFIX."pech_carton 
                (fk_lotdet, fk_product, poids, nb_plat, statut, fk_user_create, commentaire)
                VALUES (".((int)$lotdet_id).", ".((int)$objProd->fk_product).", ".((float)$objProd->poids_total).",
                0, 0, ".((int)$user->id).", 'Carton global créé automatiquement')";
            $db->query($sqlInsertCarton);
        }

        // 4.3 Mouvement de stock
        /*$product = new Product($db);
        $product->fetch($objProd->fk_product);

        $product->correct_stock($user, $sortie->fk_entrepot_source, -$objProd->poids_total, 0,
            'Transfert interne - Sortie #'.$sortie->ref);
        $product->correct_stock($user, $sortie->fk_entrepot_dest, $objProd->poids_total, 0,
            'Transfert interne - Entrée lot #'.$refLot);*/
    }

    // 5️⃣ Validation de la sortie
    $db->query("UPDATE ".MAIN_DB_PREFIX."pech_sortie SET statut = 1 WHERE rowid = ".((int)$id));

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
