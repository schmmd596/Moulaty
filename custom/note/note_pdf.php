<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once 'note.class.php';

// Chargement des traductions
$langs->loadLangs(array("companies", "other", "main"));

// Sécurité
if (!$user->rights->user->user->lire) {
    accessforbidden();
}

// Récupération des paramètres
$note_ids = GETPOST('note_ids', 'array');
$action = GETPOST('action', 'aZ09');
$token = GETPOST('token', 'alpha');

// Vérification du token CSRF (méthode Dolibarr)
if ($action == 'generate_pdf') {
    if (!isset($_SESSION['newtoken']) || $token != $_SESSION['newtoken']) {
        $langs->load("errors");
        print $langs->trans("InvalidToken");
        exit;
    }
}

// Si c'est une demande de génération de PDF
if ($action == 'generate_pdf' && !empty($note_ids)) {
    generatePDF($note_ids, $db, $user, $langs, $mysoc);
    exit;
}

// Affichage de la page de sélection
llxHeader('', $langs->trans("PrintNotes"));

// Formulaire de sélection
print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="generate_pdf">';

print '<div class="tabBar">';
print '<table class="border" width="100%">';

// Titre
print '<tr>';
print '<td colspan="2" class="title">' . $langs->trans("Sélectionner les notes à imprimer") . '</td>';
print '</tr>';

// Récupération de toutes les notes
$note = new Note($db);
$notes = $note->fetchAll('datec', 'DESC');

if ($notes) {
    // Options d'impression
    print '<tr>';
    print '<td width="20%">' . $langs->trans("Options d'impression") . '</td>';
    print '<td>';
    print '<input type="checkbox" name="print_header" id="print_header" value="1" checked> <label for="print_header">' . $langs->trans("Imprimer l'en-tête") . '</label><br>';
    print '<input type="checkbox" name="print_date" id="print_date" value="1" checked> <label for="print_date">' . $langs->trans("Imprimer la date") . '</label><br>';
    print '<input type="checkbox" name="print_user" id="print_user" value="1" checked> <label for="print_user">' . $langs->trans("Imprimer l'utilisateur") . '</label><br>';
    print '<input type="checkbox" name="new_page_per_note" id="new_page_per_note" value="1"> <label for="new_page_per_note">' . $langs->trans("Nouvelle page par note") . '</label>';
    print '</td>';
    print '</tr>';
    
    // Liste des notes
    print '<tr>';
    print '<td>' . $langs->trans("Sélectionner les notes") . '</td>';
    print '<td>';
    print '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;">';
    
    // Boutons de sélection
    print '<div style="margin-bottom: 10px;">';
    print '<button type="button" class="button" onclick="selectAll(true)">' . $langs->trans("Tout sélectionner") . '</button> ';
    print '<button type="button" class="button" onclick="selectAll(false)">' . $langs->trans("Tout désélectionner") . '</button>';
    print '</div>';
    
    // Liste des notes avec cases à cocher
    foreach ($notes as $note_data) {
        print '<div style="margin: 5px 0;">';
        print '<input type="checkbox" name="note_ids[]" id="note_' . $note_data->rowid . '" value="' . $note_data->rowid . '"> ';
        print '<label for="note_' . $note_data->rowid . '">';
        print '<strong>' . dol_escape_htmltag($note_data->libelle) . '</strong> - ';
        print dol_print_date($db->jdate($note_data->datec), 'day') . ' - ';
        print dolGetFirstLastname($note_data->firstname, $note_data->lastname);
        print '</label>';
        print '</div>';
    }
    
    print '</div>';
    print '</td>';
    print '</tr>';
    
    // Boutons
    print '<tr>';
    print '<td colspan="2" class="center">';
    print '<input type="submit" class="button" value="' . $langs->trans("Générer le PDF") . '"> ';
    print '<input type="button" class="button" value="' . $langs->trans("Annuler") . '" onclick="window.location.href=\'index.php\'">';
    print '</td>';
    print '</tr>';
} else {
    print '<tr>';
    print '<td colspan="2" class="center">' . $langs->trans("Aucun enregistrement trouvé") . '</td>';
    print '</tr>';
    print '<tr>';
    print '<td colspan="2" class="center">';
    print '<input type="button" class="button" value="' . $langs->trans("Retour") . '" onclick="window.location.href=\'index.php\'">';
    print '</td>';
    print '</tr>';
}

print '</table>';
print '</div>';
print '</form>';

// JavaScript pour la sélection multiple
print '<script>
function selectAll(select) {
    var checkboxes = document.getElementsByName("note_ids[]");
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = select;
    }
}
</script>';

llxFooter();
$db->close();

/**
 * Fonction pour générer le PDF
 */
function generatePDF($note_ids, $db, $user, $langs, $mysoc) {
    global $conf;
    
    // Récupération des options
    $print_header = GETPOST('print_header', 'int');
    $print_date = GETPOST('print_date', 'int');
    $print_user = GETPOST('print_user', 'int');
    $new_page_per_note = GETPOST('new_page_per_note', 'int');
    
    // Récupération des notes
    $notes = array();
    $note_obj = new Note($db);
    
    foreach ($note_ids as $id) {
        if ($note_obj->fetch($id)) {
            // Récupérer les informations de l'utilisateur
            $user_obj = new User($db);
            $user_obj->fetch($note_obj->user);
            $note_obj->user_name = dolGetFirstLastname($user_obj->firstname, $user_obj->lastname);
            $notes[] = clone $note_obj;
        }
    }
    
    if (empty($notes)) {
        return false;
    }
    
    // CORRECTION: Essayer plusieurs chemins pour TCPDF
    $tcpdf_found = false;
    $tcpdf_paths = array(
        DOL_DOCUMENT_ROOT.'/vendor/tecnickcom/tcpdf/tcpdf.php',
        DOL_DOCUMENT_ROOT.'/includes/tcpdf/tcpdf.php',
        DOL_DOCUMENT_ROOT.'/tcpdf/tcpdf.php',
        DOL_DOCUMENT_ROOT.'/core/class/tcpdf.class.php'
    );
    
    foreach ($tcpdf_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $tcpdf_found = true;
            break;
        }
    }
    
    if (!$tcpdf_found) {
        // Si TCPDF n'est pas trouvé, utiliser la méthode alternative avec pdf_getInstance()
        $pdf = pdf_getInstance();
        if (!$pdf) {
            print "Erreur: Bibliothèque PDF non trouvée";
            exit;
        }
    } else {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    }
    
    // Si on a utilisé pdf_getInstance(), on configure différemment
    if (!$tcpdf_found) {
        $pdf->SetCreator('Dolibarr');
        $pdf->SetAuthor($mysoc->name);
        $pdf->SetTitle($langs->trans("Notes") . ' - ' . dol_print_date(dol_now(), 'day'));
        $pdf->SetSubject($langs->trans("Notes"));
        $pdf->SetKeywords($langs->trans("Notes"));
    }
    
    // Métadonnées du document (si TCPDF direct)
    if ($tcpdf_found) {
        $pdf->SetCreator('Dolibarr');
        $pdf->SetAuthor($mysoc->name);
        $pdf->SetTitle($langs->trans("Notes") . ' - ' . dol_print_date(dol_now(), 'day'));
        $pdf->SetSubject($langs->trans("Notes"));
        $pdf->SetKeywords($langs->trans("Notes"));
    }
    
    // En-tête et pied de page
    if ($print_header) {
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        
        // Définir l'en-tête
        $pdf->SetHeaderData('', 0, $mysoc->name, $mysoc->tva_intra . "\n" . $mysoc->address . ' ' . $mysoc->zip . ' ' . $mysoc->town, array(0,0,0), array(0,0,0));
    } else {
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
    }
    
    // Marges
    $pdf->SetMargins(15, $print_header ? 20 : 10, 15);
    $pdf->SetAutoPageBreak(true, 15);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Titre du document
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $langs->trans("Notes") . ' - ' . dol_print_date(dol_now(), 'dayhour'), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Compteur de notes
    $nb_notes = count($notes);
    
    foreach ($notes as $index => $note) {
        // Nouvelle page si demandé
        if ($new_page_per_note && $index > 0) {
            $pdf->AddPage();
        }
        
        // Cadre pour chaque note
        $pdf->SetFillColor(245, 245, 245);
        
        // Libellé de la note
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, $note->libelle, 0, 1, 'L', true);
        
        // Informations complémentaires
        if ($print_date || $print_user) {
            $pdf->SetFont('helvetica', '', 9);
            $info = '';
            if ($print_date) {
                $info .= $langs->trans("Date") . ' : ' . dol_print_date($note->datec, 'dayhour');
            }
            if ($print_user) {
                if (!empty($info)) $info .= ' - ';
                $info .= $langs->trans("Créé par") . ' : ' . $note->user_name;
            }
            $pdf->Cell(0, 6, $info, 0, 1, 'L');
        }
        
        $pdf->Ln(2);
        
        // Contenu de la note
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetFillColor(255, 255, 255);
        
        // Gestion du texte (peut contenir du HTML)
        $note_content = $note->note;
        
        // Vérifier si le contenu est du HTML
        if (strip_tags($note_content) != $note_content) {
            // Contenu HTML, utiliser writeHTML
            $pdf->writeHTML($note_content, true, false, true, false, '');
        } else {
            // Texte simple, utiliser MultiCell
            $pdf->MultiCell(0, 6, $note_content, 0, 'L', false, 1, '', '', true, 0, false, true, 0);
        }
        
        // Ligne de séparation entre les notes
        if (!$new_page_per_note && $index < $nb_notes - 1) {
            $pdf->Ln(5);
            $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 180, $pdf->GetY());
            $pdf->Ln(5);
        }
    }
    
    // Sortie du PDF
    $pdf->Output('notes_' . dol_print_date(dol_now(), '%Y%m%d_%H%M%S') . '.pdf', 'I');
}