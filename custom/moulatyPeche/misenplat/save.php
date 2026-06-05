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

$action = GETPOST('action_save', 'alpha');
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$qte_plater = GETPOST('qte_plater', 'array');
$nb_plat = GETPOST('nb_plat', 'array');
$commentaire = GETPOST('commentaire', 'alpha');

$selected = GETPOST('selected', 'array');
$selected_str = implode(',', $selected);
$date_creation = GETPOST('date_creation', 'none'); // format attendu : YYYY-MM-DDTHH:MM
//print $selected_str;
// Vérification de sécurité
if (empty($fk_entrepot) || empty($qte_plater)) {
    setEventMessages($langs->trans("Données manquantes."), null, 'errors');
    header("Location: ./list.php");
    exit;
}

// ============================================================================
// DÉBUT TRANSACTION
// ============================================================================
$db->begin();
$error = 0;
$nb_insert = 0;

$resqll2 = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."pech_bon_misenplat ORDER BY rowid DESC LIMIT 1");
$ref1 = '';
    if ($resqll2 && $db->num_rows($resqll2) > 0) {
        $obj2 = $db->fetch_object($resqll2);
        $ref1 = $obj2->ref;
    }
    $nextNumber = (!empty($ref1) && preg_match('/BMP-(\d+)/', $ref1, $matches)) ? ((int)$matches[1] + 1) : 1;
$ref1 = 'BMP-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
//$ref_b = $reception ? $reception->ref : $ref1;


try {
    // ------------------------------------------------------------------------
    // 1️⃣ Création du bon de mise en plat
    // ------------------------------------------------------------------------
    $ref_bon = $ref1;//'BMP' . date('YmdHis');
    $date_creation_sql = str_replace('T', ' ', $date_creation); // convertit le T en espace
    $sql_bon = "INSERT INTO ".MAIN_DB_PREFIX."pech_bon_misenplat
                (ref, fk_entrepot,fk_receptiondet, fk_user, statut, commentaire, date_creation)
                VALUES (
                    '".$db->escape($ref_bon)."',
                    ".((int) $fk_entrepot).",
                    '".$selected_str."',
                    ".((int) $user->id).",
                    0,
                    '".$db->escape($commentaire)."',
                    '".$db->escape($date_creation_sql)."'
                )";

    $res_bon = $db->query($sql_bon);
    if (!$res_bon) {
        throw new Exception("Erreur lors de la création du bon de mise en plat");
    }

    $fk_bon_misenplat = $db->last_insert_id(MAIN_DB_PREFIX."pech_bon_misenplat");

    // ------------------------------------------------------------------------
    // 2️⃣ Insertion des lignes de mise en plat (groupées par produit)
    // ------------------------------------------------------------------------
    foreach ($qte_plater as $fk_product => $qte) {
        $qte = (float) $qte;
        $nb = (int) ($nb_plat[$fk_product] ?? 0);

        if ($qte <= 0 || $nb <= 0) continue;

        $poids_par_plat = $qte / $nb;

        // Insertion dans pech_misenplat
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."pech_misenplat
                (fk_bon_misenplat, fk_congelateur, fk_product, nombre_plat, poids_plat, statut, pointeur, commentaire, date_creation)
                VALUES (
                    ".$fk_bon_misenplat.",
                    ".((int) $fk_entrepot).",
                    ".((int) $fk_product).",
                    ".$nb.",
                    ".$poids_par_plat.",
                    0,
                    '".$db->escape($user->login)."',
                    '".$db->escape("Créé automatiquement dans le bon ".$ref_bon)."',
                    NOW()
                )";

        $resql = $db->query($sql);
        if (!$resql) {
            throw new Exception("Erreur lors de l'insertion de la ligne mise en plat");
        }

        $fk_misenplat = $db->last_insert_id(MAIN_DB_PREFIX."pech_misenplat");

        // --------------------------------------------------------------------
        // 3️⃣ Insertion des plats individuels
        // --------------------------------------------------------------------
        /*for ($i = 0; $i < $nb; $i++) {
            $sql2 = "INSERT INTO ".MAIN_DB_PREFIX."pech_plat
                     (fk_misenplat, fk_product, poids, statut, date_creation)
                     VALUES (".$fk_misenplat.", ".$fk_product.", ".$poids_par_plat.", 0, NOW())";
            if (!$db->query($sql2)) {
                throw new Exception("Erreur lors de l'ajout des plats individuels");
            }
        }*/
        // --------------------------------------------------------------------
        // 3️⃣ Insertion des plats individuels (selon receptions sélectionnées)
        // --------------------------------------------------------------------

        $poids_total_restant = $qte; // Quantité totale à mettre en plat
        $poids_par_plat = $qte / $nb;

        // Récupérer les receptions disponibles pour ce produit dans $selected
        $sqlrec = "SELECT rd.rowid AS fk_receptiondet, 
                        rd.prix_moyen, 
                        rd.poids_net, 
                        rd.poids_plater
                FROM ".MAIN_DB_PREFIX."pech_receptiondet AS rd
                WHERE rd.rowid IN (".$db->escape($selected_str).")
                    AND rd.fk_product = ".((int)$fk_product)."
                ORDER BY rd.rowid ASC";

        $resrec = $db->query($sqlrec);
        if (!$resrec) {
            throw new Exception("Erreur lors de la récupération des réceptions pour le produit ".$fk_product);
        }

        while ($objrec = $db->fetch_object($resrec)) {
            if ($poids_total_restant <= 0) break;

            $poids_restant_reception = $objrec->poids_net - $objrec->poids_plater;
            if ($poids_restant_reception <= 0) continue; // réception déjà épuisée

            $nb_plats_possibles = floor($poids_restant_reception / $poids_par_plat);
            if ($nb_plats_possibles <= 0) continue;

            for ($i = 0; $i < $nb_plats_possibles; $i++) {
                if ($poids_total_restant <= 0) break;

                // Insertion du plat
                $prix_moyen_plat = $objrec->prix_moyen * $poids_par_plat;
                $sql2 = "INSERT INTO ".MAIN_DB_PREFIX."pech_plat
                        (fk_misenplat, fk_product, poids, prix_moyen, statut, date_creation)
                        VALUES (
                            ".$fk_misenplat.",
                            ".$fk_product.",
                            ".$poids_par_plat.",
                            ".$prix_moyen_plat .",
                            0,
                            NOW()
                        )";

                if (!$db->query($sql2)) {
                    throw new Exception("Erreur lors de l'ajout d'un plat pour la réception ".$objrec->fk_receptiondet);
                }

                // Mise à jour du poids utilisé dans cette réception
                /*$sql_update = "UPDATE ".MAIN_DB_PREFIX."pech_receptiondet
                            SET poids_plater = poids_plater + ".$poids_par_plat."
                            WHERE rowid = ".$objrec->fk_receptiondet;
                if (!$db->query($sql_update)) {
                    throw new Exception("Erreur lors de la mise à jour du poids plater pour réception ".$objrec->fk_receptiondet);
                }*/

                $poids_total_restant -= $poids_par_plat;
            }
        }

        // Si du poids reste mais aucune réception disponible
        if ($poids_total_restant > 0) {
            throw new Exception("Poids restant non attribué pour le produit ".$fk_product." (poids restant : ".$poids_total_restant.")");
        }

        

        $nb_insert++;
    }

    // ------------------------------------------------------------------------
    // 4️⃣ Validation et fin
    // ------------------------------------------------------------------------
    if ($error == 0) {
        $db->commit();
        setEventMessages($langs->trans("Bon de mise en plat créé avec succès : ").$ref_bon." (".$nb_insert." produits) ", null, 'mesgs');
        header("Location: ./detail_mis.php?id=".$fk_bon_misenplat);
        exit;
    } else {
        $db->rollback();
        setEventMessages($langs->trans("Erreur lors de la mise en plat."), null, 'errors');
    }

} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
    dol_syslog("Erreur mise en plat : ".$e->getMessage(), LOG_ERR);
}

llxHeader('', $langs->trans("Mise en Plat"));
print load_fiche_titre($langs->trans("Erreur lors de la Mise en Plat"), '', 'fa-utensils');
print '<div class="error">'.$langs->trans("Une erreur s’est produite pendant le traitement.").'</div>';
print '<div class="center"><a class="button" href="./misenplat_select.php">'.$langs->trans("Retour").'</a></div>';

llxFooter();
$db->close();
