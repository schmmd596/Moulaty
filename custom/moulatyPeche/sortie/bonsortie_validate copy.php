<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $user, $conf, $langs;

$langs->load("abricot@abricot");

if (!$user->rights->stock->mouvement->creer) accessforbidden();

// ============================================================================
// 🔹 RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
// ============================================================================
$id_sortie = GETPOST('id_sortie', 'int');
$qtes = GETPOST('qte', 'array');
$pus = GETPOST('pu', 'array');
$ids = GETPOST('ids', 'array'); // IDs des services sélectionnés

if (!$id_sortie) {
    setEventMessages("Identifiant de sortie manquant.", null, 'errors');
    header("Location: ".dol_buildpath('/custom/peche/sortie/fiche.php', 1));
    exit;
}

// 🔹 Récupération de la référence de la sortie
$ref = '';
$sql = "SELECT ref FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid = ".$id_sortie;
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    $ref = $db->fetch_object($resql)->ref;
} else {
    $ref = "BS" . date("YmdHis");
}

// ============================================================================
// 🔹 CRÉATION DU BON DE SORTIE
// ============================================================================
$db->begin();

try {
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."pech_bonsortie 
            (ref, fk_user_create, fk_entrepot_source, fk_entrepot_dest, commentaire, statut, entity)
            SELECT 
                '".$db->escape($ref)."',
                ".$user->id.",
                s.fk_entrepot_source,
                s.fk_entrepot_dest,
                s.commentaire,
                0,
                ".$conf->entity."
            FROM ".MAIN_DB_PREFIX."pech_sortie AS s
            WHERE s.rowid = ".$id_sortie;

    $res = $db->query($sql);
    if (!$res) throw new Exception("Erreur lors de la création du bon de sortie : ".$db->lasterror());

    $id_bonsortie = $db->last_insert_id(MAIN_DB_PREFIX."pech_bonsortie");

    // ========================================================================
    // 🔸 Enregistrement des PRODUITS liés à la sortie
    // ========================================================================
    $sql_prod = "SELECT p.fk_product, p.nb_carton, p.poids_total, pr.label
                 FROM ".MAIN_DB_PREFIX."pech_sortiedetprod AS p
                 LEFT JOIN ".MAIN_DB_PREFIX."product AS pr ON pr.rowid = p.fk_product
                 WHERE p.fk_sortie = ".$id_sortie;

    $res_prod = $db->query($sql_prod);
    if ($res_prod && $db->num_rows($res_prod) > 0) {
        while ($obj = $db->fetch_object($res_prod)) {
            // 🔹 Valeur cumulée des cartons
            $sql_cartons = "SELECT SUM(c.prix_moyen + c.frais) AS total_carton
                            FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton sc
                            LEFT JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.rowid = sc.fk_carton
                            WHERE sc.fk_sortiedetprod IN (
                                SELECT rowid FROM ".MAIN_DB_PREFIX."pech_sortiedetprod WHERE fk_product = ".$obj->fk_product."
                            )";
            $res_carton = $db->query($sql_cartons);
            $valeur_carton = 0;
            if ($res_carton && $db->num_rows($res_carton) > 0) {
                $valeur_carton = (float) $db->fetch_object($res_carton)->total_carton;
            }

            $sql_insert_prod = "INSERT INTO ".MAIN_DB_PREFIX."pech_bonsortie_detprod
                                (fk_bonentree, fk_product, nb_carton, poids_carton, valeur, commentaire, statut, entity)
                                VALUES (
                                    ".$id_bonsortie.",
                                    ".$obj->fk_product.",
                                    ".(int)$obj->nb_carton.",
                                    ".((float)$obj->poids_total / max($obj->nb_carton, 1)).",
                                    ".((float)$valeur_carton).",
                                    '".$db->escape($obj->label)."',
                                    0,
                                    ".$conf->entity."
                                )";
            if (!$db->query($sql_insert_prod)) {
                throw new Exception("Erreur lors de l'insertion du produit : ".$db->lasterror());
            }
        }
    }

    // ========================================================================
    // 🔸 Enregistrement des SERVICES manuels + calcul total_frais
    // ========================================================================
    $total_frais = 0;
    if (is_array($ids) && count($ids) > 0) {
        foreach ($ids as $k => $fk_service) {
            $qte = (float)$qtes[$k];
            $pu = (float)$pus[$k];
            $total_service = $qte * $pu;
            $total_frais += $total_service;

            if ($fk_service && $qte > 0) {
                // 🔹 Récupérer la description du service
                $sql_desc = "SELECT label FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".$fk_service;
                $res_desc = $db->query($sql_desc);
                $desc = ($res_desc && $db->num_rows($res_desc) > 0) ? $db->fetch_object($res_desc)->label : "Service inconnu";

                $sql_insert_serv = "INSERT INTO ".MAIN_DB_PREFIX."pech_bonsortie_detserv
                                    (fk_bonentree, description, qte, pu, commentaire, statut, entity)
                                    VALUES (
                                        ".$id_bonsortie.",
                                        '".$db->escape($desc)."',
                                        ".$qte.",
                                        ".$pu.",
                                        '',
                                        0,
                                        ".$conf->entity."
                                    )";
                if (!$db->query($sql_insert_serv)) {
                    throw new Exception("Erreur lors de l'insertion du service : ".$db->lasterror());
                }
            }
        }
    }

    // ========================================================================
    // 🔹 CRÉATION AUTOMATIQUE DE LA FACTURE FOURNISSEUR POUR LES SERVICES
    // ========================================================================
    if ($total_frais > 0 && is_array($ids) && count($ids) > 0) {
        require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

        // 🔹 Vérifier ou créer le fournisseur "Générale"
        $sqlGen = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom='Generale' AND client=0 AND fournisseur=1";
        $resGen = $db->query($sqlGen);

        if ($resGen && $db->num_rows($resGen) > 0) {
            $objGen = $db->fetch_object($resGen);
            $fk_fourn_gen = $objGen->rowid;
        } else {
            $soc = new Societe($db);
            $soc->nom = 'Generale';
            $soc->fournisseur = 1;
            $soc->client = 0;
            $soc->status = 1;
            $res = $soc->create($user);
            if ($res > 0) {
                $fk_fourn_gen = $soc->id;
            } else {
                throw new Exception("❌ Impossible de créer le fournisseur 'Generale' : ".$soc->error);
            }
        }

        // 🔹 Créer la facture fournisseur
        $factureGeneral = new FactureFournisseur($db);
        $factureGeneral->socid = $fk_fourn_gen;
        $factureGeneral->ref_supplier = $ref.'-'.time();
        $factureGeneral->libelle = "Facture services - Bon de sortie $ref";
        $factureGeneral->date = dol_now();
        $factureGeneral->entity = $conf->entity;

        $resFactGen = $factureGeneral->create($user);
        if ($resFactGen < 0) throw new Exception("❌ Erreur création facture fournisseur : ".$factureGeneral->error);

        // 🔹 Ajouter les lignes de services dans la facture
        foreach ($ids as $k => $fk_service) {
            $qte = (float)$qtes[$k];
            $pu = (float)$pus[$k];
            if ($fk_service && $qte > 0) {
                $resAdd = $factureGeneral->addline('', $pu, 0, 0, 0, $qte, $fk_service);
                if ($resAdd < 0) throw new Exception("❌ Erreur ajout ligne facture fournisseur : ".$factureGeneral->error);
            }
        }

        // 🔹 Valider la facture fournisseur
        $resVal = $factureGeneral->validate($user);
        if ($resVal < 0) {
            throw new Exception("❌ Erreur validation facture fournisseur : ".$factureGeneral->error);
        }
    }

    // ========================================================================
    // 🔹 Mise à jour de la table sortie (liaison + total_frais)
    // ========================================================================
    $sql_update_sortie = "UPDATE ".MAIN_DB_PREFIX."pech_sortie
                          SET fk_bonsortie = ".$id_bonsortie.",
                              total_frais = total_frais + ".(float)$total_frais."
                          WHERE rowid = ".$id_sortie;
    if (!$db->query($sql_update_sortie)) {
        throw new Exception("Erreur lors de la mise à jour de la sortie : ".$db->lasterror());
    }

    // Validation transaction
    $db->commit();

    setEventMessages("✅ Bon de sortie créé avec succès (Réf : ".$ref.") et frais mis à jour.", null, 'mesgs');
    header("Location: detail.php?id=".$id_sortie);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("❌ ".$e->getMessage(), null, 'errors');
    header("Location: detail.php?id=".$id_sortie);
    exit;
}
?>
