<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

// ============================================================================
// 🔹 INCLUSION DES FONCTIONS DE DEVISE (SIMPLE)
// ============================================================================
// Supposons que ces fonctions sont dans le même répertoire
require_once '../functions.php';

global $db, $user, $langs, $conf;
$langs->load("abricot@abricot");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    accessforbidden();
}

$total_mro = 0; // Total converti en MRO
$taux=1;
$db->begin();

try {
    // 🔹 Données générales
    $fk_entrepot = (int)GETPOST('fk_entrepot', 'int');
    $commentaire = trim(GETPOST('commentaire', 'restricthtml'));
    $id_lot = (int)GETPOST('id_lot', 'int');

    if ($fk_entrepot <= 0) throw new Exception("⚠️ Entrepôt non spécifié.");
    if ($id_lot <= 0) throw new Exception("⚠️ Lot introuvable.");
    
    // ============================================================================
    // 🔹 RÉCUPÉRATION DE LA DEVISE DE L'ENTREPÔT
    // ============================================================================
    $id_devise_entrepot = getDeviseEntrepot($db, $fk_entrepot);
    $code_devise_entrepot = getCodeDeviseFromId($db, $id_devise_entrepot);
    
    $sql = "SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".$id_lot;
    $resql = $db->query($sql);

    if ($resql && $db->num_rows($resql) > 0) {
        $ref_lot = $db->fetch_object($resql)->ref;
    } else {
        $ref_lot = null;
    }
    
    // 🔹 Génération référence du bon d'entrée
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

    if (!$db->query($sql)) throw new Exception("Erreur création bon d'entrée : ".$db->lasterror());

    $fk_bonentree = $db->last_insert_id(MAIN_DB_PREFIX."pech_bonentree");

    // =========================================================================
    // 🔹 INSERTION DES PRODUITS DU LOT (inchangé)
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
    // 🔹 INSERTION DES SERVICES (SIMPLIFIÉ)
    // =========================================================================
    $ids = GETPOST('ids', 'array');
    $qte = GETPOST('qte', 'array');
    $pu = GETPOST('pu', 'array'); // Montants dans la devise de l'entrepôt

    if (is_array($ids) && count($ids) > 0) {
        foreach ($ids as $j => $id_service) {
            if (empty($id_service)) continue;

            $quant = (float)str_replace(',', '.', $qte[$j] ?? 0);
            $prix_devise_entrepot = (float)str_replace(',', '.', $pu[$j] ?? 0); // Prix EN DEVISE ENTREPÔT
            
            // 🔹 CONVERSION SIMPLE : Devise entrepôt → MRO
            $prix_en_mro = $prix_devise_entrepot; // Par défaut (si MRO)
            
            if ($id_devise_entrepot != 1 && $code_devise_entrepot != 'MRO') {
                $conversion = convertToMRO($db, $prix_devise_entrepot, $id_devise_entrepot);
                if ($conversion && $conversion['converted'] > 0) {
                    $prix_en_mro = $conversion['converted'];
                    $taux = $conversion['rate'];
                } else {
                    // Si conversion échoue, garder le prix original (avec avertissement)
                    error_log("ATTENTION: Conversion échouée pour devise ID {$id_devise_entrepot}");
                }
            }
            
            // 🔹 Calcul du total en MRO
            $total_ligne_mro = $quant * $prix_en_mro;
            $total_mro += $total_ligne_mro;
            
            // 🔹 Description du produit
            $prod = new Product($db);
            if ($prod->fetch($id_service) > 0) {
                $desc = $prod->ref . ' - ' . $prod->label;
            } else {
                $desc = 'Service #' . $id_service;
            }

            // 🔹 Insertion SIMPLE (sans champs supplémentaires)
            $sqlServ = "INSERT INTO ".MAIN_DB_PREFIX."pech_bonentree_detserv
                        (fk_bonentree, description, qte, pu, total, statut, entity)
                        VALUES (
                            ".((int)$fk_bonentree).",
                            '".$db->escape($desc)."',
                            ".price2num($quant).",
                            ".(float)($prix_en_mro).",  
                            ".(float)$total_ligne_mro.",
                            0,
                            ".$conf->entity."
                        )";

            if (!$db->query($sqlServ)) throw new Exception("Erreur ajout service : ".$db->lasterror());
            
            // 🔹 Gestion du stock (inchangé)
            if ($prod->type != 1) {
                $sql_warehouse = "SELECT fk_default_warehouse 
                                  FROM ".MAIN_DB_PREFIX."product 
                                  WHERE rowid = ".((int) $prod->id);
                $resql_wh = $db->query($sql_warehouse);
                if ($resql_wh && $db->num_rows($resql_wh) > 0) {
                    $obj_wh = $db->fetch_object($resql_wh);
                    $fk_entrepot_prod = $obj_wh->fk_default_warehouse;
                } else {
                    $fk_entrepot_prod = $fk_entrepot ?? 1;
                }
                
                $stock = new MouvementStock($db);
                $resStock = $stock->livraison(
                    $user,
                    $prod->id,
                    $fk_entrepot_prod,
                    $quant,
                    0,
                    "Stock pour bon d'entrée $ref"
                );
                if ($resStock < 0) throw new Exception($stock->error);
            }
        }
    }

    // =========================================================================
    // 🔹 CRÉATION DE LA FACTURE FOURNISSEUR (inchangé)
    // =========================================================================
    $service_ids = [];
    if (is_array($ids) && count($ids) > 0) {
        foreach ($ids as $j => $id_service) {
            if (empty($id_service)) continue;

            $sqlType = "SELECT fk_product_type FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".(int)$id_service;
            $resType = $db->query($sqlType);
            if ($resType && $db->num_rows($resType) > 0) {
                $prodType = $db->fetch_object($resType)->fk_product_type;
                if ($prodType == 1) {
                    $service_ids[] = $j;
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
        } /*else {
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
            $quant = (float) str_replace(',', '.', $qte[$index] ?? 0);
            $prix = (float) str_replace(',', '.', $pu[$index] ?? 0);
            
            $resAdd = $factureGeneral->addline('', $prix, 0, 0, 0, $quant, $id_service);
            if ($resAdd < 0) throw new Exception("Erreur ajout ligne facture fournisseur : ".$factureGeneral->error);
        }

        // 🔹 Valider la facture fournisseur
        if ($factureGeneral->validate($user) < 0) {
            throw new Exception("Erreur validation facture fournisseur : ".$factureGeneral->error);
        }*/


            // Dans la section de création de facture, ajoutez ces paramètres multi-devise :

// 🔹 Récupérer la devise de l'entrepôt

// 🔹 Créer la facture fournisseur avec multi-devise
$factureGeneral = new FactureFournisseur($db);
$factureGeneral->socid = $fk_fourn_gen;
$factureGeneral->ref_supplier = $ref.'-'.time();
$factureGeneral->libelle = "Facture services - Bon d'entrée $ref";
$factureGeneral->date = dol_now();
$factureGeneral->entity = $conf->entity;

// 🔹 AJOUTER ICI : Définir la devise et le taux de change
/*if ($code_devise_entrepot != $conf->currency) {
    // Récupérer le taux de change pour cette devise
    $sql_rate = "SELECT rate FROM ".MAIN_DB_PREFIX."multicurrency_rate r
                LEFT JOIN ".MAIN_DB_PREFIX."multicurrency c ON c.rowid = r.fk_multicurrency
                WHERE c.code = '".$db->escape($code_devise_entrepot)."'
                ORDER BY r.date_sync DESC LIMIT 1";
    $res_rate = $db->query($sql_rate);
    if ($res_rate && $db->num_rows($res_rate) > 0) {
        $rate = $db->fetch_object($res_rate)->rate;
        
        // Définir la devise et le taux sur la facture
        $factureGeneral->multicurrency_code = $code_devise_entrepot;
        $factureGeneral->multicurrency_tx = $rate;
        
        // Définir aussi le code devise (important pour factures fournisseur)
        $factureGeneral->multicurrency_code = $code_devise_entrepot;
    } else {
        // Si pas de taux trouvé, utiliser la devise par défaut
        $factureGeneral->multicurrency_code = '';
        $factureGeneral->multicurrency_tx = 1;
    }
     $factureGeneral->multicurrency_code = ;
        $factureGeneral->multicurrency_tx = 1;
    
} else {
   
}*/
 $factureGeneral->multicurrency_code = $code_devise_entrepot;
    $factureGeneral->multicurrency_tx = $taux;
    $rate = $taux;
$resFactGen = $factureGeneral->create($user);
if ($resFactGen < 0) throw new Exception($factureGeneral->error);

// 🔹 Ajouter les lignes services avec gestion multi-devise
// Inclure la classe manquante au début de votre script
require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';

// 🔹 Ajouter les lignes services avec gestion multi-devise
foreach ($service_ids as $index) {
    $id_service = $ids[$index];
    $quant      = (float) str_replace(',', '.', $qte[$index] ?? 0);
    $prix       = (float) str_replace(',', '.', $pu[$index] ?? 0);
    
    // Les prix saisis sont déjà dans la devise de l'entrepôt
    $pu_devise = $prix; // Prix dans la devise étrangère
    
    // Calculer le prix dans la devise principale
    $price_main_currency = $prix;
    if ($code_devise_entrepot != $conf->currency && isset($rate)) {
        $price_main_currency = $prix / $rate; // Conversion inverse
    }
    
    // 🔹 APPEL CORRECT à addline() selon la signature
    $resAdd = $factureGeneral->addline(
        '',                     // $desc - Description
        $price_main_currency,   // $pu - Prix HT dans devise principale
        0,                      // $txtva - Taux TVA
        0,                      // $txlocaltax1 - Taxe locale 1
        0,                      // $txlocaltax2 - Taxe locale 2
        $quant,                 // $qty - Quantité
        $id_service,            // $fk_product - ID produit/service
        0,                      // $remise_percent - Remise en pourcentage
        0,                      // $date_start - Date de début
        0,                      // $date_end - Date de fin
        0,                      // $fk_code_ventilation - Code ventilation
        0,                      // $info_bits - Infos bits
        'HT',                   // $price_base_type - Type de prix
        0,                      // $type - Type de ligne
        -1,                     // $rang - Position (-1 pour auto)
        0,                      // $notrigger - Ne pas déclencher triggers
        [],                     // $array_options - Options supplémentaires
        null,                   // $fk_unit - Unité
        0,                      // $origin_id - ID origine
        $pu_devise,             // $pu_devise - Prix dans devise étrangère (IMPORTANT)
        '',                     // $ref_supplier - Référence fournisseur
        0,                      // $special_code - Code spécial
        0,                      // $fk_parent_line - Ligne parent
        0                       // $fk_remise_except - Exception de remise
    );
    
    if ($resAdd < 0) throw new Exception("Erreur ajout ligne facture fournisseur : ".$factureGeneral->error);
}
    }

    // =========================================================================
    // 🔹 MISE À JOUR DU LOT (avec frais en MRO)
    // =========================================================================
    $sqlUpdateLot = "UPDATE " . MAIN_DB_PREFIX . "pech_lot
                     SET fk_bonentree = " . (int)$fk_bonentree . ",
                     total_frais = total_frais + ".price2num($total_mro)."
                     WHERE rowid = " . (int)$id_lot;
    
    if (!$db->query($sqlUpdateLot)) throw new Exception("Erreur lors de la mise à jour du lot");

    // 🔹 RÉPARTITION DES FRAIS SUR LES CARTONS (en MRO)
    $sql = "SELECT total_frais FROM " . MAIN_DB_PREFIX . "pech_lot WHERE rowid = ".((int)$id_lot);
    $res = $db->query($sql);
    if (!$res || $db->num_rows($res) == 0) {
        exit("Lot introuvable");
    }
    $lot = $db->fetch_object($res);
    $total_frais_mro = (float)$lot->total_frais;

    // Compter tous les cartons du lot
    $sql = "SELECT COUNT(c.rowid) AS nb_cartons
            FROM " . MAIN_DB_PREFIX . "pech_carton c
            JOIN " . MAIN_DB_PREFIX . "pech_lotdet d ON c.fk_lotdet = d.rowid
            WHERE d.fk_lot = ".((int)$id_lot);
    $res = $db->query($sql);
    $obj = $db->fetch_object($res);
    $nb_cartons = (int)$obj->nb_cartons;

    if ($nb_cartons > 0) {
        // Calcul du frais par carton (en MRO)
        $frais_par_carton_mro = $total_frais_mro / $nb_cartons;
        
        // Mise à jour de tous les cartons avec frais en MRO
        $sql = "UPDATE " . MAIN_DB_PREFIX . "pech_carton c
                JOIN " . MAIN_DB_PREFIX . "pech_lotdet d ON c.fk_lotdet = d.rowid
                SET c.frais = " . price2num($frais_par_carton_mro) . "
                WHERE d.fk_lot = " . ((int)$id_lot);
        
        if (!$db->query($sql)) {
            error_log("Erreur répartition frais sur cartons: " . $db->lasterror());
        }
    }

    // =========================================================================
    // 🔹 COMMIT FINAL
    // =========================================================================
    $db->commit();

    setEventMessages("✅ Bon d'entrée <strong>$ref</strong> créé avec succès.", null, 'mesgs');
    header("Location: detail_lot_m.php?id=".$id_lot);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
    header("Location: detail_lot_m.php?id=".$id_lot);
    exit;
}