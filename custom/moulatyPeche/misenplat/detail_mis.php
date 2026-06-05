<?php
/* ============================================================================
 * Module : womapeche
 * Auteur : Abdou Mahfoudh <superadmin@womapeche.com>
 * Description : Fiche détaillée du Bon de Mise en Plat
 * ============================================================================
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';

global $db, $langs, $user;

$langs->loadLangs(['main', 'other', 'womapeche@womapeche']);

$id = GETPOST('id', 'int');
if (empty($id)) {
    setEventMessages($langs->trans("IdentifiantBonManquant"), null, 'errors');
    header("Location: ./misenplat_select.php");
    exit;
}

// ============================================================================
// 1️⃣ Chargement du bon
// ============================================================================
$sql_bon = "SELECT b.*,
                   e.ref as entrepot_ref, e.ref as entrepot_label,
                   u.login as user_login
            FROM ".MAIN_DB_PREFIX."pech_bon_misenplat AS b
            LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = b.fk_entrepot
            LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = b.fk_user
            WHERE b.rowid = ".((int) $id);

$resql_bon = $db->query($sql_bon);
if (!$resql_bon || $db->num_rows($resql_bon) == 0) {
    setEventMessages($langs->trans("BonMiseEnPlatIntrouvable"), null, 'errors');
    header("Location: ./misenplat_select.php");
    exit;
}
$bon = $db->fetch_object($resql_bon);

// ============================================================================
// 2️⃣ Chargement des lignes produits
// ============================================================================
$sql_lines = "SELECT m.rowid, m.fk_product, m.nombre_plat,m.nombre_plat_sortie, m.poids_plat, 
                     p.ref as product_ref, p.label as product_label
              FROM ".MAIN_DB_PREFIX."pech_misenplat AS m
              LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = m.fk_product
              WHERE m.fk_bon_misenplat = ".((int)$id);
$resql_lines = $db->query($sql_lines);
$lines = [];
if ($resql_lines) while ($obj = $db->fetch_object($resql_lines)) $lines[] = $obj;

// ============================================================================
// 3️⃣ Affichage de la page
// ============================================================================
llxHeader('', $langs->trans("DetailsBonMiseEnPlat"));

// Inclusion du CSS global pour les détails
print '<link rel="stylesheet" href="../global_detail_style.css">';

print '<div class="detail-container">';
    
    // ============================================================================
    // 🎯 En-tête
    // ============================================================================
    print '<div class="detail-header">';
    print '<h1>';
    print '<i class="fa fa-utensils"></i>';
    print $langs->trans("BonMiseEnPlat");
    print '<span class="ref">'.$bon->ref.'</span>';
    print '</h1>';
    print '</div>';

    // ============================================================================
    // 🧾 Informations générales
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-info-circle"></i> '.$langs->trans("InformationsGenerales").'</h3>';
    print '<table class="info-table">';
    print '<tr><td class="field-label"><i class="fa fa-hashtag"></i> '.$langs->trans("Reference").'</td><td class="field-value"><strong>'.dol_escape_htmltag($bon->ref).'</strong></td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-warehouse"></i> '.$langs->trans("Entrepot").'</td><td class="field-value">'.dol_escape_htmltag($bon->entrepot_label ?: $bon->entrepot_ref).'</td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-user"></i> '.$langs->trans("Utilisateur").'</td><td class="field-value">'.dol_escape_htmltag($bon->user_login).'</td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-calendar"></i> '.$langs->trans("DateCreation").'</td><td class="field-value">'.dol_print_date($db->jdate($bon->date_creation), 'dayhour').'</td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-coins"></i> '.$langs->trans("Frais").'</td><td class="field-value">'.price($bon->total_frais).'</td></tr>';

    print '<tr><td class="field-label"><i class="fa fa-flag"></i> '.$langs->trans("Statut").'</td><td class="field-value">';
    if ($bon->statut == 0) print '<span class="badge badge-warning"><i class="fa fa-hourglass-half"></i> '.$langs->trans("EnAttente").'</span>';
    elseif ($bon->statut == 1) print '<span class="badge badge-success"><i class="fa fa-check-circle"></i> '.$langs->trans("Valide").'</span>';
    else print '<span class="badge badge-danger"><i class="fa fa-times-circle"></i> '.$langs->trans("Annule").'</span>';
    print '</td></tr>';

    if (!empty($bon->commentaire)) {
        print '<tr><td class="field-label"><i class="fa fa-comment-dots"></i> '.$langs->trans("Commentaire").'</td><td class="field-value"><div class="comment-content">'.nl2br(dol_escape_htmltag($bon->commentaire)).'</div></td></tr>';
    }
    print '</table>';
    print '</div>';

    // ============================================================================
    // 📦 Lignes produits
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-boxes-stacked"></i> '.$langs->trans("ProduitsMisenPlat").'</h3>';

    if (empty($lines)) {
        print '<div class="alert alert-info">';
        print '<i class="fa fa-info-circle"></i> '.$langs->trans("AucuneLigneEnregistree");
        print '</div>';
    } else {
        print '<div class="table-responsive">';
        print '<table class="data-table">';
        print '<thead><tr>';
        print '<th><i class="fa fa-fish"></i> '.$langs->trans("Produit").'</th>';
        print '<th class="text-right"><i class="fa fa-layer-group"></i> '.$langs->trans("NombrePlats").'</th>';
        print '<th class="text-right"><i class="fa fa-layer-group"></i> '.$langs->trans("PlatsCartonnes").'</th>';
        print '<th class="text-right"><i class="fa fa-weight-hanging"></i> '.$langs->trans("PoidsPlat").' (kg)</th>';
        print '<th class="text-right"><i class="fa fa-balance-scale"></i> '.$langs->trans("PoidsTotal").' (kg)</th>';
        print '</tr></thead>';
        print '<tbody>';

        $total_poids = 0; $total_nb = 0; $plat_cart = 0;
        foreach ($lines as $line) {
            $poids_total = $line->nombre_plat * $line->poids_plat;
            $total_poids += $poids_total;
            $total_nb += $line->nombre_plat;
            $plat_cart += $line->nombre_plat_sortie;

            print '<tr>';
            print '<td><i class="fa fa-box"></i> '.dol_escape_htmltag($line->product_label ?: $line->product_ref).'</td>';
            print '<td class="text-right">'.number_format($line->nombre_plat, 0, ',', ' ').'</td>';
            print '<td class="text-right">'.number_format($line->nombre_plat_sortie, 0, ',', ' ').' / '.number_format($line->nombre_plat, 0, ',', ' ').'</td>';
            print '<td class="text-right">'.number_format($line->poids_plat, 3, ',', ' ').'</td>';
            print '<td class="text-right">'.number_format($poids_total, 3, ',', ' ').'</td>';
            print '</tr>';
        }

        print '</tbody>';
        print '<tfoot>';
        print '<tr class="total-row">';
        print '<td class="text-right"><strong>'.$langs->trans("Total").'</strong></td>';
        print '<td class="text-right"><strong>'.number_format($total_nb, 0, ',', ' ').'</strong></td>';
        print '<td></td>';
        print '<td></td>';
        print '<td class="text-right"><strong>'.number_format($total_poids, 3, ',', ' ').'</strong></td>';
        print '</tr>';
        print '</tfoot>';
        print '</table>';
        print '</div>';
    }
    print '</div>';

    // ============================================================================
    // 📊 Section totaux
    // ============================================================================
    
    if (!empty($lines)) {
        print '<div class="total-section">';
        print '<div class="total-grid">';
        print '<div class="total-item">';
        print '<a href="./cartonner.php?id='.$bon->rowid.'">';
        print '<div class="total-label">'.$langs->trans("TotalPlatsCartonner").'</div>';
        print '<div class="total-value">'.number_format($plat_cart, 0, ',', ' ').'</div>';
        print '</a>';
        print '</div>';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("TotalPlats").'</div>';
        print '<div class="total-value">'.number_format($total_nb, 0, ',', ' ').'</div>';
        print '</div>';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("PoidsTotal").'</div>';
        print '<div class="total-value">'.number_format($total_poids, 3, ',', ' ').' kg</div>';
        print '</div>';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("FraisTotaux").'</div>';
        print '<div class="total-value">'.price($bon->total_frais).'</div>';
        print '</div>';
        print '</div>';
        print '</div>';
    }

    // ============================================================================
    // ⚙️ Boutons d'action
    // ============================================================================
    print '<div class="action-buttons">';
    switch ((int)$bon->statut) {
        case 0:
            print '<a href="./edit_bon.php?id='.$bon->rowid.'" class="detail-button detail-button-primary"><i class="fa fa-edit"></i> '.$langs->trans("Modifier").'</a>';
            print '<a href="./delete_mis.php?id='.$bon->rowid.'&token='.newToken().'" class="detail-button detail-button-danger" onclick="return confirm(\''.$langs->trans("ConfirmerSuppression").'\');"><i class="fa fa-trash"></i> '.$langs->trans("Supprimer").'</a>';
            print '<a href="./validation.php?id='.$bon->rowid.'" class="detail-button detail-button-success"><i class="fa fa-check"></i> '.$langs->trans("Valider").'</a>';
            break;

        case 1:
            print '<a href="./doc_bon_mis.php?id='.$bon->rowid.'" target="_blank" class="detail-button detail-button-primary"><i class="fa fa-file-pdf"></i> '.$langs->trans("VoirBon").'</a>';
            print '<a href="./brouillon.php?id='.$bon->rowid.'"class="detail-button  detail-button-danger" onclick="return confirm(\''.$langs->trans("ConfirmRevertReception").'\')"><i class="fa fa-undo"></i> '.$langs->trans("RevenirBrouillon").'</a>';
            if ($bon->fk_facture_frais > 0) {
                print '<a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$bon->fk_facture_frais.'" class="detail-button detail-button-info"><i class="fa fa-file-invoice-dollar"></i> '.$langs->trans("VoirFacture").'</a>';
            } else {
                $sql_check = "SELECT SUM(mp.nombre_plat_sortie) AS total_sortie
                              FROM ".MAIN_DB_PREFIX."pech_misenplat mp
                              WHERE mp.fk_bon_misenplat = ".((int)$bon->rowid);
                $resql_check = $db->query($sql_check);
                $total_sortie = 0;
                if ($resql_check && $obj = $db->fetch_object($resql_check)) $total_sortie = (float)$obj->total_sortie;

                if ($total_sortie > 0) {
                    print '<span class="detail-button detail-button-secondary" style="opacity:0.6; cursor:not-allowed;" title="'.$langs->trans("ImpossibleAjouterFrais").'"><i class="fa fa-ban"></i> '.$langs->trans("AjouterFrais").'</span>';
                } else {
                    print '<a href="./frais.php?id='.$bon->rowid.'" class="detail-button detail-button-warning"><i class="fa fa-plus-circle"></i> '.$langs->trans("AjouterFrais").'</a>';
                }
            }
            break;

        case 2:
            print '<span class="detail-button detail-button-secondary" style="opacity:0.6; cursor:not-allowed;"><i class="fa fa-lock"></i> '.$langs->trans("BonTermine").'</span>';
            break;
    }
    
    print '<a href="./list.php" class="detail-button detail-button-secondary"><i class="fa fa-arrow-left"></i> '.$langs->trans("RetourListe").'</a>';
    print '</div>';





    
    // ============================================================================
    // 📄 Factures liées
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-file-invoice-dollar"></i> '.$langs->trans("FacturesLiees").'</h3>';

    $sqlFact = "SELECT rowid, ref, fk_soc, ref_supplier, total_ht, datef 
                FROM ".MAIN_DB_PREFIX."facture_fourn 
                WHERE ref_supplier LIKE '%".$db->escape($bon->ref)."%' 
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