<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

global $db, $langs, $user;

$langs->loadLangs(['womapeche@womapeche', 'main']);
$form = new Form($db);

// ============================================================================
// 🔹 RÉCUPÉRATION DU LOT
// ============================================================================
$id_lot = GETPOST('id', 'int');
if ($id_lot <= 0) {
    setEventMessages($langs->trans("LotNonSpecifie"), null, 'errors');
    header("Location: list_lot.php");
    exit;
}

// 🔹 Requête pour récupérer la valeur totale du lot avec frais inclus
$sqlll = "
SELECT 
    SUM(
        (
            SELECT SUM(c.prix_moyen + c.frais)
            FROM ".MAIN_DB_PREFIX."pech_carton c
            WHERE c.fk_lotdet = ld.rowid
        )
    ) AS valeur_lot2
FROM ".MAIN_DB_PREFIX."pech_lot l
JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.fk_lot = l.rowid
WHERE l.rowid = ".((int)$id_lot)."
GROUP BY l.rowid, l.ref
";

// Exécution de la requête
$reslll = $db->query($sqlll);

// Récupération de la valeur totale
$valeurLottot = 0;
if ($reslll && $db->num_rows($reslll) > 0) {
    $obj = $db->fetch_object($reslll);
    $valeurLottot = $obj->valeur_lot2;
} else {
    $valeurLottot = 0;
}

// Optionnel : afficher la valeur
//echo "Valeur totale du lot : " . $valeurLottot;

// 🔹 Requête pour récupérer la valeur totale du lot avec frais inclus
$sqlll = "
SELECT 
    SUM(
        (
            SELECT SUM(p.prix_moyen + IFNULL(bm.total_frais / total_plats.total_plats, 0))
            FROM ".MAIN_DB_PREFIX."pech_plat p
            JOIN ".MAIN_DB_PREFIX."pech_misenplat mp ON mp.rowid = p.fk_misenplat
            JOIN ".MAIN_DB_PREFIX."pech_bon_misenplat bm ON bm.rowid = mp.fk_bon_misenplat
            JOIN (
                SELECT fk_bon_misenplat, SUM(nombre_plat) AS total_plats
                FROM ".MAIN_DB_PREFIX."pech_misenplat
                GROUP BY fk_bon_misenplat
            ) AS total_plats ON total_plats.fk_bon_misenplat = bm.rowid
            WHERE p.fk_carton = c.rowid
        )
    ) AS valeur_lot
FROM ".MAIN_DB_PREFIX."pech_lot l
JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.fk_lot = l.rowid
JOIN ".MAIN_DB_PREFIX."pech_carton c ON c.fk_lotdet = ld.rowid
WHERE l.rowid = ".((int)$id_lot)."
GROUP BY l.rowid, l.ref
";

$reslll = $db->query($sqlll);
$valeurLot = 0;
if ($reslll && $db->num_rows($reslll) > 0) {
    $obj = $db->fetch_object($reslll);
    $valeurLot = $obj->valeur_lot;
} else {
    $valeurLot = 0;
}

// --- Étape 2 : Valeur de la facture fournisseur (si associée)
$sqlFacture = "
    SELECT SUM(total_ht) AS total_facture
    FROM ".MAIN_DB_PREFIX."facture_fourn_det
    WHERE fk_facture_fourn = (
        SELECT fk_facture FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid = ".((int)$id_lot)."
    )
";
$resFacture = $db->query($sqlFacture);
$totalFacture = 0;
if ($resFacture && $db->num_rows($resFacture) > 0) {
    $obj = $db->fetch_object($resFacture);
    $totalFacture = $obj->total_facture ?: 0;
}

// --- Étape 3 : Valeur du bon d'entrée (produits + services)
$sqlBonEntree = "
    SELECT 
        COALESCE(SUM(ds.total), 0) AS valeur_services
    FROM ".MAIN_DB_PREFIX."pech_bonentree_detserv ds
    WHERE ds.fk_bonentree = (
        SELECT fk_bonentree 
        FROM ".MAIN_DB_PREFIX."pech_lot 
        WHERE rowid = ".((int)$id_lot)."
    )
";
$resBonEntree = $db->query($sqlBonEntree);
$totalBonEntree = 0;
if ($resBonEntree && $db->num_rows($resBonEntree) > 0) {
    $obj = $db->fetch_object($resBonEntree);
    $totalBonEntree =  $obj->valeur_services ?: 0;
}

// --- Étape 4 : Calcul final
$valeurLotTotale = $valeurLot + $totalFacture + $totalBonEntree;

// ============================================================================
// 🔹 INFORMATIONS DU LOT
// ============================================================================
$sqlLot = "SELECT l.*, u.firstname, u.lastname,e.rowid as fk_entrepot, e.ref AS ref_entrepot
           FROM ".MAIN_DB_PREFIX."pech_lot AS l
           LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = l.fk_user_create
           LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = l.fk_entrepot
           WHERE l.rowid = ".(int)$id_lot;

$resLot = $db->query($sqlLot);
if (!$resLot || $db->num_rows($resLot) == 0) {
    setEventMessages($langs->trans("LotIntrouvable"), null, 'errors');
    header("Location: list_lot.php");
    exit;
}
$lot = $db->fetch_object($resLot);

$sqljhjhjhksjkll = "SELECT ef.monnaie
                FROM ".MAIN_DB_PREFIX."entrepot_extrafields ef
                WHERE ef.fk_object = ".((int)$lot->fk_entrepot);;

        $resqlsqljhjhjhksjkll = $db->query($sqljhjhjhksjkll);

        $monnaie = '';
        if ($resqlsqljhjhjhksjkll && $db->num_rows($resqlsqljhjhjhksjkll)) {
            $objsqljhjhjhksjkll = $db->fetch_object($resqlsqljhjhjhksjkll);
            $monnaie = $objsqljhjhjhksjkll->monnaie;
        }

        $is_multidevise = empty($monnaie);

        
        if (!$is_multidevise){
            #$url = 'detail_lot_m.php?id='.$obj->rowid;
            header("Location: detail_lot_m.php?id=".$lot->rowid);
        }else{
              #$url = 'detail_lot.php?id='.$obj->rowid;
        }

// ============================================================================
// 🔹 LIGNES DE DÉTAIL DU LOT
// ============================================================================
$sqlDet = "
SELECT 
    d.rowid AS lotdet_id,
    d.nb_carton,
    d.poids_carton AS poids_par_carton,
    d.plat_carton AS plat_par_carton,
    d.commentaire,
    p.label AS produit,
    COUNT(c.rowid) AS nb_carton_sortie,
    SUM(c.poids) AS poids_total_sortie
FROM ".MAIN_DB_PREFIX."pech_lotdet AS d
LEFT JOIN ".MAIN_DB_PREFIX."pech_carton AS c ON c.fk_lotdet = d.rowid AND c.statut = 1
LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = d.fk_product
WHERE d.fk_lot = ".(int)$id_lot."
GROUP BY d.rowid, p.label, d.nb_carton, d.poids_carton, d.plat_carton, d.commentaire
ORDER BY d.rowid ASC";

$resDet = $db->query($sqlDet);
$lines = [];
while ($obj = $db->fetch_object($resDet)) $lines[] = $obj;
// Récupérer le nom du fournisseur si défini
$fournisseurName = '';
if (!empty($lot->fk_fourn)) {
    $sqlF = "SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid = ".((int)$lot->fk_fourn);
    $resF = $db->query($sqlF);
    if ($resF && $db->num_rows($resF) > 0) {
        $fournisseurName = $db->fetch_object($resF)->nom;
    }
}
// ============================================================================
// 🔹 AFFICHAGE
// ============================================================================
llxHeader('', $langs->trans("DetailsLot").$lot->ref);

// Inclusion du CSS global pour les détails
print '<link rel="stylesheet" href="../global_detail_style.css">';

print '<div class="detail-container">';
    
    // ============================================================================
    // 🎯 En-tête
    // ============================================================================
    print '<div class="detail-header">';
    print '<h1>';
    print '<i class="fa fa-box"></i>';
    print $langs->trans("Lot");
    print '<span class="ref">'.$lot->ref.'</span>';
    print '</h1>';
    print '</div>';

    // ============================================================================
    // 🧾 Informations générales
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-info-circle"></i> '.$langs->trans("InformationsLot").'</h3>';
    print '<table class="info-table">';
    
    $infos = [
        ['icon'=>'fa-hashtag','label'=>$langs->trans("Reference"),'value'=>'<strong>'.dol_escape_htmltag($lot->ref).'</strong>'],
        ['icon'=>'fa-dollar-sign','label'=>$langs->trans("Frais"),'value'=>'<strong>'.price($lot->total_frais).' '.$conf->currency.'</strong>'],
['icon' => 'fa-dollar-sign',
 'label' => $langs->trans("ValeurLots"),
 'value' => '<strong>' . price(round($valeurLottot, 2)) . ' ' . $conf->currency . '</strong>'],
 ['icon'=>'fa-user','label'=>$langs->trans("CreePar"),'value'=>dol_escape_htmltag($lot->firstname.' '.$lot->lastname)],
        ['icon'=>'fa-calendar-alt','label'=>$langs->trans("DateCreation"),'value'=>dol_print_date($db->jdate($lot->date_creation), 'dayhour')],
        ['icon'=>'fa-warehouse','label'=>$langs->trans("Entrepot"),'value'=>dol_escape_htmltag($lot->ref_entrepot ?: $langs->trans("NonSpecifie"))],
    // 🔹 Nouvelle colonne : Source
    ['icon'=>'fa-exchange-alt','label'=>$langs->trans("Source"),'value'=>($lot->source_type == 1 ? $langs->trans("ReceptionDirecte") : $langs->trans("MiseEnPlat"))],
    // 🔹 Fournisseur si défini
    ['icon'=>'fa-truck','label'=>$langs->trans("Fournisseur"),'value'=>dol_escape_htmltag($fournisseurName ?: $langs->trans("NonSpecifie"))],

    ];

    foreach($infos as $info){
        print '<tr>';
        print '<td class="field-label"><i class="fa '.$info['icon'].'"></i> '.$info['label'].'</td>';
        print '<td class="field-value">'.$info['value'].'</td>';
        print '</tr>';
    }

    // Statut
    print '<tr>';
    print '<td class="field-label"><i class="fa fa-flag"></i> '.$langs->trans("Statut").'</td>';
    print '<td class="field-value">';
    if ($lot->statut == 0) {
        print '<span class="badge badge-warning"><i class="fa fa-hourglass-half"></i> '.$langs->trans("Brouillon").'</span>';
    } else {
        print '<span class="badge badge-success"><i class="fa fa-check-circle"></i> '.$langs->trans("Valide").'</span>';
    }
    print '</td>';
    print '</tr>';

    // Commentaire
    if (!empty($lot->commentaire)) {
        print '<tr>';
        print '<td class="field-label"><i class="fa fa-comment-alt"></i> '.$langs->trans("Commentaire").'</td>';
        print '<td class="field-value"><div class="comment-content">'.nl2br(dol_escape_htmltag($lot->commentaire)).'</div></td>';
        print '</tr>';
    }

    print '</table>';
    print '</div>';

    // ============================================================================
    // 📦 Détail du lot
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-list-alt"></i> '.$langs->trans("DetailLot").'</h3>';

    if (empty($lines)) {
        print '<div class="alert alert-info">';
        print '<i class="fa fa-info-circle"></i> '.$langs->trans("AucuneLigneLot");
        print '</div>';
    } else {
        print '<div class="table-responsive">';
        print '<table class="data-table">';
        print '<thead><tr>';
        print '<th><i class="fa fa-fish"></i> '.$langs->trans("Produit").'</th>';
        print '<th class="text-right"><i class="fa fa-box"></i> '.$langs->trans("NbCartons").'</th>';
        print '<th class="text-right"><i class="fa fa-box-open"></i> '.$langs->trans("CartonsSortis").'</th>';
        print '<th class="text-right"><i class="fa fa-weight-hanging"></i> '.$langs->trans("PoidsCarton").' (kg)</th>';
        print '<th class="text-right"><i class="fa fa-layer-group"></i> '.$langs->trans("PlatsCarton").'</th>';
        print '<th class="text-right"><i class="fa fa-balance-scale"></i> '.$langs->trans("PoidsTotal").' (kg)</th>';
        print '</tr></thead>';
        print '<tbody>';

        $total_cartons = 0;
        $total_poids = 0;
        $sortis = 0;

        foreach ($lines as $line) {
            $total_cartons += $line->nb_carton;
            $poids_ligne = $line->nb_carton * $line->poids_par_carton;
            $total_poids += $poids_ligne;
            $sortis += $line->nb_carton_sortie;

            print '<tr>';
            
            $is_mixte = (stripos($line->produit, 'MIXTE') !== false) || 
                    (isset($line->ref_produit) && stripos($line->ref_produit, 'MIXTE') !== false);
        
        print '<tr>';
        print '<td>';
        print '<i class="fa fa-box"></i> ';
        
        if ($is_mixte) {
            // Lien vers le fichier des cartons mixtes
            $url_mixte = 'detail_mixte.php?id='.$id_lot;
            print '<a href="'.$url_mixte.'" class="product-link-mixte" title="'.$langs->trans("ViewMixedCartons").'">';
            print '<i class="fa fa-random mixte-icon"></i> ';
            print dol_escape_htmltag($line->produit);
            print ' <span class="mixte-badge">MIXTE</span>';
            print '</a>';
            
            // Info-bulle pour expliquer le lien
            print '<span class="mixte-hint" title="'.$langs->trans("ClickToViewComposition").'">';
            print '<i class="fa fa-info-circle"></i>';
            print '</span>';
        } else {
            print dol_escape_htmltag($line->produit);
        }
        print '</td>';
            //print '<td><i class="fa fa-box"></i> '.dol_escape_htmltag($line->produit).'</td>';
            print '<td class="text-right">'.$line->nb_carton.'</td>';
            print '<td class="text-right">'.$line->nb_carton_sortie.' / '.$line->nb_carton.'</td>';
            print '<td class="text-right">'.price($line->poids_par_carton).'</td>';
            print '<td class="text-right">'.$line->plat_par_carton.'</td>';
            print '<td class="text-right"><strong>'.price($poids_ligne).'</strong></td>';
            print '</tr>';
        }

        print '</tbody>';
        print '<tfoot>';
        print '<tr class="total-row">';
        print '<td class="text-right"><strong>'.$langs->trans("Total").'</strong></td>';
        print '<td class="text-right"><strong>'.$total_cartons.'</strong></td>';
        print '<td></td>';
        print '<td></td>';
        print '<td></td>';
        print '<td class="text-right"><strong>'.price($total_poids).' kg</strong></td>';
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
        print '<a href="sortis.php?&id='.$id_lot.'">';
        print '<div class="total-label">'.$langs->trans("TotalCartonsSortis").'</div>';
        print '<div class="total-value">'.number_format($sortis, 0, ',', ' ').'</div>';
        print '</a>';
        print '</div>';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("TotalCartons").'</div>';
        print '<div class="total-value">'.number_format($total_cartons, 0, ',', ' ').'</div>';
        print '</div>';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("PoidsTotal").'</div>';
        print '<div class="total-value">'.price($total_poids).' kg</div>';
        print '</div>';
        print '<div class="total-item">';
        print '<div class="total-label">'.$langs->trans("FraisTotaux").'</div>';
        print '<div class="total-value">'.price($lot->total_frais).'</div>';
        print '</div>';
        print '</div>';
        print '</div>';
    }
$has_locked = false;

foreach ($lines as $line) {
    if ($line->nb_carton_sortie > 0) {
        $has_locked = true;
        break;
    }
}
    // ============================================================================
    // ⚙️ Boutons d'action
 /*   // ============================================================================
    print '<div class="action-buttons">';
    $token = newToken();

    if ($lot->statut == 0) {
        // Lot en brouillon
        print '<a href="../tunnel/sortie_tunnel_create.php?action=modify&id_lot='.$id_lot.'&fk_entrepot='.$lot->fk_entrepot.'" class="detail-button detail-button-primary"><i class="fa fa-edit"></i> '.$langs->trans("Modifier").'</a>';
        print '<a href="delete_lot.php?action=delete&id='.$id_lot.'&token='.$token.'" class="detail-button detail-button-danger" onclick="return confirm(\''.$langs->trans("ConfirmerSuppressionLot").'\')"><i class="fa fa-trash"></i> '.$langs->trans("Supprimer").'</a>';
        print '<a href="validation.php?action=validate&id='.$id_lot.'" class="detail-button detail-button-success" onclick="return confirm(\''.$langs->trans("ConfirmerValidationLot").'\')"><i class="fa fa-check"></i> '.$langs->trans("Valider").'</a>';
    } else {
        if ($has_locked) {
        print '<div class="warning" style="color:red;font-weight:bold;">
                ⚠ Impossible : un carton du lot est déjà sorti.
               </div>';
        
        // Autoriser uniquement la consultation
        if ($lot->fk_bonentree > 0) {
            print '<a href="doc_bon.php?id='.$lot->fk_bonentree.'" class="detail-button detail-button-primary"><i class="fa fa-eye"></i> Voir Bon Actuel</a>';
        }
        if ($lot->fk_facture > 0) {
            print '<a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$lot->fk_facture.'" class="detail-button detail-button-info"><i class="fa fa-file-invoice-dollar"></i> Voir Facture</a>';
        }

        
        print '</div>';
    }else{
        // Lot validé
        if ($lot->fk_bonentree > 0) {
            print '<a href="doc_bon.php?id='.$lot->fk_bonentree.'" class="detail-button detail-button-primary"><i class="fa fa-eye"></i> '.$langs->trans("VoirBonActuel").'</a>';
        } else {
            print '<a href="bon_lot.php?id_lot='.$id_lot.'&fk_entrepot='.$lot->fk_entrepot.'" class="detail-button detail-button-success"><i class="fa fa-plus-circle"></i> '.$langs->trans("CreerBonEntree").'</a>';
        }
        
        if ($lot->fk_facture > 0) {
            print '<a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$lot->fk_facture.'" class="detail-button detail-button-info"><i class="fa fa-file-invoice-dollar"></i> '.$langs->trans("VoirFacture").'</a>';
        } else {
            print '<a href="facturer.php?id_lot='.$id_lot.'" class="detail-button detail-button-warning"><i class="fa fa-file-invoice"></i> '.$langs->trans("Facturer").'</a>';
        }
    }
    }
    
    print '<a href="list_lot.php" class="detail-button detail-button-secondary"><i class="fa fa-arrow-left"></i> '.$langs->trans("RetourListe").'</a>';
    print '</div>';
*/


print '<div class="action-buttons">';
$token = newToken();

if ($lot->statut == 0) {
    // Brouillon
    print '<a href="delete_lot.php?action=delete&id='.$id_lot.'&token='.$token.'" class="detail-button detail-button-danger" onclick="return confirm(\''.$langs->trans("ConfirmerSuppressionLot").'\')"><i class="fa fa-trash"></i> '.$langs->trans("Supprimer").'</a>';

    if ($lot->source_type == 1) {
        // Réception directe : validation spécifique
        print '<a href="validation2.php?action=validate&id='.$id_lot.'" class="detail-button detail-button-success" onclick="return confirm(\''.$langs->trans("ConfirmerValidationLot").'\')"><i class="fa fa-check"></i> '.$langs->trans("Valider").'</a>';
    } else {
        // Ancien lot : validation classique
        print '<a href="validation.php?action=validate&id='.$id_lot.'" class="detail-button detail-button-success" onclick="return confirm(\''.$langs->trans("ConfirmerValidationLot").'\')"><i class="fa fa-check"></i> '.$langs->trans("Valider").'</a>';
        print '<a href="edit_lot.php?id='.$id_lot.'" class="detail-button detail-button-primary"><i class="fa fa-edit"></i> '.$langs->trans("Modifier").'</a>';
    
        
    }

} else {
    // Lot validé
    print '<a href="details.php?id='.$id_lot.'" class="detail-button detail-button-primary"><i class="fa fa-eye"></i> '.$langs->trans("Details").'</a>';
    
    if ($has_locked) {
        //print '<div class="warning" style="color:red;font-weight:bold;">⚠ Impossible : un carton du lot est déjà sorti.</div>';
        // Consultation uniquement
        if ($lot->fk_bonentree > 0) {
            print '<a href="doc_bon.php?id='.$lot->fk_bonentree.'" class="detail-button detail-button-success"><i class="fa fa-eye"></i> Voir Bon</a>';
        }

        // Facture fournisseur ou facture classique
        if ($lot->source_type == 1 && $lot->fk_fourn > 0) {
            if ($lot->fk_facture_fourn) {
                // Facture fournisseur déjà existante
                print '<a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$lot->fk_facture_fourn.'" class="detail-button detail-button-info"><i class="fa fa-file-invoice-dollar"></i> Voir Facture Fournisseur</a>';
            } else {
                // Rediriger vers création facture fournisseur
                print '<a href="facture_fournisseur.php?id_lot='.$id_lot.'" class="detail-button detail-button-warning"><i class="fa fa-file-invoice"></i> Créer Facture Fournisseur</a>';
            }
        } elseif ($lot->fk_facture > 0) {
            //print '<a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$lot->fk_facture.'" class="detail-button detail-button-info"><i class="fa fa-file-invoice-dollar"></i> Voir Facture</a>';
        }
    } else {
        // Lot validé mais pas de cartons sortis
        if ($lot->fk_bonentree > 0) {
            print '<a href="doc_bon.php?id='.$lot->fk_bonentree.'" class="detail-button detail-button-success"><i class="fa fa-eye"></i> Voir Bon</a>';
        } else {
            print '<a href="bon_lot.php?id_lot='.$id_lot.'&fk_entrepot='.$lot->fk_entrepot.'" class="detail-button detail-button-success"><i class="fa fa-plus-circle"></i> Créer Bon Entrée</a>';
        }

        if ($lot->source_type == 1 && $lot->fk_fourn > 0) {
            if ($lot->fk_facture_fourn) {
                print '<a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$lot->fk_facture_fourn.'" class="detail-button detail-button-info"><i class="fa fa-file-invoice-dollar"></i> Voir Facture Fournisseur</a>';
            } else {
                print '<a href="facture_fournisseur.php?id_lot='.$id_lot.'" class="detail-button detail-button-warning"><i class="fa fa-file-invoice"></i> Créer Facture Fournisseur</a>';
            }
        } elseif ($lot->fk_facture > 0) {
            //print '<a href="'.DOL_URL_ROOT.'/fourn/facture/card.php?id='.$lot->fk_facture.'" class="detail-button detail-button-info"><i class="fa fa-file-invoice-dollar"></i> Voir Facture</a>';
        } else if ($lot->source_type != 1) {
            print '<a href="facturer.php?id_lot='.$id_lot.'" class="detail-button detail-button-warning"><i class="fa fa-file-invoice"></i> Facturer</a>';
        }

        if ($lot->source_type == 1) {
         print '<a href="brouillon2.php?id='.$id_lot.'" class="detail-button detail-button-danger" onclick="return confirm(\''.$langs->trans("ConfirmerAnnulationLot").'\')"><i class="fa fa-undo"></i> '.$langs->trans("RevertToDraft").'</a>';
        // Réception directe : validation spécifique
        } else {
            // Ancien lot : validation classique
            print '<a href="brouillon.php?id='.$id_lot.'" class="detail-button detail-button-danger" onclick="return confirm(\''.$langs->trans("ConfirmerAnnulationLot").'\')"><i class="fa fa-undo"></i> '.$langs->trans("RevertToDraft").'</a>';
        
            
        }
       
    }
}

print '<a href="list.php" class="detail-button detail-button-secondary"><i class="fa fa-arrow-left"></i> '.$langs->trans("RetourListe").'</a>';
print '</div>';

    // ============================================================================
    // 📄 Factures liées
    // ============================================================================
    print '<div class="detail-section">';
    print '<h3 class="section-title"><i class="fa fa-file-invoice-dollar"></i> '.$langs->trans("FacturesLieesLot").'</h3>';

    $sqlFact = "SELECT rowid, ref, fk_soc, ref_supplier, total_ht, datef 
                FROM ".MAIN_DB_PREFIX."facture_fourn 
                WHERE ref_supplier LIKE '".$db->escape($lot->ref)."%' 
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