<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $langs, $user;

$langs->load("bills");
$langs->load("suppliers");

// --- Sécurité : utilisateur connecté obligatoire ---
if (empty($user->id)) accessforbidden();

// ===================================================================
// 🔹 RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
// ===================================================================
$id_sortie      = GETPOST('id_sortie', 'int');
$fk_fournisseur = GETPOST('fk_fournisseur', 'int');
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
$sqlSortie = "SELECT s.ref 
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
// 🔹 CRÉATION DE LA FACTURE FOURNISSEUR
// ===================================================================
$db->begin();

try {
    // --- Chargement du fournisseur ---
    $soc = new Societe($db);
    if ($soc->fetch($fk_fournisseur) <= 0) {
        throw new Exception("Fournisseur introuvable.");
    }

    // --- Création de la facture ---
    $facture = new FactureFournisseur($db);
    $facture->socid        = $soc->id;
    $facture->type         = 0; // Facture standard
    $facture->date         = dol_now();
    $facture->note_public  = "Facture liée à la sortie #".$ref_sortie;
    $facture->ref_supplier = $ref_sortie . "-" . time();

    $res = $facture->create($user);
    if ($res <= 0) throw new Exception($facture->error);

    // ===================================================================
    // 🔹 AJOUT DES LIGNES DE SERVICES
    // ===================================================================
    if (!empty($service_id) && is_array($service_id)) {
        foreach ($service_id as $i => $fk_product) {
            $qty   = price2num($service_qte[$i]);
            $pu_ht = price2num($service_pu[$i]);
            if ($qty <= 0 || $pu_ht <= 0) continue;

            $product = new Product($db);
            $product->fetch($fk_product);

            $desc_line = $product->label;
            $resLine = $facture->addline(
    $desc_line,   // desc
    $pu_ht,       // pu
    0,            // tva
    0,            // localtax1
    0,            // localtax2
    $qty,         // qty
    $fk_product,  // fk_product
    0,            // remise
    0,            // date_start
    0,            // date_end
    0,            // fk_code_ventilation
    0,            // info_bits
    'HT',         // price_base_type
    1             // ✅ type = SERVICE
);


            if ($resLine < 0) throw new Exception($facture->error);
        }
        $resVal = $facture->validate($user);
    }

        // ===================================================================
    // 🔹 MISE À JOUR DE LA SORTIE
    // ===================================================================
    $sqlUpdateSortie = "UPDATE ".MAIN_DB_PREFIX."pech_sortie
                        SET fk_facture = ".$facture->id.",
                            total_frais = total_frais + ".price2num($facture->total_ht)."
                        WHERE rowid = ".(int)$id_sortie;

    $resUpdate = $db->query($sqlUpdateSortie);
    if (!$resUpdate) {
        throw new Exception("Erreur lors de la mise à jour de la sortie : ".$db->lasterror());
    }
    // ===================================================================
    // 🔹 VALIDATION ET CONFIRMATION
    // ===================================================================
    $db->commit();
    setEventMessages("✅ Facture fournisseur créée avec succès (#".$facture->ref.").", null, 'mesgs');
    header("Location: detail.php?id=".$id_sortie);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("❌ ".$e->getMessage(), null, 'errors');
    header("Location: detail.php?id=".$id_sortie);
    exit;
}
