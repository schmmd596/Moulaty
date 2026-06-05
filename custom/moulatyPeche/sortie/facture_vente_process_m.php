<?php

//error_reporting(E_ALL);
//ini_set('display_errors', 1);
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

global $db, $user, $langs, $conf;

$langs->load("bills");
$langs->load("main");

//if (empty($user->rights->facture->creer)) accessforbidden();

// Récupération POST
$id_sortie = GETPOST('id_sortie','int');
$devise = GETPOST('devise','alpha');
if(empty($devise)) $devise = $conf->currency;

if (!$id_sortie) exit("⚠ Sortie manquante.");

// VÉRIFICATION : Si la sortie est déjà facturée
$sql_check = "SELECT fk_facture_client, statut FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid = ".$id_sortie;
$res_check = $db->query($sql_check);
if ($res_check && $db->num_rows($res_check) > 0) {
    $check = $db->fetch_object($res_check);
    
    // Vérifier si une facture est déjà liée
    if (!empty($check->fk_facture_client) && $check->fk_facture_client > 0) {
        // Vérifier si la facture existe toujours
        $sql_facture = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."facture WHERE rowid = ".$check->fk_facture_client;
        $res_facture = $db->query($sql_facture);
        
        if ($res_facture && $db->num_rows($res_facture) > 0) {
            $facture_existante = $db->fetch_object($res_facture);
            setEventMessages("❌ Cette sortie est déjà facturée (Facture #".$facture_existante->ref.")", null, 'errors');
            header("Location: ".$_SERVER['HTTP_REFERER']);
            exit;
        }
    }
    
    // Vérifier le statut (optionnel, selon votre logique métier)
    if ($check->statut == 2) { // Supposons que 2 = facturé
        setEventMessages("❌ Cette sortie a déjà le statut 'Facturé'", null, 'errors');
        header("Location: ".$_SERVER['HTTP_REFERER']);
        exit;
    }
}

$sql_sortie = "SELECT s.*, 
                      es.ref AS ref_entrepot_source
               FROM ".MAIN_DB_PREFIX."pech_sortie AS s
               LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS es ON es.rowid = s.fk_entrepot_source
               WHERE s.rowid = ".$id_sortie;
$res_sortie = $db->query($sql_sortie);
$sortie = $db->fetch_object($res_sortie);
// 🔹 Récupérer la sortie
/*$sql_sortie = "SELECT * FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid=".$id_sortie;
$res_sortie = $db->query($sql_sortie);
$sortie = $db->fetch_object($res_sortie);*/
if (!$sortie) exit("Sortie introuvable");

// 🔹 Récupérer client
$soc = new Societe($db);
$soc->fetch($sortie->fk_client);

// 🔹 Récupérer produits
$sql_prods = "SELECT p.rowid, p.fk_product, p.nb_carton, p.poids_total, p.pu, pr.label
              FROM ".MAIN_DB_PREFIX."pech_sortiedetprod p
              LEFT JOIN ".MAIN_DB_PREFIX."product pr ON pr.rowid=p.fk_product
              WHERE fk_sortie=".$id_sortie;
$res_prods = $db->query($sql_prods);
$produits = [];
while($obj = $db->fetch_object($res_prods)) {
    // Poids total cartons
    $sql_cartons = "SELECT SUM(poids) as total_poids FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton 
                    WHERE fk_sortiedetprod=".$obj->rowid;
    $res_cartons = $db->query($sql_cartons);
    $poids_total = $db->fetch_object($res_cartons)->total_poids;
    $pu = GETPOST('pu_'.$obj->fk_product, 'alpha'); // récupère le PU saisi
    $produits[] = [
        'rowid' => $obj->fk_product,
        'label' => $obj->label,
        'poids_total' => $poids_total,
        'nb_carton' => $obj->nb_carton,
        'pu' => (float) $pu
    ];
}

// 🔹 Récupération taux multi-devise
$rate = 1;
if ($devise != $conf->currency) {
    $sqlcur = "SELECT rate FROM ".MAIN_DB_PREFIX."multicurrency_rate r
               LEFT JOIN ".MAIN_DB_PREFIX."multicurrency c ON c.rowid=r.fk_multicurrency
               WHERE c.code='".$db->escape($devise)."' 
               ORDER BY r.date_sync DESC LIMIT 1";
    $rescur = $db->query($sqlcur);
    if ($rescur) $rate = (float)$db->fetch_object($rescur)->rate;
}

// 🔹 Création facture
$db->begin();
try {
    $facture = new Facture($db);
    $facture->socid = $sortie->fk_client;
    $facture->date = dol_now();
    $facture->type = Facture::TYPE_STANDARD;
    $facture->entity = $conf->entity;
    $facture->ref_ext = 'SORTIE-'.$sortie->ref.'-'.time();
    $facture->note_public  = "Facture liée à la sortie #".$sortie->ref.' entrpot : '. $sortie->ref_entrepot_source;
    

    if($devise != $conf->currency) {
        $facture->multicurrency_code = $devise;
        $facture->multicurrency_tx = $rate;
    } else {
        $facture->multicurrency_code = '';
        $facture->multicurrency_tx = 0;
    }

    $res = $facture->create($user);
    if ($res <= 0) throw new Exception("Erreur création facture : ".$facture->error);

    // 🔹 Ajouter lignes
    foreach($produits as $prod) {
        $pu_ht_main = $prod['pu'];
        $pu_ht_devise = ($devise != $conf->currency) ? $prod['pu'] / $rate :  (float) ($prod['pu'] );
        //$qty = $prod['poids_total'];
        $qty = $prod['nb_carton'];
        $desc22 = $prod['label']. ' | nb_carton : ' .$prod['nb_carton'].' | Entrepot : '.$sortie->ref_entrepot_source;

        $resLine = $facture->addline(
            $desc22,       // description
            $pu_ht_devise,          // pu_ht monnaie principale
            $qty,                 // quantité
            0,                    // tva
            0,0,0,0,'','',0,0,0,'HT',0,0,0,0,'','',0,null,0,'',[],100,0,null,$pu_ht_main
        );
        if ($resLine < 0) throw new Exception("Erreur ajout ligne : ".$facture->error);
    }

    // 🔹 Validation facture
    //$resVal = $facture->validate($user);
    //if ($resVal < 0) throw new Exception("Erreur validation facture : ".$facture->error);

    // 🔹 Mettre à jour sortie avec facture
    $db->query("UPDATE ".MAIN_DB_PREFIX."pech_sortie SET fk_facture_client=".$facture->id.", statut=2 WHERE rowid=".$id_sortie);

    $db->commit();
    setEventMessages("✅ Facture créée avec succès (Devise : ".$devise.")", null, 'mesgs');
    header("Location: ".DOL_URL_ROOT."/compta/facture/card.php?id=".$facture->id);
    exit;

} catch(Exception $e) {
    $db->rollback();
    setEventMessages("❌ ".$e->getMessage(), null, 'errors');
    header("Location: ".$_SERVER['HTTP_REFERER']);
    exit;
}
?>
