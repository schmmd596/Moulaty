<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;

$langs->load("main");

$form = new Form($db);

// === Vérifier si table existe ===
$db->query("CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."user_entrepot (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_user INT NOT NULL,
    fk_entrepot INT NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    can_edit TINYINT(1) DEFAULT 0,
    can_view TINYINT(1) DEFAULT 1,
    entity INT DEFAULT 1,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE(fk_user, fk_entrepot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// === Actions ===
$action = GETPOST('action', 'alpha');

if ($action == 'add' && GETPOST('fk_user', 'int') && GETPOST('fk_entrepot', 'int')) {
    $fk_user = (int) GETPOST('fk_user');
    $fk_entrepot = (int) GETPOST('fk_entrepot');
    $role = $db->escape(GETPOST('role', 'alpha'));

    $sql = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."user_entrepot(fk_user, fk_entrepot, role)
            VALUES ($fk_user, $fk_entrepot, '".$role."')";
    $res = $db->query($sql);
    if ($res) setEventMessages("Relation ajoutée avec succès.", null, 'mesgs');
    else setEventMessages("Erreur lors de l'ajout.", null, 'errors');
}

if ($action == 'delete' && GETPOST('id', 'int')) {
    $id = (int) GETPOST('id');
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."user_entrepot WHERE rowid = ".$id;
    $res = $db->query($sql);
    if ($res) setEventMessages("Relation supprimée.", null, 'mesgs');
    else setEventMessages("Erreur de suppression.", null, 'errors');
}

if ($action == 'update' && GETPOST('id', 'int')) {
    $id = (int) GETPOST('id');
    $role = $db->escape(GETPOST('role', 'alpha'));
    $sql = "UPDATE ".MAIN_DB_PREFIX."user_entrepot SET role = '".$role."' WHERE rowid = ".$id;
    $res = $db->query($sql);
    if ($res) setEventMessages("Rôle mis à jour.", null, 'mesgs');
    else setEventMessages("Erreur de mise à jour.", null, 'errors');
}

// === Header ===
llxHeader('', 'Gestion des rôles par entrepôt');

print '<h2><i class="fa fa-warehouse"></i> Gestion des utilisateurs par entrepôt</h2>';

// === Formulaire d’ajout ===
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">
    <input type="hidden" name="token" value="'.newToken().'">';

print '<input type="hidden" name="action" value="add">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Utilisateur</th><th>Entrepôt</th><th>Rôle</th><th>Action</th></tr>';
print '<tr>';

print '<td>'.$form->select_dolusers('', 'fk_user', 1, null, 0, '', '', 0, 0, 0, '', '', [], '', 'minwidth200').'</td>';

print '<td>';
$sql_ent = "SELECT rowid, ref, lieu FROM ".MAIN_DB_PREFIX."entrepot WHERE entity = ".$conf->entity;
$res_ent = $db->query($sql_ent);
print '<select name="fk_entrepot" class="flat minwidth150">';
while ($obj = $db->fetch_object($res_ent)) {
    print '<option value="'.$obj->rowid.'">'.$obj->ref.' ('.$obj->lieu.')</option>';
}
print '</select>';
print '</td>';

print '<td><input type="text" name="role" placeholder="Ex: superviseur" class="flat minwidth100"></td>';
print '<td><input type="submit" class="butAction" value="Ajouter"></td>';

print '</tr></table>';
print '</form><br>';

// === Liste des relations ===
$sql = "SELECT ue.rowid, u.login, u.lastname, u.firstname, e.ref as entrepot_ref, e.lieu, ue.role
        FROM ".MAIN_DB_PREFIX."user_entrepot ue
        LEFT JOIN ".MAIN_DB_PREFIX."user u ON ue.fk_user = u.rowid
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON ue.fk_entrepot = e.rowid
        WHERE ue.entity = ".$conf->entity."
        ORDER BY e.ref, u.login";

$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    print '<table class="liste centpercent">';
    print '<tr class="liste_titre">';
    print '<th><i class="fa fa-user"></i> Utilisateur</th>';
    print '<th><i class="fa fa-warehouse"></i> Entrepôt</th>';
    print '<th><i class="fa fa-id-badge"></i> Rôle</th>';
    print '<th class="center"><i class="fa fa-cogs"></i> Actions</th>';
    print '</tr>';

    while ($obj = $db->fetch_object($resql)) {
        print '<tr>';
        print '<td>'.dol_escape_htmltag($obj->firstname.' '.$obj->lastname.' ('.$obj->login.')').'</td>';
        print '<td>'.dol_escape_htmltag($obj->entrepot_ref).' - '.dol_escape_htmltag($obj->lieu).'</td>';
        
        print '<td>
                <form method="POST" action="'.$_SERVER["PHP_SELF"].'">
                <input type="hidden" name="token" value="'.newToken().'">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="'.$obj->rowid.'">
                    <input type="text" name="role" value="'.dol_escape_htmltag($obj->role).'" class="flat">
                    <input type="submit" class="button small" value="💾">
                </form>
               </td>';

        print '<td class="center">
                <a href="'.$_SERVER["PHP_SELF"].'?action=delete&id='.$obj->rowid.'&token='.newToken().'" 
                   onclick="return confirm(\'Supprimer cette relation ?\')" 
                   class="butActionDelete">🗑️ Supprimer</a>
               </td>';
        print '</tr>';
    }

    print '</table>';
} else {
    print '<p>Aucune relation trouvée.</p>';
}

llxFooter();
$db->close();
?>
