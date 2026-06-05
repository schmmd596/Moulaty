<?php
/* Copyright (C) 2025
 * Abdou Mahfoudh <superadmin@womapeche.com>
 * All rights reserved.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
include_once '../user_entrepot_access.php';

global $db, $langs, $user, $conf;

$langs->loadLangs(['main', 'products', 'womapeche@womapeche']);

// ──────────────────────────────────────────────
// 🔹 1️⃣ Récupération de la réception
// ──────────────────────────────────────────────
$id = GETPOST('id', 'int');
if (empty($id)) {
    setEventMessages($langs->trans("IdentifiantReceptionManquant"), null, 'errors');
    header("Location: ./list.php");
    exit;
}

$sql = "SELECT r.*, 
               s.nom as fournisseur_nom, 
               c.nom as congelateur_nom, 
               e.ref as entrepot_ref
        FROM ".MAIN_DB_PREFIX."pech_reception as r
        LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON r.fk_fournisseur = s.rowid
        LEFT JOIN ".MAIN_DB_PREFIX."societe as c ON r.fk_congelateur = c.rowid
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON r.fk_entrepot = e.rowid
        WHERE r.rowid = ".((int)$id);

$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) {
    setEventMessages($langs->trans("ReceptionNotFound"), null, 'errors');
    header("Location: ./list.php");
    exit;
}
$reception = $db->fetch_object($resql);

check_user_entrepot_access($reception->fk_entrepot);

// ──────────────────────────────────────────────
// 🔹 2️⃣ En-tête de page
// ──────────────────────────────────────────────
llxHeader('', $langs->trans("DetailReception").$reception->ref);

// Inclusion du CSS global pour les détails
print '<link rel="stylesheet" href="../global_detail_style.css">';

print '<div class="detail-container">';
    
    // ============================================================================
    // 🎯 En-tête
    // ============================================================================
    print '<div class="detail-header">';
    print '<h1>';
    print '<i class="fa fa-truck-loading"></i>';
    print $langs->trans("Reception");
    print '<span class="ref">'.$reception->ref.'</span>';
    print '</h1>';
    print '</div>';



    // ============================================================================
    // 🧾 Informations principales
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-info-circle"></i> '.$langs->trans("InformationsGenerales").'</h3>';
    
    print '<table class="info-table">';
    print '<tr><td class="field-label"><i class="fa fa-user"></i> '.$langs->trans("Fournisseur").'</td><td class="field-value"><strong>'.$reception->fournisseur_nom.'</strong></td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-snowflake"></i> '.$langs->trans("Congelateur").'</td><td class="field-value">'.($reception->congelateur_nom ?: $langs->trans("NonSpecifie")).'</td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-warehouse"></i> '.$langs->trans("Entrepot").'</td><td class="field-value">'.($reception->entrepot_ref ?: $langs->trans("NonSpecifie")).'</td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-calendar-alt"></i> '.$langs->trans("DateCreation").'</td><td class="field-value">'.dol_print_date($db->jdate($reception->date_creation), 'dayhour').'</td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-balance-scale"></i> '.$langs->trans("PoidsTotal").'</td><td class="field-value"><strong>'.price($reception->poids).' Kg</strong></td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-money-bill"></i> '.$langs->trans("MontantTotal").'</td><td class="field-value"><strong>'.price($reception->montant).' '.$conf->currency.'</strong></td></tr>';

    print '<tr><td class="field-label"><i class="fa fa-flag"></i> '.$langs->trans("Etat").'</td><td class="field-value">';
    if ($reception->etat == 0) print '<span class="badge badge-warning"><i class="fa fa-hourglass-half"></i> '.$langs->trans("EnAttente").'</span>';
    elseif ($reception->etat == 1) print '<span class="badge badge-success"><i class="fa fa-check-circle"></i> '.$langs->trans("Valide").'</span>';
    else print '<span class="badge badge-danger"><i class="fa fa-times-circle"></i> '.$langs->trans("Annule").'</span>';
    print '</td></tr>';
    print '</table>';
    print '</div>';

    // ============================================================================
    // 💬 Commentaire
    // ============================================================================
    if (!empty($reception->comment)) {
        print '<div class="detail-section">';
        print '<h3 class="section-title"><i class="fa fa-comment"></i> '.$langs->trans("Commentaire").'</h3>';
        print '<div class="comment-section">';
        print '<div class="comment-content">'.nl2br(dol_escape_htmltag($reception->comment)).'</div>';
        print '</div>';
        print '</div>';
    }

    // ============================================================================
    // 📦 Lignes de réception
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-box"></i> '.$langs->trans("DetailsLignesReception").'</h3>';

    $sqlL = "SELECT d.*, p.ref as product_ref, p.label as product_label
             FROM ".MAIN_DB_PREFIX."pech_receptiondet as d
             LEFT JOIN ".MAIN_DB_PREFIX."product as p ON d.fk_product = p.rowid
             WHERE d.fk_reception = ".((int)$id);

    $resqlL = $db->query($sqlL);

    if ($resqlL && $db->num_rows($resqlL) > 0) {
        print '<div class="table-responsive">';
        print '<table class="data-table">';
        print '<thead><tr>';
        print '<th><i class="fa fa-fish"></i> '.$langs->trans("Produit").'</th>';
        print '<th><i class="fa fa-cog"></i> '.$langs->trans("Mode").'</th>';
        print '<th>'.$langs->trans("Calibre").'</th>';
        print '<th class="text-right"><i class="fa fa-truck"></i> '.$langs->trans("NbVoiture").'</th>';
        print '<th class="text-right"><i class="fa fa-money-bill-wave"></i> '.$langs->trans("PrixVoiture").'</th>';
        print '<th class="text-right"><i class="fa fa-weight-hanging"></i> '.$langs->trans("PoidsBrut").'</th>';
        print '<th class="text-right"><i class="fa fa-money-bill-wave"></i> '.$langs->trans("PUBrut").'</th>';
        print '<th class="text-right"><i class="fa fa-weight"></i> '.$langs->trans("PoidsNet").'</th>';
        print '<th class="text-right"><i class="fa fa-money-bill-wave"></i> '.$langs->trans("PUPoidsNet").'</th>';
        print '<th class="text-right"><i class="fa fa-calculator"></i> '.$langs->trans("TotalLigne").'</th>';
        print '</tr></thead>';
        print '<tbody>';

        $total_general = 0;
        while ($obj = $db->fetch_object($resqlL)) {
            $mode = ($obj->reception_mode == 1) ? $langs->trans("Voiture") :
                    (($obj->reception_mode == 2) ? $langs->trans("PoidsBrut") : $langs->trans("PoidsNet"));
            $total_general += $obj->total_line;

            print '<tr>';
            print '<td><i class="fa fa-box"></i> '.dol_escape_htmltag($obj->product_ref).' - '.dol_escape_htmltag($obj->product_label).'</td>';
            print '<td>'.$mode.'</td>';
            print '<td>'.dol_escape_htmltag($obj->calibre ?: '-').'</td>';
            print '<td class="text-right">'.price($obj->nb_voiture).'</td>';
            print '<td class="text-right">'.price($obj->prix_voiture).'</td>';
            print '<td class="text-right">'.price($obj->poids_brut).'</td>';
            print '<td class="text-right">'.price($obj->pu_brut).'</td>';
            print '<td class="text-right">'.price($obj->poids_net).'</td>';
            print '<td class="text-right">'.price($obj->pu_poids_net).'</td>';
            print '<td class="text-right"><strong>'.price($obj->total_line).'</strong></td>';
            print '</tr>';
        }

        print '</tbody>';
        print '<tfoot>';
        print '<tr class="total-row">';
        print '<td colspan="9" class="text-right"><strong>'.$langs->trans("TotalGeneral").'</strong></td>';
        print '<td class="text-right"><strong>'.price($total_general).'</strong></td>';
        print '</tr>';
        print '</tfoot>';
        print '</table>';
        print '</div>';
    } else {
        print '<div class="alert alert-info">';
        print '<i class="fa fa-info-circle"></i> '.$langs->trans("AucuneLigneReception");
        print '</div>';
    }
    print '</div>';


$poids_plater_total = 0;

$sqllk = "
    SELECT SUM(rd.poids_plater) AS poids_plater_total
    FROM ".MAIN_DB_PREFIX."pech_receptiondet rd
    WHERE rd.fk_reception = ".$id;

$resqllk = $db->query($sqllk);
if ($resqllk) {
    $objlk= $db->fetch_object($resqllk);
    $poids_plater_total = (float) $objlk->poids_plater_total;
}
    // ============================================================================
    // 📊 Section totaux
    // ============================================================================
    if ($resqlL && $db->num_rows($resqlL) > 0) {
        print '<div class="total-section">';
        print '<div class="total-grid">';
        print '<div class="total-item">';
         print '<a href="./plater.php?id='.$id.'" >';
        print '<div class="total-label">'.$langs->trans("PoidsTotalPlater").'</div>';
        print '<div class="total-value">'.price($poids_plater_total).' kg</div>';
        print '</a>';
        print '</div>';
         print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("PoidsTotal").'</div>';
        print '<div class="total-value">'.price($reception->poids).' kg</div>';
        print '</div>';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("MontantTotal").'</div>';
        print '<div class="total-value">'.price($reception->montant).'</div>';
        print '</div>';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("NombreLignes").'</div>';
        print '<div class="total-value">'.$db->num_rows($resqlL).'</div>';
        print '</div>';
        print '</div>';
        print '</div>';
    }

    // ============================================================================
    // ⚙️ Actions disponibles
    // ============================================================================
    print '<div class="action-buttons">';

    // État = En attente
    if ($reception->etat == 0) {
        print '<a href="card.php?action=edit&id='.$id.'" class="detail-button detail-button-primary"><i class="fa fa-edit"></i> '.$langs->trans("Modifier").'</a>';
        print '<a href="supp_rec.php?action=delete&id='.$id.'&token='.newToken().'" class="detail-button detail-button-danger" onclick="return confirm(\''.$langs->trans("ConfirmSuppressionReception").'\')"><i class="fa fa-trash"></i> '.$langs->trans("Supprimer").'</a>';
        print '<a href="validation.php?id='.$id.'" class="detail-button detail-button-success" onclick="return confirm(\''.$langs->trans("ConfirmValidationReception").'\')"><i class="fa fa-check"></i> '.$langs->trans("Valider").'</a>';
    }
    // État = Validé
    elseif ($reception->etat == 1) {
        // Bouton Facturer / Voir Facture
        if (empty($reception->fk_facture)) {
            //print '<a class="detail-button detail-button-primary" disable href="facture_create.php?fk_reception='.$id.'"><i class="fa fa-file-invoice"></i> '.$langs->trans("CreerFacture").'</a>';
        } else {
            print '<a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$reception->fk_facture.'" class="detail-button detail-button-primary"><i class="fa fa-eye"></i> '.$langs->trans("VoirFacture").'</a>';
        }
        
        if (empty($reception->fk_bon_recep)) {
            print '<a href="bon_rec.php?id='.$id.'" class="detail-button detail-button-success"><i class="fa fa-plus-circle"></i> '.$langs->trans("CreerBonReception").'</a>';
        } else {
            print '<a href="./doc_bon.php?id='.$reception->fk_bon_recep.'" target="_blank" class="detail-button detail-button-info"><i class="fa fa-eye"></i> '.$langs->trans("VoirBonReception").'</a>';
        }
        
        print '<a href="./poid_net_edit.php?id='.$id.'" target="_blank" class="detail-button detail-button-warning"><i class="fa fa-weight"></i> '.$langs->trans("PoidsNet").'</a>';
         // --- Nouveau bouton : Revenir en brouillon ---
        print '<a href="brouillon.php?id='.$id.'" class="detail-button detail-button-danger" onclick="return confirm(\''.$langs->trans("ConfirmRevertReception").'\')"><i class="fa fa-undo"></i> '.$langs->trans("RevenirBrouillon").'</a>';

    }
    // État = Annulé
    else {
        print '<span class="detail-button detail-button-secondary" style="opacity:0.6; cursor:not-allowed;"><i class="fa fa-ban"></i> '.$langs->trans("ReceptionAnnulee").'</span>';
    }

    print '<a href="list.php" class="detail-button detail-button-secondary"><i class="fa fa-arrow-left"></i> '.$langs->trans("RetourListe").'</a>';
    print '</div>';

    // ============================================================================
    // 📄 Factures liées
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-file-invoice-dollar"></i> '.$langs->trans("FacturesLiees").'</h3>';

    $sqlFact = "SELECT rowid, ref, fk_soc, ref_supplier, total_ht, datef 
                FROM ".MAIN_DB_PREFIX."facture_fourn 
                WHERE ref_supplier LIKE '%".$db->escape($reception->ref)."%' 
                ORDER BY datef DESC";

    $resFact = $db->query($sqlFact);

    if ($resFact && $db->num_rows($resFact) > 0) {
        print '<div class="table-responsive">';
        print '<table class="data-table">';
        print '<thead><tr>';
        print '<th><i class="fa fa-barcode"></i> '.$langs->trans("ReferenceFacture").'</th>';
        print '<th><i class="fa fa-tag"></i> '.$langs->trans("ReferenceFournisseur").'</th>';
        print '<th><i class="fa fa-user"></i> '.$langs->trans("Fournisseur").'</th>';
        print '<th><i class="fa fa-calendar"></i> '.$langs->trans("Date").'</th>';
        print '<th class="text-right"><i class="fa fa-money-bill-wave"></i> '.$langs->trans("TotalHT").'</th>';
        print '</tr></thead>';
        print '<tbody>';

        while ($obj = $db->fetch_object($resFact)) {
            // Récupérer le nom du fournisseur
            $sqlFourn = "SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid = ".((int)$obj->fk_soc);
            $resFourn = $db->query($sqlFourn);
            $fournNom = ($resFourn && $db->num_rows($resFourn) > 0) ? $db->fetch_object($resFourn)->nom : $langs->trans("NonSpecifie");

            $url = DOL_URL_ROOT.'/fourn/facture/card.php?id='.$obj->rowid;
            print '<tr>';
            print '<td><a href="'.$url.'" style="color:#3498db; font-weight:500;">'.$obj->ref.'</a></td>';
            print '<td>'.$obj->ref_supplier.'</td>';
            print '<td>'.$fournNom.'</td>';
            print '<td>'.dol_print_date($db->jdate($obj->datef), 'day').'</td>';
            print '<td class="text-right"><strong>'.price($obj->total_ht).'</strong></td>';
            print '</tr>';
        }

        print '</tbody>';
        print '</table>';
        print '</div>';
    } else {
        print '<div class="alert alert-info">';
        print '<i class="fa fa-info-circle"></i> '.$langs->trans("AucuneFactureLiee");
        print '</div>';
    }
    print '</div>';

print '</div>'; // .detail-container

llxFooter();
$db->close();
?>