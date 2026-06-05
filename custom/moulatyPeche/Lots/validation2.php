<?php
// validate_lot_direct.php - Validation lot direct (création cartons et prix unitaire)
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

global $db, $langs, $user;
$langs->load("abricot@abricot");

$id_lot = GETPOST('id', 'int');
if ($id_lot <= 0) {
    setEventMessages("⚠️ Lot non spécifié.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}
if (empty($user->rights->moulatyPeche->read_r)) {
    accessforbidden('Accès réservé à l’administrateur.');
}

// Récupération du lot
$sqlLot = "SELECT * FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid=".$id_lot;
$resLot = $db->query($sqlLot);
if (!$resLot || $db->num_rows($resLot) == 0) {
    setEventMessages("❌ Lot introuvable.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}
$lot = $db->fetch_object($resLot);

if ($lot->statut == 1) {
    setEventMessages("⚠️ Lot déjà validé.", null, 'warnings');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}
if ($lot->fk_entrepot <= 0) {
    setEventMessages("⚠️ Pas d'entrepôt défini.", null, 'warnings');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}

// Récupération lignes de détail
$sqlLines = "SELECT ld.*, p.ref as product_ref, p.label as product_label
             FROM ".MAIN_DB_PREFIX."pech_lotdet ld
             LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ld.fk_product
             WHERE ld.fk_lot=".$id_lot;
$resLines = $db->query($sqlLines);
$lines = [];
while ($obj = $db->fetch_object($resLines)) $lines[] = $obj;

if (empty($lines)) {
    setEventMessages("❌ Aucune ligne de produit dans ce lot.", null, 'errors');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}

$db->begin();
try {
    $total_cartons_crees = 0;
    
    foreach ($lines as $line) {
        $nb_cartons = $line->nb_carton;
        $poids_par_carton = $line->poids_carton;
        
        // ⚠️ CORRECTION : Prix unitaire par carton
        // Le champ 'prix' dans pech_lotdet est déjà le prix PAR CARTON pour les lots directs
        $prix_unitaire_carton = (float)$line->prix;
        
        if ($nb_cartons <= 0) {
            throw new Exception("Nombre de cartons invalide pour la ligne " . $line->product_label);
        }
        
        if ($poids_par_carton <= 0) {
            throw new Exception("Poids du carton invalide pour " . $line->product_label);
        }
        
        // 🔹 Création des cartons pour chaque ligne
        for ($i = 0; $i < $nb_cartons; $i++) {
            // Générer une référence unique pour le carton
            $ref_carton = $lot->ref . '-C' . str_pad($total_cartons_crees + 1, 3, '0', STR_PAD_LEFT);
            
            $sqlCarton = "INSERT INTO ".MAIN_DB_PREFIX."pech_carton
                          (fk_lotdet, fk_product, nb_plat, poids, fk_user_create, 
                           prix_moyen, frais, ref_carton, date_creation, statut, commentaire, entity)
                          VALUES (
                              ".((int)$line->rowid).",
                              ".((int)$line->fk_product).",
                              0,  -- nb_plat = 0 pour les lots directs (pas de plats individuels)
                              ".((float)$poids_par_carton).",
                              ".((int)$user->id).",
                              ".((float)$prix_unitaire_carton).",
                              0,  -- frais = 0
                              '".$db->escape($ref_carton)."',
                              NOW(),
                              0,  -- statut = 0 (disponible)
                              '".$db->escape("Lot direct - " . $line->product_label)."',
                              1
                          )";
            
            if (!$db->query($sqlCarton)) {
                throw new Exception("Erreur création carton " . ($i+1) . " pour " . $line->product_label . " : " . $db->lasterror());
            }
            
            $total_cartons_crees++;
        }

        // -----------------------------------------
        // 🔹 MOUVEMENT DE STOCK
        // -----------------------------------------
        // Quantité totale à ajouter au stock (en kg)
        $qty_total = $nb_cartons * $poids_par_carton;
        
        // Vérifier que le produit existe
        $sqlCheckProduct = "SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE rowid = " . ((int)$line->fk_product);
        $resCheckProduct = $db->query($sqlCheckProduct);
        if (!$resCheckProduct || $db->num_rows($resCheckProduct) == 0) {
            throw new Exception("Produit introuvable (ID: " . $line->fk_product . ")");
        }
        
        // Créer le mouvement de stock
        $mouv = new MouvementStock($db);
        
        // Libellé du mouvement
        $label = "Réception lot " . $lot->ref . " - " . $line->product_label . " (" . $nb_cartons . " cartons)";
        
        // Pour les lots directs, on fait une RÉCEPTION (augmentation du stock)
        $result = $mouv->reception(
            $user,                     // utilisateur
            $line->fk_product,         // produit
            $lot->fk_entrepot,         // entrepôt
            $qty_total,                // quantité en kg
            0,     // prix unitaire
            $label                    // libellé
            
        );
        
        if ($result < 0) {
            throw new Exception("Erreur mouvement stock pour " . $line->product_label . " : " . $mouv->error);
        }
        
        // Optionnel : Mettre à jour le coût du produit si nécessaire
        if ($prix_unitaire_carton > 0) {
            // Vous pouvez mettre à jour le coût du produit ici si nécessaire
            // $sqlUpdateCost = "UPDATE ".MAIN_DB_PREFIX."product SET cost_price = " . $prix_unitaire_carton . " WHERE rowid = " . $line->fk_product;
        }
    }
    
    // 🔹 Mettre à jour le prix total du lot
    $sqlTotalLot = "SELECT SUM(prix * nb_carton) as total_lot 
                    FROM ".MAIN_DB_PREFIX."pech_lotdet 
                    WHERE fk_lot = " . ((int)$id_lot);
    $resTotal = $db->query($sqlTotalLot);
    if ($resTotal) {
        $objTotal = $db->fetch_object($resTotal);
        $total_lot = (float)$objTotal->total_lot;
        
        /*$sqlUpdateLot = "UPDATE ".MAIN_DB_PREFIX."pech_lot 
                        SET total_frais = " . $total_lot . ",
                            tms = NOW()
                        WHERE rowid = " . ((int)$id_lot);
        $db->query($sqlUpdateLot);*/
    }

    // Validation du lot
    $sql = "UPDATE ".MAIN_DB_PREFIX."pech_lot SET statut = 1 WHERE rowid = " . $id_lot;
    if (!$db->query($sql)) {
        throw new Exception("Erreur validation lot : " . $db->lasterror());
    }
    
    // Mettre à jour le statut des lignes lotdet
    $sqlUpdateLines = "UPDATE ".MAIN_DB_PREFIX."pech_lotdet SET statut = 1 WHERE fk_lot = " . $id_lot;
    $db->query($sqlUpdateLines);

    $db->commit();
    
    // Message de succès
    $message = "✅ Lot <strong>{$lot->ref}</strong> validé avec succès.<br>";
    $message .= "📦 <strong>{$total_cartons_crees} cartons</strong> créés.<br>";
    $message .= "📊 Mouvements de stock enregistrés.";
    
    setEventMessages($message, null, 'mesgs');
    
} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
}

// Redirection vers le détail
header("Location: detail_lot.php?id=" . $id_lot);
exit;
?>