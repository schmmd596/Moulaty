<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

global $db, $user, $langs, $conf;
$langs->load("bills");

// --- Vérification utilisateur connecté ---
if (empty($user->id)) accessforbidden();

// --- Récupération des données du formulaire ---
$sortie_id   = GETPOST('id_sortie', 'int');
$fk_client   = GETPOST('fk_client', 'int');
//$produits    = GETPOST('produit_id', 'array');
$poids       = GETPOST('poids', 'array');
$pu          = GETPOST('pu_prod', 'array');
$service_desc= GETPOST('service_desc', 'array');
$service_qty = GETPOST('service_qty', 'array');
$service_pu  = GETPOST('service_pu', 'array');

$produit_ids   = GETPOST('produit_id', 'array');       // [1,2,3,...]
$produit_desc  = GETPOST('produit_desc', 'array');   


if (empty($sortie_id) || empty($fk_client)) {
    setEventMessages("Données manquantes (sortie ou client).", null, 'errors');
    header("Location: facture_vente.php?id=".$sortie_id);
    exit;
}

$db->begin();

try {
    // --- Création de la facture ---
    $invoice = new Facture($db);
    $invoice->socid     = $fk_client;
    $invoice->type      = Facture::TYPE_STANDARD;
    $invoice->date      = dol_now();
    $invoice->note_public = 'Facture générée automatiquement à partir de la sortie #'.$sortie_id;
    $invoice->fk_user_author = $user->id;
    $invoice->entity = $conf->entity;

    $result = $invoice->create($user);
    if ($result <= 0) {
        throw new Exception("Erreur lors de la création de la facture : ".$invoice->error);
    }

    // --- Ajout des lignes produits ---
    
    // --- Ajout des lignes produits ---
if (!empty($produit_ids)) {
    foreach ($produit_ids as $rowid => $prod_id) {
        $prod = new Product($db);
        if ($prod->fetch($prod_id) > 0) {
            // quantité en kg depuis le champ poids[rowid]
            $qty = price2num($poids[$rowid], 'MU');
            // prix unitaire depuis pu_prod[rowid]
            $price = price2num($pu[$rowid], 'MU');

            // description enrichie : label + nb cartons
            $desc = $produit_desc[$rowid] ?? $prod->label;

            $res = $invoice->addline(
                $desc,
                $price,
                 $qty,
                0,0,0,
                $prod_id
               
                
            );
            if ($res <= 0) {
                throw new Exception("Erreur lors de l'ajout d'une ligne produit : ".$invoice->error);
            }
        }
    }
}

    // --- Ajout des lignes services manuels ---
    if (!empty($service_desc)) {
        foreach ($service_desc as $i => $desc) {
            $qty = price2num($service_qty[$i], 'MU');
            $price = price2num($service_pu[$i], 'MU');

            /*$res = $invoice->addline(
                $desc,
                '',
                $price,
                0, 0, 0,
                0, // pas de fk_product
                $qty
            );*/
            $res = $invoice->addline(
                $desc,
                $price,
                 $qty,
                0,0,0
               
                
            );
            if ($res <= 0) {
                throw new Exception("Erreur lors de l'ajout d'une ligne service : ".$invoice->error);
            }
        }
    }

    // --- Valider la facture automatiquement (optionnel) ---
    $invoice->validate($user);

    // --- Mise à jour de la sortie ---
    $sql = "UPDATE llx_pech_sortie SET fk_facture = ".((int) $invoice->id).", statut = 1 WHERE rowid = ".((int) $sortie_id);
    if (!$db->query($sql)) {
        throw new Exception("Erreur mise à jour sortie : ".$db->lasterror());
    }

    $db->commit();

    // --- Redirection vers la facture ---
    header("Location: ".DOL_URL_ROOT."/compta/facture/card.php?id=".$invoice->id);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("Erreur : ".$e->getMessage(), null, 'errors');
    header("Location: facture_sortie.php?id=".$sortie_id);
    exit;
}
?>
