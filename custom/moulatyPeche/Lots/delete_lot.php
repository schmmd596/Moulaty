<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;

$langs->load("abricot@abricot");

// ===============================
// PARAMÈTRES
// ===============================
$id = GETPOST('id', 'int');

// ===============================
// VALIDATION
// ===============================
if (empty($id)) {
    setEventMessages("Identifiant du lot manquant.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}

// ===============================
// VÉRIFICATION DU LOT
// ===============================
$sql = "SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".((int)$id);
$res = $db->query($sql);

if (!$res || $db->num_rows($res) == 0) {
    setEventMessages("Lot introuvable.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}

$obj = $db->fetch_object($res);
$ref = $obj->ref;

// ===============================
// SUPPRESSION
// ===============================
$db->begin();

try {
    // 1️⃣ Récupérer les ID des lignes de lotdet associées
    $sqlLotdet = "SELECT rowid FROM ".MAIN_DB_PREFIX."pech_lotdet WHERE fk_lot = ".((int)$id);
    $resLotdet = $db->query($sqlLotdet);

    if ($resLotdet) {
        while ($objLotdet = $db->fetch_object($resLotdet)) {
            // 2️⃣ Supprimer les lignes mixte liées à chaque lotdet
            $sqlDelMixte = "DELETE FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat WHERE fk_lotdet_mixte = ".((int)$objLotdet->rowid);
            if (!$db->query($sqlDelMixte)) {
                throw new Exception("Erreur lors de la suppression des données mixte liées au lotdet #".$objLotdet->rowid);
            }
        }
    }
    // Supprimer les lignes associées
    $sqlDelDet = "DELETE FROM ".MAIN_DB_PREFIX."pech_lotdet WHERE fk_lot = ".((int)$id);
    if (!$db->query($sqlDelDet)) {
        throw new Exception("Erreur lors de la suppression des détails du lot.");
    }
    

    // Supprimer le lot
    $sqlDelLot = "DELETE FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".((int)$id);
    if (!$db->query($sqlDelLot)) {
        throw new Exception("Erreur lors de la suppression du lot.");
    }

    $db->commit();
    setEventMessages("✅ Lot <strong>$ref</strong> supprimé avec succès.", null, 'mesgs');
    header("Location: list.php");
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
    header("Location: detail_lot.php?id=".$id);
    exit;
}

$db->close();
?>
