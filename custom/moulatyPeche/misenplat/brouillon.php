<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;

$langs->loadLangs(['main','other']);

$id = GETPOST('id', 'int');

if (empty($user->admin)) {
    accessforbidden('Accès réservé à l’administrateur.');
}

if (empty($id)) {
    setEventMessages($langs->trans("Identifiant du bon manquant."), null, 'errors');
    header("Location: ./detail_mis.php");
    exit;
}

// Charger le bon
$sqlBon = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."pech_bon_misenplat WHERE rowid = ".$id;
$resBon = $db->query($sqlBon);
$bon = $db->fetch_object($resBon);

if (!$bon) {
    setEventMessages("Bon introuvable.", null, 'errors');
    header("Location: ./detail_mis.php?id=".$id);
    exit;
}

// Vérifier existence facture fournisseur liée
$sqlFact = "SELECT rowid
            FROM ".MAIN_DB_PREFIX."facture_fourn
            WHERE ref_supplier LIKE '%".$db->escape($bon->ref)."%'
            LIMIT 1";

$resFact = $db->query($sqlFact);

if ($resFact && $db->num_rows($resFact) > 0) {
    setEventMessages(
        "Annulation impossible : une facture fournisseur est déjà liée à ce bon.",
        null,
        'errors'
    );
    header("Location: ./detail_mis.php?id=".$id);
    exit;
}


$sqlChkPlat = "SELECT p.rowid
               FROM ".MAIN_DB_PREFIX."pech_plat p
               JOIN ".MAIN_DB_PREFIX."pech_misenplat m ON m.rowid = p.fk_misenplat
               WHERE m.fk_bon_misenplat = ".$id."
               AND p.statut = 1
               LIMIT 1";

$resChkPlat = $db->query($sqlChkPlat);

if ($resChkPlat && $db->num_rows($resChkPlat) > 0) {
    setEventMessages(
        "Annulation impossible : certains plats sont déjà cartonnés.",
        null,
        'errors'
    );
    header("Location: ./detail_mis.php?id=".$id);
    exit;
}

/* Vérifier que le bon est validé */
$sql_chk = "SELECT statut FROM ".MAIN_DB_PREFIX."pech_bon_misenplat WHERE rowid = ".$id;
$res_chk = $db->query($sql_chk);
$obj_chk = $db->fetch_object($res_chk);

if (!$obj_chk || $obj_chk->statut != 1) {
    setEventMessages("Ce bon n’est pas validé.", null, 'errors');
    header("Location: ./detail_mis.php?id=".$id);
    exit;
}

$db->begin();

try {

    /* 1️⃣ Récupérer toutes les lignes de mise en plat */
    $sql = "SELECT m.rowid, m.fk_product, m.nombre_plat, m.poids_plat,
                   b.fk_receptiondet
            FROM ".MAIN_DB_PREFIX."pech_misenplat m
            JOIN ".MAIN_DB_PREFIX."pech_bon_misenplat b ON b.rowid = m.fk_bon_misenplat
            WHERE m.fk_bon_misenplat = ".$id."
            AND m.statut = 1";

    $res = $db->query($sql);
    if (!$res || $db->num_rows($res) == 0) {
        throw new Exception("Aucune ligne validée trouvée.");
    }

    while ($line = $db->fetch_object($res)) {

        $poids_total = $line->nombre_plat * $line->poids_plat;
        $poids_a_retirer = $poids_total;

        $receptions = explode(',', $line->fk_receptiondet ?? '');

        foreach ($receptions as $fk_receptiondet) {
            $fk_receptiondet = (int) trim($fk_receptiondet);
            if ($fk_receptiondet <= 0) continue;

            $sql_rd = "SELECT poids_plater
                       FROM ".MAIN_DB_PREFIX."pech_receptiondet
                       WHERE rowid = ".$fk_receptiondet."
                       AND fk_product = ".((int)$line->fk_product);

            $res_rd = $db->query($sql_rd);
            if (!$res_rd || $db->num_rows($res_rd) == 0) continue;

            $rd = $db->fetch_object($res_rd);

            if ($rd->poids_plater <= 0) continue;

            $poids_retirer = min($poids_a_retirer, $rd->poids_plater);

            $sql_upd = "UPDATE ".MAIN_DB_PREFIX."pech_receptiondet
                        SET poids_plater = poids_plater - ".$poids_retirer."
                        WHERE rowid = ".$fk_receptiondet;

            if (!$db->query($sql_upd)) {
                throw new Exception("Erreur MAJ réception ".$fk_receptiondet);
            }

            $poids_a_retirer -= $poids_retirer;
            if ($poids_a_retirer <= 0) break;
        }

        if ($poids_a_retirer > 0) {
            throw new Exception("Incohérence détectée lors de l’annulation.");
        }

        /* Remettre la ligne en brouillon */
        $sql_reset = "UPDATE ".MAIN_DB_PREFIX."pech_misenplat
                      SET statut = 0
                      WHERE rowid = ".$line->rowid;

        if (!$db->query($sql_reset)) {
            throw new Exception("Erreur reset mise en plat ".$line->rowid);
        }

        $sql_plat = "UPDATE ".MAIN_DB_PREFIX."pech_plat
                    SET frais = 0,
                        statut = 0
                    WHERE fk_misenplat = ".$line->rowid; 

        if (!$db->query($sql_plat)) {
            throw new Exception("Erreur remise à zéro des frais des plats.");
        }
    }

    /* 2️⃣ Remettre le bon en brouillon */
    $sql_bon = "UPDATE ".MAIN_DB_PREFIX."pech_bon_misenplat
                SET statut = 0,
                    total_frais = 0,
                    fk_facture_frais = NULL
                WHERE rowid = ".$id;

    if (!$db->query($sql_bon)) {
        throw new Exception("Erreur remise en brouillon du bon.");
    }

    $db->commit();
    setEventMessages("Bon remis en brouillon avec succès.", null, 'mesgs');
    header("Location: ./detail_mis.php?id=".$id);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
    header("Location: ./detail_mis.php?id=".$id);
    exit;
}
