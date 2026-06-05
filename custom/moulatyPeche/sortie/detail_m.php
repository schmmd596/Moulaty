<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

$langs->load("products");
$form = new Form($db);

$id = GETPOST('id', 'int');
if (empty($id)) {
    setEventMessages($langs->trans("IDManquant"), null, 'errors');
    header("Location: list.php");
    exit;
}

$sql = "SELECT s.*, u.firstname, u.lastname
        FROM ".MAIN_DB_PREFIX."pech_sortie AS s
        LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = s.fk_user
        WHERE s.rowid = ".(int)$id;
$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) {
    setEventMessages($langs->trans("BonSortieIntrouvable"), null, 'errors');
    header("Location: list.php");
    exit;
}
$sortie = $db->fetch_object($resql);

$sql_prod = "SELECT p.rowid, pr.ref, pr.label, p.nb_carton, p.poids_total, p.pu, p.total_line
             FROM ".MAIN_DB_PREFIX."pech_sortiedetprod AS p
             LEFT JOIN ".MAIN_DB_PREFIX."product AS pr ON pr.rowid = p.fk_product
             WHERE p.fk_sortie = ".(int)$id;
$resql_prod = $db->query($sql_prod);

// ============================================================================
// 🔹 RÉCUPÉRATION DE LA DEVISE ET CONVERSIONS
// ============================================================================
// Récupérer la devise de l'entrepôt source
require_once '../functions.php'; // Inclure le fichier avec les fonctions de conversion
$monnaieId = getDeviseEntrepot($db, $sortie->fk_entrepot_source);
$monnaiName = getNomMonnaie($db, $monnaieId);
$code_devise = getCodeDeviseFromId($db, $monnaieId);

// Conversions pour affichage
$conversion_total_frais = convertFromMRO($db, $sortie->total_frais, $monnaieId);

// Récupérer le taux de change
$sqlRate = "
    SELECT 
        mcr.rate,
        mcr.date_sync
    FROM ".MAIN_DB_PREFIX."multicurrency_rate AS mcr
    WHERE mcr.fk_multicurrency = ".(int) $monnaieId."
    ORDER BY mcr.date_sync DESC
    LIMIT 1
";
$resRate = $db->query($sqlRate);
$exchangeRate = 1;
$exchangeDate = '';
if ($resRate && $db->num_rows($resRate) > 0) {
    $rateRow = $db->fetch_object($resRate);
    $exchangeRate = (float) $rateRow->rate;
    $exchangeDate = $db->jdate($rateRow->date_sync);
}

// ============================================================================
// 🔹 FONCTION POUR AFFICHER LES MONTANTS AVEC CONVERSION
// ============================================================================
function displayAmountWithConversion($conversion, $show_mro = true) {
    global $conf;
    
    if (!$conversion || $conversion['currency_code'] == 'MRO') {
        return price($conversion['original']) . ' ' . $conf->currency;
    }
    
    $html = price(round($conversion['converted'], 2)) . ' ' . $conversion['currency_code'];
    
    if ($show_mro && $conversion['original'] > 0) {
        $html .= '<br><small class="text-muted" style="font-size:10px;">(' . price($conversion['original']) . ' ' . $conf->currency . ')</small>';
    }
    
    return $html;
}

llxHeader('', $langs->trans("DetailsBonSortie").$sortie->ref);

// Inclusion du CSS global pour les détails
print '<link rel="stylesheet" href="../global_detail_style.css">';

print '<style>
.detail-button-recommended {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 20px 45px;
    font-size: 18px;
    font-weight: 600;
    min-width: 300px;
    height: 70px;
    border-radius: 16px;
    text-decoration: none;
    color: white !important;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: 2px solid rgba(255, 255, 255, 0.15);
    box-shadow: 
        0 10px 30px rgba(102, 126, 234, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    cursor: pointer;
}

.detail-button-recommended .button-glow {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, 
        transparent 0%, 
        rgba(255, 255, 255, 0.1) 50%, 
        transparent 100%);
    transform: translateX(-100%);
    transition: transform 0.6s ease;
}

.detail-button-recommended .button-inner {
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    z-index: 2;
}

.detail-button-recommended i {
    font-size: 22px;
    transition: transform 0.3s ease;
}

.detail-button-recommended span {
    letter-spacing: 0.5px;
}

.detail-button-recommended:hover {
    transform: translateY(-5px);
    box-shadow: 
        0 20px 50px rgba(102, 126, 234, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.detail-button-recommended:hover .button-glow {
    transform: translateX(100%);
}

.detail-button-recommended:hover i {
    transform: translateX(8px);
}

.detail-button-recommended:active {
    transform: translateY(-2px);
    box-shadow: 
        0 8px 25px rgba(102, 126, 234, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

/* Effet de chargement */
.detail-button-recommended.loading {
    pointer-events: none;
}

.detail-button-recommended.loading:after {
    content: "";
    position: absolute;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s ease infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>';
print '<div class="detail-container">';
    
    // ============================================================================
    // 🎯 En-tête
    // ============================================================================
    print '<div class="detail-header">';
    print '<h1>';
    print '<i class="fa fa-box"></i>';
    print $langs->trans("BonSortie");
    print '<span class="ref">'.$sortie->ref.'</span>';
    
    // -----------------------------------------------------------------------------
    // Affichage devise + taux de change
    // -----------------------------------------------------------------------------
    /*if ($code_devise !== 'MRO') {
        print '
        <div class="currency-rate-box">
            <span class="badge badge-info">
                <i class="fa fa-coins"></i> '.$code_devise.'
            </span>
            <span class="rate-value">
                <i class="fa fa-exchange-alt"></i>
                1 MRO = 
                <strong>'.price($exchangeRate).'</strong> '.$code_devise.'
            </span>';
        if (!empty($exchangeDate)) {
            print '
            <span class="rate-date">
                <i class="fa fa-clock"></i>
                '.dol_print_date($exchangeDate, 'day').'
            </span>';
        }
        print '</div>';
    }*/
    if ($code_devise != $conf->currency) {

    print '
    <div class="currency-rate-box">
        <span class="badge badge-info">
            <i class="fa fa-coins"></i> '.$code_devise.'
        </span>

        <span class="rate-value">
           
            <strong>5000 </strong> '.$code_devise.'
             <i class="fa fa-exchange-alt"></i>
            <strong>'.price(round(5000 / $exchangeRate, 2)).'</strong> '.$conf->currency.'
        </span>';

    if (!empty($exchangeDate)) {
        print '
        <span class="rate-date">
            <i class="fa fa-clock"></i>
            '.dol_print_date($exchangeDate, 'day').'
        </span>';
    }

    print '</div>';
}
    
    print '</h1>';
    print '</div>';

    // ============================================================================
    // 🧾 Informations générales
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-info-circle"></i> '.$langs->trans("InformationsGenerales").'</h3>';
    
    $entrepot_src = new Entrepot($db);
    $entrepot_src->fetch($sortie->fk_entrepot_source);

    $entrepot_dest_name = '';
    if ($sortie->type == 0 && !empty($sortie->fk_entrepot_dest)) {
        $entrepot_dest = new Entrepot($db);
        $entrepot_dest->fetch($sortie->fk_entrepot_dest);
        $entrepot_dest_name = $entrepot_dest->ref.' - '.$entrepot_dest->label;
    }

    $client_name = '';
    if ($sortie->type == 1 && !empty($sortie->fk_client)) {
        $soc = new Societe($db);
        $soc->fetch($sortie->fk_client);
        $client_name = $soc->name;
    }

    print '<table class="info-table">';
    print '<tr><td class="field-label"><i class="fa fa-hashtag"></i> '.$langs->trans("Reference").'</td><td class="field-value"><strong>'.$sortie->ref.'</strong></td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-tag"></i> '.$langs->trans("Type").'</td><td class="field-value">'.($sortie->type == 0 ? $langs->trans("TransfertInterne") : $langs->trans("VenteClient")).'</td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-warehouse"></i> '.$langs->trans("EntrepotSource").'</td><td class="field-value">'.$entrepot_src->ref.' - '.$entrepot_src->label.'</td></tr>';
    
    if ($sortie->type == 0) {
        print '<tr><td class="field-label"><i class="fa fa-building"></i> '.$langs->trans("EntrepotDestination").'</td><td class="field-value">'.$entrepot_dest_name.'</td></tr>';
    } else {
        print '<tr><td class="field-label"><i class="fa fa-user"></i> '.$langs->trans("Client").'</td><td class="field-value">'.$client_name.'</td></tr>';
    }
    
    print '<tr><td class="field-label"><i class="fa fa-calendar"></i> '.$langs->trans("DateCreation").'</td><td class="field-value">'.dol_print_date($db->jdate($sortie->date_creation), 'dayhour').'</td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-user"></i> '.$langs->trans("Utilisateur").'</td><td class="field-value">'.$sortie->firstname.' '.$sortie->lastname.'</td></tr>';

    // Statut
    print '<tr><td class="field-label"><i class="fa fa-flag"></i> '.$langs->trans("Statut").'</td><td class="field-value">';
    switch ($sortie->statut) {
        case 0:
            print '<span class="badge badge-warning"><i class="fa fa-hourglass-half"></i> '.$langs->trans("Brouillon").'</span>';
            break;
        case 1:
            print '<span class="badge badge-success"><i class="fa fa-check-circle"></i> '.$langs->trans("Valide").'</span>';
            break;
        case 2:
            print '<span class="badge badge-danger"><i class="fa fa-truck"></i> '.$langs->trans("Transfere").'</span>';
            break;
        default:
            print '<span class="badge badge-secondary">'.$langs->trans("Inconnu").'</span>';
            break;
    }
    print '</td></tr>';

    if (!empty($sortie->commentaire)) {
        print '<tr><td class="field-label"><i class="fa fa-comment"></i> '.$langs->trans("Commentaire").'</td><td class="field-value"><div class="comment-content">'.nl2br(dol_escape_htmltag($sortie->commentaire)).'</div></td></tr>';
    }
    
    // Frais avec conversion
    print '<tr><td class="field-label"><i class="fa fa-coins"></i> '.$langs->trans("Frais").'</td><td class="field-value"><strong>'.displayAmountWithConversion($conversion_total_frais).'</strong></td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-weight-hanging"></i> '.$langs->trans("PoidsTotal").'</td><td class="field-value"><strong>'.price($sortie->poids_total).' kg</strong></td></tr>';
    print '<tr><td class="field-label"><i class="fa fa-box"></i> '.$langs->trans("NbCartonsTotal").'</td><td class="field-value"><strong>'.$sortie->nb_carton_total.'</strong></td></tr>';
    print '</table>';
    print '</div>';

    // ============================================================================
    // 📦 Produits sortis
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-boxes"></i> '.$langs->trans("ProduitsSortis").'</h3>';

    if ($resql_prod && $db->num_rows($resql_prod) > 0) {
        print '<div class="table-responsive">';
        print '<table class="data-table">';
        print '<thead><tr>';
        print '<th><i class="fa fa-fish"></i> '.$langs->trans("Produit").'</th>';
        print '<th class="text-right"><i class="fa fa-box"></i> '.$langs->trans("NbCartons").'</th>';
        print '<th class="text-right"><i class="fa fa-weight-hanging"></i> '.$langs->trans("Poids").' (kg)</th>';
        print '<th class="text-right"><i class="fa fa-money-bill-wave"></i> '.$langs->trans("ValeurTotale").'</th>';
        print '</tr></thead>';
        print '<tbody>';

        $total_valeur = 0;
        $total_valeur_converted = 0;
        while ($obj = $db->fetch_object($resql_prod)) {
            $sql_cartons = "SELECT c.rowid, c.poids, (c.prix_moyen + c.frais) AS valeur
                            FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton AS sc
                            LEFT JOIN ".MAIN_DB_PREFIX."pech_carton AS c ON c.rowid = sc.fk_carton
                            WHERE sc.fk_sortiedetprod = ".$obj->rowid;
            $res_cartons = $db->query($sql_cartons);
            $valeur = 0;
            if ($res_cartons && $db->num_rows($res_cartons) > 0) {
                while ($c = $db->fetch_object($res_cartons)) {
                    $valeur += $c->valeur;
                }
            }
            $total_valeur += $valeur;
            
            // Conversion de la valeur
            $conversion_valeur = convertFromMRO($db, $valeur, $monnaieId);
            $total_valeur_converted += $conversion_valeur['converted'];
            
            print '<tr>';
            print '<td><i class="fa fa-box"></i> '.$obj->ref.' - '.$obj->label.'</td>';
            print '<td class="text-right">'.$obj->nb_carton.'</td>';
            print '<td class="text-right">'.price($obj->poids_total).'</td>';
            print '<td class="text-right"><strong>'.displayAmountWithConversion($conversion_valeur).'</strong></td>';
            print '</tr>';
        }

        print '</tbody>';
        print '<tfoot>';
        print '<tr class="total-row">';
        print '<td class="text-right" colspan="3"><strong>'.$langs->trans("Total").' :</strong></td>';
        $conversion_total = [
            'original' => $total_valeur,
            'converted' => $total_valeur_converted,
            'currency_code' => $code_devise
        ];
        print '<td class="text-right"><strong>'.displayAmountWithConversion($conversion_total).'</strong></td>';
        print '</tr>';
        print '</tfoot>';
        print '</table>';
        print '</div>';
    } else {
        print '<div class="alert alert-info">';
        print '<i class="fa fa-info-circle"></i> '.$langs->trans("AucunProduit");
        print '</div>';
    }
    print '</div>';

    // ============================================================================
    // 📊 Section totaux
    // ============================================================================
    if ($resql_prod && $db->num_rows($resql_prod) > 0) {
        // Calcul de la valeur totale (produits + frais)
        $valeur_totale = $total_valeur + $sortie->total_frais;
        //$valeur_totale = $total_valeur ;//+ $sortie->total_frais;
        $conversion_valeur_totale = convertFromMRO($db, $valeur_totale, $monnaieId);
        
        print '<div class="total-section">';
        print '<div class="total-grid">';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("TotalCartons").'</div>';
        print '<div class="total-value">'.$sortie->nb_carton_total.'</div>';
        print '</div>';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("PoidsTotal").'</div>';
        print '<div class="total-value">'.price($sortie->poids_total).' kg</div>';
        print '</div>';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("FraisTotaux").'</div>';
        print '<div class="total-value">';
        print '<div>'.displayAmountWithConversion($conversion_total_frais, false).'</div>';
        print '</div>';
        print '</div>';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("ValeurTotale").'</div>';
        print '<div class="total-value">';
        print '<div>'.displayAmountWithConversion($conversion_valeur_totale, false).'</div>';
        if ($code_devise != 'MRO') {
            print '<small class="text-muted">('.price(round($valeur_totale, 2)).' '.$conf->currency.')</small>';
        }
        print '</div>';
        print '</div>';
        print '</div>';
        print '</div>';
    }

    // ============================================================================
    // ⚙️ Boutons d'action
    // ============================================================================
    print '<div class="action-buttons">';

    print '<a href="list.php" class="detail-button detail-button-secondary"><i class="fa fa-arrow-left"></i> '.$langs->trans("RetourListe").'</a>';

    if ($sortie->statut == 0) {
        print '<a href="delete_sortie.php?id='.$id.'" class="detail-button detail-button-danger" onclick="return confirm(\''.$langs->trans("ConfirmerSuppression").'\')"><i class="fa fa-trash"></i> '.$langs->trans("Supprimer").'</a>';
        print '<a href="validate.php?id='.$id.'" class="detail-button detail-button-success"><i class="fa fa-check"></i> '.$langs->trans("ValiderSortie").'</a>';
    } else {
        if (!empty($sortie->fk_facture)) {
            //print '<a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$sortie->fk_facture.'" class="detail-button detail-button-primary"><i class="fa fa-eye"></i> '.$langs->trans("VoirFacture").'</a>';
        } else {
            //print '<a href="facturer_m.php?id_sortie='.$sortie->rowid.'" class="detail-button detail-button-warning"><i class="fa fa-file-invoice"></i> '.$langs->trans("Facturer").'</a>';
        }

        if ($sortie->fk_bonsortie > 0) {
            $url_bon = 'bonsortie_document_m.php?id='.$sortie->fk_bonsortie;
            print '<a href="'.$url_bon.'" class="detail-button detail-button-info"><i class="fa fa-eye"></i> '.$langs->trans("VoirBonSortie").'</a>';
        } else {
            $url_creer = 'bon_sortie_m.php?id_sortie='.$id;
            print '<a href="'.$url_creer.'" class="detail-button detail-button-success"><i class="fa fa-plus-circle"></i> '.$langs->trans("CreerBonSortie").'</a>';
        }
        if ($sortie->fk_facture_client){
            print '<a href="annuler_fact_client.php?id='.$id.'" class="detail-button detail-button-danger" onclick="return confirm(\''.$langs->trans('Confirm').' '.$langs->trans('SetToDraft').' ?\')"><i class="fa fa-undo"></i> '.$langs->trans('Annuler la facture client').'</a>';
        
        }else{
            print '<a href="brouillon.php?id='.$id.'" class="detail-button detail-button-danger" onclick="return confirm(\''.$langs->trans('Confirm').' '.$langs->trans('SetToDraft').' ?\')"><i class="fa fa-undo"></i> '.$langs->trans('SetToDraft').'</a>';
        
        }
        //print '<a href="brouillon.php?id='.$id.'" class="detail-button detail-button-danger"><i class="fa fa-undo"></i> '.$langs->trans("SetToDraft").'</a>';
        print '<a href="lot_sortis.php?id='.$id.'" class="detail-button detail-button-success"><i class="fa fa-eye"></i> '.$langs->trans("Details").'</a>';
  
        // Bouton de transfert/facturation
        $can_transfer = ($sortie->statut == 1 && !empty($sortie->fk_bonsortie) );
        if ($sortie->type == 0) {
            $label = $langs->trans("TransfererEntrepot");
            $url = 'validate_transfere.php?id='.$id;
        } else {
            $label = $langs->trans("FacturerClient");
            $url = 'facture_vente.php?id='.$id;
        }

        if ($can_transfer) {
        //if (1>0) {
            //print '<a href="'.$url.'" class="detail-button detail-button-primary" onclick="return confirm(\''.$langs->trans("ConfirmerTransfert").'\')"><i class="fa fa-truck"></i> '.$label.'</a>';
        
                    
print '</br><a href="'.$url.'" class="detail-button-recommended" onclick="return confirm(\''.$langs->trans("ConfirmerTransfert").'\')">';
print '<div class="button-glow"></div>';
print '<div class="button-inner">';
print '<i class="fa fa-truck"></i> ';
print '<span>'.$label.'</span>';
print '</div>';
print '</a>';
        } else {
            print '<span class="detail-button detail-button-secondary" style="opacity:0.6; cursor:not-allowed;" title="'.$langs->trans("ImpossibleTransferer").'"><i class="fa fa-truck"></i> '.$label.'</span>';
        }
    }

    print '</div>';

    // ============================================================================
    // 📄 Factures liées
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-file-invoice-dollar"></i> '.$langs->trans("FacturesLieesSortie").'</h3>';

    $sqlFact = "SELECT rowid, ref, fk_soc, ref_supplier, total_ht, datef 
                FROM ".MAIN_DB_PREFIX."facture_fourn 
                WHERE ref_supplier LIKE '".$db->escape($sortie->ref)."%' 
                ORDER BY datef DESC";

    $resFact = $db->query($sqlFact);

    if ($resFact && $db->num_rows($resFact) > 0) {
        print '<div class="table-responsive">';
        print '<table class="data-table">';
        print '<thead><tr>';
        print '<th><i class="fa fa-barcode"></i> '.$langs->trans("ReferenceDolibarr").'</th>';
        print '<th><i class="fa fa-tag"></i> '.$langs->trans("ReferenceFournisseur").'</th>';
        print '<th><i class="fa fa-user"></i> '.$langs->trans("Fournisseur").'</th>';
        print '<th><i class="fa fa-calendar"></i> '.$langs->trans("Date").'</th>';
        print '<th class="text-right"><i class="fa fa-money-bill-wave"></i> '.$langs->trans("TotalHT").'</th>';
        print '</tr></thead>';
        print '<tbody>';

        while ($obj = $db->fetch_object($resFact)) {
            $sqlFourn = "SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid = ".((int)$obj->fk_soc);
            $resFourn = $db->query($sqlFourn);
            $fournNom = ($resFourn && $db->num_rows($resFourn) > 0) ? $db->fetch_object($resFourn)->nom : $langs->trans("NonSpecifie");

            $url = DOL_URL_ROOT.'/fourn/facture/card.php?id='.$obj->rowid;
            
            // Conversion du montant de la facture
            $conversion_facture = convertFromMRO($db, $obj->total_ht, $monnaieId);
            
            print '<tr>';
            print '<td><a href="'.$url.'" style="color:#3498db; font-weight:500;">'.$obj->ref.'</a></td>';
            print '<td>'.$obj->ref_supplier.'</td>';
            print '<td>'.$fournNom.'</td>';
            print '<td>'.dol_print_date($db->jdate($obj->datef), 'day').'</td>';
            print '<td class="text-right">';
            print '<strong>'.displayAmountWithConversion($conversion_facture, false).'</strong>';
            if ($conversion_facture['currency_code'] != 'MRO') {
                print '<br><small class="text-muted">('.price($obj->total_ht).' '.$conf->currency.')</small>';
            }
            print '</td>';
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

    // ============================================================================
    // 📄 Facture client associée (si type = 1)
    // ============================================================================
    if ($sortie->type == 1 && !empty($sortie->fk_facture_client)) {
        print '<div class="detail-section">';
        print '<h3 class="section-title"><i class="fa fa-file-invoice"></i> '.$langs->trans("FactureClientAssociee").'</h3>';

        $sqlFactClient = "SELECT f.rowid, f.ref, f.total_ht, f.total_ttc, f.datef, f.fk_soc, s.nom AS client_name
                          FROM ".MAIN_DB_PREFIX."facture AS f
                          LEFT JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = f.fk_soc
                          WHERE f.rowid = ".((int)$sortie->fk_facture_client);

        $resFactClient = $db->query($sqlFactClient);

        if ($resFactClient && $db->num_rows($resFactClient) > 0) {
            $fact = $db->fetch_object($resFactClient);
            $urlFacture = DOL_URL_ROOT.'/compta/facture/card.php?id='.$fact->rowid;
            
            // Conversion des montants de la facture client
            $conversion_facture_ht = convertFromMRO($db, $fact->total_ht, $monnaieId);
            $conversion_facture_ttc = convertFromMRO($db, $fact->total_ttc, $monnaieId);

            print '<div class="table-responsive">';
            print '<table class="data-table">';
            print '<thead><tr>';
            print '<th><i class="fa fa-barcode"></i> '.$langs->trans("Reference").'</th>';
            print '<th><i class="fa fa-user"></i> '.$langs->trans("Client").'</th>';
            print '<th><i class="fa fa-calendar"></i> '.$langs->trans("DateFacture").'</th>';
            print '<th class="text-right"><i class="fa fa-money-bill-wave"></i> '.$langs->trans("TotalHT").'</th>';
            print '<th class="text-right"><i class="fa fa-money-bill"></i> '.$langs->trans("TotalTTC").'</th>';
            print '</tr></thead>';
            print '<tbody>';
            print '<tr>';
            print '<td><a href="'.$urlFacture.'" target="_blank" style="color:#3498db; font-weight:500;">'.$fact->ref.'</a></td>';
            print '<td>'.$fact->client_name.'</td>';
            print '<td>'.dol_print_date($db->jdate($fact->datef), 'day').'</td>';
            print '<td class="text-right"><strong>'.displayAmountWithConversion($conversion_facture_ht, false).'</strong></td>';
            print '<td class="text-right"><strong>'.displayAmountWithConversion($conversion_facture_ttc, false).'</strong></td>';
            print '</tr>';
            print '</tbody>';
            print '</table>';
            print '</div>';
        } else {
            print '<div class="alert alert-info">';
            print '<i class="fa fa-info-circle"></i> '.$langs->trans("AucuneFactureClient");
            print '</div>';
        }
        print '</div>';
    }

print '</div>'; // .detail-container

llxFooter();
$db->close();
?>