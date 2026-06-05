<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php'; // si tu veux créer une facture automatique

global $db, $user, $langs;

$id = GETPOST('id', 'int');

if (!$id) {
    setEventMessages("ID de la sortie manquant.", null, 'errors');
    header("Location: detail.php");
    exit;
}

$db->begin();

try {
    // 1️⃣ Récupération de la sortie
    $sql = "SELECT * FROM llx_pech_sortie WHERE rowid = ".((int)$id);
    $res = $db->query($sql);
    $sortie = $db->fetch_object($res);

    if (!$sortie) throw new Exception("Sortie introuvable.");
    if ($sortie->statut != 0) throw new Exception("La sortie n'est plus modifiable.");
    if ($sortie->type != 1) throw new Exception("Ce script ne traite que les ventes.");

    // 2️⃣ Vérification des lignes produits
    $sqlProd = "SELECT * FROM llx_pech_sortiedetprod WHERE fk_sortie = ".((int)$id);
    $resProd = $db->query($sqlProd);
    if ($db->num_rows($resProd) == 0) throw new Exception("Aucun produit dans la vente.");

    // 3️⃣ Vérification du client
    if (empty($sortie->fk_client)) throw new Exception("Aucun client associé à cette vente.");
    $client_name = '';
    $soc = new Societe($db);
    $soc->fetch($sortie->fk_client);
    $client_name = $soc->name;
    
        // 4️⃣ Traitement de chaque ligne produit
    while ($objProd = $db->fetch_object($resProd)) {

        // 4.1 Sortie de stock (décrémentation)
        $product = new Product($db);
        $product->fetch($objProd->fk_product);



        $product->correct_stock(
            $user,
            $sortie->fk_entrepot_source,
            -$objProd->poids_total,
            0,
            'Vente client #'.$client_name.' - Sortie #'.$sortie->ref
        );

        // 4.2 Mise à jour des cartons liés (statut = 1 = sorti/vendu)
        $db->query("UPDATE llx_pech_carton SET statut = 1 WHERE rowid IN (
            SELECT fk_carton FROM llx_pech_sortiedetcarton WHERE fk_sortiedetprod = ".((int)$objProd->rowid)."
        )");
    }

    // 5️⃣ Gestion des services additionnels
    $sqlService = "SELECT * FROM llx_pech_sortiedetservice WHERE fk_sortie = ".((int)$id);
    $resService = $db->query($sqlService);
    if ($resService && $db->num_rows($resService) > 0) {
        while ($objService = $db->fetch_object($resService)) {
            // Tu peux ici loguer les services ou les inclure dans la facture si nécessaire
            // Exemple : insertion dans facture fournisseur ou client
        }
    }

    // 6️⃣ Validation de la sortie
    $db->query("UPDATE llx_pech_sortie SET statut = 1 WHERE rowid = ".((int)$id));

    $db->commit();

    setEventMessages("Vente validée avec succès. Les stocks ont été mis à jour.", null, 'mesgs');
    header("Location: detail.php?id=".$id);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("Erreur lors de la validation : ".$e->getMessage(), null, 'errors');
    header("Location: detail.php?id=".$id);
    exit;
}
?>
