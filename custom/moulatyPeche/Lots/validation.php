<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;
$langs->load("abricot@abricot");

$id_lot = GETPOST('id', 'int');
if ($id_lot <= 0) {
    setEventMessages("⚠️ Lot non spécifié.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}
if (empty($user->rights->moulatyPeche->read_r)) {
    accessforbidden('Accès réservé à l’administrateur.');
}

// Récupération du lot
$sqlLot = "SELECT * FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid=".$id_lot;
$resLot = $db->query($sqlLot);
if (!$resLot || $db->num_rows($resLot) == 0) {
    setEventMessages("❌ Lot introuvable.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}
$lot = $db->fetch_object($resLot);

if ($lot->statut == 1) {
    setEventMessages("⚠️ Lot déjà validé.", null, 'warnings');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}

// Récupération lignes de détail
$sqlLines = "SELECT * FROM ".MAIN_DB_PREFIX."pech_lotdet WHERE fk_lot=".$id_lot;
$resLines = $db->query($sqlLines);
$lines = [];
while ($obj = $db->fetch_object($resLines)) $lines[] = $obj;

$db->begin();
try {
    foreach ($lines as $line) {
        $nb_cartons = $line->nb_carton;
        $plat_par_carton = $line->plat_carton;
        $poids_par_carton = $line->poids_carton;
        $platsDispo = [];

        // ============================================
        // 🔥 NOUVELLE LOGIQUE : GESTION DES DEUX CAS
        // ============================================
        
        // 1. 🔹 Vérifier si ce lotdet est MIXTE
        $sqlMixte = "SELECT fk_misenplat, nb_plat 
                    FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat 
                    WHERE fk_lotdet_mixte = ".((int)$line->rowid);
        $resMixte = $db->query($sqlMixte);
        
        if ($db->num_rows($resMixte) > 0) {
            // 🟢 CAS PRODUIT MIXTE : on combine les plats de plusieurs misenplat
            while ($mix = $db->fetch_object($resMixte)) {
                $sqlPlats = "SELECT rowid 
                            FROM ".MAIN_DB_PREFIX."pech_plat 
                            WHERE fk_misenplat = ".((int)$mix->fk_misenplat)." 
                            AND (fk_carton IS NULL OR fk_carton = 0)
                            ORDER BY rowid ASC
                            LIMIT ".((int)$mix->nb_plat);
                $resPlats = $db->query($sqlPlats);
                while ($obj = $db->fetch_object($resPlats)) {
                    $platsDispo[] = $obj->rowid;
                }
            }
        } 
        // 2. 🔹 Vérifier si on a PLUSIEURS misenplat (fk_misenplats)
        else if (!empty($line->fk_misenplats) && $line->mode_misenplat == 'multiple') {
            // 🟡 CAS MULTIPLE : plusieurs misenplat pour un produit
            $misenplat_ids = explode(',', $line->fk_misenplats);
            $misenplat_ids = array_map('intval', $misenplat_ids);
            
            // Calculer le nombre de plats par misenplat (distribution proportionnelle)
            $nb_plats_total_needed = $nb_cartons * $plat_par_carton;
            
            // Récupérer les disponibilités de chaque misenplat
            $disponibilites = [];
            foreach ($misenplat_ids as $mp_id) {
                $sqlDispo = "SELECT COUNT(*) as dispo 
                            FROM ".MAIN_DB_PREFIX."pech_plat 
                            WHERE fk_misenplat = ".$mp_id."
                            AND (fk_carton IS NULL OR fk_carton = 0)";
                $resDispo = $db->query($sqlDispo);
                if ($resDispo) {
                    $objDispo = $db->fetch_object($resDispo);
                    $disponibilites[$mp_id] = (int)$objDispo->dispo;
                }
            }
            
            // Distribution proportionnelle (similaire à traitment.php)
            $total_disponible = array_sum($disponibilites);
            
            if ($total_disponible < $nb_plats_total_needed) {
                throw new Exception("❌ Nombre de plats insuffisant pour le lotdet #".$line->rowid.
                                  " (demandé: ".$nb_plats_total_needed.", disponible: ".$total_disponible.")");
            }
            
            // Prendre les plats de chaque misenplat proportionnellement
            foreach ($disponibilites as $mp_id => $dispo) {
                if ($dispo <= 0) continue;
                
                $ratio = $dispo / $total_disponible;
                $plats_a_prendre = floor($nb_plats_total_needed * $ratio);
                
                if ($plats_a_prendre > 0) {
                    $sqlPlats = "SELECT rowid 
                                FROM ".MAIN_DB_PREFIX."pech_plat 
                                WHERE fk_misenplat = ".$mp_id."
                                AND (fk_carton IS NULL OR fk_carton = 0)
                                ORDER BY rowid ASC
                                LIMIT ".$plats_a_prendre;
                    $resPlats = $db->query($sqlPlats);
                    while ($obj = $db->fetch_object($resPlats)) {
                        $platsDispo[] = $obj->rowid;
                    }
                }
            }
            
            // Gérer les arrondis (prendre les plats restants d'où ils viennent)
            $plats_obtenus = count($platsDispo);
            if ($plats_obtenus < $nb_plats_total_needed) {
                $manquants = $nb_plats_total_needed - $plats_obtenus;
                
                // Prendre les plats manquants dans les misenplat qui en ont encore
                foreach ($misenplat_ids as $mp_id) {
                    if ($manquants <= 0) break;
                    
                    $sqlManquants = "SELECT rowid 
                                    FROM ".MAIN_DB_PREFIX."pech_plat 
                                    WHERE fk_misenplat = ".$mp_id."
                                    AND (fk_carton IS NULL OR fk_carton = 0)
                                    AND rowid NOT IN (".(count($platsDispo) > 0 ? implode(',', $platsDispo) : "0").")
                                    ORDER BY rowid ASC
                                    LIMIT ".$manquants;
                    $resManquants = $db->query($sqlManquants);
                    while ($obj = $db->fetch_object($resManquants)) {
                        $platsDispo[] = $obj->rowid;
                        $manquants--;
                    }
                }
            }
        }
        // 3. 🔹 CAS NORMAL : un seul misenplat (ancien système)
        else {
            // 🟡 CAS SIMPLE : un seul misenplat
            $sqlPlats = "SELECT rowid 
                        FROM ".MAIN_DB_PREFIX."pech_plat 
                        WHERE fk_misenplat = ".((int)$line->fk_misenplat)."
                        AND (fk_carton IS NULL OR fk_carton = 0)
                        ORDER BY rowid ASC
                        LIMIT ".($nb_cartons * $plat_par_carton);
            $resPlats = $db->query($sqlPlats);
            while ($obj = $db->fetch_object($resPlats)) {
                $platsDispo[] = $obj->rowid;
            }
        }

        // 🧮 Vérification : y a-t-il assez de plats ?
        if (count($platsDispo) < $nb_cartons * $plat_par_carton) {
            throw new Exception("❌ Nombre de plats insuffisant pour le lotdet #".$line->rowid);
        }

        $indexPlat = 0;

        // 🔁 Création des cartons
        for ($i = 0; $i < $nb_cartons; $i++) {
            $sqlCarton = "INSERT INTO ".MAIN_DB_PREFIX."pech_carton
                         (fk_lotdet, fk_product, nb_plat, poids, fk_user_create)
                         VALUES (
                             ".((int)$line->rowid).",
                             ".((int)$line->fk_product).",
                             ".((int)$plat_par_carton).",
                             ".((float)$poids_par_carton).",
                             ".((int)$user->id)."
                         )";
            if (!$db->query($sqlCarton)) throw new Exception("Erreur création carton : ".$db->lasterror());

            $fk_carton = $db->last_insert_id(MAIN_DB_PREFIX."pech_carton");

            // 🔹 Assigner les plats à ce carton
            $platsPourCarton = array_slice($platsDispo, $indexPlat, $plat_par_carton);
            $indexPlat += $plat_par_carton;

            if (!empty($platsPourCarton)) {
                $sqlUpdatePlats = "UPDATE ".MAIN_DB_PREFIX."pech_plat 
                                   SET fk_carton = ".((int)$fk_carton).",
                                       statut = 1
                                   WHERE rowid IN (".implode(',', array_map('intval', $platsPourCarton)).")";
                if (!$db->query($sqlUpdatePlats)) throw new Exception("Erreur MAJ plats : ".$db->lasterror());
            }
        }
    }

    // ============================================
    // 🔥 MISE À JOUR DES MISENPLAT (CAS MULTIPLE)
    // ============================================
    
    // D'abord, pour chaque misenplat, compter combien de ses plats sont utilisés
    $sqlAllMisenplat = "
        -- MISENPLAT des lignes simples
        SELECT DISTINCT fk_misenplat as mp_id
        FROM ".MAIN_DB_PREFIX."pech_lotdet 
        WHERE fk_lot = ".((int)$id_lot)."
        AND fk_misenplat IS NOT NULL
        
        UNION
        
        -- MISENPLAT des lignes multiples (décomposées)
        SELECT DISTINCT mp_id
        FROM (
            SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(fk_misenplats, ',', n.n), ',', -1) as mp_id
            FROM ".MAIN_DB_PREFIX."pech_lotdet 
            JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) n
            ON CHAR_LENGTH(fk_misenplats) - CHAR_LENGTH(REPLACE(fk_misenplats, ',', '')) >= n.n - 1
            WHERE fk_lot = ".((int)$id_lot)."
            AND fk_misenplats IS NOT NULL
            AND fk_misenplats != ''
        ) as exploded
        WHERE mp_id != ''
        
        UNION
        
        -- MISENPLAT des produits mixtes
        SELECT DISTINCT lm.fk_misenplat as mp_id
        FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat lm
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = lm.fk_lotdet_mixte
        WHERE ld.fk_lot = ".((int)$id_lot)."
    ";
    
    $resAllMp = $db->query($sqlAllMisenplat);
    $allMpIds = [];
    while ($mp = $db->fetch_object($resAllMp)) {
        $allMpIds[] = (int)$mp->mp_id;
    }
    
    // Mettre à jour chaque misenplat
    foreach ($allMpIds as $mp_id) {
        // Compter les plats de cette misenplat qui sont dans des cartons de ce lot
        $sqlCountUsed = "
            SELECT COUNT(*) as used_count
            FROM ".MAIN_DB_PREFIX."pech_plat p
            INNER JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.rowid = p.fk_carton
            INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
            WHERE p.fk_misenplat = ".$mp_id."
            AND ld.fk_lot = ".((int)$id_lot)."
        ";
        
        $resCount = $db->query($sqlCountUsed);
        $used = $resCount ? $db->fetch_object($resCount)->used_count : 0;
        
        // Mettre à jour le misenplat
        $sqlUpdateMp = "
            UPDATE ".MAIN_DB_PREFIX."pech_misenplat 
            SET nombre_plat_sortie = nombre_plat_sortie + ".$used.",
                statut = CASE 
                    WHEN (nombre_plat_sortie + ".$used.") >= nombre_plat THEN 2
                    WHEN (nombre_plat_sortie + ".$used.") > 0 THEN 1
                    ELSE 0
                END
            WHERE rowid = ".$mp_id;
        
        if (!$db->query($sqlUpdateMp)) {
            throw new Exception("Erreur mise à jour misenplat #".$mp_id." : ".$db->lasterror());
        }
    }

    // Validation du lot
    $sql = "UPDATE ".MAIN_DB_PREFIX."pech_lot SET statut=1 WHERE rowid=".$id_lot;
    if (!$db->query($sql)) throw new Exception("Erreur validation lot : ".$db->lasterror());

    // 🔹 Mise à jour du prix total pour chaque carton du lot
    $sql_cartons = "
        SELECT c.rowid AS carton_id
        FROM ".MAIN_DB_PREFIX."pech_carton AS c
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet AS ld ON ld.rowid = c.fk_lotdet
        WHERE ld.fk_lot = ".(int)$id_lot;

    $res_cartons = $db->query($sql_cartons);
    if (!$res_cartons) {
        throw new Exception("Erreur lors de la récupération des cartons : ".$db->lasterror());
    }

    if ($db->num_rows($res_cartons) == 0) {
        throw new Exception("Aucun carton trouvé pour le lot #".$id_lot);
    }

    while ($carton = $db->fetch_object($res_cartons)) {
        $carton_id = (int)$carton->carton_id;

        // 🔸 Calcul du prix total du carton à partir des plats
        $sql_sum = "
            SELECT SUM(prix_moyen + frais) AS prix_total_carton
            FROM ".MAIN_DB_PREFIX."pech_plat
            WHERE fk_carton = ".$carton_id;

        $res_sum = $db->query($sql_sum);
        if (!$res_sum) {
            throw new Exception("Erreur calcul somme pour carton #".$carton_id." : ".$db->lasterror());
        }

        $obj_sum = $db->fetch_object($res_sum);
        $prix_total_carton = (float) $obj_sum->prix_total_carton;

        if ($prix_total_carton <= 0) {
            continue;
        }

        // 🔹 Mise à jour du carton
        $sql_update = "
            UPDATE ".MAIN_DB_PREFIX."pech_carton
            SET prix_moyen = ".$prix_total_carton."
            WHERE rowid = ".$carton_id;

        if (!$db->query($sql_update)) {
            throw new Exception("Erreur mise à jour carton #".$carton_id." : ".$db->lasterror());
        }
    }

    $db->commit();
    setEventMessages("✅ Lot <strong>{$lot->ref}</strong> validé avec succès.", null, 'mesgs');
} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
}

// Redirection vers le détail
header("Location: detail_lot.php?id=".$id_lot);
exit;