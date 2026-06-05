<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once 'note.class.php';

// Chargement des traductions
$langs->loadLangs(array("companies", "other", "note@custom/note"));

// Sécurité
if (!$user->rights->user->user->lire) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$toselect = GETPOST('toselect', 'array');

// Initialisation des objets
$form = new Form($db);
$note = new Note($db);


// Traitement de la suppression individuelle
if ($action == 'delete' && $user->rights->user->user->supprimer) {
    $id = GETPOST('id', 'int');
    if ($id > 0) {
        if ($note->delete($id)) {
            setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
        } else {
            setEventMessages($note->error, null, 'errors');
        }
    }
    // Redirection pour éviter la soumission multiple
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Traitement des actions de masse
if ($massaction && $user->rights->user->user->supprimer) {
    if ($massaction == 'delete') {
        $nbdelete = 0;
        if (!empty($toselect)) {
            foreach ($toselect as $id) {
                if ($note->delete($id)) {
                    $nbdelete++;
                }
            }
        }
        if ($nbdelete > 0) {
            setEventMessages($langs->trans("RecordsDeleted", $nbdelete), null, 'mesgs');
        }
    }
    
    // Redirection pour éviter la soumission multiple
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Récupération des notes
$notes = $note->fetchAll('datec', 'DESC');

// Titre
llxHeader('', $langs->trans("Notes"));

// Formulaire de filtrage et actions
print '<div class="tabsAction">';
print '<a class="butAction" href="' . dol_buildpath('/custom/note/card.php', 1) . '">' . $langs->trans("AAjouter une note") . '</a>';
print '<a class="butAction" href="' . dol_buildpath('/custom/note/note_pdf.php', 1) . '">' . $langs->trans("Imprimer les notes") . ' ' . img_picto('', 'pdf') . '</a>';
print '</div>';

// Affichage de la liste
if ($notes) {
    print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '" name="formmassaction" id="formmassaction">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="massaction" value="">';
    
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td class="liste_titre" width="20"><input type="checkbox" id="selectall" onclick="selectAll()"></td>';
    print '<td class="liste_titre">' . $langs->trans("Libelle") . '</td>';
    print '<td class="liste_titre">' . $langs->trans("Note") . '</td>';
    print '<td class="liste_titre">' . $langs->trans("Date") . '</td>';
    print '<td class="liste_titre">' . $langs->trans("User") . '</td>';
    print '<td class="liste_titre" width="80">' . $langs->trans("Actions") . '</td>';
    print '</tr>';
    
    $var = true;
    foreach ($notes as $note_data) {
        $var = !$var;
        print '<tr ' . ($var ? 'class="oddeven"' : 'class="oddeven"') . '>';
        print '<td><input type="checkbox" name="toselect[]" value="' . $note_data->rowid . '"></td>';
        print '<td><a href="card.php?id=' . $note_data->rowid . '">' . dol_escape_htmltag($note_data->libelle) . '</a></td>';
        print '<td>' . dol_trunc($note_data->note, 50) . '</td>';
        print '<td>' . dol_print_date($db->jdate($note_data->datec), 'dayhour') . '</td>';
        print '<td>' . dolGetFirstLastname($note_data->firstname, $note_data->lastname) . '</td>';
        print '<td class="nowrap">';
        print '<a href="card.php?id=' . $note_data->rowid . '">' . img_edit() . '</a>';
        if ($user->rights->user->user->supprimer) {
            print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '" style="display:inline-block; margin:0; padding:0;" onsubmit="return confirm(\'' . $langs->trans("ConfirmDelete") . '\');">';
            print '<input type="hidden" name="token" value="' . newToken() . '">';
            print '<input type="hidden" name="action" value="delete">';
            print '<input type="hidden" name="id" value="' . $note_data->rowid . '">';
            print '<button type="submit" style="border:none; background:none; cursor:pointer; padding:0; margin-left:5px;">' . img_delete() . '</button>';
            print '</form>';
        }
        print '</td>';
        print '</tr>';
    }
    
    print '</table>';
    
    // Actions de masse
    if ($user->rights->user->user->supprimer && count($notes) > 0) {
        print '<div class="tblmassaction">';
        print $langs->trans("WithSelectedLines") . ': ';
        print '<select name="massaction" class="flat" onchange="if (this.value != 0) doMassaction(this.value);">';
        print '<option value="0">' . $langs->trans("DoNothing") . '</option>';
        print '<option value="delete">' . $langs->trans("Delete") . '</option>';
        print '</select>';
        print '</div>';
    }
    
    print '</form>';
} else {
    print '<div class="info">' . $langs->trans("NoRecordsFound") . '</div>';
}

// JavaScript pour la sélection multiple
print '<script type="text/javascript">
function selectAll() {
    var checkboxes = document.getElementsByName("toselect[]");
    var selectall = document.getElementById("selectall");
    if (selectall) {
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = selectall.checked;
        }
    }
}

function doMassaction(action) {
    if (action == "delete") {
        if (confirm("' . $langs->trans("ConfirmDeleteSelected") . '")) {
            var form = document.getElementById("formmassaction");
            if (form) {
                form.massaction.value = action;
                form.submit();
            }
        }
    }
    return false;
}

// Vérifier qu\'au moins un élément est sélectionné avant l\'action de masse
document.getElementById("formmassaction")?.addEventListener("submit", function(e) {
    var massaction = this.massaction.value;
    if (massaction != "0") {
        var checkboxes = document.getElementsByName("toselect[]");
        var checked = false;
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                checked = true;
                break;
            }
        }
        if (!checked) {
            e.preventDefault();
            alert("' . $langs->trans("NoLineSelected") . '");
        }
    }
});
</script>';

llxFooter();
$db->close();