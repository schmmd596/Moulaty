<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
require_once '../header.php';

$langs->load("main,womapeche@womapeche");

if (empty($user->rights->moulatyPeche->read_b)) {
    accessforbidden(dol_html_entity_decode($langs->trans("AccessReservedToAdmin"), ENT_QUOTES, 'UTF-8'));
}

// Paramètre : id du bon
$id = GETPOST('id', 'int');
if ($id <= 0) accessforbidden(dol_html_entity_decode($langs->trans("InvalidPlatingBonId"), ENT_QUOTES, 'UTF-8'));

// --- Récupération du bon principal ---
$sqlBon = "SELECT b.rowid, b.ref, b.date_creation, b.statut,
                  e.ref as entrepot_ref, u.login as utilisateur
           FROM ".MAIN_DB_PREFIX."pech_bon_misenplat AS b
           LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = b.fk_entrepot
           LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = b.fk_user
           WHERE b.rowid = ".((int)$id);

$resBon = $db->query($sqlBon);
if (!$resBon || $db->num_rows($resBon) == 0) {
    exit(dol_html_entity_decode($langs->trans("PlatingBonNotFound"), ENT_QUOTES, 'UTF-8'));
}
$bon = $db->fetch_object($resBon);

// --- Récupération des lignes ---
$sqlLines = "SELECT m.rowid, m.fk_product, m.nombre_plat, m.poids_plat,
                     p.ref AS product_ref, p.label AS product_label
             FROM ".MAIN_DB_PREFIX."pech_misenplat AS m
             LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = m.fk_product
             WHERE m.fk_bon_misenplat = ".((int)$id);
$resLines = $db->query($sqlLines);
$lines = [];
while ($obj = $db->fetch_object($resLines)) $lines[] = $obj;

// --- Création du PDF ---
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configuration pour l'arabe
if ($langs->defaultlang == 'ar_MA') {
    $pdf->setRTL(true);
}

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($conf->global->MAIN_INFO_SOCIETE_NOM ?? dol_html_entity_decode($langs->trans("Company"), ENT_QUOTES, 'UTF-8'));
$pdf->SetTitle(dol_html_entity_decode($langs->trans("PlatingBon"), ENT_QUOTES, 'UTF-8').' - '.$bon->ref);
$pdf->SetSubject(dol_html_entity_decode($langs->trans("PlatingBon"), ENT_QUOTES, 'UTF-8'));
$pdf->SetKeywords(dol_html_entity_decode($langs->trans("PlatingBon"), ENT_QUOTES, 'UTF-8').', '.dol_html_entity_decode($langs->trans("MiseEnPlat"), ENT_QUOTES, 'UTF-8').', '.$bon->ref);

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
addProfessionalHeader($pdf, $mysoc, dol_html_entity_decode($langs->trans("PlatingBon"), ENT_QUOTES, 'UTF-8'), $subtitle);

// ============================================================================
// 🔹 INFORMATIONS PRINCIPALES
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->SetFillColor(67, 142, 204); // Bleu professionnel
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("PlatingBon"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
$pdf->Ln(5);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', '', 10);

// Tableau des informations
$infoData = [
    dol_html_entity_decode($langs->trans("Reference"), ENT_QUOTES, 'UTF-8') => $bon->ref,
    dol_html_entity_decode($langs->trans("CreationDate"), ENT_QUOTES, 'UTF-8') => dol_print_date($bon->date_creation, 'daytext'),
    dol_html_entity_decode($langs->trans("Warehouse"), ENT_QUOTES, 'UTF-8') => $bon->entrepot_ref ?: '-',
    dol_html_entity_decode($langs->trans("CreatedBy"), ENT_QUOTES, 'UTF-8') => $bon->utilisateur ?: '-'
];

foreach ($infoData as $label => $value) {
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(40, 6, $label.' :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 6, $value, 0, 1, 'L');
}

$pdf->Ln(8);

// ============================================================================
// 🔹 TABLEAU DES LIGNES DE MISE EN PLAT
// ============================================================================
if (!empty($lines)) {
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetFillColor(67, 142, 204); // Vert professionnel
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("PlatingDetails"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
    $pdf->Ln(5);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(230, 255, 230);
    
    // En-tête du tableau
    $headerWidths = [70, 30, 40, 40];
    $headerLabels = [
        dol_html_entity_decode($langs->trans("Product"), ENT_QUOTES, 'UTF-8'),
        dol_html_entity_decode($langs->trans("NumberOfPlates"), ENT_QUOTES, 'UTF-8'),
        dol_html_entity_decode($langs->trans("WeightPerPlate"), ENT_QUOTES, 'UTF-8').' (kg)',
        dol_html_entity_decode($langs->trans("TotalWeight"), ENT_QUOTES, 'UTF-8').' (kg)'
    ];
    
    for ($i = 0; $i < count($headerWidths); $i++) {
        $pdf->Cell($headerWidths[$i], 7, $headerLabels[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Lignes des produits
    $pdf->SetFont('dejavusans', '', 9);
    $fill = false;
    $total_nb = 0;
    $total_poids = 0;
    
    foreach ($lines as $line) {
        if ($fill) {
            $pdf->SetFillColor(245, 255, 245);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $poids_total = $line->nombre_plat * $line->poids_plat;
        $total_nb += $line->nombre_plat;
        $total_poids += $poids_total;
        
        // Produit
        $productText = $line->product_label ?: $line->product_ref;
        
        $pdf->Cell($headerWidths[0], 6, $productText, 1, 0, 'L', $fill);
        $pdf->Cell($headerWidths[1], 6, $line->nombre_plat, 1, 0, 'C', $fill);
        $pdf->Cell($headerWidths[2], 6, number_format($line->poids_plat, 2, ',', ' '), 1, 0, 'R', $fill);
        $pdf->Cell($headerWidths[3], 6, number_format($poids_total, 2, ',', ' '), 1, 1, 'R', $fill);
        
        $fill = !$fill;
    }
    
    // Ligne de total
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetTextColor(0, 0, 0);
    $textTotal = dol_html_entity_decode($langs->trans("Total"), ENT_QUOTES, 'UTF-8');
    $pdf->Cell($headerWidths[0] + $headerWidths[1] + $headerWidths[2], 7, $textTotal, 1, 0, 'R', 1);
    $pdf->Cell($headerWidths[3], 7, number_format($total_poids, 2, ',', ' ').' kg', 1, 1, 'R', 1);
    
    $pdf->Ln(10);
}

// ============================================================================
// 🔹 FRAIS ASSOCIÉS
// ============================================================================
$sqlFrais = "SELECT description, qte, PU, total_line
             FROM ".MAIN_DB_PREFIX."pech_bon_misenplatdet
             WHERE fk_bon_misenplat = ".((int)$id);
$resFrais = $db->query($sqlFrais);

if ($resFrais && $db->num_rows($resFrais) > 0) {
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetFillColor(67, 142, 204); // Violet professionnel
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("AssociatedFees"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
    $pdf->Ln(5);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(240, 230, 255);
    
    // En-tête du tableau frais
    $fraisHeaderWidths = [70, 30, 40, 40];
    $fraisHeaderLabels = [
        dol_html_entity_decode($langs->trans("Description"), ENT_QUOTES, 'UTF-8'),
        dol_html_entity_decode($langs->trans("Quantity"), ENT_QUOTES, 'UTF-8'),
        dol_html_entity_decode($langs->trans("UnitPrice"), ENT_QUOTES, 'UTF-8'),
        dol_html_entity_decode($langs->trans("LineTotal"), ENT_QUOTES, 'UTF-8')
    ];
    
    for ($i = 0; $i < count($fraisHeaderWidths); $i++) {
        $pdf->Cell($fraisHeaderWidths[$i], 7, $fraisHeaderLabels[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Lignes des frais
    $pdf->SetFont('dejavusans', '', 9);
    $fill = false;
    $total_frais = 0;
    
    while ($f = $db->fetch_object($resFrais)) {
        if ($fill) {
            $pdf->SetFillColor(245, 240, 255);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($fraisHeaderWidths[0], 6, $f->description, 1, 0, 'L', $fill);
        $pdf->Cell($fraisHeaderWidths[1], 6, number_format($f->qte, 0, ',', ' '), 1, 0, 'C', $fill);
        $pdf->Cell($fraisHeaderWidths[2], 6, number_format($f->PU, 2, ',', ' '), 1, 0, 'R', $fill);
        $pdf->Cell($fraisHeaderWidths[3], 6, number_format($f->total_line, 2, ',', ' '), 1, 1, 'R', $fill);
        
        $total_frais += $f->total_line;
        $fill = !$fill;
    }
    
    // Ligne de total frais
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetTextColor(0, 0, 0);
    $textTotalFees = dol_html_entity_decode($langs->trans("TotalFees"), ENT_QUOTES, 'UTF-8');
    $pdf->Cell($fraisHeaderWidths[0] + $fraisHeaderWidths[1] + $fraisHeaderWidths[2], 7, $textTotalFees, 1, 0, 'R', 1);
    $pdf->Cell($fraisHeaderWidths[3], 7, number_format($total_frais, 2, ',', ' ').' '.$conf->currency, 1, 1, 'R', 1) ;
    
    $pdf->Ln(15);
}

// ============================================================================
// 🔹 ZONE DE SIGNATURES
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->SetFillColor(120, 120, 120);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, dol_html_entity_decode($langs->trans("Signatures"), ENT_QUOTES, 'UTF-8'), 0, 1, 'L', 1);
$pdf->Ln(4);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', '', 10);

$signatureWidth = 85;
$spacing = 10;

// Vérifier espace restant sur la page
if ($pdf->GetY() > 250) {
    $pdf->AddPage();
}

// Affichage des lignes de signatures
$textWarehouseManager = dol_html_entity_decode($langs->trans("WarehouseManagerSignature"), ENT_QUOTES, 'UTF-8');
$textProductionManager = dol_html_entity_decode($langs->trans("ProductionManagerSignature"), ENT_QUOTES, 'UTF-8');

$pdf->Cell($signatureWidth, 8, $textWarehouseManager.' : ___________________________', 0, 0, 'L');
$pdf->Cell($spacing, 8, '', 0, 0);
$pdf->Cell($signatureWidth, 8, $textProductionManager.' : ___________________________', 0, 1, 'L');


// ============================================================================
// 🔹 SORTIE PDF
// ============================================================================
$pdf->Output(dol_html_entity_decode($langs->trans("PlatingBon"), ENT_QUOTES, 'UTF-8').'_'.$bon->ref.'.pdf', 'I');
exit;