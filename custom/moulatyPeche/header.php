<?php
/**
 * Fonction pour ajouter un en-tête professionnel sur un document PDF Dolibarr
 * @param TCPDF $pdf        Instance du PDF
 * @param object $mysoc     Informations de la société (objet $mysoc)
 * @param string $title     Titre du document (ex: "BON DE RÉCEPTION")
 * @param string $subtitle  Sous-titre (ex: référence, date, etc.)
 */
function addProfessionalHeader($pdf, $mysoc, $title = '', $subtitle = '')
{
    global $conf, $langs;

    // ============================================================================
    // 🔹 CONFIGURATION DES COULEURS PROFESSIONNELLES
    // ============================================================================
    $primaryColor = [67, 142, 204];   // Bleu professionnel
    $secondaryColor = [76, 175, 80];  // Vert professionnel  
    $accentColor = [255, 152, 0];     // Orange d'accent
    $lightGray = [245, 245, 245];     // Gris clair
    $darkGray = [51, 51, 51];         // Gris foncé
    $white = [255, 255, 255];         // Blanc

    // ============================================================================
    // 🔹 BANDEAU PRINCIPAL AVEC DÉGRADÉ
    // ============================================================================
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Rect(0, 0, 210, 35, 'F'); // Bandeau plein sur toute la largeur
    
    // Effet de dégradé (simulé avec des rectangles)
    $pdf->SetFillColor($primaryColor[0]-10, $primaryColor[1]-10, $primaryColor[2]-10);
    $pdf->Rect(0, 0, 210, 5, 'F');

    // ============================================================================
    // 🔹 LOGO DE LA SOCIÉTÉ
    // ============================================================================
    $logoFile = '';
    $logoSize = 20; // Taille du logo
    
    if (!empty($mysoc->logo)) {
        $possible = $conf->mycompany->dir_output . '/logos/' . $mysoc->logo;
        if (is_readable($possible)) $logoFile = $possible;
    }

    // Zone du logo avec fond blanc
    $pdf->SetFillColor($white[0], $white[1], $white[2]);
    $pdf->Rect(15, 5, $logoSize + 10, $logoSize + 10, 'F');
    $pdf->SetDrawColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Rect(15, 5, $logoSize + 10, $logoSize + 10, 'D');

    if (!empty($logoFile)) {
        try {
            $pdf->Image($logoFile, 20, 13, $logoSize, 0, '', '', '', false, 300, '', false, false, 0, false, false, false);
        } catch (Exception $e) {
            // Fallback si problème avec le logo
            $pdf->SetFont('dejavusans', 'I', 7);
            $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
            $pdf->SetXY(15, 20);
            $pdf->Cell($logoSize + 10, 5, $langs->trans("Logo"), 0, 0, 'C');
        }
    } else {
        $pdf->SetFont('dejavusans', 'I', 7);
        $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
        $pdf->SetXY(15, 20);
        $pdf->Cell($logoSize + 10, 5, $langs->trans("Logo"), 0, 0, 'C');
    }

    // ============================================================================
    // 🔹 INFORMATIONS DE LA SOCIÉTÉ (À GAUCHE)
    // ============================================================================
    $pdf->SetTextColor($white[0], $white[1], $white[2]);
    
    // Nom de la société
    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->SetXY(50, 10);
    $pdf->Cell(80, 7, $mysoc->name, 0, 1, 'L');

    // Adresse et coordonnées
    $pdf->SetFont('dejavusans', '', 8);
    
    if (!empty($mysoc->address)) {
        $pdf->SetX(50);
        $pdf->Cell(80, 4, $mysoc->address, 0, 1, 'L');
    }
    
    $location = '';
    if (!empty($mysoc->zip)) $location .= $mysoc->zip . ' ';
    if (!empty($mysoc->town)) $location .= $mysoc->town;
    if (!empty($mysoc->country)) $location .= ' - ' . $mysoc->country;
    
    if (!empty($location)) {
        $pdf->SetX(50);
        $pdf->Cell(80, 4, $location, 0, 1, 'L');
    }

    // Coordonnées
    $contactInfo = '';
    if (!empty($mysoc->phone)) $contactInfo .= $langs->trans("Tel") . ': ' . $mysoc->phone . '  ';
    if (!empty($mysoc->email)) $contactInfo .= 'Email: ' . $mysoc->email;
    
    if (!empty($contactInfo)) {
        $pdf->SetX(50);
        $pdf->Cell(80, 4, $contactInfo, 0, 1, 'L');
    }

    // ============================================================================
    // 🔹 TITRE DU DOCUMENT (À DROITE)
    // ============================================================================
    $titleX = 135;
    
    // Encadré du titre
    $pdf->SetFillColor($white[0], $white[1], $white[2]);
    $pdf->SetDrawColor($white[0], $white[1], $white[2]);
    $pdf->Rect($titleX, 8, 60, 22, 'F');
    
    // Titre principal
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->SetXY($titleX, 10);
    $pdf->MultiCell(60, 8, $title, 0, 'C', false, 1);

    // Sous-titre
    if (!empty($subtitle)) {
        $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetXY($titleX, 22);
        $pdf->MultiCell(60, 4, $subtitle, 0, 'C', false, 1);
    }

    // ============================================================================
    // 🔹 BARRE DE SÉPARATION
    // ============================================================================
    $pdf->SetDrawColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetLineWidth(0.5);
    
    // Ligne épaisse principale
    $pdf->Line(0, 35, 210, 35);
    
    // Ligne fine d'accent
    $pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(0, 36, 210, 36);

    // ============================================================================
    // 🔹 INFORMATIONS COMPLÉMENTAIRES (OPTIONNELLES)
    // ============================================================================
    if (!empty($mysoc->idprof1) || !empty($mysoc->idprof2)) {
        $pdf->SetY(38);
        $pdf->SetFont('dejavusans', '', 7);
        $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
        
        $footerInfo = '';
        if (!empty($mysoc->idprof1)) $footerInfo .= $langs->trans("SIRET") . ': ' . $mysoc->idprof1 . '  ';
        if (!empty($mysoc->idprof2)) $footerInfo .= $langs->trans("VAT") . ': ' . $mysoc->idprof2 . '  ';
        if (!empty($mysoc->url)) $footerInfo .= 'Web: ' . $mysoc->url;
        
        if (!empty($footerInfo)) {
            $pdf->Cell(0, 4, $footerInfo, 0, 1, 'C');
        }
    }

    // ============================================================================
    // 🔹 RÉINITIALISATION DES PARAMÈTRES
    // ============================================================================
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);
    
    // Position Y après le header
    $pdf->SetY(45);

    // ============================================================================
    // 🔹 MARGE SUPÉRIEURE POUR LE CONTENU
    // ============================================================================
    $pdf->setHeaderMargin(50);
}
?>