<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $langs, $user;

$langs->load("bills");
$langs->load("suppliers");

if (empty($user->id)) accessforbidden();

// 🔹 Récupération des données du formulaire
$id_lot         = GETPOST('id_lot', 'int');
$fk_fournisseur = GETPOST('fk_fournisseur', 'int');

$service_id  = GETPOST('service_id', 'array');
$service_qte = GETPOST('service_qty', 'array');
$service_pu  = GETPOST('service_price', 'array');

// Vérifications
if ($id_lot <= 0 || $fk_fournisseur <= 0) {
    setEventMessages("Paramètres manquants.", null, 'errors');
    header("Location: facture_fournisseur.php?id_lot=" . $id_lot);
    exit;
}

// Vérification du lot
$sqlLot = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".((int)$id_lot);
$resLot = $db->query($sqlLot);
if (!$resLot || $db->num_rows($resLot) == 0) {
    setEventMessages("Lot introuvable.", null, 'errors');
    header("Location: facture_fournisseur.php?id_lot=" . $id_lot);
    exit;
}
$lot = $db->fetch_object($resLot);
$ref = $lot->ref;

// Début transaction
$db->begin();

try {
    // --- Fournisseur ---
    $soc = new Societe($db);
    if ($soc->fetch($fk_fournisseur) <= 0) throw new Exception("Fournisseur introuvable.");

    // --- Création facture ---
    $facture = new FactureFournisseur($db);
    $facture->socid        = $soc->id;
    $facture->type         = 0; // standard
    $facture->date         = dol_now();
    $facture->note_public  = "Facture liée au lot #".$ref;
    $facture->ref_supplier = $ref . "-" . time();

    $res = $facture->create($user);
    if ($res <= 0) throw new Exception($facture->error);

    $total = 0;

    // --- Ajouter produits du lot comme description ---
    $sqlLines = "SELECT ld.*, p.ref AS product_ref, p.label AS product_label
                 FROM ".MAIN_DB_PREFIX."pech_lotdet ld
                 LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ld.fk_product
                 WHERE ld.fk_lot = ".$id_lot;
    $resLines = $db->query($sqlLines);
    while ($line = $db->fetch_object($resLines)) {
        $pu_carton = $line->prix ;
        $desc = 'Espece '.$line->product_label
              .' | Nb Cartons: '.$line->nb_carton;

        $resLine = $facture->addline(
            $desc,
            $pu_carton, // PU=0 car description
            0, // TVA
            0,0,
            $line->nb_carton,
            0
        );

        if ($resLine < 0) throw new Exception($facture->error);
    }

    // --- Ajouter services ---
    if (!empty($service_id) && is_array($service_id)) {
        foreach ($service_id as $i => $fk_product) {
            $qty   = price2num($service_qte[$i]);
            $pu_ht = price2num($service_pu[$i]);
            if ($qty <= 0 || $pu_ht <= 0) continue;

            $product = new Product($db);
            $product->fetch($fk_product);

            $resLine = $facture->addline(
                $product->label,
                $pu_ht,
                0,
                0,0,
                $qty,
                $fk_product
            );

            if ($resLine < 0) throw new Exception($facture->error);
            $total += ($pu_ht * $qty);
        }
    }

    // --- Mettre à jour le lot avec l’ID facture et total frais ---
    $sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."pech_lot 
                  SET fk_facture_fourn = ".((int)$facture->id).",
                      total_frais = total_frais + ".(float)$total."
                  WHERE rowid = ".((int)$id_lot);
    if (!$db->query($sqlUpdate)) throw new Exception("Erreur lors de la mise à jour du lot.");

    // --- Répartition du frais sur cartons ---
    $sql = "SELECT COUNT(c.rowid) AS nb_cartons
            FROM " . MAIN_DB_PREFIX . "pech_carton c
            JOIN " . MAIN_DB_PREFIX . "pech_lotdet d ON c.fk_lotdet = d.rowid
            WHERE d.fk_lot = ".((int)$id_lot);
    $res = $db->query($sql);
    $obj = $db->fetch_object($res);
    $nb_cartons = (int)$obj->nb_cartons;
    if ($nb_cartons > 0) {
        $frais_par_carton = $total / $nb_cartons;
        $sql = "UPDATE " . MAIN_DB_PREFIX . "pech_carton c
                JOIN " . MAIN_DB_PREFIX . "pech_lotdet d ON c.fk_lotdet = d.rowid
                SET c.frais = c.frais + ".(float)$frais_par_carton."
                WHERE d.fk_lot = ".((int)$id_lot);
        $db->query($sql);
    }

    // --- Commit ---
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
