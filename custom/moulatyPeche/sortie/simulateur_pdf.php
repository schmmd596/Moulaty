<?php
/**
 * Simulateur de sortie - Génération du devis PDF
 * Version organisée avec affichage structuré
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

require '../header.php';
$langs->loadLangs(['stocks', 'main', 'womapeche@womapeche', 'abricot@abricot']);

// ============================================================================
// 🔹 VÉRIFICATION DES DONNÉES
// ============================================================================
$fk_entrepot_source = GETPOST('fk_entrepot_source', 'int');
$fk_client_dest = GETPOST('fk_client_dest', 'int');
$mode_sortie = GETPOST('mode_sortie', 'alpha');

// Données produits
$products = GETPOST('products', 'array');
$nb_carton = GETPOST('nb_carton', 'array');
$poids = GETPOST('poids', 'array');
$valeur = GETPOST('valeur', 'array');
$frais_stockage = GETPOST('frais_stockage', 'array');
$cartons_rowid = GETPOST('cartons_rowid', 'array');

// Services
$services_data = GETPOST('services', 'array');
$total_services = GETPOST('total_services', 'float');

// Marge
$marge_pourcentage = GETPOST('marge_pourcentage', 'float');
$marge_fixe = GETPOST('marge_fixe', 'float');
$marge_montant = GETPOST('marge_montant', 'float');

// Totaux
$valeur_totale = GETPOST('valeur_totale', 'float');
$total_frais_stockage = GETPOST('total_frais_stockage', 'float');

// Vérifier que nous avons des données
if (empty($fk_entrepot_source) || empty($fk_client_dest) || empty($products)) {
    setEventMessages($langs->trans("DonneesManquantes"), null, 'errors');
    header("Location: simulateur_sortie.php");
    exit;
}

// ============================================================================
// 🔹 RÉCUPÉRATION DES INFORMATIONS
// ============================================================================
$entrepot_src = new Entrepot($db);
$entrepot_src->fetch($fk_entrepot_source);

$client = new Societe($db);
$client->fetch($fk_client_dest);

// ============================================================================
// 🔹 PRÉPARATION DES DONNÉES POUR LE PDF
// ============================================================================
$produits_details = [];
$total_valeur_produits = 0;
$total_cartons = 0;
$total_poids = 0;
$total_frais_stockage_calc = 0;

foreach ($products as $i => $product_id) {
    if ($product_id <= 0) continue;
    
    $product = new Product($db);
    $product->fetch($product_id);
    
    $nb_carton_i = isset($nb_carton[$i]) ? (float)$nb_carton[$i] : 0;
    $poids_i = isset($poids[$i]) ? (float)$poids[$i] : 0;
    $valeur_i = isset($valeur[$i]) ? (float)$valeur[$i] : 0;
    $frais_stockage_i = isset($frais_stockage[$i]) ? (float)$frais_stockage[$i] : 0;
    
    if ($nb_carton_i <= 0) continue;
    
    // Calcul des valeurs
    $prix_moyen_carton = ($nb_carton_i > 0) ? ($valeur_i - $frais_stockage_i) / $nb_carton_i : 0;
    $valeur_moyenne_carton = ($nb_carton_i > 0) ? $valeur_i / $nb_carton_i : 0;
    
    $produits_details[] = [
        'id' => $product_id,
        'ref' => $product->ref,
        'label' => $product->label,
        'nb_carton' => $nb_carton_i,
        'poids' => $poids_i,
        'valeur' => $valeur_i,
        'frais_stockage' => $frais_stockage_i,
        'prix_moyen' => $prix_moyen_carton,
        'valeur_moyenne' => $valeur_moyenne_carton,
        'cartons_rowid' => isset($cartons_rowid[$i]) ? $cartons_rowid[$i] : ''
    ];
    
    $total_valeur_produits += $valeur_i;
    $total_frais_stockage_calc += $frais_stockage_i;
    $total_cartons += $nb_carton_i;
    $total_poids += $poids_i;
}

// Services sélectionnés
$services_details = [];
$total_services_calc = 0;

if (is_array($services_data) && !empty($services_data)) {
    foreach ($services_data as $service) {
        if (!empty($service['id']) && $service['id'] > 0) {
            $product_service = new Product($db);
            $product_service->fetch($service['id']);
            
            $qty = isset($service['qty']) ? (float)$service['qty'] : 0;
            $pu = isset($service['pu']) ? (float)$service['pu'] : 0;
            $total = isset($service['total']) ? (float)$service['total'] : ($qty * $pu);
            
            $services_details[] = [
                'id' => $service['id'],
                'ref' => $product_service->ref,
                'label' => $product_service->label,
                'qty' => $qty,
                'pu' => $pu,
                'total' => $total
            ];
            
            $total_services_calc += $total;
        }
    }
}

// Calcul des totaux finaux
$sous_total = $total_valeur_produits + $total_services_calc;
$total_general = $valeur_totale ? $valeur_totale : $sous_total + (float) $marge_montant;

// ============================================================================
// 🔹 CRÉATION DU PDF
// ============================================================================
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configuration du document
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($mysoc->name);
$pdf->SetTitle(dol_html_entity_decode($langs->trans("DevisSimulation"), ENT_QUOTES, 'UTF-8').' - '.$client->name);
$pdf->SetSubject(dol_html_entity_decode($langs->trans("DevisSimulationStock"), ENT_QUOTES, 'UTF-8'));
$pdf->SetKeywords(dol_html_entity_decode($langs->trans("Devis"), ENT_QUOTES, 'UTF-8').', Simulation, '.$client->name);

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
function addCustomHeader($pdf, $mysoc, $title, $subtitle) {
    $pdf->SetY(15);
    
    // Logo si disponible
    if (!empty($mysoc->logo) && file_exists(DOL_DOCUMENT_ROOT.'/viewimage.php?modulepart=companylogo&file='.$mysoc->logo)) {
        $logo_path = DOL_DOCUMENT_ROOT.'/viewimage.php?modulepart=companylogo&file='.$mysoc->logo;
        $pdf->Image($logo_path, 15, 15, 40, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $pdf->SetX(60);
    } else {
        $pdf->SetX(15);
    }
    
    // Nom de l'entreprise
    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->SetTextColor(67, 142, 204);
    $pdf->Cell(0, 8, $mysoc->name, 0, 1, 'L');
    
    // Adresse
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $address_lines = [];
    if (!empty($mysoc->address)) $address_lines[] = $mysoc->address;
    if (!empty($mysoc->zip)) $address_lines[] = $mysoc->zip;
    if (!empty($mysoc->town)) $address_lines[] = $mysoc->town;
    if (!empty($mysoc->country)) $address_lines[] = $mysoc->country;
    if (!empty($mysoc->phone)) $address_lines[] = 'Tél: ' . $mysoc->phone;
    if (!empty($mysoc->email)) $address_lines[] = 'Email: ' . $mysoc->email;
    
    $address_text = implode(' - ', $address_lines);
    $pdf->MultiCell(0, 4, $address_text, 0, 'L');
    
    // Titre et sous-titre
    $pdf->SetY(45);
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->SetTextColor(67, 142, 204);
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    
    if ($subtitle) {
        $pdf->SetFont('dejavusans', 'I', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, $subtitle, 0, 1, 'C');
    }
    
    $pdf->SetY(60);
}

// Utiliser l'en-tête personnalisé
//$subtitle = dol_html_entity_decode($langs->trans("ReferenceSimulation"), ENT_QUOTES, 'UTF-8') . " : SIM-" . date('Ymd-His');
//addCustomHeader($pdf, $mysoc, dol_html_entity_decode($langs->trans("DevisSimulationStock"), ENT_QUOTES, 'UTF-8'), $subtitle);
//DevisSimulationStock
//$subtitle = dol_html_entity_decode($langs->trans("ReferenceSimulation"), ENT_QUOTES, 'UTF-8')." : ".$bon->ref."\n".dol_html_entity_decode($langs->trans("Date"), ENT_QUOTES, 'UTF-8')." : ".dol_print_date($bon->date_creation, 'daytext');
addProfessionalHeader($pdf, $mysoc, dol_html_entity_decode($langs->trans("DevisSimulationStock"), ENT_QUOTES, 'UTF-8'), '');
// Titre principal
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->SetTextColor(67, 142, 204); // Bleu professionnel
$pdf->Cell(0, 12, dol_html_entity_decode($langs->trans("Simulation"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C');
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
    dol_html_entity_decode($langs->trans("DateSimulation"), ENT_QUOTES, 'UTF-8') => dol_print_date(dol_now(), 'dayhourtext'),
    dol_html_entity_decode($langs->trans("EntrepotSource"), ENT_QUOTES, 'UTF-8') => $entrepot_src->ref,
    dol_html_entity_decode($langs->trans("Client"), ENT_QUOTES, 'UTF-8') => $client->name,
    //dol_html_entity_decode($langs->trans("ModeSortie"), ENT_QUOTES, 'UTF-8') => ($mode_sortie == 'vente') ? dol_html_entity_decode($langs->trans("Vente"), ENT_QUOTES, 'UTF-8') : dol_html_entity_decode($langs->trans("TransfertInterne"), ENT_QUOTES, 'UTF-8'),
    dol_html_entity_decode($langs->trans("TypeDocument"), ENT_QUOTES, 'UTF-8') => dol_html_entity_decode($langs->trans("DevisSimulation"), ENT_QUOTES, 'UTF-8')
];

// Affichage des informations
foreach ($infoData as $label => $value) {
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(45, 6, $label.' :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 6, $value, 0, 1, 'L');
}

$pdf->Ln(10);

// ============================================================================
// 🔹 TABLEAU DES PRODUITS - AFFICHAGE BIEN ORGANISÉ
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->SetFillColor(67, 142, 204);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("DetailsProduitsFraisStockage"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
$pdf->Ln(5);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->SetFillColor(230, 240, 255);

// Définir les largeurs des colonnes
$col1 = 60; // Produit
$col2 = 25; // Nb Cartons
$col3 = 25; // Poids (kg)
$col4 = 30; // Prix moyen
$col5 = 40; // Valeur totale
$totalWidth = $col1 + $col2 + $col3 + $col4 + $col5;

// En-tête du tableau
$pdf->Cell($col1, 7, dol_html_entity_decode($langs->trans("Produit"), ENT_QUOTES, 'UTF-8'), 1, 0, 'C', 1);
$pdf->Cell($col2, 7, dol_html_entity_decode($langs->trans("NbCartons"), ENT_QUOTES, 'UTF-8'), 1, 0, 'C', 1);
$pdf->Cell($col3, 7, dol_html_entity_decode($langs->trans("PoidsKg"), ENT_QUOTES, 'UTF-8'), 1, 0, 'C', 1);
$pdf->Cell($col4, 7, dol_html_entity_decode($langs->trans("PrixMoyenCarton"), ENT_QUOTES, 'UTF-8'), 1, 0, 'C', 1);
$pdf->Cell($col5, 7, dol_html_entity_decode($langs->trans("ValeurTotale"), ENT_QUOTES, 'UTF-8'), 1, 1, 'C', 1);

// Lignes des produits - VERSION CORRIGÉE
$pdf->SetFont('dejavusans', '', 9);
$fill = false;

foreach ($produits_details as $prod) {
    if ($fill) {
        $pdf->SetFillColor(245, 248, 255);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    // Calculer la hauteur nécessaire pour cette ligne
    $startY = $pdf->GetY();
    
    // Colonne 1: Produit (avec gestion multi-lignes)
    $productText = $prod['ref'];
    if (!empty($prod['label'])) {
        $productText .= "\n" . $prod['label'];
    }
    
    // Obtenir la hauteur nécessaire
    $pdf->MultiCell($col1, 5, $productText, 0, 'L', false, 0);
    $endY = $pdf->GetY();
    $lineHeight = max(10, $endY - $startY); // Hauteur minimale de 10
    
    // Réinitialiser à la position de départ
    $pdf->SetY($startY);
    
    // Afficher toute la ligne avec la même hauteur
    // Colonne 1: Produit
    $pdf->MultiCell($col1, $lineHeight, $productText, 1, 'L', $fill, 0);
    
    // Positionner pour les colonnes suivantes
    $pdf->SetY($startY);
    $pdf->SetX(15 + $col1);
    
    // Colonne 2: Nb Cartons
    $pdf->MultiCell($col2, $lineHeight, $prod['nb_carton'], 1, 'C', $fill, 0);
    
    // Colonne 3: Poids
    $pdf->SetY($startY);
    $pdf->SetX(15 + $col1 + $col2);
    $pdf->MultiCell($col3, $lineHeight, price($prod['poids']), 1, 'R', $fill, 0);
    
    // Colonne 4: Prix moyen
    $pdf->SetY($startY);
    $pdf->SetX(15 + $col1 + $col2 + $col3);
    $pdf->MultiCell($col4, $lineHeight, number_format($prod['valeur_moyenne'], 2, '.', ' ') . ' ' . $conf->currency, 1, 'R', $fill, 0);
    
    // Colonne 5: Valeur totale
    $pdf->SetY($startY);
    $pdf->SetX(15 + $col1 + $col2 + $col3 + $col4);
    $pdf->MultiCell($col5, $lineHeight, number_format($prod['valeur'], 2, '.', ' ') . ' ' . $conf->currency, 1, 'R', $fill, 1);
    
    // Définir la position Y pour la ligne suivante
    $pdf->SetY($startY + $lineHeight);
    
    $fill = !$fill;
}

// Ligne de total
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(67, 142, 204);
$pdf->SetTextColor(255, 255, 255);

$startY = $pdf->GetY();
$totalHeight = 8;

// Cellule "Total Produits" (regroupe les 4 premières colonnes)
$pdf->MultiCell($col1 + $col2 + $col3 + $col4, $totalHeight, dol_html_entity_decode($langs->trans("TotalProduits"), ENT_QUOTES, 'UTF-8'), 1, 'R', 1, 0);
$pdf->SetY($startY);
$pdf->SetX(15 + $col1 + $col2 + $col3 + $col4);

// Cellule avec la valeur totale
$pdf->MultiCell($col5, $totalHeight, number_format($total_valeur_produits, 2, '.', ' ') . ' ' . $conf->currency, 1, 'R', 1, 1);

$pdf->SetY($startY + $totalHeight);
$pdf->Ln(5);

// ============================================================================
// 🔹 NOTE SUR LES FRAIS DE STOCKAGE
// ============================================================================


// ============================================================================
// 🔹 TABLEAU DES SERVICES - AFFICHAGE BIEN ORGANISÉ
// ============================================================================
if (!empty($services_details)) {
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetFillColor(76, 175, 80);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("ServicesAdditionnels"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
    $pdf->Ln(5);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(230, 255, 230);
    
    // Définir les largeurs des colonnes pour les services
    $servCol1 = 80; // Service
    $servCol2 = 30; // Quantité
    $servCol3 = 30; // Prix unitaire
    $servCol4 = 40; // Total
    
    // En-tête du tableau services
    $pdf->Cell($servCol1, 7, dol_html_entity_decode($langs->trans("Service"), ENT_QUOTES, 'UTF-8'), 1, 0, 'C', 1);
    $pdf->Cell($servCol2, 7, dol_html_entity_decode($langs->trans("Quantite"), ENT_QUOTES, 'UTF-8'), 1, 0, 'C', 1);
    $pdf->Cell($servCol3, 7, dol_html_entity_decode($langs->trans("PrixUnitaire"), ENT_QUOTES, 'UTF-8'), 1, 0, 'C', 1);
    $pdf->Cell($servCol4, 7, dol_html_entity_decode($langs->trans("Total"), ENT_QUOTES, 'UTF-8'), 1, 1, 'C', 1);
    
    // Lignes des services - VERSION CORRIGÉE
    $pdf->SetFont('dejavusans', '', 9);
    $fill = false;
    
    foreach ($services_details as $service) {
        if ($fill) {
            $pdf->SetFillColor(245, 255, 245);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // Calculer la hauteur nécessaire
        $startY = $pdf->GetY();
        
        // Texte du service
        $serviceText = $service['ref'];
        if (!empty($service['label'])) {
            $serviceText .= "\n" . $service['label'];
        }
        
        // Obtenir la hauteur nécessaire
        $pdf->MultiCell($servCol1, 5, $serviceText, 0, 'L', false, 0);
        $endY = $pdf->GetY();
        $lineHeight = max(10, $endY - $startY);
        
        // Réinitialiser à la position de départ
        $pdf->SetY($startY);
        
        // Colonne 1: Service
        $pdf->MultiCell($servCol1, $lineHeight, $serviceText, 1, 'L', $fill, 0);
        
        // Colonne 2: Quantité
        $pdf->SetY($startY);
        $pdf->SetX(15 + $servCol1);
        $pdf->MultiCell($servCol2, $lineHeight, number_format($service['qty'], 2, '.', ' '), 1, 'R', $fill, 0);
        
        // Colonne 3: Prix unitaire
        $pdf->SetY($startY);
        $pdf->SetX(15 + $servCol1 + $servCol2);
        $pdf->MultiCell($servCol3, $lineHeight, number_format($service['pu'], 2, '.', ' ') . ' ' . $conf->currency, 1, 'R', $fill, 0);
        
        // Colonne 4: Total
        $pdf->SetY($startY);
        $pdf->SetX(15 + $servCol1 + $servCol2 + $servCol3);
        $pdf->MultiCell($servCol4, $lineHeight, number_format($service['total'], 2, '.', ' ') . ' ' . $conf->currency, 1, 'R', $fill, 1);
        
        // Définir la position Y pour la ligne suivante
        $pdf->SetY($startY + $lineHeight);
        
        $fill = !$fill;
    }
    
    // Ligne de total pour les services
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetFillColor(76, 175, 80);
    $pdf->SetTextColor(255, 255, 255);
    
    $startY = $pdf->GetY();
    $totalHeight = 8;
    
    // Cellule "Total Services" (regroupe les 3 premières colonnes)
    $pdf->MultiCell($servCol1 + $servCol2 + $servCol3, $totalHeight, dol_html_entity_decode($langs->trans("TotalServices"), ENT_QUOTES, 'UTF-8'), 1, 'R', 1, 0);
    $pdf->SetY($startY);
    $pdf->SetX(15 + $servCol1 + $servCol2 + $servCol3);
    
    // Cellule avec le total des services
    $pdf->MultiCell($servCol4, $totalHeight, number_format($total_services_calc, 2, '.', ' ') . ' ' . $conf->currency, 1, 'R', 1, 1);
    
    $pdf->SetY($startY + $totalHeight);
    $pdf->Ln(10);
}

// ============================================================================
// 🔹 RÉSUMÉ DES CALCULS - SECTION BIEN ORGANISÉE
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 12);
$pdf->SetFillColor(138, 43, 226);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, dol_html_entity_decode($langs->trans("ResultatsSimulation"), ENT_QUOTES, 'UTF-8'), 0, 1, 'C', 1);
$pdf->Ln(5);

// Créer un tableau pour le résumé
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavusans', '', 10);

// Largeurs pour le tableau de résumé
$resumeLabelWidth = 100;
$resumeValueWidth = 70;

// Valeur produits
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell($resumeLabelWidth, 8, dol_html_entity_decode($langs->trans("ValeurProduits"), ENT_QUOTES, 'UTF-8') . ' :', 0, 0, 'L');
$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell($resumeValueWidth, 8, number_format($total_valeur_produits, 2, '.', ' ') . ' ' . $conf->currency, 0, 1, 'R');

$pdf->SetTextColor(0, 0, 0);

// Total services (si existant)
if ($total_services_calc > 0) {
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell($resumeLabelWidth, 8, dol_html_entity_decode($langs->trans("TotalServices"), ENT_QUOTES, 'UTF-8') . ' :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell($resumeValueWidth, 8, number_format($total_services_calc, 2, '.', ' ') . ' ' . $conf->currency, 0, 1, 'R');
}

// Sous-total
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell($resumeLabelWidth, 8, dol_html_entity_decode($langs->trans("SousTotal"), ENT_QUOTES, 'UTF-8') . ' :', 0, 0, 'L');
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell($resumeValueWidth, 8, number_format($sous_total, 2, '.', ' ') . ' ' . $conf->currency, 0, 1, 'R');

// Marge (si existante)
if ($marge_montant > 0) {
    $marge_label = ($marge_fixe > 0) 
        ? dol_html_entity_decode($langs->trans("MargeFixe"), ENT_QUOTES, 'UTF-8') . ' (' . number_format((float)($marge_fixe), 2, '.', ' ') . ' ' . $conf->currency . ')'
        : dol_html_entity_decode($langs->trans("MargePourcentage"), ENT_QUOTES, 'UTF-8') . ' (' . $marge_pourcentage . '%)';
    
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell($resumeLabelWidth, 8, $marge_label . ' :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell($resumeValueWidth, 8, number_format((float) $marge_montant, 2, '.', ' ') . ' ' . $conf->currency, 0, 1, 'R');
}

// Ligne de séparation
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

// Total général - Mise en valeur
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->SetTextColor(138, 43, 226);
$pdf->Cell($resumeLabelWidth, 10, dol_html_entity_decode($langs->trans("ValeurTotaleEstimee"), ENT_QUOTES, 'UTF-8') . ' :', 0, 0, 'L');
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->SetTextColor(67, 142, 204);
$pdf->Cell($resumeValueWidth, 10, number_format($total_general, 2, '.', ' ') . ' ' . $conf->currency, 0, 1, 'R');

// Valeur moyenne par carton - Mise en valeur
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->SetTextColor(138, 43, 226);
$pdf->Cell($resumeLabelWidth, 10, dol_html_entity_decode($langs->trans("ValeurCatonEstimee"), ENT_QUOTES, 'UTF-8') . ' :', 0, 0, 'L');
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->SetTextColor(67, 142, 204);
$pdf->Cell($resumeValueWidth, 10, number_format($total_general / ($total_cartons > 0 ? $total_cartons : 1), 2, '.', ' ') . ' ' . $conf->currency, 0, 1, 'R');

$pdf->Ln(15);

// Vérifier espace restant sur la page
if ($pdf->GetY() > 250) {
    $pdf->AddPage();
}

// ============================================================================
// 🔹 DÉTAILS TECHNIQUES (optionnel)
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, dol_html_entity_decode($langs->trans("DetailsTechniques"), ENT_QUOTES, 'UTF-8'), 0, 1, 'L', 1);
$pdf->Ln(3);

$pdf->SetFont('dejavusans', '', 9);
$details = [
    dol_html_entity_decode($langs->trans("TotalCartons"), ENT_QUOTES, 'UTF-8') . ' : ' . $total_cartons,
    dol_html_entity_decode($langs->trans("TotalPoids"), ENT_QUOTES, 'UTF-8') . ' : ' . price($total_poids) . ' kg',
    dol_html_entity_decode($langs->trans("NombreProduits"), ENT_QUOTES, 'UTF-8') . ' : ' . count($produits_details),
    dol_html_entity_decode($langs->trans("DateGeneration"), ENT_QUOTES, 'UTF-8') . ' : ' . dol_print_date(dol_now(), 'dayhourtext')
];

foreach ($details as $detail) {
    $pdf->Cell(0, 6, '• ' . $detail, 0, 1, 'L');
}

$pdf->Ln(10);



// ============================================================================
// 🔹 ZONE DE SIGNATURES
// ============================================================================
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(0, 8, dol_html_entity_decode($langs->trans("Signatures"), ENT_QUOTES, 'UTF-8'), 0, 1, 'L', 1);
$pdf->Ln(4);

$pdf->SetFont('dejavusans', '', 10);
$signatureWidth = 85;
$spacing = 20;

// Vérifier espace restant sur la page
if ($pdf->GetY() > 250) {
    $pdf->AddPage();
}

// Signature du préparateur
$pdf->Cell($signatureWidth, 8, dol_html_entity_decode($langs->trans("PreparationPar"), ENT_QUOTES, 'UTF-8') . ' : ___________________________', 0, 0, 'L');
$pdf->Cell($spacing, 8, '', 0, 0);
// Signature du client
$pdf->Cell($signatureWidth, 8, dol_html_entity_decode($langs->trans("Client"), ENT_QUOTES, 'UTF-8') . ' : ___________________________', 0, 1, 'L');

$pdf->Ln(15);


// ============================================================================
// 🔹 SORTIE PDF
// ============================================================================
$filename = 'Devis_Simulation_' . dol_sanitizeFileName($client->name) . '_' . date('Ymd-His') . '.pdf';
$pdf->Output($filename, 'I');

exit;