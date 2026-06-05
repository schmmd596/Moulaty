<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

// ============================================================================
// 🔹 INCLUSION DES FONCTIONS DE DEVISE
// ============================================================================
require_once '../functions.php';

global $db, $langs, $user, $conf;

$langs->load("bills");
$langs->load("suppliers");

// --- Sécurité : utilisateur connecté obligatoire ---
if (empty($user->id)) accessforbidden();

// ===================================================================
// 🔹 RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
// ===================================================================
$id_sortie      = GETPOST('id_sortie', 'int');
$fk_fournisseur = GETPOST('fk_fournisseur', 'int');
$fk_entrepot    = GETPOST('fk_entrepot', 'int');
$code_devise_entrepot = GETPOST('code_devise_entrepot', 'alpha');
$id_devise_entrepot = GETPOST('id_devise_entrepot', 'int');
$service_id     = GETPOST('service_id', 'array');
$service_qte    = GETPOST('service_qte', 'array');
$service_pu     = GETPOST('service_pu', 'array');

// Vérification minimale
if ($id_sortie <= 0 || $fk_fournisseur <= 0) {
    setEventMessages("Paramètres manquants.", null, 'errors');
    header("Location: facture_sortie_form.php?id_sortie=" . $id_sortie);
    exit;
}

// ===================================================================
// 🔹 RÉCUPÉRATION DES INFORMATIONS DE LA SORTIE
// ===================================================================
$sqlSortie = "SELECT s.ref, s.fk_entrepot_source
              FROM ".MAIN_DB_PREFIX."pech_sortie AS s
              WHERE s.rowid = ".((int)$id_sortie);
$resSortie = $db->query($sqlSortie);
if (!$resSortie || $db->num_rows($resSortie) == 0) {
    setEventMessages("Sortie introuvable.", null, 'errors');
    header("Location: facture_sortie_form.php?id_sortie=" . $id_sortie);
    exit;
}
$sortie = $db->fetch_object($resSortie);
$ref_sortie = $sortie->ref;

// ===================================================================
// 🔹 TRAITEMENT - FACTURE FOURNISSEUR MULTI-DEVISE
// ====================================================================
$db->begin();

try {
    // ========================================================================
    // 🔸 PRÉPARATION DES SERVICES POUR LA FACTURE
    // ========================================================================
    $total_frais_mro = 0;
    $total_frais_devise = 0;
    $services_for_invoice = [];
    
    if (!empty($service_id) && is_array($service_id)) {
        foreach ($service_id as $i => $fk_product) {
            $qty   = price2num($service_qte[$i]);
            $pu_devise = price2num($service_pu[$i]); // Prix dans devise entrepôt
            
            if ($qty <= 0 || $pu_devise <= 0) continue;

            $product = new Product($db);
            $product->fetch($fk_product);

            // 🔹 CONVERSION : Devise entrepôt → MRO
            $pu_en_mro = $pu_devise; // Par défaut (si MRO)
            
            if ($id_devise_entrepot != 1 && $code_devise_entrepot != 'MRO') {
                $conversion = convertToMRO($db, $pu_devise, $id_devise_entrepot);
                if ($conversion && $conversion['converted'] > 0) {
                    $pu_en_mro = $conversion['converted'];
                    $taux = $conversion['rate']; // Récupérer le taux
                } else {
                    error_log("ATTENTION: Conversion échouée pour devise ID {$id_devise_entrepot}");
                }
            }
            
            // 🔹 Calcul des totaux
            $total_service_mro = $qty * $pu_en_mro;
            $total_service_devise = $qty * $pu_devise;
            
            $total_frais_mro += $total_service_mro;
            $total_frais_devise += $total_service_devise;
            
            // 🔹 Stocker les infos pour la facture
            $services_for_invoice[] = [
                'fk_service' => $fk_product,
                'qte' => $qty,
                'pu_devise' => $pu_devise, // Prix dans devise étrangère
                'pu_mro' => $pu_en_mro,    // Prix converti en MRO
                'desc' => $product->label
            ];
        }
    }

    // ========================================================================
    // 🔹 CRÉATION AUTOMATIQUE DE LA FACTURE FOURNISSEUR POUR LES SERVICES
    // ========================================================================
    if ($total_frais_mro > 0 && count($services_for_invoice) > 0) {
        require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
        require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';

        // 🔹 Charger le fournisseur lié à l'entrepôt
        $soc = new Societe($db);
        if ($soc->fetch($fk_fournisseur) <= 0) {
            throw new Exception("Fournisseur introuvable.");
        }

        // 🔹 Créer la facture fournisseur avec multi-devise
        $factureFournisseur = new FactureFournisseur($db);
        $factureFournisseur->socid = $soc->id;
        $factureFournisseur->ref_supplier = $ref_sortie.'-'.time();
        $factureFournisseur->libelle = "Facture services - Sortie $ref_sortie";
        $factureFournisseur->date = dol_now();
        $factureFournisseur->entity = $conf->entity;
        
        // 🔹 CONFIGURATION MULTI-DEVISE (comme votre exemple)
        if ($id_devise_entrepot != 1 && $code_devise_entrepot != 'MRO') {
            $factureFournisseur->multicurrency_code = $code_devise_entrepot;
            $factureFournisseur->multicurrency_tx = $taux;
        }

        $resFact = $factureFournisseur->create($user);
        if ($resFact < 0) throw new Exception("❌ Erreur création facture fournisseur : ".$factureFournisseur->error);

        // 🔹 Ajouter les lignes de services dans la facture (avec gestion multi-devise)
        foreach ($services_for_invoice as $service) {
            $fk_service = $service['fk_service'];
            $qte = $service['qte'];
            $pu_devise = $service['pu_devise']; // Prix dans devise étrangère
            $pu_mro = $service['pu_mro']; // Prix en MRO
            
            // Calculer le prix dans la devise principale (MRO)
            $price_main_currency = $pu_mro;
            
            // 🔹 AJOUT DE LA LIGNE AVEC GESTION MULTI-DEVISE
            $resAdd = $factureFournisseur->addline(
                '',                     // $desc - Description
                $price_main_currency,   // $pu - Prix HT dans devise principale (MRO)
                0,                      // $txtva - Taux TVA
                0,                      // $txlocaltax1 - Taxe locale 1
                0,                      // $txlocaltax2 - Taxe locale 2
                $qte,                   // $qty - Quantité
                $fk_service,            // $fk_product - ID produit/service
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
            
            if ($resAdd < 0) throw new Exception("❌ Erreur ajout ligne facture fournisseur : ".$factureFournisseur->error);
        }

        // 🔹 Valider la facture fournisseur
        $resVal = $factureFournisseur->validate($user);
        if ($resVal < 0) {
            throw new Exception("❌ Erreur validation facture fournisseur : ".$factureFournisseur->error);
        }

        // ========================================================================
        // 🔹 Mise à jour de la table sortie (liaison + total_frais EN MRO)
        // ========================================================================
        $sql_update_sortie = "UPDATE ".MAIN_DB_PREFIX."pech_sortie
                              SET fk_facture = ".$factureFournisseur->id.",
                                  total_frais = total_frais + ".(float)$total_frais_mro." -- Ajout en MRO
                              WHERE rowid = ".$id_sortie;
        if (!$db->query($sql_update_sortie)) {
            throw new Exception("Erreur lors de la mise à jour de la sortie : ".$db->lasterror());
        }

        // ========================================================================
        // 🔹 RÉPARTITION DES FRAIS SUR LES CARTONS DE LA SORTIE (en MRO)
        // ========================================================================
        if ($total_frais_mro > 0) {
            // Compter tous les cartons de la sortie
            $sql_cartons_sortie = "SELECT COUNT(DISTINCT sc.fk_carton) AS nb_cartons
                                   FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton sc
                                   JOIN ".MAIN_DB_PREFIX."pech_sortiedetprod sp ON sp.rowid = sc.fk_sortiedetprod
                                   WHERE sp.fk_sortie = ".$id_sortie;
            $res_cartons = $db->query($sql_cartons_sortie);
            
            if ($res_cartons && $db->num_rows($res_cartons) > 0) {
                $nb_cartons = (int) $db->fetch_object($res_cartons)->nb_cartons;
                
                if ($nb_cartons > 0) {
                    // Calcul du frais par carton (en MRO)
                    $frais_par_carton_mro = $total_frais_mro / $nb_cartons;
                    
                    // Mise à jour de tous les cartons de la sortie avec frais additionnels
                    $sql_update_cartons = "UPDATE ".MAIN_DB_PREFIX."pech_carton c
                                          SET c.frais = c.frais + ".price2num($frais_par_carton_mro)."
                                          WHERE c.rowid IN (
                                              SELECT sc.fk_carton
                                              FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton sc
                                              JOIN ".MAIN_DB_PREFIX."pech_sortiedetprod sp ON sp.rowid = sc.fk_sortiedetprod
                                              WHERE sp.fk_sortie = ".$id_sortie."
                                          )";
                    $db->query($sql_update_cartons);
                }
            }
        }
    }

    // ========================================================================
    // 🔹 VALIDATION TRANSACTION
    // ========================================================================
    $db->commit();

    // Message avec la devise utilisée
    $message = "✅ Facture fournisseur créée avec succès (#" . ($factureFournisseur->ref ?? '') . ")";
    if ($code_devise_entrepot != 'MRO') {
        $message .= " - Devise : " . $code_devise_entrepot;
        $message .= " - Montant : " . price($total_frais_devise) . " " . $code_devise_entrepot;
    }
    
    setEventMessages($message, null, 'mesgs');
    header("Location: detail.php?id=".$id_sortie);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("❌ ".$e->getMessage(), null, 'errors');
    header("Location: detail.php?id=".$id_sortie);
    exit;
}
?>