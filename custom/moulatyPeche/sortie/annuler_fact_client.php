<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

global $db, $user;

$id_sortie = GETPOST('id', 'int');

if (!$id_sortie) {
    setEventMessages("ID de la sortie manquant.", null, 'errors');
    header("Location: detail.php");
    exit;
}

if (empty($user->admin)) {
    accessforbidden('Accès réservé à l\'administrateur.');
}

$db->begin();

try {
    // 1️⃣ Récupération de la sortie
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid = ".((int)$id_sortie);
    $res = $db->query($sql);
    $sortie = $db->fetch_object($res);

    if (!$sortie) throw new Exception("Sortie introuvable.");
    
    // 2️⃣ Vérifier si une facture client est liée
    if (empty($sortie->fk_facture_client)) {
        throw new Exception("Aucune facture client n'est liée à cette sortie.");
    }

    // 3️⃣ Récupérer et vérifier la facture
    $facture = new Facture($db);
    $result = $facture->fetch($sortie->fk_facture_client);
    
    if (!$result) {
        throw new Exception("Facture introuvable (ID: ".$sortie->fk_facture_client.").");
    }

    // 4️⃣ Vérifier le statut de la facture
    if ($facture->statut == Facture::STATUS_VALIDATED) {
        throw new Exception("Impossible d'annuler : la facture est déjà validée.");
    }

   

    // 5️⃣ Supprimer la facture
    $result = $facture->delete($user);
    if ($result <= 0) {
        throw new Exception("Erreur lors de la suppression de la facture : " . $facture->error);
    }

    // 6️⃣ Mettre à jour la sortie
    $sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."pech_sortie 
                  SET fk_facture_client = NULL, 
                      statut = 1 
                  WHERE rowid = ".((int)$id_sortie);
    
    $db->query($sqlUpdate);

    $db->commit();
    setEventMessages("Facturation annulée avec succès. La facture a été supprimée et la sortie est revenue au statut validé.", null, 'mesgs');
    header("Location: detail.php?id=".$id_sortie);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("Erreur : ".$e->getMessage(), null, 'errors');
    header("Location: detail.php?id=".$id_sortie);
    exit;
}
?>