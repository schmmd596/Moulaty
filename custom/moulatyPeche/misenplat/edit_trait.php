<?php
/* Copyright (C) 2025
 * Abdou Mahfoudh <superadmin@womapeche.com>
 * All rights reserved.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

global $db, $langs, $user;

$langs->loadLangs(['womapeche@womapeche', 'main', 'other']);

// ============================================================================
// Paramètres
// ============================================================================
$action = GETPOST('action_update', 'alpha');
$id_bon = GETPOST('id_bon', 'int');
$qte_plater = GETPOST('qte_plater', 'array');
$nb_plat = GETPOST('nb_plat', 'array');
$commentaire = GETPOST('commentaire', 'text');
$add_product = GETPOST('add_product', 'array');
$delete_product = GETPOST('delete_product', 'array');
$misenplat_id = GETPOST('misenplat_id', 'array');

// ============================================================================
// Vérifications
// ============================================================================
if (empty($id_bon)) {
    setEventMessages($langs->trans("Identifiant du bon invalide."), null, 'errors');
    header("Location: ./index.php");
    exit;
}

// Récupération des informations du bon
$sql_bon = "SELECT b.*, e.ref as entrepot_ref, b.fk_receptiondet
            FROM ".MAIN_DB_PREFIX."pech_bon_misenplat AS b
            LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = b.fk_entrepot
            WHERE b.rowid = ".((int)$id_bon);

$res_bon = $db->query($sql_bon);
if (!$res_bon || $db->num_rows($res_bon) == 0) {
    setEventMessages($langs->trans("Bon introuvable."), null, 'errors');
    header("Location: ./index.php");
    exit;
}
$bon = $db->fetch_object($res_bon);

// ============================================================================
// DÉBUT TRANSACTION
// ============================================================================
$db->begin();
$error = 0;
$nb_modifie = 0;
$nb_ajoute = 0;
$nb_supprime = 0;

try {
    // ------------------------------------------------------------------------
    // 1️⃣ Mise à jour du commentaire du bon
    // ------------------------------------------------------------------------
    $sql_update_bon = "UPDATE ".MAIN_DB_PREFIX."pech_bon_misenplat
                      SET commentaire = '".$db->escape($commentaire)."',
                          tms = NOW()
                      WHERE rowid = ".((int)$id_bon);
    
    if (!$db->query($sql_update_bon)) {
        throw new Exception("Erreur lors de la mise à jour du commentaire du bon");
    }

    // Récupérer les IDs des réceptions liées
    $receptiondet_ids = array_filter(array_map('intval', explode(',', $bon->fk_receptiondet)));

    // ------------------------------------------------------------------------
    // 2️⃣ Traitement des lignes existantes (modification/suppression)
    // ------------------------------------------------------------------------
    if (is_array($misenplat_id)) {
        foreach ($misenplat_id as $fk_product => $id_misenplat) {
            // Vérifier si la ligne doit être supprimée
            $a_supprimer = isset($delete_product[$fk_product]) && $delete_product[$fk_product] == 1;
            
            if ($a_supprimer) {
                // Supprimer tous les plats associés
                $sql_delete_plats = "DELETE FROM ".MAIN_DB_PREFIX."pech_plat
                                    WHERE fk_misenplat = ".((int)$id_misenplat);
                if (!$db->query($sql_delete_plats)) {
                    throw new Exception("Erreur lors de la suppression des plats pour le produit ".$fk_product);
                }

                // Récupérer le poids total des plats supprimés
                $sql_get_info = "SELECT nombre_plat, poids_plat 
                                 FROM ".MAIN_DB_PREFIX."pech_misenplat 
                                 WHERE rowid = ".((int)$id_misenplat);
                $res_info = $db->query($sql_get_info);
                $poids_total_supprime = 0;
                if ($res_info && $obj = $db->fetch_object($res_info)) {
                    $poids_total_supprime = $obj->nombre_plat * $obj->poids_plat;
                }

                // Supprimer la ligne misenplat
                $sql_delete = "DELETE FROM ".MAIN_DB_PREFIX."pech_misenplat
                              WHERE rowid = ".((int)$id_misenplat);
                if (!$db->query($sql_delete)) {
                    throw new Exception("Erreur lors de la suppression de la ligne misenplat pour le produit ".$fk_product);
                }

                // Mettre à jour le poids plater dans les réceptions
                if ($poids_total_supprime > 0 && !empty($receptiondet_ids)) {
                    //retirerPoidsPlater($db, $fk_product, $poids_total_supprime, $receptiondet_ids);
                }

                $nb_supprime++;
            } 
            // Sinon, mise à jour de la ligne
            else if (isset($qte_plater[$fk_product]) && isset($nb_plat[$fk_product])) {
                $nouvelle_qte = (float) $qte_plater[$fk_product];
                $nouveau_nb = (int) $nb_plat[$fk_product];
                
                if ($nouvelle_qte > 0 && $nouveau_nb > 0) {
                    $poids_par_plat = $nouvelle_qte / $nouveau_nb;
                    
                    // Récupérer l'ancien poids total et ancien nombre de plats
                    $sql_ancien = "SELECT nombre_plat, poids_plat
                                  FROM ".MAIN_DB_PREFIX."pech_misenplat
                                  WHERE rowid = ".((int)$id_misenplat);
                    $res_ancien = $db->query($sql_ancien);
                    $ancien_nombre_plat = 0;
                    $ancien_poids_plat = 0;
                    if ($res_ancien && $obj = $db->fetch_object($res_ancien)) {
                        $ancien_nombre_plat = $obj->nombre_plat;
                        $ancien_poids_plat = $obj->poids_plat;
                    }
                    $ancien_poids_total = $ancien_nombre_plat * $ancien_poids_plat;

                    // 1. Supprimer tous les plats existants
                    $sql_delete_plats = "DELETE FROM ".MAIN_DB_PREFIX."pech_plat
                                        WHERE fk_misenplat = ".((int)$id_misenplat);
                    if (!$db->query($sql_delete_plats)) {
                        throw new Exception("Erreur lors de la suppression des anciens plats pour le produit ".$fk_product);
                    }

                    // 2. Mettre à jour la ligne misenplat
                    $sql_update = "UPDATE ".MAIN_DB_PREFIX."pech_misenplat
                                  SET nombre_plat = ".$nouveau_nb.",
                                      poids_plat = ".$poids_par_plat.",
                                      tms = NOW()
                                  WHERE rowid = ".((int)$id_misenplat);
                    
                    if (!$db->query($sql_update)) {
                        throw new Exception("Erreur lors de la mise à jour de la ligne misenplat pour le produit ".$fk_product);
                    }

                    // 3. Recréer tous les plats avec les nouvelles données
                    ajouterPlatsAvecRepartition($db, $id_misenplat, $fk_product, $nouvelle_qte, $nouveau_nb, $receptiondet_ids);

                    // 4. Gérer la différence de poids total dans les réceptions
                    $difference_poids = $nouvelle_qte - $ancien_poids_total;
                    
                    if ($difference_poids != 0 && !empty($receptiondet_ids)) {
                        // Mettre à jour le poids plater dans les réceptions
                        if ($difference_poids > 0) {
                            // Ajouter du poids
                            //ajouterPoidsPlater($db, $fk_product, $difference_poids, $receptiondet_ids);
                        } else {
                            // Retirer du poids
                            //retirerPoidsPlater($db, $fk_product, abs($difference_poids), $receptiondet_ids);
                        }
                    }

                    $nb_modifie++;
                }
            }
        }
    }

    // ------------------------------------------------------------------------
    // 3️⃣ Ajout de nouvelles lignes
    // ------------------------------------------------------------------------
    if (is_array($add_product)) {
        foreach ($add_product as $fk_product => $value) {
            if ($value == 1 && isset($qte_plater[$fk_product]) && isset($nb_plat[$fk_product])) {
                $qte = (float) $qte_plater[$fk_product];
                $nb = (int) $nb_plat[$fk_product];
                
                if ($qte > 0 && $nb > 0) {
                    $poids_par_plat = $qte / $nb;
                    
                    // Insertion dans pech_misenplat
                    $sql_insert = "INSERT INTO ".MAIN_DB_PREFIX."pech_misenplat
                                  (fk_bon_misenplat, fk_congelateur, fk_product, nombre_plat, poids_plat, statut, pointeur, date_creation)
                                  VALUES (
                                      ".((int)$id_bon).",
                                      ".((int)$bon->fk_entrepot).",
                                      ".((int)$fk_product).",
                                      ".$nb.",
                                      ".$poids_par_plat.",
                                      0,
                                      '".$db->escape($user->login)."',
                                      NOW()
                                  )";
                    
                    if (!$db->query($sql_insert)) {
                        throw new Exception("Erreur lors de l'ajout de la nouvelle ligne pour le produit ".$fk_product);
                    }
                    
                    $fk_misenplat = $db->last_insert_id(MAIN_DB_PREFIX."pech_misenplat");
                    
                    // Ajouter les plats individuels
                    ajouterPlatsAvecRepartition($db, $fk_misenplat, $fk_product, $qte, $nb, $receptiondet_ids);
                    
                    // Mettre à jour le poids plater dans les réceptions
                    //ajouterPoidsPlater($db, $fk_product, $qte, $receptiondet_ids);
                    
                    $nb_ajoute++;
                }
            }
        }
    }

    // ------------------------------------------------------------------------
    // 4️⃣ Validation et fin
    // ------------------------------------------------------------------------
    if ($error == 0) {
        $db->commit();
        
        $message = $langs->trans("Bon mis à jour avec succès");
        if ($nb_modifie > 0) $message .= " - " . $nb_modifie . " " . $langs->trans("lignes modifiées");
        if ($nb_ajoute > 0) $message .= " - " . $nb_ajoute . " " . $langs->trans("lignes ajoutées");
        if ($nb_supprime > 0) $message .= " - " . $nb_supprime . " " . $langs->trans("lignes supprimées");
        
        setEventMessages($message, null, 'mesgs');
        header("Location: ./detail_mis.php?id=".$id_bon);
        exit;
    } else {
        $db->rollback();
        setEventMessages($langs->trans("Erreur lors de la modification du bon."), null, 'errors');
    }

} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
    dol_syslog("Erreur modification bon misenplat : " . $e->getMessage(), LOG_ERR);
    
    // Redirection vers le formulaire d'édition
    header("Location: ./edit_bon.php?id=".$id_bon);
    exit;
}

// ============================================================================
// Fonctions auxiliaires
// ============================================================================

/**
 * Ajouter des plats avec répartition sur les réceptions
 */
function ajouterPlatsAvecRepartition($db, $fk_misenplat, $fk_product, $qte_totale, $nb_plats, $receptiondet_ids) {
    $poids_par_plat = $qte_totale / $nb_plats;
    $poids_restant = $qte_totale;
    
    if (empty($receptiondet_ids)) {
        throw new Exception("Aucune réception disponible pour le produit " . $fk_product);
    }
    
    // Récupérer les réceptions disponibles pour ce produit
    $sql_receptions = "SELECT rd.rowid, rd.prix_moyen, 
                              (rd.poids_net - rd.poids_plater) as disponible
                       FROM ".MAIN_DB_PREFIX."pech_receptiondet AS rd
                       WHERE rd.rowid IN (".implode(',', $receptiondet_ids).")
                       AND rd.fk_product = " . ((int)$fk_product) . "
                       AND (rd.poids_net - rd.poids_plater) > 0
                       ORDER BY rd.rowid";
    
    $res_receptions = $db->query($sql_receptions);
    if (!$res_receptions) {
        throw new Exception("Erreur lors de la récupération des réceptions disponibles");
    }
    
    $receptions = [];
    $total_disponible = 0;
    while ($rec = $db->fetch_object($res_receptions)) {
        $receptions[] = $rec;
        $total_disponible += $rec->disponible;
    }
    
    if ($total_disponible < $qte_totale) {
        throw new Exception("Stock insuffisant pour le produit " . $fk_product . 
                          " (disponible: " . $total_disponible . ", demandé: " . $qte_totale . ")");
    }
    
    // Répartir les plats sur les réceptions
    foreach ($receptions as $rec) {
        if ($poids_restant <= 0) break;
        
        // Calculer le nombre de plats possible pour cette réception
        $nb_plats_possible = floor($rec->disponible / $poids_par_plat);
        if ($nb_plats_possible <= 0) continue;
        
        $nb_plats_a_creer = min($nb_plats_possible, ceil($poids_restant / $poids_par_plat));
        
        for ($i = 0; $i < $nb_plats_a_creer; $i++) {
            // Créer le plat
            $sql_plat = "INSERT INTO ".MAIN_DB_PREFIX."pech_plat
                        (fk_misenplat, fk_product, poids, prix_moyen, statut, date_creation)
                        VALUES (
                            " . ((int)$fk_misenplat) . ",
                            " . ((int)$fk_product) . ",
                            " . $poids_par_plat . ",
                            " . ($rec->prix_moyen * $poids_par_plat) . ",
                            0,
                            NOW()
                        )";
            
            if (!$db->query($sql_plat)) {
                throw new Exception("Erreur lors de la création d'un plat");
            }
            
            // Mettre à jour le poids utilisé dans cette réception
            /*$sql_update = "UPDATE ".MAIN_DB_PREFIX."pech_receptiondet
                          SET poids_plater = poids_plater + " . ((float)$poids_par_plat) . "
                          WHERE rowid = " . ((int)$rec->rowid);
            
            if (!$db->query($sql_update)) {
                throw new Exception("Erreur lors de la mise à jour du poids plater pour réception " . $rec->rowid);
            }
            
            $poids_restant -= $poids_par_plat;
            if ($poids_restant <= 0) break;*/
        }
    }
    
    
}

/**
 * Ajouter du poids plater dans les réceptions
 */
/*
function ajouterPoidsPlater($db, $fk_product, $poids_total, $receptiondet_ids) {
    // Répartir proportionnellement sur toutes les réceptions disponibles
    $sql_receptions = "SELECT rowid, (poids_net - poids_plater) as disponible
                      FROM ".MAIN_DB_PREFIX."pech_receptiondet
                      WHERE rowid IN (".implode(',', $receptiondet_ids).")
                      AND fk_product = " . ((int)$fk_product) . "
                      AND (poids_net - poids_plater) > 0
                      ORDER BY disponible DESC";
    
    $res_receptions = $db->query($sql_receptions);
    if ($res_receptions) {
        $poids_restant = $poids_total;
        
        while ($rec = $db->fetch_object($res_receptions) && $poids_restant > 0) {
            $poids_a_ajouter = min($poids_restant, $rec->disponible);
            
            if ($poids_a_ajouter > 0) {
                $sql_update = "UPDATE ".MAIN_DB_PREFIX."pech_receptiondet
                              SET poids_plater = poids_plater + " . ((float)$poids_a_ajouter) . "
                              WHERE rowid = " . ((int)$rec->rowid);
                
                if (!$db->query($sql_update)) {
                    throw new Exception("Erreur lors de l'ajout du poids plater");
                }
                
                $poids_restant -= $poids_a_ajouter;
            }
        }
        
        if ($poids_restant > 0) {
            throw new Exception("Stock insuffisant pour ajouter " . $poids_total . " kg de poids plater (reste " . $poids_restant . " kg non attribué)");
        }
    } else {
        throw new Exception("Aucune réception disponible pour ajouter du poids plater");
    }
}
*/
/**
 * Retirer du poids plater dans les réceptions
 */
/*
function retirerPoidsPlater($db, $fk_product, $poids_total, $receptiondet_ids) {
    $sql_receptions = "SELECT rowid, poids_plater
                      FROM ".MAIN_DB_PREFIX."pech_receptiondet
                      WHERE rowid IN (".implode(',', $receptiondet_ids).")
                      AND fk_product = " . ((int)$fk_product) . "
                      AND poids_plater > 0
                      ORDER BY poids_plater DESC";
    
    $res_receptions = $db->query($sql_receptions);
    if ($res_receptions) {
        $poids_restant = $poids_total;
        
        while ($rec = $db->fetch_object($res_receptions) && $poids_restant > 0) {
            $poids_a_retirer = min($poids_restant, $rec->poids_plater);
            
            if ($poids_a_retirer > 0) {
                $sql_update = "UPDATE ".MAIN_DB_PREFIX."pech_receptiondet
                              SET poids_plater = poids_plater - " . ((float)$poids_a_retirer) . "
                              WHERE rowid = " . ((int)$rec->rowid);
                
                if (!$db->query($sql_update)) {
                    throw new Exception("Erreur lors de la diminution du poids plater");
                }
                
                $poids_restant -= $poids_a_retirer;
            }
        }
        
        if ($poids_restant > 0) {
            dol_syslog("Warning: Impossible de retirer tout le poids plater (reste " . $poids_restant . " kg)", LOG_WARNING);
        }
    } else {
        dol_syslog("Warning: Aucune réception avec du poids plater à retirer", LOG_WARNING);
    }
}
*/
// ============================================================================
// Affichage d'erreur (si on arrive ici, c'est qu'il y a une erreur)
// ============================================================================
llxHeader('', $langs->trans("Modification du Bon"));
print load_fiche_titre($langs->trans("Erreur lors de la Modification"), '', 'fa-edit');
print '<div class="error">'.$langs->trans("Une erreur s'est produite pendant le traitement.").'</div>';
print '<div class="center">';
print '<a class="button" href="./edit_bon.php?id='.$id_bon.'">'.$langs->trans("Retour à l'édition").'</a>';
print '&nbsp;&nbsp;';
print '<a class="button button-cancel" href="./detail_mis.php?id='.$id_bon.'">'.$langs->trans("Voir le bon").'</a>';
print '</div>';

llxFooter();
$db->close();