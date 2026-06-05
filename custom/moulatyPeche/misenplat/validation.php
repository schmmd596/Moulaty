<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

global $db, $langs, $user;

$langs->loadLangs(['main','other','womapeche@womapeche']);

$id = GETPOST('id','int');
if (empty($user->rights->moulatyPeche->read_r)) {
    accessforbidden('Accès réservé à l’administrateur.');
}
if(empty($id)) {
    setEventMessages($langs->trans("Identifiant du bon manquant."), null, 'errors');
    header("Location: ./detail_mis.php");
    exit;
}

// Début transaction
$db->begin();
$error = 0;

try {
    // 1️⃣ Récupérer toutes les lignes de mise en plat pour ce bon
    $sql_lines = "SELECT m.rowid, m.fk_product, m.nombre_plat, m.poids_plat, m.commentaire, m.fk_congelateur, m.fk_bon_misenplat,
                         b.fk_receptiondet
                  FROM ".MAIN_DB_PREFIX."pech_misenplat AS m
                  LEFT JOIN ".MAIN_DB_PREFIX."pech_bon_misenplat AS b ON b.rowid = m.fk_bon_misenplat
                  WHERE m.fk_bon_misenplat = ".((int)$id);
    $resql_lines = $db->query($sql_lines);
    if(!$resql_lines || $db->num_rows($resql_lines)==0) {
        throw new Exception("Aucune ligne de mise en plat trouvée pour ce bon.");
    }

    
    while($line = $db->fetch_object($resql_lines)) {
        $poids_total = $line->nombre_plat * $line->poids_plat;

        // 2️⃣ Récupérer tous les fk_receptiondet liés (stockés sous forme CSV)
        $receptions = explode(',', $line->fk_receptiondet ?? '');
        if(empty($receptions)) continue;

        // 3️⃣ Mettre à jour chaque réceptiondet
        $poids_a_distribuer = $line->nombre_plat * $line->poids_plat;

        foreach($receptions as $fk_receptiondet) {
            $fk_receptiondet = (int) trim($fk_receptiondet);
            if($fk_receptiondet <= 0) continue;

            // Récupérer la réception
            $sql_rd = "SELECT poids_net, poids_plater FROM ".MAIN_DB_PREFIX."pech_receptiondet
                    WHERE rowid = ".$fk_receptiondet." AND fk_product = ".((int)$line->fk_product);
            $res_rd = $db->query($sql_rd);
            if(!$res_rd || $db->num_rows($res_rd) == 0) continue;

            $rd = $db->fetch_object($res_rd);

            // Calculer le poids disponible
            $poids_disponible = $rd->poids_net - $rd->poids_plater;
            if($poids_disponible <= 0) continue;

            // Déterminer combien placer dans cette réception
            $poids_a_ajouter = min($poids_a_distribuer, $poids_disponible);

            // Mettre à jour la réception
            $sql_upd = "UPDATE ".MAIN_DB_PREFIX."pech_receptiondet
                        SET poids_plater = poids_plater + ".$poids_a_ajouter."
                        WHERE rowid = ".$fk_receptiondet;
            if(!$db->query($sql_upd)) throw new Exception("Erreur lors de la mise à jour de la réception (ID: $fk_receptiondet).");

            // Décrémenter le poids restant à distribuer
            $poids_a_distribuer -= $poids_a_ajouter;

            // Si tout le poids est distribué, on sort de la boucle
            if($poids_a_distribuer <= 0) break;
        }

        // Vérifier si tout le poids a été distribué
        if($poids_a_distribuer > 0) {
            throw new Exception("Impossible de répartir tout le poids, certaines réceptions sont déjà pleines.");
        }

        // 4️⃣ Mettre à jour le statut de la ligne mise en plat
        $sql_upd_mpl = "UPDATE ".MAIN_DB_PREFIX."pech_misenplat
                        SET statut = 1
                        WHERE rowid = ".((int)$line->rowid);
        if(!$db->query($sql_upd_mpl)) throw new Exception("Erreur lors de la validation de la mise en plat ID: ".$line->rowid);
    }


    // 5️⃣ Mettre à jour le statut du bon
    $sql_upd_bon = "UPDATE ".MAIN_DB_PREFIX."pech_bon_misenplat
                    SET statut = 1
                    WHERE rowid = ".((int)$id);
    if(!$db->query($sql_upd_bon)) throw new Exception("Erreur lors de la validation du bon.");

    $db->commit();
    setEventMessages($langs->trans("Bon de mise en plat validé avec succès."), null, 'mesgs');
    header("Location: ./detail_mis.php?id=".$id);
    exit;

} catch(Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
    header("Location: ./detail_mis.php?id=".$id);
    exit;
}
