<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

global $db, $user, $langs;

$langs->load("reception");

// Récupération de l'ID et du token
$id = GETPOST('id', 'int');
//$token = GETPOST('token', 'alpha');

//if (!$user->rights->pech->reception->delete) accessforbidden(); // Vérifie les droits

// Vérification du token pour la sécurité


// Vérification que la réception existe
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_reception WHERE rowid = ".(int)$id;
$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) {
    setEventMessages($langs->trans("ReceptionNotFound"), null, 'errors');
    header("Location: ./list.php");
    exit;
}

$rec = $db->fetch_object($resql);

// On n'autorise la suppression que si l'état = 0 (en attente)
if ($rec->etat != 0) {
    setEventMessages($langs->trans("CannotDeleteValidatedReception"), null, 'errors');
    header("Location: ./list.php");
    exit;
}

// Début transaction
$db->begin();

try {
    // Suppression des lignes de réception
    $sql_det = "DELETE FROM ".MAIN_DB_PREFIX."pech_receptiondet WHERE fk_reception = ".(int)$id;
    $res_det = $db->query($sql_det);
    if (!$res_det) throw new Exception($db->lasterror());

    // Suppression de la réception principale
    $sql_rec = "DELETE FROM ".MAIN_DB_PREFIX."pech_reception WHERE rowid = ".(int)$id;
    $res_rec = $db->query($sql_rec);
    if (!$res_rec) throw new Exception($db->lasterror());

    $db->commit();

    setEventMessages($langs->trans("ReceptionDeletedSuccessfully"), null, 'mesgs');
    header("Location: ./list.php");
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages($langs->trans("ErrorDeletingReception").": ".$e->getMessage(), null, 'errors');
    header("Location: ./list.php");
    exit;
}
?>
