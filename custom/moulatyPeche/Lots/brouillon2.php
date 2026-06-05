<?php
/**
 * Fichier : revert_lot_direct.php
 * Description : Permet de remettre un lot DIRECT à l'état brouillon
 * (Annule les mouvements de stock et supprime les cartons créés)
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

global $db, $langs, $user;
$langs->load("abricot@abricot");

$id_lot = GETPOST('id', 'int');
$confirm = GETPOST('confirm', 'alpha');

if ($id_lot <= 0) {
    setEventMessages("⚠️ Lot non spécifié.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}

// Vérification des droits
if (empty($user->admin)) {
    accessforbidden('Accès réservé.');
}

// Récupération du lot
$sqlLot = "SELECT * FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".(int)$id_lot;
$resLot = $db->query($sqlLot);
if (!$resLot || $db->num_rows($resLot) == 0) {
    setEventMessages("❌ Lot introuvable.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}
$lot = $db->fetch_object($resLot);

// Vérifier si c'est un lot direct
if ($lot->source_type != 1) {
    setEventMessages("❌ Ce n'est pas un lot direct. Utilisez la fonction d'annulation standard.", null, 'errors');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}

if ($lot->statut == 0) {
    setEventMessages("⚠️ Lot déjà en état brouillon.", null, 'warnings');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}

// ============================================
// 🔍 VÉRIFICATION SI LE LOT EST LIÉ À DES FACTURES
// ============================================
$sqlFact = "SELECT rowid, ref, fk_soc, ref_supplier, total_ht, datef 
            FROM ".MAIN_DB_PREFIX."facture_fourn 
            WHERE ref_supplier LIKE '".$db->escape($lot->ref)."%' 
            ORDER BY datef DESC";

$resFact = $db->query($sqlFact);
$factures_liees = [];

if ($resFact && $db->num_rows($resFact) > 0) {
    while ($fact = $db->fetch_object($resFact)) {
        $factures_liees[] = $fact;
    }
}

// Si le lot est lié à des factures, refuser l'annulation
if (!empty($factures_liees)) {
    $message = "❌ Impossible d'annuler ce lot car il est lié à " . count($factures_liees) . " facture(s) :<br><br>";
    $message .= "<ul>";
    foreach ($factures_liees as $facture) {
        $message .= "<li><strong>Facture " . $facture->ref . "</strong>";
        $message .= " - Client: " . $facture->fk_soc;
        $message .= " - Montant: " . price($facture->total_ht, 0, '', 1, -1, -1, $conf->currency);
        $message .= " - Date: " . dol_print_date($db->jdate($facture->datef), 'day');
        $message .= "</li>";
    }
    $message .= "</ul>";
    
    setEventMessages($message, null, 'errors');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}

// ============================================
// 🔍 VÉRIFICATION SI LE LOT CONTIENT DES CARTONS DÉJÀ SORTIS (statut 1)
// ============================================
$sqlCartonsSortis = "SELECT COUNT(*) as nb_cartons_sortis
                     FROM ".MAIN_DB_PREFIX."pech_carton c
                     INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
                     WHERE ld.fk_lot = ".(int)$id_lot."
                     AND c.statut = 1";

$resCartons = $db->query($sqlCartonsSortis);
if ($resCartons) {
    $objCartons = $db->fetch_object($resCartons);
    $nb_cartons_sortis = (int)$objCartons->nb_cartons_sortis;
    
    if ($nb_cartons_sortis > 0) {
        setEventMessages("❌ Impossible d'annuler ce lot car il contient <strong>$nb_cartons_sortis carton(s) déjà sortis</strong> (statut = sorti).<br>Vous devez d'abord retourner ces cartons en stock.", null, 'errors');
        header("Location: detail_lot.php?id=".$id_lot);
        exit;
    }
}

// ============================================
// 🔥 CONFIRMATION DE L'ANNULATION
// ============================================
if (empty($confirm)) {
    llxHeader('', $langs->trans("RevertDirectLotToDraft"));
     print '
    <style>
    .confirm-section {
        max-width: 700px;
        margin: 40px auto;
        padding: 30px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        border: 2px solid #ff6b6b;
    }
    
    .confirm-section h3 {
        color: #e74c3c;
        margin-top: 0;
        padding-bottom: 15px;
        border-bottom: 2px solid #ffd7d7;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 22px;
    }
    
    .confirm-section p {
        font-size: 16px;
        line-height: 1.6;
        color: #555;
        margin: 20px 0;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 6px;
        border-left: 4px solid #3498db;
    }
    
    .confirm-section ul {
        margin: 25px 0;
        padding-left: 30px;
    }
    
    .confirm-section li {
        margin-bottom: 12px;
        padding: 12px 15px;
        background: #fff8e1;
        border-radius: 6px;
        border-left: 4px solid #ffa726;
        transition: 0.2s;
        list-style: none;
        position: relative;
    }
    
    .confirm-section li:before {
        content: "⚠️";
        margin-right: 10px;
    }
    
    .confirm-section li:hover {
        background: #fff3cd;
        transform: translateX(5px);
    }
    
    .action-buttons {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 35px;
        padding-top: 25px;
        border-top: 1px solid #eee;
    }
    
    .btn {
        padding: 12px 30px;
        border-radius: 6px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        font-size: 15px;
    }
    
    .btn-confirm {
        background: #27ae60;
        color: white;
    }
    
    .btn-confirm:hover {
        background: #219a52;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
    }
    
    .btn-cancel {
        background: #95a5a6;
        color: white;
    }
    
    .btn-cancel:hover {
        background: #7f8c8d;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(149, 165, 166, 0.3);
    }
    
    @media (max-width: 768px) {
        .confirm-section {
            margin: 20px 15px;
            padding: 20px;
        }
        
        .action-buttons {
            flex-direction: column;
            align-items: center;
        }
        
        .btn {
            width: 100%;
            max-width: 300px;
            justify-content: center;
        }
    }
    </style>
    ';
    
    // Récupérer les informations sur les cartons à supprimer
    $sqlCartonsInfo = "SELECT COUNT(*) as nb_cartons, SUM(poids) as total_poids
                      FROM ".MAIN_DB_PREFIX."pech_carton c
                      INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
                      WHERE ld.fk_lot = ".(int)$id_lot;
    
    $resCartonsInfo = $db->query($sqlCartonsInfo);
    $cartons_info = $resCartonsInfo ? $db->fetch_object($resCartonsInfo) : null;
    
    print load_fiche_titre($langs->trans("ConfirmRevertDirectLot").' : '.$lot->ref, '', 'fa-undo');
    
    print '<div class="confirm-section" style="max-width: 800px; margin: 20px auto; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">';
    print '<h3><i class="fa fa-exclamation-triangle"></i> '.$langs->trans("Warning").'</h3>';
    print '<p>'.$langs->trans("AreYouSureToRevertDirectLot").'</p>';
    print '<ul style="margin-left: 20px;">';
    print '<li>'.$langs->trans("AllCartonsWillBeDeleted").' : <strong>'.($cartons_info ? $cartons_info->nb_cartons : 0).' cartons</strong></li>';
    print '<li>'.$langs->trans("StockMovementsWillBeReversed").' : <strong>'.($cartons_info ? price($cartons_info->total_poids, 0, '', 1, -1, -1).' kg' : '0 kg').'</strong></li>';
    print '<li><strong>'.$langs->trans("NoLinkedInvoices").'</strong> ✓</li>';
    print '<li><strong>'.$langs->trans("NoShippedCartons").'</strong> ✓</li>';
    print '</ul>';
    
    print '<div class="center" style="margin-top: 30px;">';
    print '<a class="button button-delete" href="'.$_SERVER['PHP_SELF'].'?id='.$id_lot.'&confirm=yes">';
    print '<i class="fa fa-check"></i> '.$langs->trans("YesRevertToDraft");
    print '</a>';
    print '&nbsp;&nbsp;';
    print '<a class="button button-cancel" href="detail_lot.php?id='.$id_lot.'">';
    print '<i class="fa fa-times"></i> '.$langs->trans("Cancel");
    print '</a>';
    print '</div>';
    print '</div>';
    
    llxFooter();
    exit;
}

// ============================================
// 🔥 EXÉCUTION DE L'ANNULATION
// ============================================
$db->begin();
try {
    // ============================================
    // 1. 📊 RÉCUPÉRER TOUTES LES LIGNES DU LOT
    // ============================================
    $sqlLines = "SELECT ld.*, p.label as product_label
                FROM ".MAIN_DB_PREFIX."pech_lotdet ld
                LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ld.fk_product
                WHERE ld.fk_lot = ".(int)$id_lot;
    
    $resLines = $db->query($sqlLines);
    $lines = [];
    while ($line = $db->fetch_object($resLines)) {
        $lines[] = $line;
    }
    
    // ============================================
    // 2. 🔄 ANNULER LES MOUVEMENTS DE STOCK
    // ============================================
    foreach ($lines as $line) {
        // Récupérer le nombre total de cartons pour cette ligne
        $sqlCartonsLine = "SELECT COUNT(*) as nb_cartons, SUM(poids) as total_poids
                          FROM ".MAIN_DB_PREFIX."pech_carton
                          WHERE fk_lotdet = ".(int)$line->rowid;
        
        $resCartonsLine = $db->query($sqlCartonsLine);
        if ($resCartonsLine) {
            $cartonsLine = $db->fetch_object($resCartonsLine);
            $qty_to_remove = (float)$cartonsLine->total_poids;
            
            if ($qty_to_remove > 0 && $lot->fk_entrepot > 0) {
                // Créer un mouvement de SORTIE (diminution du stock)
                $mouv = new MouvementStock($db);
                
                $label = "Annulation lot " . $lot->ref . " - " . $line->product_label;
                
                // Pour annuler, on fait une SORTIE (diminue le stock)
                $result = $mouv->livraison(
                    $user,                     // utilisateur
                    $line->fk_product,         // produit
                    $lot->fk_entrepot,         // entrepôt
                    $qty_to_remove,            // quantité à retirer
                    0,                         // prix unitaire
                    $label                     // libellé
                );
                
                if ($result < 0) {
                    throw new Exception("❌ Erreur annulation stock pour " . $line->product_label . " : " . $mouv->error);
                }
            }
        }
    }
    
    // ============================================
    // 3. 🗑️ SUPPRIMER TOUS LES CARTONS DU LOT
    // ============================================
    $sqlDeleteCartons = "
        DELETE c FROM ".MAIN_DB_PREFIX."pech_carton c
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
        WHERE ld.fk_lot = ".(int)$id_lot;
    
    if (!$db->query($sqlDeleteCartons)) {
        throw new Exception("❌ Erreur lors de la suppression des cartons : ".$db->lasterror());
    }
    
    // ============================================
    // 4. 📝 METTRE LE LOT ET SES LIGNES À BROUILLON
    // ============================================
    // Mettre les lignes lotdet à brouillon
    $sqlUpdateLines = "
        UPDATE ".MAIN_DB_PREFIX."pech_lotdet 
        SET statut = 0,
            tms = NOW()
        WHERE fk_lot = ".(int)$id_lot;
    
    if (!$db->query($sqlUpdateLines)) {
        throw new Exception("❌ Erreur mise à jour lignes lotdet : ".$db->lasterror());
    }
    
    // Mettre le lot à brouillon
    $sqlUpdateLot = "
        UPDATE ".MAIN_DB_PREFIX."pech_lot 
        SET statut = 0,
        total_frais = 0,
        fk_facture_fourn = NULL,
        fk_bonentree = NULL,
            tms = NOW()
        WHERE rowid = ".(int)$id_lot;
    
    if (!$db->query($sqlUpdateLot)) {
        throw new Exception("❌ Erreur mise à jour statut lot : ".$db->lasterror());
    }
    
    

    $db->commit();
    
    setEventMessages("✅ Lot direct <strong>{$lot->ref}</strong> remis à l'état brouillon avec succès.<br>Mouvements de stock annulés et cartons supprimés.", null, 'mesgs');
    
} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
}

// ============================================
// 🔄 REDIRECTION
// ============================================
header("Location: detail_lot.php?id=".$id_lot);
exit;