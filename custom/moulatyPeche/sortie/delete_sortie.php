<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

global $db, $user;

if (empty($user->id)) accessforbidden();

// 🔹 Récupération de l'ID du bon à supprimer
$id_sortie = GETPOST('id', 'int');

if ($id_sortie <= 0) {
    setEventMessages("❌ ID de sortie invalide.", null, 'errors');
    header("Location: list.php");
    exit;
}

$db->begin();

try {
    // Vérifier l'existence et le statut du bon
    $sql_check = "SELECT rowid, statut FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid = ".$id_sortie;
    $res_check = $db->query($sql_check);

    if (!$res_check || $db->num_rows($res_check) == 0) {
        throw new Exception("Bon de sortie introuvable.");
    }

    $obj = $db->fetch_object($res_check);

    // Autoriser la suppression uniquement si le statut = 0 (brouillon)
    if ((int)$obj->statut !== 0) {
        throw new Exception("Impossible de supprimer un bon validé ou livré.");
    }

    // Suppression des produits enfants
    $sql_del_prod = "DELETE FROM ".MAIN_DB_PREFIX."pech_sortiedetprod WHERE fk_sortie = ".$id_sortie;
    if (!$db->query($sql_del_prod)) {
        throw new Exception("Erreur suppression produits : ".$db->lasterror());
    }

    // Suppression des services enfants
    $sql_del_serv = "DELETE FROM ".MAIN_DB_PREFIX."pech_sortiedetservice WHERE fk_sortie = ".$id_sortie;
    if (!$db->query($sql_del_serv)) {
        throw new Exception("Erreur suppression services : ".$db->lasterror());
    }

    // Suppression du bon principal
    $sql_del_sortie = "DELETE FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid = ".$id_sortie;
    if (!$db->query($sql_del_sortie)) {
        throw new Exception("Erreur suppression du bon principal : ".$db->lasterror());
    }

    $db->commit();

    setEventMessages("✅ Bon de sortie supprimé avec succès.", null, 'mesgs');
    header("Location: list.php");
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("❌ Erreur lors de la suppression : ".$e->getMessage(), null, 'errors');
    header("Location: detail.php?id=".$id_sortie);
    exit;
}
?>
