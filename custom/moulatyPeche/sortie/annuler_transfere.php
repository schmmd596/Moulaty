<?php
/**
 * Annulation d'un transfert interne
 * Remet la sortie au statut "Validé" (1) après avoir :
 *  - Supprimé le lot créé automatiquement dans l'entrepôt de destination
 *  - Inversé les mouvements de stock
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

global $db, $user;

$id = GETPOST('id', 'int');

if (!$id) {
    setEventMessages("ID de la sortie manquant.", null, 'errors');
    header("Location: detail.php");
    exit;
}

// Seul un administrateur peut annuler un transfert
if (empty($user->admin)) {
    accessforbidden('Accès réservé à l\'administrateur.');
}

// ============================================================================
// 1️⃣ Récupération de la sortie
// ============================================================================
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid = ".((int)$id);
$res = $db->query($sql);
$sortie = $db->fetch_object($res);

if (!$sortie) {
    setEventMessages("Sortie introuvable.", null, 'errors');
    header("Location: detail.php?id=".$id);
    exit;
}

// Vérifier que c'est un transfert interne avec statut = 2 (transféré)
if ($sortie->type != 0) {
    setEventMessages("Cette opération est uniquement disponible pour les transferts internes.", null, 'errors');
    header("Location: detail.php?id=".$id);
    exit;
}

if ($sortie->statut != 2) {
    setEventMessages("La sortie doit être au statut 'Transféré' pour pouvoir annuler le transfert.", null, 'errors');
    header("Location: detail.php?id=".$id);
    exit;
}

// ============================================================================
// 2️⃣ Rechercher le lot créé automatiquement depuis cette sortie
// ============================================================================
$sql_lot = "SELECT rowid FROM ".MAIN_DB_PREFIX."pech_lot 
            WHERE commentaire LIKE 'Créé automatiquement depuis la sortie #".$db->escape($sortie->ref)."'
            AND fk_entrepot = ".((int)$sortie->fk_entrepot_dest)."
            LIMIT 1";
$res_lot = $db->query($sql_lot);
$lot = ($res_lot && $db->num_rows($res_lot) > 0) ? $db->fetch_object($res_lot) : null;

// ============================================================================
// 3️⃣ Vérifier si les cartons du lot ont déjà été utilisés
// ============================================================================
if ($lot) {
    $sql_check = "SELECT COUNT(*) as nb 
                  FROM ".MAIN_DB_PREFIX."pech_carton c
                  INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
                  WHERE ld.fk_lot = ".((int)$lot->rowid)."
                  AND c.statut != 0";
    $res_check = $db->query($sql_check);
    $obj_check = $db->fetch_object($res_check);

    if ($obj_check && $obj_check->nb > 0) {
        setEventMessages("Impossible d'annuler le transfert : ".$obj_check->nb." carton(s) du lot de destination ont déjà été utilisés (vendus ou transférés).", null, 'errors');
        header("Location: detail.php?id=".$id);
        exit;
    }
}

// ============================================================================
// 4️⃣ Début de la transaction
// ============================================================================
$db->begin();

try {
    // 4.1 Récupérer les produits de la sortie pour inverser les stocks
    $sql_prods = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortiedetprod WHERE fk_sortie = ".((int)$id);
    $res_prods = $db->query($sql_prods);
    if (!$res_prods || $db->num_rows($res_prods) == 0) {
        throw new Exception("Aucun produit trouvé dans cette sortie.");
    }

    $produits = [];
    while ($objProd = $db->fetch_object($res_prods)) {
        $produits[] = $objProd;
    }

    // 4.2 Supprimer le lot et ses données associées si trouvé
    if ($lot) {
        $lot_id = (int)$lot->rowid;

        // Récupérer les lotdet pour les supprimer
        $sql_lotdet = "SELECT rowid FROM ".MAIN_DB_PREFIX."pech_lotdet WHERE fk_lot = ".$lot_id;
        $res_lotdet = $db->query($sql_lotdet);
        $lotdet_ids = [];
        if ($res_lotdet) {
            while ($ld = $db->fetch_object($res_lotdet)) {
                $lotdet_ids[] = (int)$ld->rowid;
            }
        }

        if (!empty($lotdet_ids)) {
            // Supprimer les cartons liés à ces lotdet
            $in_str = implode(',', $lotdet_ids);
            $sql_del_cartons = "DELETE FROM ".MAIN_DB_PREFIX."pech_carton WHERE fk_lotdet IN (".$in_str.")";
            if (!$db->query($sql_del_cartons)) {
                throw new Exception("Erreur suppression cartons : ".$db->lasterror());
            }

            // Supprimer les détails du lot
            $sql_del_lotdet = "DELETE FROM ".MAIN_DB_PREFIX."pech_lotdet WHERE fk_lot = ".$lot_id;
            if (!$db->query($sql_del_lotdet)) {
                throw new Exception("Erreur suppression détails lot : ".$db->lasterror());
            }
        }

        // Supprimer le lot lui-même
        $sql_del_lot = "DELETE FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".$lot_id;
        if (!$db->query($sql_del_lot)) {
            throw new Exception("Erreur suppression lot : ".$db->lasterror());
        }
    }

    // 4.3 Inverser les mouvements de stock pour chaque produit
    foreach ($produits as $objProd) {
        $product = new Product($db);
        $product->fetch($objProd->fk_product);

        // Retirer le stock de l'entrepôt de destination
        $product->correct_stock(
            $user,
            $sortie->fk_entrepot_dest,
            -$objProd->poids_total,
            0,
            'Annulation transfert interne - Sortie #'.$sortie->ref,
            $product->pmp
        );

        // Remettre le stock dans l'entrepôt source
        $entrepot_src = !empty($objProd->fk_entrepot) ? $objProd->fk_entrepot : $sortie->fk_entrepot_source;
        $product->correct_stock(
            $user,
            $entrepot_src,
            $objProd->poids_total,
            0,
            'Annulation transfert interne - Retour entrée #'.$sortie->ref,
            $product->pmp
        );
    }

    // 4.4 Mettre à jour le statut de la sortie à 1 (Validé)
    $sql_upd = "UPDATE ".MAIN_DB_PREFIX."pech_sortie 
                SET statut = 1,
                    fk_bonsortie = NULL,
                    total_frais = 0
                WHERE rowid = ".((int)$id);
    if (!$db->query($sql_upd)) {
        throw new Exception("Erreur mise à jour statut sortie : ".$db->lasterror());
    }

    $db->commit();
    setEventMessages("✅ Transfert annulé avec succès. La sortie est revenue au statut 'Validé'. Vous pouvez maintenant la remettre en brouillon si nécessaire.", null, 'mesgs');
    header("Location: detail.php?id=".$id);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("❌ Erreur : ".$e->getMessage(), null, 'errors');
    header("Location: detail.php?id=".$id);
    exit;
}
?>
