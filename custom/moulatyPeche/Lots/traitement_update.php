<?php
/**
 * traitement_update.php
 * Traitement de la mise à jour d'un lot existant
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;
$langs->load("abricot@abricot");

// ===============================
// PARAMÈTRES
// ===============================
$action = GETPOST('action', 'alpha');
$id_lot = GETPOST('id', 'int');
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$commentaire = GETPOST('commentaire', 'restricthtml');
$nombre_plat = GETPOST('nombre_plat', 'array'); // array: produit_id => quantité
$plat_par_carton = GETPOST('plat_par_carton', 'array'); // array: produit_id => plats par carton
$misenplat_ids = GETPOST('misenplat_ids', 'array'); // array: produit_id => liste d'IDs séparés par virgules
$lotdet_id = GETPOST('lotdet_id', 'array'); // array: produit_id => lotdet_id existant
$nb_plats_mixte = GETPOST('nb_plats_mixte', 'int');
$plat_par_carton_mixte = GETPOST('plat_par_carton_mixte', 'int');

// ===============================
// VÉRIFICATIONS
// ===============================
if ($action != 'update') {
    setEventMessages($langs->trans("InvalidAction"), null, 'errors');
    header("Location: edit_lot.php?id=".$id_lot);
    exit;
}

if ($id_lot <= 0) {
    setEventMessages("⚠️ Lot non spécifié.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}

// Vérifier si le lot existe et n'est pas validé
$sqlCheckLot = "SELECT * FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".(int)$id_lot;
$resCheckLot = $db->query($sqlCheckLot);
if (!$resCheckLot || $db->num_rows($resCheckLot) == 0) {
    setEventMessages("❌ Lot introuvable.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}

$lot = $db->fetch_object($resCheckLot);
if ($lot->statut == 1) {
    setEventMessages("⚠️ Impossible de modifier un lot validé.", null, 'warnings');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}

if (empty($fk_entrepot)) {
    setEventMessages($langs->trans("WarehouseRequired"), null, 'errors');
    header("Location: edit_lot.php?id=".$id_lot);
    exit;
}

if (empty($nombre_plat) || !is_array($nombre_plat)) {
    setEventMessages($langs->trans("NoProductsSelected"), null, 'errors');
    header("Location: edit_lot.php?id=".$id_lot);
    exit;
}

// ===============================
// TRAITEMENT DE LA MODIFICATION
// ===============================
$db->begin();
try {
    // 🔄 RÉCUPÉRATION DES DONNÉES EXISTANTES POUR COMPARAISON
    $existing_lines = [];
    $sqlExisting = "SELECT * FROM ".MAIN_DB_PREFIX."pech_lotdet WHERE fk_lot = ".(int)$id_lot;
    $resExisting = $db->query($sqlExisting);
    if ($resExisting) {
        while ($line = $db->fetch_object($resExisting)) {
            $existing_lines[$line->rowid] = $line;
        }
    }
    
    // 1. METTRE À JOUR L'EN-TÊTE DU LOT
    $sqlUpdateLot = "UPDATE ".MAIN_DB_PREFIX."pech_lot 
                    SET commentaire = '".$db->escape($commentaire)."',
                        tms = NOW()
                    WHERE rowid = ".(int)$id_lot;
    
    if (!$db->query($sqlUpdateLot)) {
        throw new Exception("Erreur mise à jour lot : ".$db->lasterror());
    }
    
    // 2. METTRE À JOUR LES LIGNES EXISTANTES
    $total_restant_mixte = 0;
    $poids_moyen_mixte = 0;
    $produits_count_mixte = 0;
    
    foreach ($nombre_plat as $produit_id => $qte_a_utiliser) {
        if (empty($qte_a_utiliser) || $qte_a_utiliser <= 0) {
            continue;
        }
        
        // Vérifier si une ligne existe déjà pour ce produit
        $existing_lotdet_id = isset($lotdet_id[$produit_id]) ? (int)$lotdet_id[$produit_id] : 0;
        
        // Récupérer les informations du produit
        $sql_produit = "SELECT label FROM ".MAIN_DB_PREFIX."product 
                       WHERE rowid = ".((int)$produit_id);
        $res_produit = $db->query($sql_produit);
        if (!$res_produit || $db->num_rows($res_produit) == 0) {
            throw new Exception("Produit introuvable : ".$produit_id);
        }
        $produit_info = $db->fetch_object($res_produit);
        
        // Récupérer les misenplat pour ce produit
        if (!isset($misenplat_ids[$produit_id]) || empty($misenplat_ids[$produit_id])) {
            throw new Exception("Aucune misenplat trouvée pour le produit : ".$produit_info->label);
        }
        
        $misenplat_list = explode(',', $misenplat_ids[$produit_id]);
        $misenplat_list = array_map('intval', $misenplat_list);
        
        // Calculer le poids moyen pour ce produit
        $sql_poids = "SELECT AVG(poids_plat) as poids_moyen, 
                             SUM(nombre_plat - nombre_plat_sortie) as total_disponible
                      FROM ".MAIN_DB_PREFIX."pech_misenplat 
                      WHERE rowid IN (".implode(',', $misenplat_list).")";
        
        $res_poids = $db->query($sql_poids);
        if (!$res_poids) {
            throw new Exception("Erreur calcul poids moyen : ".$db->lasterror());
        }
        
        $poids_info = $db->fetch_object($res_poids);
        $poids_moyen = (float)$poids_info->poids_moyen;
        $total_disponible = (int)$poids_info->total_disponible;
        
        // Vérifier la disponibilité
        if ($qte_a_utiliser > $total_disponible) {
            throw new Exception("Stock insuffisant pour ".$produit_info->label.
                              " (demandé: ".$qte_a_utiliser.", disponible: ".$total_disponible.")");
        }
        
        // Récupérer les plats par carton
        $plat_par_carton_val = isset($plat_par_carton[$produit_id]) ? (int)$plat_par_carton[$produit_id] : 1;
        if ($plat_par_carton_val <= 0) $plat_par_carton_val = 1;
        
        // Calculs
        $nb_cartons = intdiv($qte_a_utiliser, $plat_par_carton_val);
        $restant = $qte_a_utiliser % $plat_par_carton_val;
        $poids_total_carton = $poids_moyen * $plat_par_carton_val;
        
        // Déterminer le mode (single ou multiple)
        $mode = (count($misenplat_list) == 1) ? 'single' : 'multiple';
        $fk_misenplat_single = ($mode == 'single') ? $misenplat_list[0] : null;
        $fk_misenplats_list = ($mode == 'multiple') ? implode(',', $misenplat_list) : null;
        
        if ($existing_lotdet_id > 0) {
            // 🔄 METTRE À JOUR LA LIGNE EXISTANTE
            $sqlUpdateDet = "UPDATE ".MAIN_DB_PREFIX."pech_lotdet
                            SET fk_misenplat = ".($fk_misenplat_single ?: 'NULL').",
                                fk_misenplats = ".($fk_misenplats_list ? "'".$db->escape($fk_misenplats_list)."'" : "NULL").",
                                mode_misenplat = '".$mode."',
                                poids_carton = ".((float)$poids_total_carton).",
                                plat_carton = ".((int)$plat_par_carton_val).",
                                nb_carton = ".((int)$nb_cartons).",
                                commentaire = '".$db->escape($produit_info->label)."',
                                tms = NOW()
                            WHERE rowid = ".((int)$existing_lotdet_id);
            
            if (!$db->query($sqlUpdateDet)) {
                throw new Exception("Erreur mise à jour lotdet : ".$db->lasterror());
            }
        } else {
            // ➕ INSÉRER UNE NOUVELLE LIGNE (si produit ajouté)
            $sqlInsertDet = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet
                           (fk_lot, fk_product, fk_misenplat, fk_misenplats, mode_misenplat,
                            poids_carton, plat_carton, nb_carton, taux_rendement, commentaire, date_creation)
                           VALUES (
                               ".((int)$id_lot).",
                               ".((int)$produit_id).",
                               ".($fk_misenplat_single ?: 'NULL').",
                               ".($fk_misenplats_list ? "'".$db->escape($fk_misenplats_list)."'" : "NULL").",
                               '".$mode."',
                               ".((float)$poids_total_carton).",
                               ".((int)$plat_par_carton_val).",
                               ".((int)$nb_cartons).",
                               100,
                               '".$db->escape($produit_info->label)."',
                               NOW()
                           )";
            
            if (!$db->query($sqlInsertDet)) {
                throw new Exception("Erreur insertion lotdet : ".$db->lasterror());
            }
        }
        
        // Cumul pour produit mixte
        $total_restant_mixte += $restant;
        $poids_moyen_mixte += $poids_moyen;
        $produits_count_mixte++;
    }
    
    // 3. SUPPRIMER LES LIGNES QUI N'EXISTENT PLUS
    $kept_lotdet_ids = array_filter($lotdet_id);
    if (!empty($kept_lotdet_ids)) {
        $sqlDeleteOld = "DELETE FROM ".MAIN_DB_PREFIX."pech_lotdet 
                        WHERE fk_lot = ".((int)$id_lot)."
                        AND rowid NOT IN (".implode(',', array_map('intval', $kept_lotdet_ids)).")";
        $db->query($sqlDeleteOld);
    }
    
    // 4. GÉRER LE PRODUIT MIXTE
    if ($nb_plats_mixte > 0) {
        // Supprimer l'ancienne ligne mixte si elle existe
        $sqlDeleteMixte = "DELETE FROM ".MAIN_DB_PREFIX."pech_lotdet 
                          WHERE fk_lot = ".((int)$id_lot)."
                          AND EXISTS (
                              SELECT 1 FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat lm
                              WHERE lm.fk_lotdet_mixte = ".MAIN_DB_PREFIX."pech_lotdet.rowid
                          )";
        $db->query($sqlDeleteMixte);
        
        // Supprimer les anciens liens mixtes
        $sqlDeleteMixteLinks = "DELETE lm FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat lm
                               INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = lm.fk_lotdet_mixte
                               WHERE ld.fk_lot = ".((int)$id_lot);
        $db->query($sqlDeleteMixteLinks);
        
        // Récupération de l'ID du produit "mixte"
        $sqlProductMixte = "SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE label LIKE '%MIXTE%' LIMIT 1";
        $resMixte = $db->query($sqlProductMixte);
        $fk_product_mixte = null;
        
        if ($resMixte && $db->num_rows($resMixte) > 0) {
            $fk_product_mixte = $db->fetch_object($resMixte)->rowid;
        }
        
        $plat_par_carton_mixte_val = $plat_par_carton_mixte;
        if ($plat_par_carton_mixte_val <= 0) $plat_par_carton_mixte_val = 1;
        
        $nb_cartons_mixte = intdiv($nb_plats_mixte, $plat_par_carton_mixte_val);
        $restant_mixte = $nb_plats_mixte % $plat_par_carton_mixte_val;
        
        // Calcul du poids moyen pour le mixte
        $poids_total_mixte = 0;
        if ($produits_count_mixte > 0) {
            $poids_moyen_plat = $poids_moyen_mixte / $produits_count_mixte;
            $poids_total_mixte = $poids_moyen_plat * $plat_par_carton_mixte_val;
        }
        
        // Insertion du produit mixte
        $sqlMixte = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet
                    (fk_lot, fk_product, fk_misenplat, fk_misenplats, mode_misenplat,
                     poids_carton, plat_carton, nb_carton, taux_rendement, commentaire, date_creation)
                    VALUES (
                        ".((int)$id_lot).",
                        ".($fk_product_mixte ? (int)$fk_product_mixte : 0).",
                        NULL,
                        NULL,
                        'single',
                        ".((float)$poids_total_mixte).",
                        ".((int)$plat_par_carton_mixte_val).",
                        ".((int)$nb_cartons_mixte).",
                        100,
                        'Produit mixte',
                        NOW()
                    )";
        
        if (!$db->query($sqlMixte)) {
            throw new Exception("Erreur insertion produit mixte : ".$db->lasterror());
        }
        
        $id_lotdet_mixte = $db->last_insert_id(MAIN_DB_PREFIX."pech_lotdet");
        
        // 🔗 Lier le produit mixte aux mises en plat sources
        foreach ($nombre_plat as $produit_id => $qte_a_utiliser) {
            if (empty($qte_a_utiliser)) continue;
            
            $plat_par_carton_val = isset($plat_par_carton[$produit_id]) ? (int)$plat_par_carton[$produit_id] : 1;
            if ($plat_par_carton_val <= 0) $plat_par_carton_val = 1;
            
            $restant_produit = $qte_a_utiliser % $plat_par_carton_val;
            
            if ($restant_produit > 0 && isset($misenplat_ids[$produit_id])) {
                $misenplat_list = explode(',', $misenplat_ids[$produit_id]);
                $misenplat_list = array_map('intval', $misenplat_list);
                
                // Répartir le restant sur les misenplat (logique simplifiée)
                $plats_par_misenplat = floor($restant_produit / count($misenplat_list));
                $reste_distribution = $restant_produit % count($misenplat_list);
                
                $index = 0;
                foreach ($misenplat_list as $mp_id) {
                    $plats_du_detail = $plats_par_misenplat;
                    if ($index < $reste_distribution) {
                        $plats_du_detail++;
                    }
                    
                    if ($plats_du_detail > 0) {
                        $sqlLink = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat
                                    (fk_lotdet_mixte, fk_misenplat, fk_product, nb_plat)
                                    VALUES (
                                        ".(int)$id_lotdet_mixte.",
                                        ".(int)$mp_id.",
                                        ".(int)$produit_id.",
                                        ".(int)$plats_du_detail."
                                    )";
                        
                        if (!$db->query($sqlLink)) {
                            throw new Exception("Erreur liaison lotdet mixte : ".$db->lasterror());
                        }
                    }
                    $index++;
                }
            }
        }
    } else {
        // Si pas de produit mixte, supprimer les anciens
        $sqlDeleteMixte = "DELETE FROM ".MAIN_DB_PREFIX."pech_lotdet 
                          WHERE fk_lot = ".((int)$id_lot)."
                          AND EXISTS (
                              SELECT 1 FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat lm
                              WHERE lm.fk_lotdet_mixte = ".MAIN_DB_PREFIX."pech_lotdet.rowid
                          )";
        $db->query($sqlDeleteMixte);
        
        $sqlDeleteMixteLinks = "DELETE lm FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat lm
                               INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = lm.fk_lotdet_mixte
                               WHERE ld.fk_lot = ".((int)$id_lot);
        $db->query($sqlDeleteMixteLinks);
    }
    
    $db->commit();
    setEventMessages("✅ Lot modifié avec succès.", null, 'mesgs');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
    
} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
    
    // Redirection vers le formulaire avec les erreurs
    header("Location: edit_lot.php?id=".$id_lot);
    exit;
}

// ===============================
// AFFICHAGE D'ERREUR (si on arrive ici)
// ===============================
llxHeader('', $langs->trans("LotUpdateError"));
print load_fiche_titre($langs->trans("ErrorDuringUpdate"), '', 'fa-exclamation-triangle');

print '<div class="error" style="max-width: 800px; margin: 20px auto; padding: 20px;">';
print '<h3><i class="fa fa-exclamation-circle"></i> '.$langs->trans("AnErrorOccurred").'</h3>';
print '<p>'.$langs->trans("PleaseTryAgainOrContactAdministrator").'</p>';
print '<div class="center" style="margin-top: 20px;">';
print '<a class="button" href="edit_lot.php?id='.$id_lot.'">'.$langs->trans("ReturnToForm").'</a>';
print '&nbsp;&nbsp;';
print '<a class="button button-cancel" href="detail_lot.php?id='.$id_lot.'">'.$langs->trans("ReturnToDetail").'</a>';
print '</div>';
print '</div>';

llxFooter();
$db->close();