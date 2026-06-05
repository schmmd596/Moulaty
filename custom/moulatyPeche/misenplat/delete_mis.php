<?php
/* Copyright (C) 2025
 * Abdou Mahfoudh <superadmin@womapeche.com>
 * All rights reserved.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

global $db, $langs, $user;

$langs->loadLangs(['womapeche@womapeche', 'main', 'other']);

$id_bon = GETPOST('id', 'int');

if ($id_bon <= 0) {
    setEventMessages($langs->trans("Identifiant de bon invalide."), null, 'errors');
    header("Location: ./list.php");
    exit;
}

// ============================================================================
// 🔒 Vérification du statut du bon avant suppression
// ============================================================================
$sql_check = "SELECT ref, statut FROM ".MAIN_DB_PREFIX."pech_bon_misenplat WHERE rowid = ".$id_bon;
$res_check = $db->query($sql_check);

if (!$res_check || !$db->num_rows($res_check)) {
    setEventMessages($langs->trans("Bon introuvable."), null, 'errors');
    header("Location: ./list.php");
    exit;
}

$obj = $db->fetch_object($res_check);
if ($obj->statut == 1) { // 1 = Validé
    setEventMessages($langs->trans("Le bon ".$obj->ref." est déjà validé et ne peut pas être supprimé."), null, 'errors');
    header("Location: ./detail_mis.php?id=".$id_bon);
    exit;
}

// ============================================================================
// 🗑️ Suppression du bon et de ses enfants
// ============================================================================
$db->begin();

try {
    // 1️⃣ Récupérer tous les misenplat liés à ce bon
    $sql_mis = "SELECT rowid FROM ".MAIN_DB_PREFIX."pech_misenplat WHERE fk_bon_misenplat = ".$id_bon;
    $res_mis = $db->query($sql_mis);

    if ($res_mis) {
        while ($obj_mis = $db->fetch_object($res_mis)) {
            $fk_misenplat = (int) $obj_mis->rowid;

            // 🔸 Supprimer les plats enfants
            $sql_del_plat = "DELETE FROM ".MAIN_DB_PREFIX."pech_plat WHERE fk_misenplat = ".$fk_misenplat;
            if (!$db->query($sql_del_plat)) throw new Exception("Erreur lors de la suppression des plats");
        }
    }

    // 2️⃣ Supprimer les lignes de mise en plat
    $sql_del_misenplat = "DELETE FROM ".MAIN_DB_PREFIX."pech_misenplat WHERE fk_bon_misenplat = ".$id_bon;
    if (!$db->query($sql_del_misenplat)) throw new Exception("Erreur lors de la suppression des lignes de mise en plat");

    // 3️⃣ Supprimer le bon principal
    $sql_del_bon = "DELETE FROM ".MAIN_DB_PREFIX."pech_bon_misenplat WHERE rowid = ".$id_bon;
    if (!$db->query($sql_del_bon)) throw new Exception("Erreur lors de la suppression du bon");

    $db->commit();
    setEventMessages($langs->trans("Bon de mise en plat supprimé avec succès."), null, 'mesgs');
    header("Location: ./list.php");
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("Erreur lors de la suppression : ".$e->getMessage(), null, 'errors');
    header("Location: ./detail_mis.php?id=".$id_bon);
    exit;
}
