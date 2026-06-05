<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';

$langs->load("main");

// --- Vérification des droits ---
if (!$user->rights->societe->lire) accessforbidden();

// --- Récupération des infos société ---
$mysoc = new stdClass();
$mysoc->name    = $conf->global->MAIN_INFO_SOCIETE_NOM    ?? '-';
$mysoc->address = $conf->global->MAIN_INFO_SOCIETE_ADDRESS ?? '-';
$mysoc->town    = $conf->global->MAIN_INFO_SOCIETE_TOWN    ?? '-';
$mysoc->country = $conf->global->MAIN_INFO_SOCIETE_COUNTRY ?? '-';
$mysoc->phone   = $conf->global->MAIN_INFO_SOCIETE_TEL     ?? '-';
$mysoc->email   = $conf->global->MAIN_INFO_SOCIETE_MAIL    ?? '-';
$mysoc->logo    = (!empty($conf->global->MAIN_INFO_SOCIETE_LOGO)
    ? DOL_DATA_ROOT.'/mycompany/logos/'.$conf->global->MAIN_INFO_SOCIETE_LOGO
    : '');

// --- Paramètre : id du bon d’entrée ---
$id = GETPOST('id', 'int');
if ($id <= 0) accessforbidden("Identifiant du bon d’entrée invalide");

// --- Récupération du bon principal ---
$sqlBon = "SELECT rowid, ref, fk_user, commentaire, date_creation, statut
           FROM ".MAIN_DB_PREFIX."pech_bonentree
           WHERE rowid = ".((int)$id);
$resBon = $db->query($sqlBon);
if (!$resBon || $db->num_rows($resBon) == 0) {
    exit('Bon d’entrée introuvable');
}
$bon = $db->fetch_object($resBon);

// --- Récupération des lignes de détail ---
$sqlDet = "SELECT d.rowid, d.fk_soc, s.nom as fournisseur, d.fk_reception, r.ref as ref_reception,
                  d.fk_prod, p.label as produit, d.nombre_plat, d.poids_total, d.commentaire, d.date_ligne
           FROM ".MAIN_DB_PREFIX."pech_bonentree_det d
           LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = d.fk_soc
           LEFT JOIN ".MAIN_DB_PREFIX."pech_reception r ON r.rowid = d.fk_reception
           LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = d.fk_prod
           WHERE d.fk_bonentree = ".((int)$id);
$resDet = $db->query($sqlDet);
$lines = [];
while ($obj = $db->fetch_object($resDet)) $lines[] = $obj;

// --- Création du PDF ---
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($mysoc->name);
$pdf->SetTitle('Bon d\'entrée - '.$bon->ref);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// --- Bloc logo + infos société ---
if (!empty($mysoc->logo) && file_exists($mysoc->logo)) {
    $pdf->Image($mysoc->logo, 100, 10, 30);
}
/*if (!empty($mysoc->logo) && file_exists($mysoc->logo)) {
    $pdf->Image($mysoc->logo, 15, $startY, 30, 0, '', '', '', false, 300, '', false, false, 0);
}*/
$pdf->SetXY(50, 10);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 6, strtoupper($mysoc->name), 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->MultiCell(0, 5,
    "Adresse : ".$mysoc->address."\n".
    "Ville : ".$mysoc->town."\n".
    "Pays : ".$mysoc->country."\n".
    "Téléphone : ".$mysoc->phone."\n".
    "Email : ".$mysoc->email,
    0, 'L'
);

$pdf->Ln(8);
$pdf->SetFont('helvetica','B',14);
$pdf->Cell(0,10,"BON D'ENTRÉE",0,1,'C');
$pdf->Ln(4);

// --- Infos du bon ---
$pdf->SetFont('helvetica','',10);
$pdf->Cell(40,6,"Référence :",0,0);
$pdf->Cell(50,6,$bon->ref,0,1);
$pdf->Cell(40,6,"Date de création :",0,0);
$pdf->Cell(50,6,dol_print_date($db->jdate($bon->date_creation), 'dayhour'),0,1);
$pdf->Cell(40,6,"Créé par :",0,0);
$pdf->Cell(50,6,dolGetFirstLastname($user->firstname, $user->lastname),0,1);
$pdf->Cell(40,6,"Statut :",0,0);
$pdf->Cell(50,6,($bon->statut ? 'Validé' : 'Brouillon'),0,1);

$pdf->Ln(5);
if (!empty($bon->commentaire)) {
    $pdf->MultiCell(0, 6, "Commentaire : ".$bon->commentaire, 0, 'L');
}
$pdf->Ln(5);

// --- Tableau des lignes ---
$pdf->SetFont('helvetica','B',10);
$pdf->SetFillColor(230,230,230);
$pdf->Cell(40,7,'Fournisseur',1,0,'C',true);
$pdf->Cell(35,7,'Réception',1,0,'C',true);
$pdf->Cell(45,7,'Produit',1,0,'C',true);
$pdf->Cell(25,7,'Nb Plats',1,0,'C',true);
$pdf->Cell(30,7,'Poids Total (kg)',1,1,'C',true);

$pdf->SetFont('helvetica','',10);
foreach ($lines as $line) {
    $pdf->Cell(40,7,substr($line->fournisseur,0,25),1);
    $pdf->Cell(35,7,$line->ref_reception,1);
    $pdf->Cell(45,7,substr($line->produit,0,25),1);
    $pdf->Cell(25,7,$line->nombre_plat,1,0,'C');
    $pdf->Cell(30,7,price($line->poids_total),1,1,'R');
}

$total_lignes = count($lines);
$total_plats = 0;
$total_poids = 0;

foreach ($lines as $line) {
    $total_plats += (int) $line->nombre_plat;
    $total_poids += (float) $line->poids_total;
}

// --- Affichage de la ligne des totaux ---
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(120, 7, 'TOTAL : ('.$total_lignes.')', 1, 0, 'R', true);
$pdf->Cell(25, 7, $total_plats, 1, 0, 'C', true);
$pdf->Cell(30, 7, price($total_poids), 1, 1, 'R', true);

// --- Pied de page ---
$pdf->Ln(10);
$pdf->SetFont('helvetica','I',9);
$pdf->Cell(0,5,"Document généré automatiquement depuis Dolibarr - ".dol_print_date(dol_now(),'dayhour'),0,1,'C');

// --- Sortie PDF ---
$pdf->Output('Bon_Entree_'.$bon->ref.'.pdf', 'I');
exit;
?>
