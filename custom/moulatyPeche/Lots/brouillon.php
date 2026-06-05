<?php
/**
 * Fichier : revert_lot.php
 * Description : Permet de remettre un lot à l'état brouillon
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;
$langs->load("abricot@abricot");

$id_lot = GETPOST('id', 'int');
$confirm = GETPOST('confirm', 'alpha');

if ($id_lot <= 0) {
    setEventMessages("⚠️ Lot non spécifié.", null, 'errors');
    header("Location: list.php");
    exit;
}

// Récupération du lot
$sqlLot = "SELECT * FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".(int)$id_lot;
$resLot = $db->query($sqlLot);
if (!$resLot || $db->num_rows($resLot) == 0) {
    setEventMessages("❌ Lot introuvable.", null, 'errors');
    header("Location: list.php");
    exit;
}
$lot = $db->fetch_object($resLot);

if ($lot->statut == 0) {
    setEventMessages("⚠️ Lot déjà en état brouillon.", null, 'warnings');
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
    
    
    setEventMessages($message, null, 'errors');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}


// ============================================
// 🔥 CONFIRMATION DE L'ANNULATION
// ============================================
/*if (empty($confirm)) {
    llxHeader('', $langs->trans("RevertLotToDraft"));
    
    print load_fiche_titre($langs->trans("ConfirmRevertLot").' : '.$lot->ref, '', 'fa-undo');
    
    print '<div class="confirm-section" style="max-width: 800px; margin: 20px auto; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">';
    print '<h3><i class="fa fa-exclamation-triangle"></i> '.$langs->trans("Warning").'</h3>';
    print '<p>'.$langs->trans("AreYouSureToRevertLot").'</p>';
    print '<ul style="margin-left: 20px;">';
    print '<li>'.$langs->trans("AllCartonsWillBeDeleted").'</li>';
    print '<li>'.$langs->trans("PlatsWillBeReleased").'</li>';
    print '<li>'.$langs->trans("MisenplatStatsWillBeUpdated").'</li>';
    print '</ul>';
    
    print '<div class="center" style="margin-top: 30px;">';
    print '<a class="button button-delete" href="'.$_SERVER['PHP_SELF'].'?id='.$id_lot.'&confirm=yes">';
    print '<i class="fa fa-check"></i> '.$langs->trans("YesRevertToDraft");
    print '</a>';
    print '&nbsp;&nbsp;';
    print '<a class="button button-cancel" href="detail_lot.php?id="'.$id_lot.'">';
    print '<i class="fa fa-times"></i> '.$langs->trans("Cancel");
    print '</a>';
    print '</div>';
    print '</div>';
    
    llxFooter();
    exit;
}*/
if (empty($confirm)) {
    llxHeader('', $langs->trans("RevertLotToDraft"));
    
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
    
    print load_fiche_titre($langs->trans("ConfirmRevertLot") . ' : <span style="color: #e74c3c">' . $lot->ref . '</span>', '', 'fa-undo');
    
    print '<div class="confirm-section">';
    print '<h3><i class="fa fa-exclamation-triangle"></i> ' . $langs->trans("Warning") . '</h3>';
    print '<p>' . $langs->trans("AreYouSureToRevertLot") . '</p>';
    print '<ul>';
    print '<li>' . $langs->trans("AllCartonsWillBeDeleted") . '</li>';
    print '<li>' . $langs->trans("PlatsWillBeReleased") . '</li>';
    print '<li>' . $langs->trans("MisenplatStatsWillBeUpdated") . '</li>';
    print '</ul>';
    
    print '<div class="action-buttons">';
    print '<a class="btn btn-confirm" href="' . $_SERVER['PHP_SELF'] . '?id=' . $id_lot . '&confirm=yes">';
    print '<i class="fa fa-check"></i> ' . $langs->trans("YesRevertToDraft");
    print '</a>';
    print '<a class="btn btn-cancel" href="detail_lot.php?id=' . $id_lot . '">';
    print '<i class="fa fa-times"></i> ' . $langs->trans("Cancel");
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
    // 1. 🔄 RÉCUPÉRER TOUS LES MISENPLAT IMPLIQUÉS
    // ============================================
    $sqlAllMisenplat = "
        -- MISENPLAT des lignes simples
        SELECT DISTINCT fk_misenplat as mp_id
        FROM ".MAIN_DB_PREFIX."pech_lotdet 
        WHERE fk_lot = ".((int)$id_lot)."
        AND fk_misenplat IS NOT NULL
        
        UNION
        
        -- MISENPLAT des lignes multiples (décomposées)
        SELECT DISTINCT mp_id
        FROM (
            SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(fk_misenplats, ',', n.n), ',', -1) as mp_id
            FROM ".MAIN_DB_PREFIX."pech_lotdet 
            JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) n
            ON CHAR_LENGTH(fk_misenplats) - CHAR_LENGTH(REPLACE(fk_misenplats, ',', '')) >= n.n - 1
            WHERE fk_lot = ".((int)$id_lot)."
            AND fk_misenplats IS NOT NULL
            AND fk_misenplats != ''
        ) as exploded
        WHERE mp_id != ''
        
        UNION
        
        -- MISENPLAT des produits mixtes
        SELECT DISTINCT lm.fk_misenplat as mp_id
        FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat lm
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = lm.fk_lotdet_mixte
        WHERE ld.fk_lot = ".((int)$id_lot)."
    ";
    
    $resAllMp = $db->query($sqlAllMisenplat);
    $allMpIds = [];
    while ($mp = $db->fetch_object($resAllMp)) {
        $allMpIds[] = (int)$mp->mp_id;
    }
    
    // ============================================
    // 2. 📊 COMPTER LES PLATS UTILISÉS DANS CE LOT (avant suppression)
    // ============================================
    $used_counts = [];
    if (!empty($allMpIds)) {
        $sqlCountAllUsed = "
            SELECT 
                p.fk_misenplat as mp_id,
                COUNT(*) as plats_utilises
            FROM ".MAIN_DB_PREFIX."pech_plat p
            INNER JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.rowid = p.fk_carton
            INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
            WHERE ld.fk_lot = ".((int)$id_lot)."
            AND p.fk_misenplat IN (".implode(',', $allMpIds).")
            GROUP BY p.fk_misenplat
        ";
        
        $resCountAll = $db->query($sqlCountAllUsed);
        if ($resCountAll) {
            while ($row = $db->fetch_object($resCountAll)) {
                $used_counts[(int)$row->mp_id] = (int)$row->plats_utilises;
            }
        }
    }
    
    // ============================================
    // 3. 🔄 LIBÉRER LES PLATS (fk_carton = NULL, statut = 0)
    // ============================================
    $sqlFreePlats = "
        UPDATE ".MAIN_DB_PREFIX."pech_plat p
        INNER JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.rowid = p.fk_carton
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
        SET 
            p.fk_carton = NULL,
            p.statut = 0,
            p.tms = NOW()
        WHERE ld.fk_lot = ".((int)$id_lot);
    
    if (!$db->query($sqlFreePlats)) {
        throw new Exception("❌ Erreur lors de la libération des plats : ".$db->lasterror());
    }
    
    // ============================================
    // 4. 🗑️ SUPPRIMER TOUS LES CARTONS DU LOT
    // ============================================
    $sqlDeleteCartons = "
        DELETE c FROM ".MAIN_DB_PREFIX."pech_carton c
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
        WHERE ld.fk_lot = ".((int)$id_lot);
    
    if (!$db->query($sqlDeleteCartons)) {
        throw new Exception("❌ Erreur lors de la suppression des cartons : ".$db->lasterror());
    }
    
    // ============================================
    // 5. 🔢 MISE À JOUR DES STATISTIQUES DES MISENPLAT
    // ============================================
    foreach ($allMpIds as $mp_id) {
        $plats_utilises = isset($used_counts[$mp_id]) ? $used_counts[$mp_id] : 0;
        
        if ($plats_utilises > 0) {
            // Récupérer les valeurs actuelles
            $sqlCurrent = "SELECT nombre_plat, nombre_plat_sortie FROM ".MAIN_DB_PREFIX."pech_misenplat 
                          WHERE rowid = ".$mp_id;
            $resCurrent = $db->query($sqlCurrent);
            
            if ($resCurrent && $db->num_rows($resCurrent) > 0) {
                $current = $db->fetch_object($resCurrent);
                $nouveau_nombre_plat_sortie = max(0, $current->nombre_plat_sortie - $plats_utilises);
                
                // Calculer le nouveau statut
                $nouveau_statut = 0; // Par défaut brouillon
                if ($nouveau_nombre_plat_sortie > 0) {
                    if ($nouveau_nombre_plat_sortie >= $current->nombre_plat) {
                        $nouveau_statut = 2; // Complètement sorti
                    } else {
                        $nouveau_statut = 1; // Partiellement sorti
                    }
                }
                
                $sqlUpdateMp = "
                    UPDATE ".MAIN_DB_PREFIX."pech_misenplat 
                    SET nombre_plat_sortie = ".$nouveau_nombre_plat_sortie.",
                    statut = 1, 
                        tms = NOW()
                    WHERE rowid = ".$mp_id;
                
                if (!$db->query($sqlUpdateMp)) {
                    throw new Exception("❌ Erreur mise à jour misenplat #".$mp_id." : ".$db->lasterror());
                }
            }
        }
    }
    
    // ============================================
    // 6. 🗑️ SUPPRIMER LES LIENS MIXTES (si existent)
    // ============================================
    $sqlDeleteMixteLinks = "
        DELETE lm FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat lm
        INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = lm.fk_lotdet_mixte
        WHERE ld.fk_lot = ".((int)$id_lot);
    
    if (!$db->query($sqlDeleteMixteLinks)) {
        // Ne pas échouer si la table n'existe pas
        if (strpos($db->lasterror(), "doesn't exist") === false) {
            throw new Exception("❌ Erreur suppression liens mixtes : ".$db->lasterror());
        }
    }
    
    // Supprimer aussi les lignes de lotdet mixtes
    $sqlDeleteMixteLotdet = "
        DELETE FROM ".MAIN_DB_PREFIX."pech_lotdet 
        WHERE fk_lot = ".((int)$id_lot)."
        AND EXISTS (
            SELECT 1 FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat lm
            WHERE lm.fk_lotdet_mixte = ".MAIN_DB_PREFIX."pech_lotdet.rowid
        )";
    
    $db->query($sqlDeleteMixteLotdet);
    
    // ============================================
    // 7. 📝 METTRE LE LOT À BROUILLON
    // ============================================
    $sqlUpdateLot = "
        UPDATE ".MAIN_DB_PREFIX."pech_lot 
        SET statut = 0,
        total_frais = 0,
        fk_facture = NULL,
        fk_bonentree = NULL,



            tms = NOW()
        WHERE rowid = ".((int)$id_lot);
    
    if (!$db->query($sqlUpdateLot)) {
        throw new Exception("❌ Erreur mise à jour statut lot : ".$db->lasterror());
    }
    
    $db->commit();
    
    setEventMessages("✅ Lot <strong>{$lot->ref}</strong> remis à l'état brouillon avec succès.", null, 'mesgs');
    
} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
}

// ============================================
// 🔄 REDIRECTION
// ============================================
header("Location: detail_lot.php?id=".$id_lot);
exit;