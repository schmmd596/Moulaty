<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
global $db, $user, $langs, $conf;
$langs->load("abricot@abricot");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    accessforbidden();
}
$total = 0;
$db->begin();

try {
    // 🔹 Données générales
    $fk_entrepot = (int)GETPOST('fk_entrepot', 'int');
    $commentaire = trim(GETPOST('commentaire', 'restricthtml'));
    $id_lot = (int)GETPOST('id_lot', 'int');

    if ($fk_entrepot <= 0) throw new Exception("⚠️ Entrepôt non spécifié.");
    if ($id_lot <= 0) throw new Exception("⚠️ Lot introuvable.");
    $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".$id_lot;
    $resql = $db->query($sql);

    if ($resql && $db->num_rows($resql) > 0) {
        $ref_lot = $db->fetch_object($resql)->ref;
    } else {
        $ref_lot = null; // Lot introuvable
    }
    // 🔹 Génération référence du bon d’entrée
    $ref = $ref_lot;

    $sql = "INSERT INTO ".MAIN_DB_PREFIX."pech_bonentree(ref, fk_user_create, fk_entrepot, commentaire, statut, entity)
            VALUES (
                '".$db->escape($ref)."',
                ".((int)$user->id).",
                ".$fk_entrepot.",
                '".$db->escape($commentaire)."',
                0,
                ".$conf->entity."
            )";

    if (!$db->query($sql)) throw new Exception("Erreur création bon d’entrée : ".$db->lasterror());

    $fk_bonentree = $db->last_insert_id(MAIN_DB_PREFIX."pech_bonentree");

    // =========================================================================
    // 🔹 INSERTION DES PRODUITS DU LOT
    // =========================================================================
    $prod_fk_product = GETPOST('prod_fk_product', 'array');
    $prod_nb_carton = GETPOST('prod_nb_carton', 'array');
    $prod_poids_carton = GETPOST('prod_poids_carton', 'array');
    

    if (is_array($prod_fk_product) && count($prod_fk_product) > 0) {
        foreach ($prod_fk_product as $i => $fk_prod) {
            if (empty($fk_prod)) continue;

            $sqlProd = "INSERT INTO ".MAIN_DB_PREFIX."pech_bonentree_detprod
                        (fk_bonentree, fk_product, nb_carton, poids_carton, statut, entity)
                        VALUES (
                            ".((int)$fk_bonentree).",
                            ".((int)$fk_prod).",
                            ".((float)$prod_nb_carton[$i]).",
                            ".((float)$prod_poids_carton[$i]).",
                            0,
                            ".$conf->entity."
                        )";

            if (!$db->query($sqlProd)) throw new Exception("Erreur ajout produit : ".$db->lasterror());

            
        }
    }

    // =========================================================================
    // 🔹 INSERTION DES SERVICES (automatiques et manuels)
    // =========================================================================
    $ids = GETPOST('ids', 'array'); // contient les id des services sélectionnés
    $qte = GETPOST('qte', 'array');
    $pu = GETPOST('pu', 'array');

    if (is_array($ids) && count($ids) > 0) {
    foreach ($ids as $j => $id_service) {
        if (empty($id_service)) continue;

        $quant = (float)str_replace(',', '.', $qte[$j] ?? 0);
        $prix  = (float)str_replace(',', '.', $pu[$j] ?? 0);

        // 🔹 Utilisation de la classe Product
        $prod = new Product($db);
        if ($prod->fetch($id_service) > 0) {
            $desc = $prod->ref . ' - ' . $prod->label;
        } else {
            $desc = 'Service #' . $id_service; // fallback si service introuvable
        }

        $sqlServ = "INSERT INTO ".MAIN_DB_PREFIX."pech_bonentree_detserv
                    (fk_bonentree, description, qte, pu, statut, entity)
                    VALUES (
                        ".((int)$fk_bonentree).",
                        '".$db->escape($desc)."',
                        ".$quant.",
                        ".$prix.",
                        0,
                        ".$conf->entity."
                    )";

        if (!$db->query($sqlServ)) throw new Exception("Erreur ajout service : ".$db->lasterror());
        $total += ($quant * $prix);
        // 🔹 Décrémenter le stock si c’est un produit physique (type != 1)
            if ($prod->type != 1) {
                // Récupérer l'entrepôt lié au produit
                //$fk_entrepot_prod = $prod->fk_stock_entrepot ?? $fk_entrepot; // fallback à l’entrepôt global
                /*$resStock = $prod->correct_stock(
                    $fk_entrepot_prod,                  // ID de l'entrepôt
                    -$quant,                            // Quantité à retirer
                    "Stock pour bon d'entrée $ref",     // Libellé du mouvement
                    0,                                  // Date actuelle
                    $user
                );*/
                $sql_warehouse = "SELECT fk_default_warehouse 
                                  FROM ".MAIN_DB_PREFIX."product 
                                  WHERE rowid = ".((int) $prod->id);
                $resql_wh = $db->query($sql_warehouse);
                if ($resql_wh && $db->num_rows($resql_wh) > 0) {
                    $obj_wh = $db->fetch_object($resql_wh);
                    $fk_entrepot_prod = $obj_wh->fk_default_warehouse;
                } else {
                    // Si aucun entrepôt par défaut défini, tu peux définir un fallback
                    $fk_entrepot_prod = $fk_entrepot ?? 1;
                }
                $stock = new MouvementStock($db);

                $resStock = $stock->livraison(
                    $user,                        // utilisateur
                    $prod->id,              // produit
                    $fk_entrepot_prod,                 // entrepôt
                    $quant,                   // quantité
                    0,                             // prix (facultatif)
                    "Stock pour bon d'entrée $ref" // libellé
                );
                if ($resStock < 0) throw new Exception($stock->error);
                //if ($resStock < 0) throw new Exception("Erreur mise à jour stock produit #$id_service : ".$prod->error);
            }
    }
}

// =========================================================================
// 🔹 FACTURATION FOURNISSEUR POUR LES SERVICES
// =========================================================================
/*if (is_array($ids) && count($ids) > 0) {

    // Récupérer l'ID du fournisseur "Générale"
    $sqlGen = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom='Generale' AND client=0 AND fournisseur=1";
    $resGen = $db->query($sqlGen);

    if ($resGen && $db->num_rows($resGen) > 0) {
        $objGen = $db->fetch_object($resGen);
        $fk_fourn_gen = $objGen->rowid;
    } else {
        // Si le fournisseur n'existe pas, on le crée
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        $soc = new Societe($db);
        $soc->nom = 'Generale';
        $soc->fournisseur = 1;
        $soc->client = 0;
        $soc->status = 1;
        $res = $soc->create($user);
        if ($res > 0) {
            $fk_fourn_gen = $soc->id;
        } else {
            throw new Exception("Impossible de créer le fournisseur 'Generale' : ".$soc->error);
        }
    }

    // Créer la facture fournisseur
    $factureGeneral = new FactureFournisseur($db);
    $factureGeneral->socid = $fk_fourn_gen;
    $factureGeneral->ref_supplier = $ref.'-'.time(); // même référence que le bon d'entrée
    $factureGeneral->libelle = "Facture services - Bon d'entrée $ref";
    $factureGeneral->date = dol_now();
    $factureGeneral->entity = $conf->entity;

    $resFactGen = $factureGeneral->create($user);
    if ($resFactGen < 0) throw new Exception($factureGeneral->error);

    // Ajouter les lignes services
    foreach ($ids as $j => $id_service) {
        if (empty($id_service)) continue;

        // Vérifier si le produit est bien un service
        $sqlType = "SELECT fk_product_type FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".(int)$id_service;
        $resType = $db->query($sqlType);
        if ($resType && $db->num_rows($resType) > 0) {
            $prodType = $db->fetch_object($resType)->fk_product_type;
            if ($prodType != 1) continue; // 1 = service, skip si ce n'est pas un service
        } else {
            continue; // produit introuvable, ignorer
        }

        $quant = (float) str_replace(',', '.', $qte[$j] ?? 0);
        $prix  = (float) str_replace(',', '.', $pu[$j] ?? 0);

        

        $resAdd = $factureGeneral->addline('', $prix, 0, 0, 0, $quant, $id_service);
        if ($resAdd < 0) throw new Exception("Erreur ajout ligne facture fournisseur : ".$factureGeneral->error);
    }

    // Valider la facture fournisseur
    if ($factureGeneral->validate($user) < 0) throw new Exception("Erreur validation facture fournisseur : ".$factureGeneral->error);
}
*/

$service_ids = [];
if (is_array($ids) && count($ids) > 0) {
    foreach ($ids as $j => $id_service) {
        if (empty($id_service)) continue;

        // Vérifier si le produit est un service
        $sqlType = "SELECT fk_product_type FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".(int)$id_service;
        $resType = $db->query($sqlType);
        if ($resType && $db->num_rows($resType) > 0) {
            $prodType = $db->fetch_object($resType)->fk_product_type;
            if ($prodType == 1) { // 1 = service
                $service_ids[] = $j; // conserver l'index pour qte/pu
            }
        }
    }
}

// Créer la facture uniquement si au moins un service
if (count($service_ids) > 0) {

    // 🔹 Récupérer ou créer le fournisseur "Générale"
    $sqlGen = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom='Generale' AND client=0 AND fournisseur=1";
    $resGen = $db->query($sqlGen);

    if ($resGen && $db->num_rows($resGen) > 0) {
        $objGen = $db->fetch_object($resGen);
        $fk_fourn_gen = $objGen->rowid;
    } else {
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        $soc = new Societe($db);
        $soc->nom = 'Generale';
        $soc->fournisseur = 1;
        $soc->client = 0;
        $soc->status = 1;
        $res = $soc->create($user);
        if ($res > 0) {
            $fk_fourn_gen = $soc->id;
        } else {
            throw new Exception("Impossible de créer le fournisseur 'Generale' : ".$soc->error);
        }
    }

    // 🔹 Créer la facture fournisseur
    $factureGeneral = new FactureFournisseur($db);
    $factureGeneral->socid = $fk_fourn_gen;
    $factureGeneral->ref_supplier = $ref.'-'.time();
    $factureGeneral->libelle = "Facture services - Bon d'entrée $ref";
    $factureGeneral->date = dol_now();
    $factureGeneral->entity = $conf->entity;

    $resFactGen = $factureGeneral->create($user);
    if ($resFactGen < 0) throw new Exception($factureGeneral->error);

    // 🔹 Ajouter les lignes services
    foreach ($service_ids as $index) {
        $id_service = $ids[$index];
        $quant      = (float) str_replace(',', '.', $qte[$index] ?? 0);
        $prix       = (float) str_replace(',', '.', $pu[$index] ?? 0);

        $resAdd = $factureGeneral->addline('', $prix, 0, 0, 0, $quant, $id_service);
        if ($resAdd < 0) throw new Exception("Erreur ajout ligne facture fournisseur : ".$factureGeneral->error);
    }

    // 🔹 Valider la facture fournisseur
    if ($factureGeneral->validate($user) < 0) {
        throw new Exception("Erreur validation facture fournisseur : ".$factureGeneral->error);
    }
}
    // =========================================================================
    // 🔹 COMMIT
    // =========================================================================
     // 🔹 MISE À JOUR DU LOT
    $sqlUpdateLot = "UPDATE " . MAIN_DB_PREFIX . "pech_lot
                     SET fk_bonentree = " . (int)$fk_bonentree . ",
                     total_frais = total_frais + ".(float)$total."
                     WHERE rowid = " . (int)$id_lot;
    if (!$db->query($sqlUpdateLot)) throw new Exception("Erreur lors de la mise à jour du lot");
    //$id_lot = 123; // <-- l’ID du lot à traiter

// 1️⃣ Récupérer total_frais du lot
$sql = "SELECT total_frais FROM " . MAIN_DB_PREFIX . "pech_lot WHERE rowid = ".((int)$id_lot);
$res = $db->query($sql);
if (!$res || $db->num_rows($res) == 0) {
    exit("Lot introuvable");
}
$lot = $db->fetch_object($res);
$total_frais = (float)$lot->total_frais;

// 2️⃣ Compter tous les cartons du lot
$sql = "SELECT COUNT(c.rowid) AS nb_cartons
        FROM " . MAIN_DB_PREFIX . "pech_carton c
        JOIN " . MAIN_DB_PREFIX . "pech_lotdet d ON c.fk_lotdet = d.rowid
        WHERE d.fk_lot = ".((int)$id_lot);
$res = $db->query($sql);
$obj = $db->fetch_object($res);
$nb_cartons = (int)$obj->nb_cartons;

if ($nb_cartons == 0) {
    exit("Aucun carton trouvé pour ce lot.");
}

// 3️⃣ Calcul du frais par carton
$frais_par_carton = $total_frais / $nb_cartons;

// 4️⃣ Mise à jour de tous les cartons
$sql = "UPDATE " . MAIN_DB_PREFIX . "pech_carton c
        JOIN " . MAIN_DB_PREFIX . "pech_lotdet d ON c.fk_lotdet = d.rowid
        SET c.frais = ".price2num($frais_par_carton)."
        WHERE d.fk_lot = ".((int)$id_lot);

if ($db->query($sql)) {
    //print "✅ Frais de ".$frais_par_carton." réparti sur ".$nb_cartons." cartons du lot #".$id_lot;
} else {
    print "❌ Erreur lors de la mise à jour : ".$db->lasterror();
}

    $db->commit();

    setEventMessages("✅ Bon d’entrée <strong>$ref</strong> créé avec succès.", null, 'mesgs');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}
