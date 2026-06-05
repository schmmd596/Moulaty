<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $langs, $user;
$langs->load("abricot@abricot");

$form = new Form($db);

// ===============================
// PARAMÈTRES
// ===============================
$action = GETPOST('action', 'alpha');
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$commentaire = GETPOST('commentaire', 'restricthtml');
$nombre_plat = GETPOST('nombre_plat', 'array'); // array: produit_id => quantité
$plat_par_carton = GETPOST('plat_par_carton', 'array'); // array: produit_id => plats par carton
$misenplat_ids = GETPOST('misenplat_ids', 'array'); // array: produit_id => liste d'IDs séparés par virgules
$nb_plats_mixte = GETPOST('nb_plats_mixte', 'int');
$plat_par_carton_mixte = GETPOST('plat_par_carton_mixte', 'int');
$selected_ids = GETPOST('select_misenplat', 'array');
$date_creation = GETPOST('date_creation', 'none'); // format attendu : YYYY-MM-DDTHH:MM

// ===============================
// VÉRIFICATIONS
// ===============================
if ($action != 'save') {
    setEventMessages($langs->trans("InvalidAction"), null, 'errors');
    header("Location: tunnel.php");
    exit;
}

if (empty($fk_entrepot)) {
    setEventMessages($langs->trans("WarehouseRequired"), null, 'errors');
    header("Location: tunnel.php");
    exit;
}

if (empty($nombre_plat) || !is_array($nombre_plat)) {
    setEventMessages($langs->trans("NoProductsSelected"), null, 'errors');
    header("Location: tunnel.php");
    exit;
}

// ===============================
// RÉCUPÉRATION DES DONNÉES POUR VALIDATION
// ===============================
// Récupérer les misenplat sélectionnées pour validation
$selected_misenplats = [];
if (!empty($selected_ids)) {
    $sql_check = "SELECT m.rowid, m.fk_product, m.nombre_plat, m.nombre_plat_sortie, 
                         m.poids_plat, p.label as produit_label
                  FROM ".MAIN_DB_PREFIX."pech_misenplat AS m
                  LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = m.fk_product
                  WHERE m.rowid IN (".implode(',', array_map('intval', $selected_ids)).")";
    
    $res_check = $db->query($sql_check);
    if ($res_check) {
        while ($obj = $db->fetch_object($res_check)) {
            $selected_misenplats[$obj->rowid] = $obj;
        }
    }
}

// ===============================
// ACTION CONFIRMÉE
// ===============================
$resqlll2 = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot ORDER BY rowid DESC LIMIT 1");
$ref1 = '';
if ($resqlll2 && $db->num_rows($resqlll2) > 0) {
    $obj2 = $db->fetch_object($resqlll2);
    $ref1 = $obj2->ref;
}
$nextNumber = (!empty($ref1) && preg_match('/Lot-(\d+)/', $ref1, $matches)) ? ((int)$matches[1] + 1) : 1;
$ref1 = 'Lot-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

$db->begin();
try {
    // 🆕 CRÉATION DU LOT
    $ref = $ref1;
    $sqlInsertLot = "INSERT INTO ".MAIN_DB_PREFIX."pech_lot(ref, fk_user_create, fk_entrepot, commentaire, date_creation)
                     VALUES ('".$db->escape($ref)."', ".((int)$user->id).", ".((int)$fk_entrepot).", '".$db->escape($commentaire)."', '".$date_creation."' )";
    
    if (!$db->query($sqlInsertLot)) {
        throw new Exception("Erreur création lot : ".$db->lasterror());
    }

    $id_lot = $db->last_insert_id(MAIN_DB_PREFIX."pech_lot");

    // 💾 INSÉRER LES LIGNES PRODUITS REGROUPÉES
    $total_restant_mixte = 0;
    $poids_moyen_mixte = 0;
    $produits_count_mixte = 0;

    foreach ($nombre_plat as $produit_id => $qte_a_utiliser) {
        if (empty($qte_a_utiliser) || $qte_a_utiliser <= 0) {
            continue;
        }

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

        // Insérer la ligne du produit
        $sqlDet = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet
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
        
        if (!$db->query($sqlDet)) {
            throw new Exception("Erreur insertion lotdet : ".$db->lasterror());
        }

        // Mettre à jour le nombre de plats sortis dans chaque misenplat
        $plats_a_distribuer = $qte_a_utiliser - $restant;
        
        if ($plats_a_distribuer > 0) {
            // Répartir proportionnellement
            $sql_distrib = "SELECT rowid, (nombre_plat - nombre_plat_sortie) as disponible
                           FROM ".MAIN_DB_PREFIX."pech_misenplat
                           WHERE rowid IN (".implode(',', $misenplat_list).")
                           AND (nombre_plat - nombre_plat_sortie) > 0
                           ORDER BY disponible DESC";
            
            $res_distrib = $db->query($sql_distrib);
            if ($res_distrib) {
                $distributions = [];
                $total_disponible_distrib = 0;
                
                while ($dist = $db->fetch_object($res_distrib)) {
                    $distributions[] = $dist;
                    $total_disponible_distrib += $dist->disponible;
                }
                
                foreach ($distributions as $dist) {
                    if ($plats_a_distribuer <= 0) break;
                    
                    $ratio = $dist->disponible / $total_disponible_distrib;
                    $plats_a_prendre = min(
                        floor($plats_a_distribuer * $ratio),
                        $dist->disponible
                    );
                    
                    if ($plats_a_prendre > 0) {
                        /*$sql_update = "UPDATE ".MAIN_DB_PREFIX."pech_misenplat
                                      SET nombre_plat_sortie = nombre_plat_sortie + ".((int)$plats_a_prendre)."
                                      WHERE rowid = ".((int)$dist->rowid);
                        
                        if (!$db->query($sql_update)) {
                            throw new Exception("Erreur mise à jour misenplat : ".$db->lasterror());
                        }*/
                        
                        $plats_a_distribuer -= $plats_a_prendre;
                    }
                }
            }
        }

        // Cumul pour produit mixte
        $total_restant_mixte += $restant;
        $poids_moyen_mixte += $poids_moyen;
        $produits_count_mixte++;
    }

    // 💾 INSÉRER LE PRODUIT MIXTE
    $total_restants = GETPOST('nb_plats_mixte', 'int');   

    if ($total_restants > 0) {
        // Récupération de l'ID du produit "mixte"
        $sqlProductMixte = "SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE label = 'MIXTE' LIMIT 1";
        $resMixte = $db->query($sqlProductMixte);
        $fk_product_mixte = null;
        
        if ($resMixte && $db->num_rows($resMixte) > 0) {
            $fk_product_mixte = $db->fetch_object($resMixte)->rowid;
        }

        $plat_par_carton_mixte_val = GETPOST('plat_par_carton_mixte', 'int');
        if ($plat_par_carton_mixte_val <= 0) $plat_par_carton_mixte_val = 1;

        $nb_cartons_mixte = intdiv($total_restants, $plat_par_carton_mixte_val);
        $restant_mixte = $total_restants % $plat_par_carton_mixte_val;

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
                
                // Répartir le restant sur les misenplat
                $sql_distrib_mixte = "SELECT rowid, (nombre_plat - nombre_plat_sortie) as disponible
                                     FROM ".MAIN_DB_PREFIX."pech_misenplat
                                     WHERE rowid IN (".implode(',', $misenplat_list).")
                                     AND (nombre_plat - nombre_plat_sortie) > 0
                                     ORDER BY disponible DESC";
                
                $res_distrib_mixte = $db->query($sql_distrib_mixte);
                if ($res_distrib_mixte) {
                    $distributions_mixte = [];
                    $total_disponible_mixte = 0;
                    
                    while ($dist = $db->fetch_object($res_distrib_mixte)) {
                        $distributions_mixte[] = $dist;
                        $total_disponible_mixte += $dist->disponible;
                    }
                    
                    foreach ($distributions_mixte as $dist) {
                        if ($restant_produit <= 0) break;
                        
                        $ratio = $dist->disponible / $total_disponible_mixte;
                        $plats_du_detail = floor($restant_produit * $ratio);
                        
                        if ($plats_du_detail > 0) {
                            $sqlLink = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat
                                        (fk_lotdet_mixte, fk_misenplat, fk_product, nb_plat)
                                        VALUES (
                                            ".(int)$id_lotdet_mixte.",
                                            ".(int)$dist->rowid.",
                                            ".(int)$produit_id.",
                                            ".(int)$plats_du_detail."
                                        )";
                            
                            if (!$db->query($sqlLink)) {
                                throw new Exception("Erreur liaison lotdet mixte : ".$db->lasterror());
                            }
                            
                            $restant_produit -= $plats_du_detail;
                        }
                    }
                }
            }
        }
    }

    $db->commit();
    setEventMessages("✅ Lot créé avec succès. Total restants pour mixte : ".$total_restants , null, 'mesgs');
    header("Location: ../Lots/detail_lot.php?id=".$id_lot);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
    
    // Redirection vers le formulaire
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// ===============================
// AFFICHAGE D'ERREUR (si on arrive ici)
// ===============================
llxHeader('', $langs->trans("LotCreationError"));
print load_fiche_titre($langs->trans("ErrorDuringCreation"), '', 'fa-exclamation-triangle');

print '<div class="error" style="max-width: 800px; margin: 20px auto; padding: 20px;">';
print '<h3><i class="fa fa-exclamation-circle"></i> '.$langs->trans("AnErrorOccurred").'</h3>';
print '<p>'.$langs->trans("PleaseTryAgainOrContactAdministrator").'</p>';
print '<div class="center" style="margin-top: 20px;">';
print '<a class="button" href="tunnel.php">'.$langs->trans("ReturnToSelection").'</a>';
print '&nbsp;&nbsp;';
print '<a class="button button-cancel" href="'.$_SERVER['HTTP_REFERER'].'">'.$langs->trans("ReturnToForm").'</a>';
print '</div>';
print '</div>';

llxFooter();
$db->close();