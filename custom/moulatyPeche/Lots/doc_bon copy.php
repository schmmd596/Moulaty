<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
require_once '../header.php';

$langs->load("main,abricot@abricot");

$id = GETPOST('id', 'int');
if ($id <= 0) accessforbidden("Identifiant du bon d'entrée invalide");

// --- Récupération du bon principal ---
$sqlBon = "SELECT b.rowid, b.ref, b.date_creation, b.statut,
                  e.ref AS entrepot_ref, u.login AS utilisateur, b.commentaire
           FROM ".MAIN_DB_PREFIX."pech_bonentree AS b
           LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = b.fk_entrepot
           LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = b.fk_user_create
           WHERE b.rowid = ".((int)$id);

$resBon = $db->query($sqlBon);
if (!$resBon || $db->num_rows($resBon) == 0) {
    exit('Bon d\'entrée introuvable');
}
$bon = $db->fetch_object($resBon);

// --- Récupération des lignes produits ---
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

// --- Récupération des lignes services ---
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

// --- Création du PDF ---
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($conf->global->MAIN_INFO_SOCIETE_NOM ?? 'Société');
$pdf->SetTitle('Bon d\'Entrée - '.$bon->ref);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

$subtitle = "Référence : ".$bon->ref."\nDate : ".dol_print_date($bon->date_creation, 'daytext');
addProfessionalHeader($pdf, $mysoc, 'BON D\'ENTRÉE EN STOCK', $subtitle);
// --- En-tête ---
$pdf->SetFont('helvetica', '', 10);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(0, 10, 'BON D\'ENTRÉE EN STOCK', 0, 1, 'C', 1);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, 'Référence : '.$bon->ref, 0, 1, 'L');
$pdf->Cell(0, 6, 'Date : '.dol_print_date($bon->date_creation,'daytext'), 0, 1, 'L');
$pdf->Cell(0, 6, 'Entrepôt : '.($bon->entrepot_ref ?: '-'), 0, 1, 'L');
$pdf->Cell(0, 6, 'Établi par : '.($bon->utilisateur ?: '-'), 0, 1, 'L');
if ($bon->commentaire) $pdf->Cell(0, 6, 'Commentaire : '.$bon->commentaire, 0, 1, 'L');
$pdf->Ln(5);

// --- Total général ---
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, ' Total Poids Produits : '.price($total_poids_produits).' kg', 0, 1, 'L');
//$pdf->Cell(0, 6, ' Total Montant Services : '.price($total_montant_services), 0, 1, 'L');
$pdf->Ln(8);

// --- Tableau des produits ---
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(200,220,255);
$pdf->Cell(70,7,'Produit',1,0,'C',1);
$pdf->Cell(30,7,'Nb Cartons',1,0,'C',1);
$pdf->Cell(40,7,'Poids / Carton (kg)',1,0,'C',1);
$pdf->Cell(40,7,'Total Poids (kg)',1,1,'C',1);

$pdf->SetFont('helvetica', '', 10);
foreach($linesProd as $line) {
    $pdf->Cell(70,6,$line->product_ref.' - '.$line->product_label,1,0,'L');
    $pdf->Cell(30,6,$line->nb_carton,1,0,'C');
    $pdf->Cell(40,6,price($line->poids_carton),1,0,'R');
    $pdf->Cell(40,6,price($line->total_poids),1,1,'R');
}

$pdf->Ln(10); // espace avant services
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'Total Montant Services : '.price($total_montant_services), 0, 1, 'L');
$pdf->Ln(2);
// --- Tableau des services ---
if(!empty($linesServ)) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(200,255,200);
    $pdf->Cell(80,7,'Description',1,0,'C',1);
    $pdf->Cell(30,7,'Quantité',1,0,'C',1);
    $pdf->Cell(30,7,'PU',1,0,'C',1);
    $pdf->Cell(40,7,'Total',1,1,'C',1);

    $pdf->SetFont('helvetica', '', 10);
    foreach($linesServ as $line) {
        $pdf->Cell(80,6,$line->description,1,0,'L');
        $pdf->Cell(30,6,price($line->qte),1,0,'R');
        $pdf->Cell(30,6,price($line->pu),1,0,'R');
        $pdf->Cell(40,6,price($line->total),1,1,'R');
    }
}

$pdf->Ln(15);

// --- Signature ---
$pdf->SetFont('helvetica','B',11);
$pdf->Cell(0,6,'Signatures',0,1,'L');
$pdf->Ln(5);
//$pdf->Cell(90,0,'',1,0,'C'); // cadre signature établi par
$pdf->Cell(20,0,'',0,0); // espace
//$pdf->Cell(90,0,'',1,1,'C'); // cadre signature validé par
$pdf->Ln(2);
//$pdf->SetFont('helvetica','',10);
//$pdf->Cell(90,5,'Établi par',0,0,'C');
//$pdf->Cell(20,5,'',0,0);
//$pdf->Cell(90,5,'Validé par',0,1,'C');
$pdf->SetFont('helvetica','',10);
$pdf->Cell(90,6,'Établi par : ___________________________',0,0,'L');
$pdf->Cell(20,6,'',0,0);
$pdf->Cell(90,6,'Validé par : ___________________________',0,1,'L');

// --- Sortie PDF ---
$pdf->Output('Bon_Entree_'.$bon->ref.'.pdf', 'I');
exit;
?>
