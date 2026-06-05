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
$total = 0;
// ===================================================================
// 🔹 RÉCUPÉRATION DES DONNÉES DU FORMULAIRE
// ===================================================================
$id_lot         = GETPOST('id_lot', 'int');
$fk_fournisseur = GETPOST('fk_fournisseur', 'int');

$service_id = GETPOST('service_id', 'array');
$service_qte = GETPOST('service_qte', 'array');
$service_pu = GETPOST('service_pu', 'array');

$desc = GETPOST('desc', 'array');
$qte  = GETPOST('qte', 'array');
$pu   = GETPOST('pu', 'array');

// --- Vérification des paramètres ---
if ($id_lot <= 0 || $fk_fournisseur <= 0) {
    setEventMessages("Paramètres manquants.", null, 'errors');
    header("Location: facture_fournisseur_form.php?id_lot=" . $id_lot);
    exit;
}

// --- Vérification de l’existence du lot ---
$sqlLot = "SELECT rowid, ref, fk_entrepot 
           FROM ".MAIN_DB_PREFIX."pech_lot 
           WHERE rowid = ".((int)$id_lot);
$resLot = $db->query($sqlLot);
if (!$resLot || $db->num_rows($resLot) == 0) {
    setEventMessages("Lot introuvable dans la base de données.", null, 'errors');
    header("Location: facture_fournisseur_form.php?id_lot=" . $id_lot);
    exit;
}
$lot = $db->fetch_object($resLot);
$ref = $lot->ref;

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
    $facture->note_public  = "Facture liée au lot #".$ref;
    $facture->ref_supplier = $ref . "-" . time();

    $res = $facture->create($user);
    if ($res <= 0) throw new Exception($facture->error);

    // ===================================================================
    // 🔹 AJOUT DES LIGNES DE SERVICES (produits Dolibarr)
    // ===================================================================
    if (!empty($service_id) && is_array($service_id)) {
        foreach ($service_id as $i => $fk_product) {
            $qty   = price2num($service_qte[$i]);
            $pu_ht = price2num($service_pu[$i]);
            if ($qty <= 0 && $pu_ht <= 0) continue;

            $product = new Product($db);
            $product->fetch($fk_product);

            $desc_line = $product->label;
            $resLine = $facture->addline(
                $desc_line,   // Description
                $pu_ht,       // Prix unitaire HT
                0,            // TVA
                0, 0,         // Taxes locales
                $qty,         // Quantité
                $fk_product   // Produit lié
            );

            if ($resLine < 0) throw new Exception($facture->error);
            $total += ($pu_ht * $qty);
        }
    }

    

    // ===================================================================
    // 🔹 MISE À JOUR DU LOT AVEC L’ID DE LA FACTURE
    // ===================================================================
    $sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."pech_lot 
                  SET fk_facture = ".((int)$facture->id)." ,
                  total_frais = total_frais + ".(float)$total."
                  WHERE rowid = ".((int)$id_lot);
    if (!$db->query($sqlUpdate)) {
        throw new Exception("Erreur lors de la mise à jour du lot.");
    }

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

    // ===================================================================
    // 🔹 VALIDATION ET CONFIRMATION
    // ===================================================================
    $db->commit();
    setEventMessages("✅ Facture fournisseur créée avec succès (#".$facture->ref.").", null, 'mesgs');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("❌ ".$e->getMessage(), null, 'errors');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}
