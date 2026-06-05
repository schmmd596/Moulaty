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
if ($id <= 0) accessforbidden(dol_html_entity_decode($langs->trans("IdentifiantBonReceptionInvalide"), ENT_QUOTES, 'UTF-8'));

// ============================================================================
// 🔹 RÉCUPÉRATION DU BON PRINCIPAL
// ============================================================================
$sqlBon = "SELECT b.rowid, b.ref, b.date_creation, b.comment, b.total, b.poids,
                  f.nom as fournisseur, c.nom as congelateur, u.login as utilisateur
           FROM ".MAIN_DB_PREFIX."pech_bonreception b
           LEFT JOIN ".MAIN_DB_PREFIX."societe f ON f.rowid = b.fk_fournisseur
           LEFT JOIN ".MAIN_DB_PREFIX."societe c ON c.rowid = b.fk_congelateur
           LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = b.user_create
           WHERE b.rowid = ".((int)$id);

$resBon = $db->query($sqlBon);
if (!$resBon || $db->num_rows($resBon) == 0) {
    exit(dol_html_entity_decode($langs->trans('BonReceptionIntrouvable'), ENT_QUOTES, 'UTF-8'));
}
$bon = $db->fetch_object($resBon);

// ============================================================================
// 🔹 RÉCUPÉRATION DES LIGNES
// ============================================================================
// Lignes automatiques
$sqlDetAuto = "SELECT description, qte, PU, total_line
               FROM ".MAIN_DB_PREFIX."pech_bonreceptiondet
               WHERE fk_bonreception = ".((int)$id)." AND (type = 0 OR type IS NULL)";
$resAuto = $db->query($sqlDetAuto);
$lines_auto = [];
while ($obj = $db->fetch_object($resAuto)) $lines_auto[] = $obj;

// Lignes manuelles
$sqlDetManual = "SELECT description, qte, PU, total_line
                 FROM ".MAIN_DB_PREFIX."pech_bonreceptiondet
                 WHERE fk_bonreception = ".((int)$id)." AND type = 1";
$resManual = $db->query($sqlDetManual);
$lines_manual = [];
while ($obj = $db->fetch_object($resManual)) $lines_manual[] = $obj;


$sqlDetManualhhh = "SELECT date_creation
                 FROM ".MAIN_DB_PREFIX."pech_reception
                 WHERE fk_bon_recep = ".$id;

$resManualhhh = $db->query($sqlDetManualhhh);

$date_rec = null;

if ($resManualhhh) {
    if ($objjj = $db->fetch_object($resManualhhh)) {
        $date_rec = dol_print_date($db->jdate($objjj->date_creation), '%d/%m/%Y %H:%M');
    }
}

// ============================================================================
// 🔹 CRÉATION DU PDF
// ============================================================================
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configuration du document
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($mysoc->name);
$pdf->SetTitle(dol_html_entity_decode($langs->trans("BonReception"), ENT_QUOTES, 'UTF-8').' - '.$bon->ref);
$pdf->SetSubject(dol_html_entity_decode($langs->trans("BonReception"), ENT_QUOTES, 'UTF-8'));
$pdf->SetKeywords(dol_html_entity_decode($langs->trans("BonReception"), ENT_QUOTES, 'UTF-8').', '.$bon->ref.', '.$bon->fournisseur);

// Marges
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);

// Police par défaut
$pdf->SetFont('dejavusans', '', 10);

// ============================================================================
// 🔹 PAGE 1 : BON SIMPLIFIÉ (POUR SIGNATURE)
// ============================================================================
$pdf->AddPage();

// En-tête professionnel
$textReference = dol_html_entity_decode($langs->trans("Reference"), ENT_QUOTES, 'UTF-8');
$textDate = dol_html_entity_decode($langs->trans("Date"), ENT_QUOTES, 'UTF-8');
$subtitle = $textReference." : ".$bon->ref."\n".$textDate." : ".dol_print_date($bon->date_creation, 'daytext');
addProfessionalHeader($pdf, $mysoc, dol_html_entity_decode($langs->trans("BonReception"), ENT_QUOTES, 'UTF-8'), $subtitle);

// Titre principal
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->SetTextColor(67, 142, 204); // Bleu professionnel
$pdf->Cell(0, 12, dol_html_entity_decode($langs->trans("BonReception"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C');
$pdf->Ln(5);

// ============================================================================
// 🔹 INFORMATIONS PRINCIPALES
// ============================================================================
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 8, dol_html_entity_decode($langs->trans("InformationsReception"), ENT_QUOTES, 'UTF-8'), 0, 1, 'L', 1);
$pdf->Ln(3);

$pdf->SetFont('dejavusans', '', 10);
$infoData = [
    dol_html_entity_decode($langs->trans("Reference"), ENT_QUOTES, 'UTF-8') => $bon->ref,
    dol_html_entity_decode($langs->trans("Fournisseur"), ENT_QUOTES, 'UTF-8') => $bon->fournisseur ?: '-',
    dol_html_entity_decode($langs->trans("DateCreation"), ENT_QUOTES, 'UTF-8') => $date_rec, //dol_print_date($date_rec, '%d/%m/%Y %H:%M');,
    dol_html_entity_decode($langs->trans("Congelateur"), ENT_QUOTES, 'UTF-8') => $bon->congelateur ?: '-',
    dol_html_entity_decode($langs->trans("EtabliPar"), ENT_QUOTES, 'UTF-8') => $bon->utilisateur ?: '-'
];

if ($bon->comment) {
    $infoData[dol_html_entity_decode($langs->trans("Commentaire"), ENT_QUOTES, 'UTF-8')] = $bon->comment;
}

foreach ($infoData as $label => $value) {
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(40, 6, $label.' :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 6, $value, 0, 1, 'L');
}

$pdf->Ln(8);

// ============================================================================
// 🔹 TABLEAU DES LIGNES AUTOMATIQUES
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->SetFillColor(67, 142, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("LignesReception"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
$pdf->Ln(5);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->SetFillColor(230, 240, 255);

// En-tête du tableau
$headerWidths = [85, 25, 35, 35];
$headerLabels = [
    dol_html_entity_decode($langs->trans("Description"), ENT_QUOTES, 'UTF-8'),
    dol_html_entity_decode($langs->trans("Quantite"), ENT_QUOTES, 'UTF-8'),
    dol_html_entity_decode($langs->trans("PrixUnitaire"), ENT_QUOTES, 'UTF-8'),
    dol_html_entity_decode($langs->trans("TotalLigne"), ENT_QUOTES, 'UTF-8').' ('.$conf->currency.')'
];

for ($i = 0; $i < count($headerWidths); $i++) {
    $pdf->Cell($headerWidths[$i], 7, $headerLabels[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Lignes des produits
$pdf->SetFont('dejavusans', '', 9);
$fill = false;
$total_auto = 0;

foreach ($lines_auto as $line) {
    if ($fill) {
        $pdf->SetFillColor(245, 248, 255);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    $pdf->Cell($headerWidths[0], 7, $line->description, 1, 0, 'L', $fill);
    $pdf->Cell($headerWidths[1], 7, $line->qte, 1, 0, 'C', $fill);
    $pdf->Cell($headerWidths[2], 7, price($line->PU), 1, 0, 'R', $fill);
    $pdf->Cell($headerWidths[3], 7, price($line->total_line), 1, 1, 'R', $fill);
    
    $total_auto += $line->total_line;
    $fill = !$fill;
}

// Lignes vides pour compléter le tableau
$max_lines = 10;
$count_lines = count($lines_auto);
$line_height = 7;

for ($i = $count_lines; $i < $max_lines; $i++) {
    $border = ($i == $max_lines - 1) ? 'LRB' : 'LR';
    
    if ($fill) {
        $pdf->SetFillColor(245, 248, 255);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    $pdf->Cell($headerWidths[0], $line_height, "", $border, 0, 'L', $fill);
    $pdf->Cell($headerWidths[1], $line_height, "", $border, 0, 'C', $fill);
    $pdf->Cell($headerWidths[2], $line_height, "", $border, 0, 'R', $fill);
    $pdf->Cell($headerWidths[3], $line_height, "", $border, 1, 'R', $fill);
    
    $fill = !$fill;
}

// Ligne de total
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(67, 142, 204);
$pdf->SetTextColor(255, 255, 255);
$textTotal = dol_html_entity_decode($langs->trans("Total"), ENT_QUOTES, 'UTF-8');
$pdf->Cell($headerWidths[0] + $headerWidths[1] + $headerWidths[2], 8, $textTotal, 1, 0, 'R', 1);
$pdf->Cell($headerWidths[3], 8, price($total_auto), 1, 1, 'R', 1);

$pdf->Ln(6);
// ============================================================================
// 🔹 ZONE DE SIGNATURES (PAGE 1)
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
// 🔹 PAGE 2 : BON COMPLET
// ============================================================================
$pdf->AddPage();

// En-tête professionnel
addProfessionalHeader($pdf, $mysoc, dol_html_entity_decode($langs->trans("BonReceptionComplet"), ENT_QUOTES, 'UTF-8'), $subtitle);

// Titre principal
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->SetTextColor(67, 142, 204);
$pdf->Cell(0, 12, dol_html_entity_decode($langs->trans("BonReceptionComplet"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C');
$pdf->Ln(8);

// ============================================================================
// 🔹 INFORMATIONS GÉNÉRALES (PAGE 2)
// ============================================================================
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 8, dol_html_entity_decode($langs->trans("InformationsGenerales"), ENT_QUOTES, 'UTF-8'), 0, 1, 'L', 1);
$pdf->Ln(3);

$pdf->SetFont('dejavusans', '', 10);
foreach ($infoData as $label => $value) {
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(40, 6, $label.' :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 6, $value, 0, 1, 'L');
}

$pdf->Ln(10);

// ============================================================================
// 🔹 TABLEAU 1 : LIGNES AUTOMATIQUES (PAGE 2)
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->SetFillColor(67, 142, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("LignesAutomatiques"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
$pdf->Ln(5);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->SetFillColor(230, 240, 255);

// En-tête du tableau
for ($i = 0; $i < count($headerWidths); $i++) {
    $pdf->Cell($headerWidths[$i], 7, $headerLabels[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Lignes des produits
$pdf->SetFont('dejavusans', '', 9);
$fill = false;
$total_auto = 0;

foreach ($lines_auto as $line) {
    if ($fill) {
        $pdf->SetFillColor(245, 248, 255);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    $pdf->Cell($headerWidths[0], 7, $line->description, 1, 0, 'L', $fill);
    $pdf->Cell($headerWidths[1], 7, $line->qte, 1, 0, 'C', $fill);
    $pdf->Cell($headerWidths[2], 7, price($line->PU), 1, 0, 'R', $fill);
    $pdf->Cell($headerWidths[3], 7, price($line->total_line), 1, 1, 'R', $fill);
    
    $total_auto += $line->total_line;
    $fill = !$fill;
}

// Total automatique
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(67, 142, 204);
$pdf->SetTextColor(255, 255, 255);
$textTotalAuto = dol_html_entity_decode($langs->trans("TotalAutomatique"), ENT_QUOTES, 'UTF-8');
$pdf->Cell($headerWidths[0] + $headerWidths[1] + $headerWidths[2], 8, $textTotalAuto, 1, 0, 'R', 1);
$pdf->Cell($headerWidths[3], 8, price($total_auto), 1, 1, 'R', 1);

$pdf->Ln(12);

$total_manual = 0;
// ============================================================================
// 🔹 TABLEAU 2 : LIGNES MANUELLES (PAGE 2)
// ============================================================================
if (!empty($lines_manual)) {
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetFillColor(76, 175, 80); // Vert professionnel
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("LignesManuelles"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
    $pdf->Ln(5);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(230, 255, 230);
    
    // En-tête du tableau
    for ($i = 0; $i < count($headerWidths); $i++) {
        $pdf->Cell($headerWidths[$i], 7, $headerLabels[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Lignes des services manuels
    $pdf->SetFont('dejavusans', '', 9);
    $fill = false;
    $total_manual = 0;
    
    foreach ($lines_manual as $line) {
        if ($fill) {
            $pdf->SetFillColor(245, 255, 245);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($headerWidths[0], 7, $line->description, 1, 0, 'L', $fill);
        $pdf->Cell($headerWidths[1], 7, price($line->qte), 1, 0, 'C', $fill);
        $pdf->Cell($headerWidths[2], 7, price($line->PU), 1, 0, 'R', $fill);
        $pdf->Cell($headerWidths[3], 7, price($line->total_line), 1, 1, 'R', $fill);
        
        $total_manual += $line->total_line;
        $fill = !$fill;
    }
    
    // Total manuel
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetFillColor(76, 175, 80);
    $pdf->SetTextColor(255, 255, 255);
    $textTotalManuel = dol_html_entity_decode($langs->trans("TotalManuel"), ENT_QUOTES, 'UTF-8');
    $pdf->Cell($headerWidths[0] + $headerWidths[1] + $headerWidths[2], 8, $textTotalManuel, 1, 0, 'R', 1);
    $pdf->Cell($headerWidths[3], 8, price($total_manual), 1, 1, 'R', 1);
    
    $pdf->Ln(8);
}

// ============================================================================
// 🔹 TOTAL GÉNÉRAL (PAGE 2)
// ============================================================================
$total_general = $total_auto + $total_manual;

$pdf->SetFont('dejavusans', 'B', 12);
$pdf->SetFillColor(100, 100, 100); // Orange d'accent
$pdf->SetTextColor(255, 255, 255);
$textTotalGeneral = dol_html_entity_decode($langs->trans("TotalGeneral"), ENT_QUOTES, 'UTF-8');
$pdf->Cell($headerWidths[0] + $headerWidths[1] + $headerWidths[2]  - 20, 10, $textTotalGeneral, 1, 0, 'R', 1);
$pdf->Cell($headerWidths[3] + 20, 10, price($total_general).' '.$conf->currency, 1, 1, 'R', 1);

// ============================================================================
// 🔹 SORTIE PDF
// ============================================================================
$pdf->Output(dol_html_entity_decode($langs->trans("BonReception"), ENT_QUOTES, 'UTF-8').'_'.$bon->ref.'.pdf', 'I');
exit;
?>