<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once 'note.class.php';

// Chargement des traductions
$langs->loadLangs(array("companies", "other"));

// Sécurité
if (!$user->rights->user->user->lire) {
    accessforbidden();
}

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

// Initialisation des objets
$form = new Form($db);
$note = new Note($db);

// Traitement du formulaire
if ($action == 'add' || $action == 'update') {
    if (!GETPOST('cancel', 'alpha')) {
        $note->libelle = GETPOST('libelle', 'alpha');
        $note->note = GETPOST('note', 'restricthtml');
        
        if ($action == 'add') {
            $note->datec = dol_now();
            $note->user = $user->id;
            
            $result = $note->create();
            if ($result > 0) {
                setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
                header("Location: " . dol_buildpath('/custom/note/index.php', 1));
                exit;
            } else {
                setEventMessages($note->error, null, 'errors');
            }
        } elseif ($action == 'update') {
            $note->rowid = $id;
            $result = $note->update();
            if ($result) {
                setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
                header("Location: " . dol_buildpath('/custom/note/index.php', 1));
                exit;
            } else {
                setEventMessages($note->error, null, 'errors');
            }
        }
    } else {
        header("Location: " . dol_buildpath('/custom/note/index.php', 1));
        exit;
    }
}

// Chargement des données pour édition
if ($id > 0 && $action != 'add') {
    $note->fetch($id);
    $action = 'edit';
}

// Titre
llxHeader('', $langs->trans("Note"));

// Formulaire
print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="' . ($id > 0 ? 'update' : 'add') . '">';
if ($id > 0) {
    print '<input type="hidden" name="id" value="' . $id . '">';
}

print '<table class="border" width="100%">';

// Libellé
print '<tr>';
print '<td class="fieldrequired">' . $langs->trans("Libelle") . '</td>';
print '<td><input type="text" name="libelle" value="' . dol_escape_htmltag($note->libelle) . '" class="minwidth300" required></td>';
print '</tr>';

// Note
print '<tr>';
print '<td class="fieldrequired">' . $langs->trans("Note") . '</td>';
print '<td>';
print '<textarea name="note" rows="10" cols="70" class="quatrevingtpercent">' . dol_escape_htmltag($note->note) . '</textarea>';
print '</td>';
print '</tr>';

// Date de création (affichage seulement)
if ($note->datec) {
    print '<tr>';
    print '<td>' . $langs->trans("DateCreation") . '</td>';
    print '<td>' . dol_print_date($note->datec, 'dayhour') . '</td>';
    print '</tr>';
}

// Utilisateur (affichage seulement)
if ($note->user) {
    print '<tr>';
    print '<td>' . $langs->trans("CreatedBy") . '</td>';
    print '<td>' . $user->getNomUrl($note->user) . '</td>';
    print '</tr>';
}

print '</table>';

// Boutons
print '<div class="center">';
print '<input type="submit" class="button" value="' . $langs->trans("Save") . '">';
print '&nbsp;&nbsp;&nbsp;';
print '<input type="submit" name="cancel" class="button" value="' . $langs->trans("Cancel") . '">';
print '</div>';

print '</form>';

// Pied de page
llxFooter();
$db->close();