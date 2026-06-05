<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

global $db, $user, $langs;
$langs->load("reception");

// Récupérer les données POST
$fk_reception = GETPOST('fk_reception', 'int');
$fk_fournisseur2 = GETPOST('fk_fournisseur2', 'int');
$comment      = GETPOST('comment', 'alpha');
$descriptions = GETPOST('description'); // array
$qtes         = GETPOST('qte');         // array
$PUs          = GETPOST('PU');          // array
$manual_descriptions = GETPOST('manual_description');
$manual_qtes         = GETPOST('manual_qte');
$manual_PUs          = GETPOST('manual_PU');

$frais = 0;
// Vérification minimale
if(empty($fk_reception)) {
    setEventMessage($langs->trans("Réception invalide"), 'errors');
    header("Location: ".$_SERVER['HTTP_REFERER']);
    exit;
}

// Commencer la transaction
$db->begin();

try {

    // --- 1️⃣ Vérifier si un bon existe déjà pour cette réception ---
    /*$sqlCheck = "SELECT rowid FROM ".MAIN_DB_PREFIX."pech_bonreception WHERE fk_reception=".$fk_reception;
    $resCheck = $db->query($sqlCheck);
    if($resCheck && $db->num_rows($resCheck) > 0) {
        throw new Exception("Un bon de réception existe déjà pour cette réception.");
    }*/

    // --- 2️⃣ Récupérer infos de la réception ---
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_reception WHERE rowid=".$fk_reception;
    $res = $db->query($sql);
    if(!$res || $db->num_rows($res) == 0) {
        throw new Exception($langs->trans("Réception introuvable"));
    }else{
        $reception = $db->fetch_object($res);
    }
    // --- 2️⃣ Récupérer infos de la réception ---

    

    // --- 3️⃣ Créer le bon de réception ---
    $now = dol_now();
    $ref = $reception->ref;

    $sqlInsert = "INSERT INTO ".MAIN_DB_PREFIX."pech_bonreception
    (ref, fk_reception, fk_fournisseur, fk_congelateur, date_creation, user_create, comment, entity)
    VALUES (
        '".$reception->ref."',
        ".(int)$fk_reception.",
        ".(!empty($reception->fk_fournisseur)?(int)$reception->fk_fournisseur:"NULL").",
        ".(!empty($reception->fk_congelateur)?(int)$reception->fk_congelateur:"NULL").",
        '".$db->idate($now)."',
        ".(int)$user->id.",
        '".$db->escape($comment)."',
        ".(int)$conf->entity."
    )";

    if(!$db->query($sqlInsert)) throw new Exception("Erreur création du bon de réception : ".$db->lasterror());
    $fk_bon = $db->last_insert_id(MAIN_DB_PREFIX."pech_bonreception");

    // --- 4️⃣ Insérer les lignes automatiques et manuelles ---
    $total_bon = 0;

    // Fonctions internes pour ajouter les lignes
    function addLigneBon($fk_bon, $desc, $qte, $pu, $type) {
        global $db, $conf;
        $total_line = round($qte * $pu, 2);

        if(is_numeric($desc) && $desc > 0) {
            $sqlS = "SELECT label FROM ".MAIN_DB_PREFIX."product WHERE rowid=".((int)$desc);
            $resS = $db->query($sqlS);
            if($resS && $db->num_rows($resS) > 0) {
                $objS = $db->fetch_object($resS);
                $desc = $objS->label;
            }
        }

        $sqlLine = "INSERT INTO ".MAIN_DB_PREFIX."pech_bonreceptiondet
        (fk_bonreception, description, qte, PU, total_line, type, entity)
        VALUES (
            ".(int)$fk_bon.",
            '".$db->escape($desc)."',
            ".(float)$qte.",
            ".(float)$pu.",
            ".(float)$total_line.",
            ".(int)$type.",
            ".(int)$conf->entity."
        )";
        if(!$db->query($sqlLine)) throw new Exception("Erreur insertion ligne bon : ".$db->lasterror());

        return $total_line;
    }

    // Lignes automatiques type 0
    if(!empty($descriptions) && is_array($descriptions)) {
        foreach($descriptions as $k => $desc) {
            $qte = isset($qtes[$k]) ? (float)$qtes[$k] : 0;
            $pu  = isset($PUs[$k]) ? (float)$PUs[$k] : 0;
            $total_bon += addLigneBon($fk_bon, $desc, $qte, $pu, 0);
        }
    }

    // Lignes manuelles type 1
    if(!empty($manual_descriptions) && is_array($manual_descriptions)) {
        foreach($manual_descriptions as $k => $desc) {
            if(empty($desc)) continue;
            $qte = isset($manual_qtes[$k]) ? (float)$manual_qtes[$k] : 0;
            $pu  = isset($manual_PUs[$k]) ? (float)$manual_PUs[$k] : 0;
            $total_bon += addLigneBon($fk_bon, $desc, $qte, $pu, 1);
        }
    }

    // --- 5️⃣ Créer factures fournisseurs ---
    $id_fac = 0;
    if(!empty($descriptions)) {
        $factureFourn = new FactureFournisseur($db);
        $factureFourn->socid = $reception->fk_fournisseur;
        $factureFourn->ref_supplier = 'REC-'.$ref.''.time();
        $factureFourn->libelle = "Facture de la réception ".$reception->ref;
        $factureFourn->date = dol_now();
        $factureFourn->entity = $conf->entity;

        $resFact = $factureFourn->create($user);
        if($resFact < 0) throw new Exception($factureFourn->error);
        $id_fac = $resFact;

        foreach($descriptions as $k => $desc) {
            $qte = isset($qtes[$k]) ? (float)$qtes[$k] : 0;
            $pu  = isset($PUs[$k]) ? (float)$PUs[$k] : 0;
            $desc2 = $desc;
            if(is_numeric($desc) && $desc > 0) {
                $sqlS = "SELECT label FROM ".MAIN_DB_PREFIX."product WHERE rowid=".(int)$desc;
                $resS = $db->query($sqlS);
                if($resS && $db->num_rows($resS) > 0) {
                    $objS = $db->fetch_object($resS);
                    $desc2 = $objS->label;
                }
                $resAdd = $factureFourn->addline($desc2, $pu, 0,0,0, $qte, (int)$desc);
            } else {
                $resAdd = $factureFourn->addline($desc2, $pu, 0,0,0, $qte);
            }
            if($resAdd < 0) throw new Exception("Erreur ajout ligne facture : ".$factureFourn->error);
        }
    }

    // Facture Générale pour lignes manuelles
    if(!empty($manual_descriptions)) {
        $sqlGen = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom='Generale' AND client=0 AND fournisseur=1";
        $resGen = $db->query($sqlGen);
        if($resGen && $db->num_rows($resGen) > 0) {
            $objGen = $db->fetch_object($resGen);
            $fkFournGen = $objGen->rowid;
        } else {
            $soc = new Societe($db);
            $soc->nom = 'Generale';
            $soc->fournisseur = 1;
            $soc->client = 0;
            $resSoc = $soc->create($user);
            if($resSoc < 0) throw new Exception("Impossible de créer le fournisseur 'Generale': ".$soc->error);
            $fkFournGen = $soc->id;
        }
        if (!empty($fk_fournisseur2) && $fk_fournisseur2 > 0){
            $fkFournGen = $fk_fournisseur2 ;
        }

        $factureGen = new FactureFournisseur($db);
        $factureGen->socid = $fkFournGen;
        $factureGen->ref_supplier = 'REC2-'.$ref.''.time();
        $factureGen->libelle = "Facture services manuels - Réception ".$reception->ref;
        $factureGen->date = dol_now();
        $factureGen->entity = $conf->entity;

        $resFactGen = $factureGen->create($user);
        if($resFactGen < 0) throw new Exception($factureGen->error);

        foreach($manual_descriptions as $k => $desc) {
            $desc = trim($desc);
            if(empty($desc)) continue;
            $qte = isset($manual_qtes[$k]) ? (float)$manual_qtes[$k] : 0;
            $pu  = isset($manual_PUs[$k]) ? (float)$manual_PUs[$k] : 0;
            $desc2 = $desc;
            if(is_numeric($desc) && $desc > 0) {
                $sqlS = "SELECT label FROM ".MAIN_DB_PREFIX."product WHERE rowid=".(int)$desc;
                $resS = $db->query($sqlS);
                if($resS && $db->num_rows($resS) > 0) {
                    $objS = $db->fetch_object($resS);
                    $desc2 = $objS->label;
                }
            }
            $resAdd = $factureGen->addline($desc2, $pu,0,0,0, $qte, (int)$desc);
            if($resAdd < 0) throw new Exception("Erreur ajout ligne facture 'Générale' : ".$factureGen->error);
        }
    }

    // --- 6️⃣ Mettre à jour total et réception ---
    $sqlUpd = "UPDATE ".MAIN_DB_PREFIX."pech_bonreception SET total=".$total_bon." WHERE rowid=".((int)$fk_bon);
    if(!$db->query($sqlUpd)) throw new Exception("Erreur maj total bon : ".$db->lasterror());



    // =======================
    // 5. Ajustement du prix moyen selon le montant final
    // =======================
    $montant_reception = (float) $reception->montant;
    $montant_final = (float) $total_bon; // supposons que tu reçois ce montant final
    $diff = max(0,$montant_final - $montant_reception);

    if (abs($diff) > 0.0001) { // s'il y a une différence notable

        // Récupérer toutes les lignes de la réception
        $sqlDet = "SELECT rowid, poids_net, prix_moyen FROM ".MAIN_DB_PREFIX."pech_receptiondet WHERE fk_reception = ".$fk_reception;
        $resDet = $db->query($sqlDet);
        if (!$resDet) throw new Exception("Erreur lecture réceptiondet : ".$db->lasterror());

        $total_poids_net = 0;
        $rows = [];
        while ($obj = $db->fetch_object($resDet)) {
            $total_poids_net += $obj->poids_net;
            $rows[] = $obj;
        }

        if ($total_poids_net <= 0){
            $ajustement_par_kg = 0;

        }else{
            $ajustement_par_kg = $diff / $total_poids_net;

        }
        // Calcul de l'ajustement par kg
        
        // Mettre à jour chaque ligne
        foreach ($rows as $r) {
            $nouveau_prix_moyen = (float) $r->prix_moyen + $ajustement_par_kg;
            $sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."pech_receptiondet 
                        SET prix_moyen = ".$nouveau_prix_moyen." 
                        WHERE rowid = ".$r->rowid;
            if (!$db->query($sqlUpdate)) throw new Exception("Erreur maj prix_moyen : ".$db->lasterror());
        }

        // Mettre à jour le montant total dans la réception
        $sqlUpRec = "UPDATE ".MAIN_DB_PREFIX."pech_reception 
                    SET montant = ".$montant_final." ,
                     frais = ".$diff." 
                    WHERE rowid = ".$fk_reception;
        if (!$db->query($sqlUpRec)) throw new Exception("Erreur maj montant réception : ".$db->lasterror());
    }
    $sqlUpdateReception = "UPDATE ".MAIN_DB_PREFIX."pech_reception 
                           SET fk_facture = ".$id_fac.",
                               fk_bon_recep = ".$fk_bon."
                           WHERE rowid = ".$fk_reception;
    if(!$db->query($sqlUpdateReception)) throw new Exception("Erreur maj réception.");

    // Valider transaction
    $db->commit();

    // --- 7️⃣ Message et redirection ---
    setEventMessages($langs->trans("BonReceptionCreated"), null, 'mesgs');
    header("Location: detail_rec.php?id=".$fk_reception);
    exit;

} catch(Exception $e) {
    $db->rollback();
    setEventMessage($e->getMessage(), 'errors');
    header("Location: ".$_SERVER['HTTP_REFERER']);
    exit;
}
?>
