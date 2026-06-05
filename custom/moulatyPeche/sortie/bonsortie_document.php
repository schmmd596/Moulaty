<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
require_once '../header.php';

$langs->loadLangs(["main", "abricot@abricot", "womapeche@womapeche"]);

// ============================================================================
// 🔹 VÉRIFICATION DES PARAMÈTRES
// ============================================================================
$id = GETPOST('id', 'int');
if ($id <= 0) accessforbidden(dol_html_entity_decode($langs->trans("IdentifiantBonSortieInvalide"), ENT_QUOTES, 'UTF-8'));

// ============================================================================
// 🔹 RÉCUPÉRATION DU BON DE SORTIE PRINCIPAL
// ============================================================================
$sqlBon = "SELECT b.rowid, b.ref, b.date_creation, b.statut,
                  e1.ref AS entrepot_source_ref, 
                  e2.ref AS entrepot_dest_ref,
                  u.login AS utilisateur, 
                  b.commentaire
           FROM ".MAIN_DB_PREFIX."pech_bonsortie AS b
           LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e1 ON e1.rowid = b.fk_entrepot_source
           LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e2 ON e2.rowid = b.fk_entrepot_dest
           LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = b.fk_user_create
           WHERE b.rowid = ".((int)$id);

$resBon = $db->query($sqlBon);
if (!$resBon || $db->num_rows($resBon) == 0) {
    exit(dol_html_entity_decode($langs->trans('BonSortieIntrouvable'), ENT_QUOTES, 'UTF-8'));
}
$bon = $db->fetch_object($resBon);

// ============================================================================
// 🔹 RÉCUPÉRATION DU TYPE DE SORTIE ET DU CLIENT
// ============================================================================
$sqlSortie = "SELECT type, fk_client, fk_facture
              FROM ".MAIN_DB_PREFIX."pech_sortie 
              WHERE fk_bonsortie = ".((int)$id)."
              LIMIT 1";

$resSortie = $db->query($sqlSortie);
$sortie_type = null;
$sortie_fk_client = 0;
$total_facture = 0;

if ($resSortie && $db->num_rows($resSortie) > 0) {
    $objSortie = $db->fetch_object($resSortie);
    $sortie_type = (int)$objSortie->type;
    $sortie_fk_client = (int)$objSortie->fk_client;
    
    if ($objSortie->fk_facture > 0) {
        $sqlFact = "SELECT total_ttc 
                    FROM ".MAIN_DB_PREFIX."facture_fourn 
                    WHERE rowid = ".((int)$objSortie->fk_facture);

        $resFact = $db->query($sqlFact);
        if ($resFact && $db->num_rows($resFact) > 0) {
            $fobj = $db->fetch_object($resFact);
            $total_facture = (float)$fobj->total_ttc;
        }
    }
}

// ============================================================================
// 🔹 RÉCUPÉRATION DES LIGNES PRODUITS
// ============================================================================
$sqlProd = "SELECT d.rowid, d.fk_product, d.nb_carton, d.poids_carton, d.valeur,
                   p.ref AS product_ref, p.label AS product_label,
                   (d.nb_carton * d.poids_carton) AS total_poids
            FROM ".MAIN_DB_PREFIX."pech_bonsortie_detprod AS d
            LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = d.fk_product
            WHERE d.fk_bonentree = ".((int)$id);

$resProd = $db->query($sqlProd);
$linesProd = [];
$total_poids_produits = 0;
$total_valeur_produits = 0;

while ($obj = $db->fetch_object($resProd)) {
    $linesProd[] = $obj;
    $total_poids_produits += $obj->total_poids;
    $total_valeur_produits += $obj->valeur;
}

// ============================================================================
// 🔹 RÉCUPÉRATION DES LIGNES SERVICES
// ============================================================================
$sqlServ = "SELECT rowid, description, qte, pu, total
            FROM ".MAIN_DB_PREFIX."pech_bonsortie_detserv
            WHERE fk_bonentree = ".((int)$id);

$resServ = $db->query($sqlServ);
$linesServ = [];
$total_montant_services = 0;

while ($obj = $db->fetch_object($resServ)) {
    $linesServ[] = $obj;
    $total_montant_services += $obj->total;
}

// ============================================================================
// 🔹 CRÉATION DU PDF
// ============================================================================
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configuration du document
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($mysoc->name);
$pdf->SetTitle(dol_html_entity_decode($langs->trans("BonSortie"), ENT_QUOTES, 'UTF-8').' - '.$bon->ref);
$pdf->SetSubject(dol_html_entity_decode($langs->trans("BonSortieStock"), ENT_QUOTES, 'UTF-8'));
$pdf->SetKeywords(dol_html_entity_decode($langs->trans("BonSortie"), ENT_QUOTES, 'UTF-8').', Stock, '.$bon->ref);

// Marges
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);

// Police par défaut
$pdf->SetFont('dejavusans', '', 10);

// Ajout de la page
$pdf->AddPage();

// ============================================================================
// 🔹 EN-TÊTE PROFESSIONNEL
// ============================================================================
$subtitle = dol_html_entity_decode($langs->trans("Reference"), ENT_QUOTES, 'UTF-8')." : ".$bon->ref."\n".dol_html_entity_decode($langs->trans("Date"), ENT_QUOTES, 'UTF-8')." : ".dol_print_date($bon->date_creation, 'daytext');
addProfessionalHeader($pdf, $mysoc, dol_html_entity_decode($langs->trans("BonSortieStock"), ENT_QUOTES, 'UTF-8'), $subtitle);

// Titre principal
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->SetTextColor(67, 142, 204); // Bleu professionnel
$pdf->Cell(0, 12, dol_html_entity_decode($langs->trans("BonSortieStock"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C');
$pdf->Ln(5);

// ============================================================================
// 🔹 INFORMATIONS PRINCIPALES
// ============================================================================
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 8, dol_html_entity_decode($langs->trans("InformationsSortie"), ENT_QUOTES, 'UTF-8'), 0, 1, 'L', 1);
$pdf->Ln(3);

$pdf->SetFont('dejavusans', '', 10);

// Construction des données d'information
$infoData = [
    dol_html_entity_decode($langs->trans("Reference"), ENT_QUOTES, 'UTF-8') => $bon->ref,
    dol_html_entity_decode($langs->trans("DateCreation"), ENT_QUOTES, 'UTF-8') => dol_print_date($bon->date_creation, 'daytext'),
    dol_html_entity_decode($langs->trans("EntrepotSource"), ENT_QUOTES, 'UTF-8') => $bon->entrepot_source_ref ?: '-',
    dol_html_entity_decode($langs->trans("EtabliPar"), ENT_QUOTES, 'UTF-8') => $bon->utilisateur ?: '-'
];

// Affichage conditionnel selon le type de sortie
if ($sortie_type == 0) {
    // Transfert interne
    $infoData[dol_html_entity_decode($langs->trans("EntrepotDestination"), ENT_QUOTES, 'UTF-8')] = $bon->entrepot_dest_ref ?: '-';
} elseif ($sortie_type == 1) {
    // Vente : récupération du client
    $client_label = '-';
    if ($sortie_fk_client > 0) {
        $sqlC = "SELECT nom 
                 FROM ".MAIN_DB_PREFIX."societe 
                 WHERE rowid = ".((int)$sortie_fk_client);
        $resC = $db->query($sqlC);
        if ($resC && $db->num_rows($resC) > 0) {
            $cobj = $db->fetch_object($resC);
            $client_label = $cobj->nom;
        }
    }
    $infoData[dol_html_entity_decode($langs->trans("Client"), ENT_QUOTES, 'UTF-8')] = $client_label;
} else {
    // Par défaut
    $infoData[dol_html_entity_decode($langs->trans("EntrepotDestination"), ENT_QUOTES, 'UTF-8')] = $bon->entrepot_dest_ref ?: '-';
}

if ($bon->commentaire) {
    $infoData[dol_html_entity_decode($langs->trans("Commentaire"), ENT_QUOTES, 'UTF-8')] = $bon->commentaire;
}

// Affichage des informations
foreach ($infoData as $label => $value) {
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(45, 6, $label.' :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 6, $value, 0, 1, 'L');
}

$pdf->Ln(8);

// ============================================================================
// 🔹 RÉSUMÉ DES TOTAUX
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 8, dol_html_entity_decode($langs->trans("ResumeTotaux"), ENT_QUOTES, 'UTF-8'), 0, 1, 'L', 1);
$pdf->Ln(3);

$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell(80, 6, dol_html_entity_decode($langs->trans("TotalPoidsProduits"), ENT_QUOTES, 'UTF-8').' :', 0, 0, 'L');
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell(0, 6, price($total_poids_produits).' kg', 0, 1, 'L');

$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell(80, 6, dol_html_entity_decode($langs->trans("TotalValeurProduits"), ENT_QUOTES, 'UTF-8').' :', 0, 0, 'L');
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell(0, 6, price($total_valeur_produits).' '.$conf->currency, 0, 1, 'L');

if ($total_montant_services > 0) {
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(80, 6, dol_html_entity_decode($langs->trans("TotalMontantServices"), ENT_QUOTES, 'UTF-8').' :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(0, 6, price($total_montant_services).' '.$conf->currency, 0, 1, 'L');
}

if ($total_facture > 0) {
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(80, 6, dol_html_entity_decode($langs->trans("TotalMontantFacture"), ENT_QUOTES, 'UTF-8').' :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(0, 6, price($total_facture).' '.$conf->currency, 0, 1, 'L');
}

$pdf->Ln(10);

// ============================================================================
// 🔹 TABLEAU DES PRODUITS SORTIS
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->SetFillColor(67, 142, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("ProduitsSortis"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
$pdf->Ln(5);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->SetFillColor(230, 240, 255);

// En-tête du tableau produits
$prodHeaderWidths = [60, 25, 30, 30, 35];
$prodHeaderLabels = [
    dol_html_entity_decode($langs->trans("Produit"), ENT_QUOTES, 'UTF-8'),
    dol_html_entity_decode($langs->trans("Nb Cartons"), ENT_QUOTES, 'UTF-8'),
    dol_html_entity_decode($langs->trans("P.Carton Kg"), ENT_QUOTES, 'UTF-8'),
    dol_html_entity_decode($langs->trans("PUCarton"), ENT_QUOTES, 'UTF-8'),
    dol_html_entity_decode($langs->trans("Valeur").' ('.$conf->currency.')', ENT_QUOTES, 'UTF-8')
];

for ($i = 0; $i < count($prodHeaderWidths); $i++) {
    $pdf->Cell($prodHeaderWidths[$i], 7, $prodHeaderLabels[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Calcul du supplément pour la répartition des frais
$nb_lignes = count($linesProd);
$supplement = 0;

if ($nb_lignes > 0) {
    $supplement = ($total_montant_services + $total_facture) / $nb_lignes;
}

// Lignes des produits
$pdf->SetFont('dejavusans', '', 9);
$fill = false;
$total_valeur_recalculee = 0;

foreach ($linesProd as $line) {
    if ($fill) {
        $pdf->SetFillColor(245, 248, 255);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    $valeur_recalculee = $line->valeur + $supplement;
    $total_valeur_recalculee += $valeur_recalculee;
    
    // Produit
    $productText = $line->product_ref;
    if (!empty($line->product_label)) {
        $productText .= ' - ' . $line->product_label;
    }
    
    $pdf->Cell($prodHeaderWidths[0], 7, $productText, 1, 0, 'L', $fill);
    $pdf->Cell($prodHeaderWidths[1], 7, $line->nb_carton, 1, 0, 'C', $fill);
    $pdf->Cell($prodHeaderWidths[2], 7, price($line->poids_carton), 1, 0, 'R', $fill);
    $pdf->Cell($prodHeaderWidths[3], 7, number_format($valeur_recalculee / $line->nb_carton, 2, '.', ' '), 1, 0, 'R', $fill);
    $pdf->Cell($prodHeaderWidths[4], 7, number_format($valeur_recalculee, 2, '.', ' '), 1, 1, 'R', $fill);
    
    $fill = !$fill;
}

// Total produits
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(67, 142, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell($prodHeaderWidths[0] + $prodHeaderWidths[1] + $prodHeaderWidths[2] + $prodHeaderWidths[3], 8, dol_html_entity_decode($langs->trans("Total"), ENT_QUOTES, 'UTF-8'), 1, 0, 'R', 1);
$pdf->Cell($prodHeaderWidths[4], 8, number_format($total_valeur_recalculee, 2, '.', ' '), 1, 1, 'R', 1);

$pdf->Ln(12);

// ============================================================================
// 🔹 TABLEAU DES FRAIS ASSOCIÉS
// ============================================================================
if (!empty($linesServ) || $total_facture > 0) {
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetFillColor(76, 175, 80); // Vert professionnel
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("FraisAssocies"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
    $pdf->Ln(5);
}

// Tableau des services
if (!empty($linesServ)) {
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(230, 255, 230);
    
    // En-tête du tableau services
    $servHeaderWidths = [80, 30, 30, 40];
    $servHeaderLabels = [
        dol_html_entity_decode($langs->trans("Description"), ENT_QUOTES, 'UTF-8'),
        dol_html_entity_decode($langs->trans("Quantite"), ENT_QUOTES, 'UTF-8'),
        dol_html_entity_decode($langs->trans("PrixUnitaire"), ENT_QUOTES, 'UTF-8'),
        dol_html_entity_decode($langs->trans("Total"), ENT_QUOTES, 'UTF-8')
    ];
    
    for ($i = 0; $i < count($servHeaderWidths); $i++) {
        $pdf->Cell($servHeaderWidths[$i], 7, $servHeaderLabels[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Lignes des services
    $pdf->SetFont('dejavusans', '', 9);
    $fill = false;
    
    foreach ($linesServ as $line) {
        if ($fill) {
            $pdf->SetFillColor(245, 255, 245);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($servHeaderWidths[0], 7, $line->description, 1, 0, 'L', $fill);
        $pdf->Cell($servHeaderWidths[1], 7, price($line->qte), 1, 0, 'R', $fill);
        $pdf->Cell($servHeaderWidths[2], 7, price($line->pu), 1, 0, 'R', $fill);
        $pdf->Cell($servHeaderWidths[3], 7, price($line->total), 1, 1, 'R', $fill);
        
        $fill = !$fill;
    }
    
    // Total services
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetFillColor(76, 175, 80);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($servHeaderWidths[0] + $servHeaderWidths[1] + $servHeaderWidths[2], 8, dol_html_entity_decode($langs->trans("TotalServices"), ENT_QUOTES, 'UTF-8'), 1, 0, 'R', 1);
    $pdf->Cell($servHeaderWidths[3], 8, price($total_montant_services).' '.$conf->currency, 1, 1, 'R', 1);
    
    $pdf->Ln(8);
}

// ============================================================================
// 🔹 ZONE DE SIGNATURES
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 11);

// Fond gris clair pour être visible
$pdf->SetFillColor(230, 230, 230);
$pdf->SetTextColor(0, 0, 0); // texte noir

$pdf->Cell(0, 8, dol_html_entity_decode($langs->trans("Signatures"), ENT_QUOTES, 'UTF-8'), 0, 1, 'L', 1);
$pdf->Ln(4);

// Bordures et texte lisible
$pdf->SetFont('dejavusans', '', 10);
$pdf->SetTextColor(0, 0, 0);

$signatureWidth = 85;
$spacing = 10;

// Vérifier espace restant sur la page
if ($pdf->GetY() > 250) {
    $pdf->AddPage();
}

// Affichage des lignes de signatures
$textEtabli = dol_html_entity_decode($langs->trans("EtabliPar"), ENT_QUOTES, 'UTF-8');
$textValide = dol_html_entity_decode($langs->trans("ValidePar"), ENT_QUOTES, 'UTF-8');

$pdf->Cell($signatureWidth, 8, $textEtabli.' : ___________________________', 0, 0, 'L');
$pdf->Cell($spacing, 8, '', 0, 0);
$pdf->Cell($signatureWidth, 8, $textValide.' : ___________________________', 0, 1, 'L');
$pdf->Ln(6);


// ============================================================================
// 🔹 SORTIE PDF
// ============================================================================
$pdf->Output(dol_html_entity_decode($langs->trans("BonSortie"), ENT_QUOTES, 'UTF-8').'_'.$bon->ref.'.pdf', 'I');
exit;
?>