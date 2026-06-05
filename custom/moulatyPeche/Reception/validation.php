<?php
require '../../../main.inc.php';
//require_once DOL_DOCUMENT_ROOT.'/product/class/stock.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
global $db, $user, $langs, $conf;

$langs->load("stocks");
$langs->load("reception");

$id = GETPOST('id', 'int');
$token = GETPOST('token', 'alpha');

//if (!$user->rights->pech->reception->validate) accessforbidden();

if (empty($user->rights->moulatyPeche->read_r)) {
    accessforbidden('Accès réservé à l’administrateur.');
}

// Vérification de la réception
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_reception WHERE rowid = ".(int)$id;
$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) accessforbidden();

$rec = $db->fetch_object($resql);

// Vérifier si déjà validée
if ($rec->etat != 0) {
    setEventMessages($langs->trans("ReceptionAlreadyValidated"), null, 'errors');
    header("Location: ./list.php");
    exit;
}

$db->begin();

try {
    // Parcourir les lignes de réception
    $sql_det = "SELECT * FROM ".MAIN_DB_PREFIX."pech_receptiondet WHERE fk_reception = ".(int)$id;
    $res_det = $db->query($sql_det);
    if (!$res_det) throw new Exception($db->lasterror());

    while ($obj = $db->fetch_object($res_det)) {
        $product = new Product($db);
        $product->fetch($obj->fk_product);
        $qty_total = ($obj->poids_net ?? 0);

        if ($qty_total <= 0) continue;

        // Création du mouvement de stock type Réception (reception = 0)
        /*$stock = new Stock($db);
        $result = $stock->reception(
            $rec->fk_entrepot,       // Entrepôt
            $obj->fk_product,        // Produit
            $qty_total,              // Quantité
            "Réception ".$rec->ref,  // Note
            $user,                   // Utilisateur
            $rec->rowid              // Id de référence
        );*/
        $modes = [1=>'Voiture',2=>'Poids Brut',3=>'Poids Net'];
        $modeLabel = isset($modes[$obj->reception_mode]) ? $modes[$obj->reception_mode] : '';

        $stock = new MouvementStock($db);

                $result = $stock->reception(
                    $user,                        // utilisateur
                    $obj->fk_product,              // produit
                    $rec->fk_entrepot,                 // entrepôt
                    $qty_total,                   // quantité
                    0,                             // prix (facultatif)
                    $langs->trans("Reception")." #".$rec->ref.' - '.$modeLabel // libellé
                );
        if ($result < 0) throw new Exception($stock->error);
    }

    // Changer l'état de la réception
    $sql_update = "UPDATE ".MAIN_DB_PREFIX."pech_reception SET etat = 1 WHERE rowid = ".(int)$id;
    if (!$db->query($sql_update)) throw new Exception($db->lasterror());

    $db->commit();
    setEventMessages($langs->trans("ReceptionValidated"), null, 'mesgs');
    header("Location: ./detail_rec.php?id=".$id);
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages($langs->trans("ErrorValidatingReception").": ".$e->getMessage(), null, 'errors');
    header("Location: ./detail_rec.php?id=".$id);
    exit;
}
?>
