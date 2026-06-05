<?php
// save_lot.php - Traitement création lot direct SANS créer de cartons
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

global $db, $user, $langs, $conf;
$langs->load("abricot@abricot");

$action      = GETPOST('action', 'alpha');
$token       = GETPOST('token', 'alpha');
$ref         = GETPOST('ref', 'alpha');
$fk_fourn    = GETPOST('fk_fournisseur', 'int');
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$commentaire = GETPOST('commentaire', 'restricthtml');
$source_type = GETPOST('source_type', 'int');

$date_creation = GETPOST('date_creation', 'none'); // format attendu : YYYY-MM-DDTHH:MM

// ⚠️ CORRECTION : Récupérer les bons noms de champs du formulaire
$lines_product    = GETPOST('product_id', 'array');
$lines_nb_carton  = GETPOST('nb_carton', 'array');
$lines_poids_carton = GETPOST('poids_carton', 'array');
$lines_pu_carton  = GETPOST('pu_carton', 'array');  // Prix unitaire par carton
$lines_total_line = GETPOST('total_line', 'array');  // Total par ligne
$lines_comment    = GETPOST('line_comment', 'array');

$total_lot = GETPOST('total_lot', 'float');  // Total général

try {
    // Vérifications basiques
    if ($action !== 'save') {
        throw new Exception($langs->trans("BadAction"));
    }
    
    // Vérification token
    
    
    if (empty($fk_fourn) || $fk_fourn <= 0) {
        throw new Exception($langs->trans("SupplierMissing"));
    }
    
    if (empty($fk_entrepot) || $fk_entrepot <= 0) {
        throw new Exception($langs->trans("EntrepotMissing"));
    }
    
    if (empty($lines_product) || count($lines_product) == 0) {
        throw new Exception($langs->trans("NoLines"));
    }

    // Vérifier référence dernière
    $res = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot ORDER BY rowid DESC LIMIT 1");
    $lastRef = ($res && $db->num_rows($res) > 0) ? $db->fetch_object($res)->ref : '';
    
    // Si la référence existe déjà, en générer une nouvelle
    if (!empty($lastRef)) {
        $sqlCheck = "SELECT COUNT(*) as count FROM ".MAIN_DB_PREFIX."pech_lot WHERE ref = '".$db->escape($ref)."'";
        $resCheck = $db->query($sqlCheck);
        if ($resCheck) {
            $objCheck = $db->fetch_object($resCheck);
            if ($objCheck->count > 0) {
                // Générer nouvelle référence
                preg_match('/Lot-(\d+)/', $lastRef, $matches);
                $nextNumber = !empty($matches[1]) ? ((int)$matches[1] + 1) : 1;
                $ref = 'Lot-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        }
    }

    // Vérifier lignes valides
    $validLines = [];
    $total_lot_calculated = 0;
    
    foreach ($lines_product as $i => $product_id) {
        $product_id = (int)$product_id;
        
        if ($product_id <= 0) {
            continue; // Ignorer les lignes sans produit
        }
        
        $nb_carton = isset($lines_nb_carton[$i]) ? (int)$lines_nb_carton[$i] : 0;
        $poids_carton = isset($lines_poids_carton[$i]) ? (float)str_replace(',', '.', $lines_poids_carton[$i]) : 0.0;
        $pu_carton = isset($lines_pu_carton[$i]) ? (float)str_replace(',', '.', $lines_pu_carton[$i]) : 0.0;
        $total_line = isset($lines_total_line[$i]) ? (float)str_replace(',', '.', $lines_total_line[$i]) : 0.0;
        $comment = isset($lines_comment[$i]) ? trim($db->escape($lines_comment[$i])) : '';
        
        // Vérifications pour chaque ligne
        if ($nb_carton <= 0) {
            throw new Exception($langs->trans("QuantityMustBeGreaterThanZero") . " (ligne " . ($i+1) . ")");
        }
        
        if ($poids_carton <= 0) {
            throw new Exception("Poids du carton doit être > 0 (ligne " . ($i+1) . ")");
        }
        
        // Vérifier que le produit existe
        $sqlProduct = "SELECT label FROM ".MAIN_DB_PREFIX."product WHERE rowid = " . $product_id;
        $resProduct = $db->query($sqlProduct);
        if (!$resProduct || $db->num_rows($resProduct) == 0) {
            throw new Exception("Produit introuvable (ID: " . $product_id . ")");
        }
        $product_info = $db->fetch_object($resProduct);
        
        $validLines[] = [
            'product_id' => $product_id,
            'product_label' => $product_info->label,
            'nb_carton' => $nb_carton,
            'poids_carton' => $poids_carton,
            'pu_carton' => $pu_carton,
            'total_line' => $total_line,
            'comment' => $comment
        ];
        
        $total_lot_calculated += $total_line;
    }
    
    if (empty($validLines)) {
        throw new Exception($langs->trans("NoValidLines"));
    }

    // =========================
    // Tout est OK, début transaction
    // =========================
    $db->begin();

    // Ajouter info que lot créé par réception directe
    if (!empty($commentaire)) {
        $commentaire .= "\n";
    }
    $commentaire .= "Lot créé par réception directe des cartons.";
    
    // Vérifier le fournisseur
    $sqlFourn = "SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid = " . (int)$fk_fourn;
    $resFourn = $db->query($sqlFourn);
    if (!$resFourn || $db->num_rows($resFourn) == 0) {
        throw new Exception("Fournisseur introuvable");
    }

    // Insert pech_lot
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."pech_lot
            (ref, fk_user_create, fk_entrepot, fk_bonentree, source_type, fk_fourn, 
             fk_facture_fourn, total_frais, date_creation, commentaire, statut)
            VALUES (
                '" . $db->escape($ref) . "',
                " . ((int)$user->id) . ",
                " . ((int)$fk_entrepot) . ",
                NULL,
                " . ((int)$source_type) . ",
                " . ((int)$fk_fourn) . ",
                NULL,
                0,
                '".$date_creation."',
                '" . $db->escape($commentaire) . "',
                0  -- statut = 0 (brouillon)
                
            )";
    
    if (!$db->query($sql)) {
        throw new Exception("Erreur création lot: " . $db->lasterror());
    }
    
    $id_lot = $db->last_insert_id(MAIN_DB_PREFIX."pech_lot");

    // Insert lignes lotdet (SANS créer de cartons)
    foreach ($validLines as $index => $line) {
        // Calculer le prix unitaire
        $prix_unitaire = $line['pu_carton'];
        if ($prix_unitaire <= 0 && $line['nb_carton'] > 0) {
            $prix_unitaire = $line['total_line'] / $line['nb_carton'];
        }
        
        $sqlDet = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet
                   (fk_lot, fk_product, fk_misenplat, fk_misenplats, mode_misenplat, 
                    source_type, poids_carton, plat_carton, nb_carton, 
                    taux_rendement, commentaire, prix, date_creation, statut)
                   VALUES (
                       " . (int)$id_lot . ",
                       " . (int)$line['product_id'] . ",
                       NULL,
                       NULL,
                       'single',
                       " . ((int)$source_type) . ",
                       " . (float)$line['poids_carton'] . ",
                       0,  -- plat_carton = 0 (pas de plats, seulement cartons)
                       " . (int)$line['nb_carton'] . ",
                       100,
                       '" . $db->escape($line['comment']) . "',
                       " . (float)$prix_unitaire . ",
                       NOW(),
                       0  -- statut = 0 (brouillon)
                       
                   )";
        
        if (!$db->query($sqlDet)) {
            throw new Exception("Erreur insertion ligne " . ($index+1) . ": " . $db->lasterror());
        }
        
        // ⚠️ PAS de création de cartons ici ! Les cartons seront créés lors de la validation
    }

    // Commit transaction
    $db->commit();
    
    // Message de succès
    $message = $langs->trans("LotCreatedSuccess") . " " . $ref . ".<br>";
    $message .= $langs->trans("LotMustBeValidatedToCreateCartons");
    setEventMessages($message, null, 'mesgs');
    
    // Redirection vers la page de détail du lot
    header("Location: ../Lots/detail_lot.php?id=" . $id_lot);
    exit;

} catch (Exception $e) {
    // Rollback si erreur
    if (isset($db) && method_exists($db, 'status') && $db->status === 'begin') {
        $db->rollback();
    }
    
    setEventMessages($e->getMessage(), null, 'errors');
    
    // Retour vers le formulaire avec les données
    $url = "recep_cart.php";
    if (isset($ref)) $url .= "?ref=" . urlencode($ref);
    if (isset($fk_fourn)) $url .= (strpos($url, '?') === false ? "?" : "&") . "fk_fourn=" . $fk_fourn;
    if (isset($fk_entrepot)) $url .= "&fk_entrepot=" . $fk_entrepot;
    
    header("Location: " . $url);
    exit;
}
?>