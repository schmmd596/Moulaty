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

if (empty($user->admin)) {
    accessforbidden('Accès réservé à l\'administrateur.');
}


// 1️⃣ Récupération de la sortie
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid = ".((int)$id);
    $res = $db->query($sql);
    $sortie = $db->fetch_object($res);

    if (!$sortie) throw new Exception("Sortie introuvable.");
    if ($sortie->statut != 1){
        //throw new Exception("Impossible de remettre en brouillon : cette sortie a déjà ".$objFact->nb_factures." facture(s) liée(s).");
         setEventMessages("La sortie doit être validée pour être annulée.", null, 'errors');
    header("Location: detail.php?id=".$id);
    exit;
    } //throw new Exception("La sortie doit être validée pour être annulée.");
    
    // 2️⃣ Vérifier s'il existe des factures liées à cette sortie
    $sqlFact = "SELECT COUNT(*) as nb_factures 
                FROM ".MAIN_DB_PREFIX."facture_fourn 
                WHERE ref_supplier LIKE '".$db->escape($sortie->ref)."%'";
    
    $resFact = $db->query($sqlFact);
    $objFact = $db->fetch_object($resFact);
    
    if ($objFact && $objFact->nb_factures > 0) {
        //throw new Exception("Impossible de remettre en brouillon : cette sortie a déjà ".$objFact->nb_factures." facture(s) liée(s).");
         setEventMessages("Impossible de remettre en brouillon : cette sortie a déjà ".$objFact->nb_factures." facture(s) liée(s).", null, 'errors');
    header("Location: detail.php?id=".$id);
    exit;
    }

$db->begin();

try {
    // 1️⃣ Récupération de la sortie
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid = ".((int)$id);
    $res = $db->query($sql);
    $sortie = $db->fetch_object($res);

    if (!$sortie) throw new Exception("Sortie introuvable.");
    if ($sortie->statut != 1) throw new Exception("La sortie doit être validée pour être annulée.");

    // 2️⃣ Récupérer tous les produits de la sortie
    $sqlProd = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortiedetprod WHERE fk_sortie = ".((int)$id);
    $resProd = $db->query($sqlProd);
    if ($db->num_rows($resProd) == 0) throw new Exception("Aucun produit dans la sortie.");

    // 3️⃣ Pour chaque produit, restaurer les cartons
    while ($objProd = $db->fetch_object($resProd)) {
        // Récupérer les cartons associés à cette ligne produit
        $sqlCarton = "SELECT sdc.* 
                      FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton sdc
                      WHERE sdc.fk_sortiedetprod = ".((int)$objProd->rowid);
        $resCarton = $db->query($sqlCarton);
        
        if ($db->num_rows($resCarton) > 0) {
            // Pour chaque carton de la sortie, remettre en stock (statut = 0)
            $db->query("UPDATE ".MAIN_DB_PREFIX."pech_carton SET statut = 0 WHERE rowid IN (
                SELECT fk_carton FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton WHERE fk_sortiedetprod = ".((int)$objProd->rowid)."
            )");
        } else {
            // Si un carton global avait été créé, le supprimer
            $db->query("DELETE FROM ".MAIN_DB_PREFIX."pech_carton 
                        WHERE fk_lotdet IS NULL 
                        AND fk_product = ".((int)$objProd->fk_product)."
                        AND poids = ".((float)$objProd->poids_total)."
                        AND commentaire LIKE 'Carton global créé automatiquement%'");
        }
    }

    // 4️⃣ Mettre la sortie en statut brouillon (0)
    $db->query("UPDATE ".MAIN_DB_PREFIX."pech_sortie SET statut = 0,
                                                fk_facture = NULL,
                                                fk_bonsortie = NULL,
                                                fk_facture_client = NULL,
                                                total_frais = 0
                WHERE rowid = ".((int)$id));

    $db->commit();
    setEventMessages("Sortie remise en brouillon avec succès. Les cartons ont été remis en stock.", null, 'mesgs');
    header("Location: detail.php?id=".$id);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("Erreur : ".$e->getMessage(), null, 'errors');
    header("Location: detail.php?id=".$id);
    exit;
}
?>