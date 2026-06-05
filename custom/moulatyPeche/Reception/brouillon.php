<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
global $db, $user, $langs, $conf;

$langs->load("stocks");
$langs->load("reception");

$id = GETPOST('id', 'int');

if (empty($user->admin)) {
    accessforbidden('Accès réservé à l’administrateur.');
}

// Vérification de la réception
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_reception WHERE rowid = ".(int)$id;
$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) accessforbidden();

$rec = $db->fetch_object($resql);

// Vérifier si déjà brouillon
if ($rec->etat == 0) {
    setEventMessages($langs->trans("ReceptionAlreadyDraft"), null, 'errors');
    header("Location: ./detail_rec.php?id=".$id);
    exit;
}

// Vérifier que toutes les lignes ont poids_plater = 0
$sql_check = "SELECT COUNT(*) as cnt
              FROM ".MAIN_DB_PREFIX."pech_receptiondet
              WHERE fk_reception = ".(int)$id."
              AND poids_plater != 0";

$res_check = $db->query($sql_check);
if ($res_check) {
    $obj_check = $db->fetch_object($res_check);
    if ($obj_check->cnt > 0) {
        setEventMessages($langs->trans("CannotRevertReceptionPlaterNotZero"), null, 'errors');
        header("Location: ./detail_rec.php?id=".$id);
        exit;
    }
} else {
    setEventMessages($langs->trans("ErrorCheckingReceptionLines"), null, 'errors');
    header("Location: ./detail_rec.php?id=".$id);
    exit;
}

// Vérifier si une facture existe pour cette réception
$sqlFact = "SELECT rowid, ref, fk_soc, ref_supplier, total_ht, datef
            FROM ".MAIN_DB_PREFIX."facture_fourn
            WHERE ref_supplier LIKE '%".$db->escape($rec->ref)."%'
            ORDER BY datef DESC";

$resFact = $db->query($sqlFact);

if ($resFact && $db->num_rows($resFact) > 0) {
    setEventMessages($langs->trans("CannotRevertReceptionHasInvoice"), null, 'errors');
    header("Location: ./detail_rec.php?id=".$id);
    exit;
}

// Commencer transaction
$db->begin();

try {
    // Parcourir les lignes de réception
    $sql_det = "SELECT * FROM ".MAIN_DB_PREFIX."pech_receptiondet WHERE fk_reception = ".(int)$id;
    $res_det = $db->query($sql_det);
    if (!$res_det) throw new Exception($db->lasterror());

    while ($obj = $db->fetch_object($res_det)) {
        $qty_total = ($obj->poids_net ?? 0);
        if ($qty_total <= 0) continue;

        $modes = [1=>'Voiture',2=>'Poids Brut',3=>'Poids Net'];
        $modeLabel = isset($modes[$obj->reception_mode]) ? $modes[$obj->reception_mode] : '';

        $stock = new MouvementStock($db);

        // --- Annulation du mouvement de stock ---
        $result = $stock->livraison(
            $user,
            $obj->fk_product,
            $rec->fk_entrepot,
            $qty_total, // quantité négative pour annuler
            0,
            $langs->trans("CancelReception")." #".$rec->ref.' - '.$modeLabel
        );

        if ($result < 0) throw new Exception($stock->error);
    }

    // Remettre l'état en brouillon
    //$sql_update = "UPDATE ".MAIN_DB_PREFIX."pech_reception SET etat = 0 WHERE rowid = ".(int)$id;
    //if (!$db->query($sql_update)) throw new Exception($db->lasterror());
    // Mise à jour de la réception pour revenir en brouillon

// Parcourir toutes les lignes de la réception pour recalculer le prix moyen
$TOT = 0;
$sql_det = "SELECT rowid, total_line, poids_net
            FROM ".MAIN_DB_PREFIX."pech_receptiondet
            WHERE fk_reception = ".(int)$id;

$res_det = $db->query($sql_det);
if ($res_det) {
    while ($line = $db->fetch_object($res_det)) {
        $prix_moyen = ($line->poids_net > 0) ? ($line->total_line / $line->poids_net) : 0;
        $TOT += $line->total_line;

        $sql_update_det = "UPDATE ".MAIN_DB_PREFIX."pech_receptiondet
                           SET prix_moyen = ".$prix_moyen."
                           WHERE rowid = ".(int)$line->rowid;

        if (!$db->query($sql_update_det)) {
            throw new Exception("Erreur mise à jour prix moyen ligne ".$line->rowid.": ".$db->lasterror());
        }
    }
} else {
    throw new Exception("Erreur récupération lignes réception : ".$db->lasterror());
}
$sql_update = "UPDATE ".MAIN_DB_PREFIX."pech_reception
               SET etat = 0,
                   fk_facture = NULL,
                   frais = 0,
                   fk_bon_recep = NULL,
                   montant = ".$TOT." 
               WHERE rowid = ".(int)$id;

if (!$db->query($sql_update)) throw new Exception($db->lasterror());




    $db->commit();
    setEventMessages($langs->trans("ReceptionRevertedToDraft"), null, 'mesgs');
    header("Location: ./detail_rec.php?id=".$id);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages($langs->trans("ErrorRevertingReception").": ".$e->getMessage(), null, 'errors');
    header("Location: ./detail_rec.php?id=".$id);
    exit;
}
?>
