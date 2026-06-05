<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';


global $db, $user;

$reception_id = GETPOST('id', 'int');
$poids_net_data = GETPOST('poids_net', 'array');

if (!$reception_id) {
    setEventMessages('Réception non spécifiée.', null, 'errors');
    header("Location: reception_list.php");
    exit;
}

if (empty($poids_net_data)) {
    setEventMessages('Aucune donnée reçue.', null, 'errors');
    header("Location: poid_net_edit.php?id=" . $reception_id);
    exit;
}

$db->begin();

try {
    // --- 1️⃣ Vérifier que la réception existe
    $sql = "SELECT rowid, frais, fk_entrepot,ref  FROM " . MAIN_DB_PREFIX . "pech_reception WHERE rowid = " . ((int)$reception_id);
    $res = $db->query($sql);
    if (!$res) throw new Exception("Erreur SQL : " . $db->lasterror());
    if ($db->num_rows($res) == 0) throw new Exception("Réception introuvable.");

    $reception = $db->fetch_object($res);
    $frais_reception = (float)$reception->frais;

    // --- 2️⃣ Mise à jour des poids nets
    foreach ($poids_net_data as $line_id => $new_poids_net) {
        $new_poids_net = (float)price2num($new_poids_net);

        // Récupérer les infos de la ligne
        $sql_line = "SELECT fk_product, poids_net, total_line, reception_mode
                    FROM " . MAIN_DB_PREFIX . "pech_receptiondet
                    WHERE rowid = " . ((int)$line_id);
        $res_line = $db->query($sql_line);
        if (!$res_line) throw new Exception("Erreur SQL : " . $db->lasterror());
        if ($db->num_rows($res_line) == 0) continue;

        $line = $db->fetch_object($res_line);
        $old_poids_net = (float)$line->poids_net;

        if ($old_poids_net == $new_poids_net) continue; // rien à faire si inchangé

        $product_id = $line->fk_product;
        $modeLabel = '';
        $modes = [1=>'Voiture',2=>'Poids Brut',3=>'Poids Net'];
        if (isset($modes[$line->reception_mode])) $modeLabel = $modes[$line->reception_mode];

        $stock = new MouvementStock($db);

        // --- 1️⃣ Sortir l'ancien poids net du stock (livraison)
        if ($old_poids_net > 0) {
            $result = $stock->livraison(
                $user,
                $product_id,
                $reception->fk_entrepot,
                $old_poids_net,
                0,
                "Annulation ancien poids net Réception #".$reception->ref." - ".$modeLabel
            );
            if ($result < 0) throw new Exception("Erreur stock sortie : ".$stock->error);
        }

        // --- 2️⃣ Entrer le nouveau poids net dans le stock (réception)
        if ($new_poids_net > 0) {
            $result = $stock->reception(
                $user,
                $product_id,
                $reception->fk_entrepot,
                $new_poids_net,
                0,
                "Mise à jour poids net Réception #".$reception->ref." - ".$modeLabel
            );
            if ($result < 0) throw new Exception("Erreur stock entrée : ".$stock->error);
        }


        // Récupérer les infos de la ligne
        $sql_line = "SELECT total_line FROM " . MAIN_DB_PREFIX . "pech_receptiondet WHERE rowid = " . ((int)$line_id);
        $res_line = $db->query($sql_line);
        if (!$res_line) throw new Exception("Erreur SQL : " . $db->lasterror());
        if ($db->num_rows($res_line) == 0) continue;

        // Mise à jour du poids net
        $update = "UPDATE " . MAIN_DB_PREFIX . "pech_receptiondet
                   SET poids_net = " . $new_poids_net . "
                   WHERE rowid = " . ((int)$line_id);
        if (!$db->query($update)) {
            throw new Exception("Erreur mise à jour ligne $line_id : " . $db->lasterror());
        }
    }

    // --- 3️⃣ Calcul du nouveau poids total
    $sql_poids = "SELECT SUM(poids_net) as total_poids FROM " . MAIN_DB_PREFIX . "pech_receptiondet WHERE fk_reception = " . ((int)$reception_id);
    $res_poids = $db->query($sql_poids);
    if (!$res_poids) throw new Exception("Erreur SQL : " . $db->lasterror());

    $obj_poids = $db->fetch_object($res_poids);
    $poids_total = (float)$obj_poids->total_poids;

    if ($poids_total <= 0) {
        throw new Exception("Poids total nul ou invalide. Impossible de recalculer les prix moyens.");
    }

    // --- 4️⃣ Recalcul du prix moyen pour chaque ligne
    $sql_lines = "SELECT rowid, total_line, poids_net
                  FROM " . MAIN_DB_PREFIX . "pech_receptiondet
                  WHERE fk_reception = " . ((int)$reception_id);
    $res_lines = $db->query($sql_lines);
    if (!$res_lines) throw new Exception("Erreur SQL lors du recalcul des lignes : " . $db->lasterror());

    while ($line = $db->fetch_object($res_lines)) {
        $poids_net_ligne = (float)$line->poids_net;
        $montant_ligne = (float)$line->total_line;

        if ($poids_net_ligne <= 0) {
            // On évite toute division par zéro
            continue;
        }

        $prix_moyen = ($montant_ligne / $poids_net_ligne) + ($frais_reception / $poids_total);

        $update_price = "UPDATE " . MAIN_DB_PREFIX . "pech_receptiondet
                         SET prix_moyen = " . price2num($prix_moyen) . "
                         WHERE rowid = " . $line->rowid;

        if (!$db->query($update_price)) {
            throw new Exception("Erreur mise à jour prix moyen (ligne " . $line->rowid . ") : " . $db->lasterror());
        }
    }

    // --- 5️⃣ Mise à jour du poids total dans la table réception
    $update_recep = "UPDATE " . MAIN_DB_PREFIX . "pech_reception
                     SET poids = " . price2num($poids_total) . "
                     WHERE rowid = " . ((int)$reception_id);

    if (!$db->query($update_recep)) {
        throw new Exception("Erreur mise à jour poids total réception : " . $db->lasterror());
    }

    // ✅ Validation finale
    $db->commit();
    setEventMessages('✅ Mise à jour effectuée avec succès.', null, 'mesgs');
    header("Location: detail_rec.php?id=" . $reception_id);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages('❌ Erreur : ' . $e->getMessage(), null, 'errors');
    header("Location: poid_net_edit.php?id=" . $reception_id);
    exit;
}
?>
