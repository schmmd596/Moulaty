<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
require_once '../header.php';

$langs->loadLangs(["main", "abricot@abricot", "womapeche@womapeche"]);

$id = GETPOST('id', 'int');
if ($id <= 0) accessforbidden(dol_html_entity_decode($langs->trans("IdentifiantBonEntreeInvalide"), ENT_QUOTES, 'UTF-8'));

// ============================================================================
// 🔹 RÉCUPÉRATION DU BON PRINCIPAL
// ============================================================================
$sqlBon = "SELECT b.rowid, b.ref, b.date_creation, b.statut,
                  e.ref AS entrepot_ref, u.login AS utilisateur, b.commentaire
           FROM ".MAIN_DB_PREFIX."pech_bonentree AS b
           LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = b.fk_entrepot
           LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = b.fk_user_create
           WHERE b.rowid = ".((int)$id);

$resBon = $db->query($sqlBon);
if (!$resBon || $db->num_rows($resBon) == 0) {
    exit(dol_html_entity_decode($langs->trans('BonEntreeIntrouvable'), ENT_QUOTES, 'UTF-8'));
}
$bon = $db->fetch_object($resBon);

$dateCreationLot = null;

$sqlGetLotDateFromBonEntree = "
    SELECT lot.date_creation
    FROM ".MAIN_DB_PREFIX."pech_lot AS lot
    INNER JOIN ".MAIN_DB_PREFIX."pech_bonentree AS bon
        ON bon.rowid = lot.fk_bonentree
    WHERE bon.rowid = ".$id;

$resultLotDate = $db->query($sqlGetLotDateFromBonEntree);

if ($resultLotDate && $db->num_rows($resultLotDate) > 0) {
    $lotRow = $db->fetch_object($resultLotDate);
    $dateCreationLot = $lotRow->date_creation;
}
// ============================================================================
// 🔹 RÉCUPÉRATION DES LIGNES PRODUITS
// ============================================================================
$sqlProd = "SELECT d.rowid, d.fk_product, d.nb_carton, d.poids_carton,
                   p.ref AS product_ref, p.label AS product_label,
                   (d.nb_carton*d.poids_carton) AS total_poids
            FROM ".MAIN_DB_PREFIX."pech_bonentree_detprod AS d
            LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = d.fk_product
            WHERE d.fk_bonentree = ".((int)$id);

$resProd = $db->query($sqlProd);
$linesProd = [];
$total_poids_produits = 0;
while ($obj = $db->fetch_object($resProd)) {
    $linesProd[] = $obj;
    $total_poids_produits += $obj->total_poids;
}

// ============================================================================
// 🔹 RÉCUPÉRATION DES LIGNES SERVICES
// ============================================================================
$sqlServ = "SELECT rowid, description, qte, pu, total
            FROM ".MAIN_DB_PREFIX."pech_bonentree_detserv
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
$pdf->SetAuthor($conf->global->MAIN_INFO_SOCIETE_NOM ?? dol_html_entity_decode($langs->trans("Societe"), ENT_QUOTES, 'UTF-8'));
$pdf->SetTitle(dol_html_entity_decode($langs->trans("BonEntree"), ENT_QUOTES, 'UTF-8').' - '.$bon->ref);
$pdf->SetSubject(dol_html_entity_decode($langs->trans("BonEntreeStock"), ENT_QUOTES, 'UTF-8'));
$pdf->SetKeywords(dol_html_entity_decode($langs->trans("BonEntree"), ENT_QUOTES, 'UTF-8').', '.dol_html_entity_decode($langs->trans("Stock"), ENT_QUOTES, 'UTF-8').', '.$bon->ref);

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
$textReference = dol_html_entity_decode($langs->trans("Reference"), ENT_QUOTES, 'UTF-8');
$textDate = dol_html_entity_decode($langs->trans("Date"), ENT_QUOTES, 'UTF-8');
$subtitle = $textReference." : ".$bon->ref."\n".$textDate." : ".dol_print_date($bon->date_creation, 'daytext');
addProfessionalHeader($pdf, $mysoc, dol_html_entity_decode($langs->trans("BonEntreeStock"), ENT_QUOTES, 'UTF-8'), $subtitle);

// ============================================================================
// 🔹 INFORMATIONS PRINCIPALES
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->SetFillColor(67, 142, 204); // Bleu professionnel
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("InformationsBonEntree"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
$pdf->Ln(5);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', '', 10);

// Tableau des informations
$textNonRenseigne = dol_html_entity_decode($langs->trans("NonRenseigne"), ENT_QUOTES, 'UTF-8');
$infoData = [
    dol_html_entity_decode($langs->trans("Reference"), ENT_QUOTES, 'UTF-8') => $bon->ref,
    
    
    
    
    dol_html_entity_decode($langs->trans("DateCreation"), ENT_QUOTES, 'UTF-8')
    => dol_print_date($db->jdate($dateCreationLot), 'dayhour'),
    //dol_html_entity_decode($langs->trans("DateCreation"), ENT_QUOTES, 'UTF-8') => dol_print_date($dateCreationLot, 'daytext'),
    dol_html_entity_decode($langs->trans("Entrepot"), ENT_QUOTES, 'UTF-8') => $bon->entrepot_ref ?: $textNonRenseigne,
    dol_html_entity_decode($langs->trans("EtabliPar"), ENT_QUOTES, 'UTF-8') => $bon->utilisateur ?: $textNonRenseigne
];

if ($bon->commentaire) {
    $infoData[dol_html_entity_decode($langs->trans("Commentaire"), ENT_QUOTES, 'UTF-8')] = $bon->commentaire;
}

foreach ($infoData as $label => $value) {
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(40, 6, $label.' :', 0, 0, 'L');
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
$pdf->Ln(2);

$pdf->SetFont('dejavusans', '', 10);
$textTotalPoids = dol_html_entity_decode($langs->trans("TotalPoidsProduits"), ENT_QUOTES, 'UTF-8');
$pdf->Cell(80, 6, $textTotalPoids.' :', 0, 0, 'L');
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell(0, 6, price($total_poids_produits).' kg', 0, 1, 'L');

if ($total_montant_services > 0) {
    $pdf->SetFont('dejavusans', '', 10);
    $textTotalServices = dol_html_entity_decode($langs->trans("TotalMontantServices"), ENT_QUOTES, 'UTF-8');
    $pdf->Cell(80, 6, $textTotalServices.' :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(0, 6, price($total_montant_services).' '.$conf->currency, 0, 1, 'L');
}

$pdf->Ln(10);

// ============================================================================
// 🔹 TABLEAU DES PRODUITS
// ============================================================================
if (!empty($linesProd)) {
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetFillColor(67, 142, 204);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("ProduitsEntree"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
    $pdf->Ln(5);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(230, 240, 255);
    
    // En-tête du tableau
    $headerWidths = [70, 30, 40, 40];
    $headerLabels = [
        dol_html_entity_decode($langs->trans("Produit"), ENT_QUOTES, 'UTF-8'),
        dol_html_entity_decode($langs->trans("NbCartons"), ENT_QUOTES, 'UTF-8'),
        dol_html_entity_decode($langs->trans("PoidsCartonKg"), ENT_QUOTES, 'UTF-8'),
        dol_html_entity_decode($langs->trans("PoidsTotalKg"), ENT_QUOTES, 'UTF-8')
    ];
    
    for ($i = 0; $i < count($headerWidths); $i++) {
        $pdf->Cell($headerWidths[$i], 7, $headerLabels[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Lignes des produits
    $pdf->SetFont('dejavusans', '', 9);
    $fill = false;
    
    foreach ($linesProd as $line) {
        if ($fill) {
            $pdf->SetFillColor(245, 248, 255);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // Produit
        $productText = $line->product_ref;
        if (!empty($line->product_label)) {
            $productText .= ' - ' . $line->product_label;
        }
        
        $pdf->Cell($headerWidths[0], 6, $productText, 1, 0, 'L', $fill);
        $pdf->Cell($headerWidths[1], 6, $line->nb_carton, 1, 0, 'C', $fill);
        $pdf->Cell($headerWidths[2], 6, price($line->poids_carton), 1, 0, 'R', $fill);
        $pdf->Cell($headerWidths[3], 6, price($line->total_poids), 1, 1, 'R', $fill);
        
        $fill = !$fill;
    }
    
    // Ligne de total
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(67, 142, 204);
    $pdf->SetTextColor(255, 255, 255);
    $textTotal = dol_html_entity_decode($langs->trans("Total"), ENT_QUOTES, 'UTF-8');
    $pdf->Cell($headerWidths[0] + $headerWidths[1] + $headerWidths[2], 7, $textTotal, 1, 0, 'R', 1);
    $pdf->Cell($headerWidths[3], 7, price($total_poids_produits), 1, 1, 'R', 1);
    
    $pdf->Ln(10);
}

// ============================================================================
// 🔹 TABLEAU DES SERVICES
// ============================================================================
if (!empty($linesServ)) {
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetFillColor(76, 175, 80); // Vert professionnel
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("ServicesAssocies"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
    $pdf->Ln(5);
    
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
        
        $pdf->Cell($servHeaderWidths[0], 6, $line->description, 1, 0, 'L', $fill);
        $pdf->Cell($servHeaderWidths[1], 6, price($line->qte), 1, 0, 'R', $fill);
        $pdf->Cell($servHeaderWidths[2], 6, price($line->pu), 1, 0, 'R', $fill);
        $pdf->Cell($servHeaderWidths[3], 6, price($line->total), 1, 1, 'R', $fill);
        
        $fill = !$fill;
    }
    
    // Ligne de total services
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(76, 175, 80);
    $pdf->SetTextColor(255, 255, 255);
    $textTotal = dol_html_entity_decode($langs->trans("Total"), ENT_QUOTES, 'UTF-8');
    $pdf->Cell($servHeaderWidths[0] + $servHeaderWidths[1] + $servHeaderWidths[2], 7, $textTotal, 1, 0, 'R', 1);
    $pdf->Cell($servHeaderWidths[3], 7, price($total_montant_services), 1, 1, 'R', 1);
    
    $pdf->Ln(15);
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
$pdf->Output(dol_html_entity_decode($langs->trans("BonEntree"), ENT_QUOTES, 'UTF-8').'_'.$bon->ref.'.pdf', 'I');
exit;
?>