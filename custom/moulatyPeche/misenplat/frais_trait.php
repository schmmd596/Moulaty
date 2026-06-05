<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $user, $langs, $conf;

$id = GETPOST('fk_bon', 'int');
if (empty($id)) {
    setEventMessages("Bon non spécifié.", null, 'errors');
    header("Location: misenplat_select.php");
    exit;
}

// 🔹 Récupérer la référence du bon
$sqlRef = "SELECT ref FROM ".MAIN_DB_PREFIX."pech_bon_misenplat WHERE rowid = ".((int)$id);
$resRef = $db->query($sqlRef);
$ref_bon = '';
if ($resRef && $db->num_rows($resRef) > 0) {
    $objRef = $db->fetch_object($resRef);
    $ref_bon = $objRef->ref;
} else {
    throw new Exception("Impossible de récupérer la référence du bon mise en plat #".$id);
}

// 🔹 Récupérer les données du formulaire
$desc = GETPOST('desc', 'array');
$qte  = GETPOST('qte', 'array');
$puL   = GETPOST('pu', 'array');

if (empty($desc)) {
    setEventMessages("Aucune ligne à enregistrer.", null, 'errors');
    header("Location: frais.php?id=".$id);
    exit;
}

$db->begin();

try {
    $total_general = 0;

    // 🔹 Supprimer les anciennes lignes pour ce bon
    $db->query("DELETE FROM ".MAIN_DB_PREFIX."pech_bon_misenplatdet WHERE fk_bon_misenplat = ".(int)$id);

    // 🔹 Insérer les nouvelles lignes
    /*for ($i = 0; $i < count($desc); $i++) {
        $d = trim($desc[$i]);
        $q = (float) ($qte[$i] ?? 0);
        $p = (float) ($pu[$i] ?? 0);
        $total_line = round($q * $p, 2);

        if ($d !== '' && $q > 0) {
            $sqlIns = "INSERT INTO ".MAIN_DB_PREFIX."pech_bon_misenplatdet
                       (fk_bon_misenplat, description, qte, PU, total_line)
                       VALUES (".((int)$id).", '".$db->escape($d)."', ".$q.", ".$p.", ".$total_line.")";
            if (!$db->query($sqlIns)) throw new Exception("Erreur insertion ligne : ".$d);
            $total_general += $total_line;
        }
    }

    // 🔹 Vérifier ou créer le fournisseur "Generale"
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
        $resSoc = $soc->create($user);
        if ($resSoc > 0) {
            $fk_fourn_gen = $soc->id;
        } else {
            throw new Exception("Impossible de créer le fournisseur 'Generale' : ".$soc->error);
        }
    }

    // 🔹 Créer la facture fournisseur
    $facture = new FactureFournisseur($db);
    $facture->socid = $fk_fourn_gen;
    $facture->ref_supplier = 'FRAIS-BON-'.$ref_bon;
    $facture->libelle = "Facture automatique des frais - Bon mise en plat #".$ref_bon;
    $facture->date = dol_now();
    $facture->entity = $conf->entity;

    $resFact = $facture->create($user);
    if ($resFact < 0) throw new Exception("Erreur création facture fournisseur : ".$facture->error);

    // 🔹 Ajouter les lignes de frais à la facture
    for ($i = 0; $i < count($desc); $i++) {
        $description = trim($desc[$i]);
        $qteL = (float) ($qte[$i] ?? 0);
        $puL  = (float) ($pu[$i] ?? 0);
        if ($description === '' || $qteL <= 0) continue;
        $resAdd = $facture->addline($description, $puL, 0, 0, 0, $qteL);
        if ($resAdd < 0) throw new Exception("Erreur ajout ligne facture 'Generale' : ".$facture->error);
    }*/

    for ($i = 0; $i < count($desc); $i++) {
    $service_id = (int)$desc[$i];       // desc contient maintenant l'ID du service
    $q = (float) ($qte[$i] ?? 0);
    $pu_input = (float) ($puL[$i] ?? 0);
    

    if ($service_id > 0 && $q > 0) {
        // 🔹 Récupérer label et prix du service
        $sqlS = "SELECT label, price FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".$service_id." AND fk_product_type = 1";
        $resS = $db->query($sqlS);
        if ($resS && $db->num_rows($resS) > 0) {
            $sObj = $db->fetch_object($resS);
            $label_service = $sObj->label;
            $pu = $pu_input > 0 ? $pu_input : $sObj->price; // si PU vide, utiliser le prix du service
            $total_line = round($q * $pu, 2);

            // 🔹 Insérer dans la table détail
            $sqlIns = "INSERT INTO ".MAIN_DB_PREFIX."pech_bon_misenplatdet
                       (fk_bon_misenplat, description, qte, PU, total_line)
                       VALUES (".(int)$id.", '".$db->escape($label_service)."', ".$q.", ".$pu.", ".$total_line.")";
            if (!$db->query($sqlIns)) throw new Exception("Erreur insertion ligne : ".$label_service);

            $total_general += $total_line;
        }
    }
}

// 🔹 Vérifier ou créer le fournisseur "Generale"
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
    $resSoc = $soc->create($user);
    if ($resSoc > 0) {
        $fk_fourn_gen = $soc->id;
    } else {
        throw new Exception("Impossible de créer le fournisseur 'Generale' : ".$soc->error);
    }
}

// 🔹 Créer la facture fournisseur
$facture = new FactureFournisseur($db);
$facture->socid = $fk_fourn_gen;
$facture->ref_supplier = 'FRAIS-BON-'.$ref_bon. '-'.time();
$facture->libelle = "Facture automatique des frais - Bon mise en plat #".$ref_bon;
$facture->date = dol_now();
$facture->entity = $conf->entity;

$resFact = $facture->create($user);
if ($resFact < 0) throw new Exception("Erreur création facture fournisseur : ".$facture->error);

// 🔹 Ajouter les lignes à la facture en liant le service

for ($i = 0; $i < count($desc); $i++) {
    $service_id = (int)$desc[$i];
    $qteL = (float) ($qte[$i] ?? 0);
    $pu_input = (float) ($puL[$i] ?? 0);
    

    if ($service_id > 0 && $qteL > 0) {
        // Récupérer label et prix du service
        $sqlS = "SELECT label, price FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".$service_id." AND fk_product_type = 1";
        $resS = $db->query($sqlS);
        if ($resS && $db->num_rows($resS) > 0) {
            $sObj = $db->fetch_object($resS);
            $label_service = $sObj->label;
            $pu_final = $pu_input; //> 0 ? $pu_input : $sObj->price;

            // Ajouter la ligne dans la facture fournisseur
            $resAdd = $facture->addline(
                $label_service,
                $pu_final, // <- PU correct
                0, 0, 0,
                $qteL,
                $service_id
            );
            if ($resAdd < 0) throw new Exception("Erreur ajout ligne facture 'Generale' : ".$facture->error);
        }
    }
}

    // 🔹 Mettre à jour le bon avec total frais et fk_facture
    $sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."pech_bon_misenplat 
                  SET total_frais = ".$total_general.",
                      fk_facture_frais = ".((int)$facture->id)."
                  WHERE rowid = ".((int)$id);
    $db->query($sqlUpdate);

    // 🔹 Répartir les frais sur les plats
    $sql_count = "
        SELECT COUNT(p.rowid) AS total_plats
        FROM ".MAIN_DB_PREFIX."pech_plat p
        INNER JOIN ".MAIN_DB_PREFIX."pech_misenplat mp ON mp.rowid = p.fk_misenplat
        WHERE mp.fk_bon_misenplat = ".(int)$id;
    $res_count = $db->query($sql_count);
    if ($res_count) {
        $obj = $db->fetch_object($res_count);
        $total_plats = (int)$obj->total_plats;
        if ($total_plats > 0) {
            $frais_par_plat = round($total_general / $total_plats, 2);
            $sql_update = "
                UPDATE ".MAIN_DB_PREFIX."pech_plat p
                INNER JOIN ".MAIN_DB_PREFIX."pech_misenplat mp ON mp.rowid = p.fk_misenplat
                SET p.frais = ".$frais_par_plat."
                WHERE mp.fk_bon_misenplat = ".(int)$id;
            $res_update = $db->query($sql_update);
            if (!$res_update) throw new Exception("Erreur mise à jour frais sur plats : ".$db->lasterror());
        }
    }

    $db->commit();
    setEventMessages("Frais enregistrés et facture fournisseur 'Generale' créée avec succès.", null, 'mesgs');
    header("Location: detail_mis.php?id=".$id);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages("Erreur lors du traitement : ".$e->getMessage(), null, 'errors');
    header("Location: detail_mis.php?id=".$id);
    exit;
}
?>
