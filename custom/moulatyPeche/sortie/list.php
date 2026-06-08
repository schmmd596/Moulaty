<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $langs, $conf, $user;

$langs->load("stocks");
$form = new Form($db);

$entrepots_accessibles = [];
$sql_ent = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot WHERE fk_user = ".((int)$user->id);
$res_ent = $db->query($sql_ent);
if ($res_ent && $db->num_rows($res_ent) > 0) {
    while ($obj_ent = $db->fetch_object($res_ent)) {
        $entrepots_accessibles[] = (int)$obj_ent->fk_entrepot;
    }
}

// -----------------------------------------------------------------------------
// FILTRES
// -----------------------------------------------------------------------------
$search_ref = GETPOST('search_ref', 'alpha');
$search_type = GETPOST('search_type', 'int');
$search_entrepot = GETPOST('search_entrepot', 'int');
$is_regroupement = GETPOST('regroupement', 'int');
$search_date_start = GETPOST('search_date_start', 'alpha');
$search_date_end = GETPOST('search_date_end', 'alpha');
$limit = GETPOST('limit', 'int') ?: 25;
$page = GETPOST('page', 'int') ?: 0;
$offset = $limit * $page;

// -----------------------------------------------------------------------------
// Types de sortie
$types = array(
    0 => $langs->trans("TransfertInterne"),
    1 => $langs->trans("Vente")
);

// -----------------------------------------------------------------------------
// Construire la requête SQL
if ($is_regroupement) {
    $sql = "SELECT bs.rowid AS rowid_bon, bs.ref AS ref, s.type, bs.date_creation, s.fk_client, 
                   s.fk_entrepot_dest, SUM(s.poids_total) AS poids_total, 
                   SUM(s.nb_carton_total) AS nb_carton_total, bs.statut, 
                   GROUP_CONCAT(DISTINCT e.ref SEPARATOR ' + ') AS entrepot_sources
            FROM ".MAIN_DB_PREFIX."pech_bonsortie AS bs
            INNER JOIN ".MAIN_DB_PREFIX."pech_sortie AS s ON s.fk_bonsortie = bs.rowid
            LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = s.fk_entrepot_source
            WHERE s.entity = ".((int)$conf->entity)." AND s.is_regroupe = 1";
    
    if (!empty($search_ref)) {
        $sql .= " AND bs.ref LIKE '%".$db->escape($search_ref)."%'";
    }
    if ($search_type >= 0 && $search_type !== null) {
        $sql .= " AND s.type = ".((int)$search_type);
    }
    if (!empty($search_date_start)) {
        $ts_start = dol_stringtotime($search_date_start.' 00:00:00');
        if ($ts_start !== false) $sql .= " AND bs.date_creation >= '".$db->idate($ts_start)."'";
    }
    if (!empty($search_date_end)) {
        $ts_end = dol_stringtotime($search_date_end.' 23:59:59');
        if ($ts_end !== false) $sql .= " AND bs.date_creation <= '".$db->idate($ts_end)."'";
    }
    
    $sql .= " GROUP BY bs.rowid, bs.ref, s.type, bs.date_creation, s.fk_client, s.fk_entrepot_dest, bs.statut";
    $sql .= " ORDER BY bs.date_creation DESC ".$db->plimit($limit + 1, $offset);
} else {
    $sql = "SELECT s.rowid, s.ref, s.type, s.date_creation, s.fk_client, s.fk_entrepot_source, 
                   s.fk_entrepot_dest, s.poids_total, s.nb_carton_total, s.statut, s.commentaire, s.total_frais
            FROM ".MAIN_DB_PREFIX."pech_sortie AS s
            WHERE s.entity = ".((int)$conf->entity)." AND s.is_regroupe = 0";

    if (!empty($entrepots_accessibles)) {
        $sql .= " AND s.fk_entrepot_source IN (".implode(',', $entrepots_accessibles).")";
    }
    if (!empty($search_ref)) {
        $sql .= " AND s.ref LIKE '%".$db->escape($search_ref)."%'";
    }
    if ($search_type >= 0 && $search_type !== null) {
        $sql .= " AND s.type = ".((int)$search_type);
    }
    if (!empty($search_entrepot) && $search_entrepot > 0) {
        $sql .= " AND s.fk_entrepot_source = ".((int)$search_entrepot);
    }
    if (!empty($search_date_start)) {
        $ts_start = dol_stringtotime($search_date_start.' 00:00:00');
        if ($ts_start !== false) $sql .= " AND s.date_creation >= '".$db->idate($ts_start)."'";
    }
    if (!empty($search_date_end)) {
        $ts_end = dol_stringtotime($search_date_end.' 23:59:59');
        if ($ts_end !== false) $sql .= " AND s.date_creation <= '".$db->idate($ts_end)."'";
    }

    $sql .= " ORDER BY s.date_creation DESC ".$db->plimit($limit + 1, $offset);
}
$resql = $db->query($sql);

// -----------------------------------------------------------------------------
// HEADER
$page_title = $is_regroupement ? $langs->trans("RegroupementSortie") : $langs->trans("ListeBonsSortie");
llxHeader('', $page_title);

// Inclusion du CSS global pour les listes
print '<link rel="stylesheet" href="../reception_list_style.css">';
// Ajoutez ce CSS dans votre header
echo '
<style>
.badge-type {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    color: white;
    font-weight: 600;
    font-size: 12px;
    text-align: center;
    min-width: 80px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.badge-entrepot {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: #e8f4fc;
    border: 1px solid #3498db;
    border-radius: 8px;
    color: #2c3e50;
    font-weight: 500;
    font-size: 13px;
    transition: all 0.2s;
}

.badge-entrepot:hover {
    background: #d6eaf8;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(52, 152, 219, 0.2);
}

.badge-destination {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: #fef9e7;
    border: 1px solid #f39c12;
    border-radius: 8px;
    color: #2c3e50;
    font-weight: 500;
    font-size: 13px;
    transition: all 0.2s;
}

.badge-destination:hover {
    background: #fcf3cf;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(243, 156, 18, 0.2);
}

.badge-empty {
    display: inline-block;
    padding: 6px 12px;
    background: #f8f9fa;
    border: 1px dashed #dee2e6;
    border-radius: 8px;
    color: #6c757d;
    font-style: italic;
    font-size: 13px;
}

.badge-cartons {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    background: linear-gradient(135deg, #f3e5f5, #e1bee7);
    border: 1px solid #9b59b6;
    border-radius: 8px;
    color: #4a235a;
    font-weight: 600;
    font-size: 14px;
    box-shadow: 0 2px 4px rgba(155, 89, 182, 0.1);
}

.badge-cartons strong {
    margin-left: 4px;
    font-size: 15px;
}

.badge-status {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 12px;
    text-align: center;
    min-width: 100px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Responsive */
@media (max-width: 1200px) {
    .badge-type, .badge-status {
        min-width: 70px;
        font-size: 11px;
        padding: 5px 10px;
    }
    
    .badge-entrepot, .badge-destination, .badge-cartons, .badge-empty {
        padding: 5px 10px;
        font-size: 12px;
    }
}

@media (max-width: 768px) {
    .badge-type, .badge-status {
        min-width: 60px;
        font-size: 10px;
        padding: 4px 8px;
    }
    
    .badge-entrepot, .badge-destination, .badge-cartons, .badge-empty {
        padding: 4px 8px;
        font-size: 11px;
    }
}
</style>';
print '<div class="reception-list-container">';
print '<div class="reception-list-header">';
print load_fiche_titre('<i class="fa fa-box"></i> '.$page_title, '', 'object_list');
print '</div>';

// -----------------------------------------------------------------------------
// FORMULAIRE DE RECHERCHE
print '<form method="GET" class="search-form">';
print '<table class="search-table">';
print '<tr>';
print '<th>'.$langs->trans("Reference").'</th>';
print '<th>'.$langs->trans("Type").'</th>';
if (!$is_regroupement) print '<th>'.$langs->trans("EntrepotSource").'</th>';
print '<th>'.$langs->trans("DateDebut").'</th>';
print '<th>'.$langs->trans("DateFin").'</th>';
print '<th>'.$langs->trans("LignesPage").'</th>';
print '<th></th>';
print '</tr><tr>';

// Référence
print '<td><input type="text" name="search_ref" class="search-input" value="'.dol_escape_htmltag($search_ref).'" placeholder="'.$langs->trans("RechercherReference").'"></td>';

// Type
print '<td>'.$form->selectarray('search_type', $types, $search_type, 1, 0, 0, '', 0, 0, 0, 'class="search-select"').'</td>';

// Entrepôts
$entrepots = array('' => $langs->trans("Tous"));
$resEntrepot = $db->query("SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot ORDER BY ref ASC");
if ($resEntrepot) {
    while ($objEnt = $db->fetch_object($resEntrepot)) {
        $entrepots[$objEnt->rowid] = $objEnt->ref;
    }
}
if (!$is_regroupement) print '<td>'.$form->selectarray('search_entrepot', $entrepots, $search_entrepot, 1, 0, 0, '', 0, 0, 0, 'class="search-select"').'</td>';

// Dates
print '<td><input type="date" name="search_date_start" class="search-input" value="'.dol_escape_htmltag($search_date_start).'"></td>';
print '<td><input type="date" name="search_date_end" class="search-input" value="'.dol_escape_htmltag($search_date_end).'"></td>';

// Limite
$limit_array = array(10=>10, 25=>25, 50=>50, 100=>100);
print '<td>'.$form->selectarray('limit', $limit_array, $limit, 0, 0, 0, '', 0, 0, 0, 'class="search-select"').'</td>';

// Bouton rechercher
print '<td><input type="submit" class="search-button" value="'.$langs->trans("Rechercher").'"></td>';
print '</tr></table>';
print '</form>';

// -----------------------------------------------------------------------------
// AFFICHAGE DES RÉSULTATS
if ($resql) {
    $num = $db->num_rows($resql);
    $i = 0;

    if ($num > 0) {
        if (!$is_regroupement) {
            print '<form method="post" action="regrouper.php" id="formRegrouper">';
            print '<input type="hidden" name="token" value="'.newToken().'">';
            
            // Bouton de regroupement en haut
            print '<div class="group-action-bar" id="group_action_container" style="display:none; background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">';
            print '<span style="font-weight: 600; color: #1e293b;"><i class="fa fa-info-circle" style="color:#3b82f6; margin-right:8px;"></i>Sélectionnez les sorties du même client ou même entrepôt pour les regrouper.</span>';
            print '<button type="submit" class="search-button" style="margin:0; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color:white;"><i class="fa fa-object-group" style="margin-right:8px;"></i>Regrouper et confirmer</button>';
            print '</div>';
        }

        print '<div class="results-table-container">';
        print '<table class="results-table">';
        print '<thead><tr>';
        if (!$is_regroupement) {
            print '<th class="col-checkbox" style="width: 40px; text-align:center;"><input type="checkbox" id="check_all_sorties" onclick="toggleAllSorties(this)"></th>';
        }
        print '<th class="col-ref"><i class="fas fa-barcode"></i> '.$langs->trans("Reference").'</th>';
        print '<th class="col-type"><i class="fas fa-boxes"></i> '.$langs->trans("Type").'</th>';
        print '<th class="col-fournisseur"><i class="fas fa-warehouse"></i> '.$langs->trans("EntrepotSource").'</th>';
        print '<th class="col-destination"><i class="fas fa-user"></i> '.$langs->trans("ClientDestination").'</th>';
        print '<th class="col-date"><i class="fas fa-calendar-alt"></i> '.$langs->trans("DateCreation").'</th>';
        print '<th class="col-poids"><i class="fas fa-weight-hanging"></i> '.$langs->trans("PoidsNet").' (kg)</th>';
        print '<th class="col-cartons"><i class="fas fa-cube"></i> '.$langs->trans("Cartons").'</th>';
        print '<th class="col-frais"><i class="fas fa-money-bill-wave"></i> '.$langs->trans("Frais").' '.$conf->currency.'</th>';
        print '<th class="col-etat"><i class="fas fa-info-circle"></i> '.$langs->trans("Statut").'</th>';
        print '<th class="col-actions"><i class="fas fa-tools"></i> '.$langs->trans("Actions").'</th>';
        print '</tr></thead>';
        print '<tbody>';

        while ($i < min($num, $limit)) {
            $obj = $db->fetch_object($resql);
            $i++;

            $typeLabel = (isset($types[$obj->type]) ? $types[$obj->type] : '—');

            // Statuts avec traductions
            switch ($obj->statut) {
                case 0:
                    $statutLabel = '<span class="badge badge-warning">'.$langs->trans("Brouillon").'</span>';
                    break;
                case 1:
                    $statutLabel = '<span class="badge badge-success">'.$langs->trans("Valide").'</span>';
                    break;
                case 2:
                    $statutLabel = '<span class="badge badge-danger">'.$langs->trans("Transfere").'</span>';
                    break;
                default:
                    $statutLabel = '<span class="badge">'.$langs->trans("Inconnu").'</span>';
                    break;
            }

            $entrepotSrc = $entrepotDst = '';
            if ($is_regroupement) {
                $entrepotSrc = $obj->entrepot_sources;
            } else {
                if ($obj->fk_entrepot_source) {
                    $ent = new Entrepot($db);
                    if ($ent->fetch($obj->fk_entrepot_source) > 0) $entrepotSrc = $ent->ref;
                }
            }
            if ($obj->fk_entrepot_dest) {
                $ent = new Entrepot($db);
                if ($ent->fetch($obj->fk_entrepot_dest) > 0) $entrepotDst = $ent->ref;
            } else {
                $ent = new Societe($db);
                if ($ent->fetch($obj->fk_client) > 0) $entrepotDst = $ent->nom;
            }

            print '<tr>';
            if (!$is_regroupement) {
                print '<td class="col-checkbox" style="text-align:center;"><input type="checkbox" name="selected_sorties[]" value="'.$obj->rowid.'" data-client="'.(int)$obj->fk_client.'" data-dest="'.(int)$obj->fk_entrepot_dest.'" data-type="'.(int)$obj->type.'" class="sortie-checkbox"></td>';
            }
            if ($is_regroupement) {
                print '<td class="col-ref"><a href="bonsortie_document.php?id='.$obj->rowid_bon.'" style="color:#3498db; font-weight:500;">'.dol_escape_htmltag($obj->ref).'</a></td>';
            } else {
                print '<td class="col-ref"><a href="detail.php?id='.$obj->rowid.'" style="color:#3498db; font-weight:500;">'.dol_escape_htmltag($obj->ref).'</a></td>';
            }
            print '<td class="col-type">'.$typeLabel.'</td>';
            // Colonne Entrepôt source avec span stylé
            print '<td class="col-fournisseur">';
            print '<span class="badge-entrepot">';
            print '<i class="fa fa-warehouse" style="color:#3498db; margin-right:6px;"></i>';
            print dol_escape_htmltag($entrepotSrc);
            print '</span>';
            print '</td>';

            // Colonne Destination avec span stylé
            print '<td class="col-destination">';
            if (!empty($entrepotDst)) {
                print '<span class="badge-destination">';
                print '<i class="fa fa-truck-moving" style="color:#e67e22; margin-right:6px;"></i>';
                print dol_escape_htmltag($entrepotDst);
                print '</span>';
            } else {
                print '<span class="badge-empty">-</span>';
            }
            print '</td>';

            print '<td class="col-date">'.dol_print_date(dol_stringtotime($obj->date_creation), 'dayhour').'</td>';
            print '<td class="col-poids">'.price($obj->poids_total).'</td>';

            // Colonne Nombre de cartons avec span stylé
            print '<td class="col-cartons">';
            print '<span class="badge-cartons">';
            print '<i class="fa fa-box" style="color:#9b59b6; margin-right:6px;"></i>';
            print '<strong>'.(int)$obj->nb_carton_total.'</strong>';
            print '</span>';
            print '</td>';
            print '<td class="col-frais">'.price($obj->total_frais).'</td>';
            print '<td class="col-etat">'.$statutLabel.'</td>';
            print '<td class="col-actions">';
            if ($is_regroupement) {
                print '<a class="action-button" href="bonsortie_document.php?id='.$obj->rowid_bon.'"><i class="fa fa-eye"></i> '.$langs->trans("VoirDetails").'</a>';
            } else {
                print '<a class="action-button" href="detail.php?id='.$obj->rowid.'"><i class="fa fa-eye"></i> '.$langs->trans("VoirDetails").'</a>';
            }
            print '</td>';
            print '</tr>';
        }

        print '</tbody></table>';
        print '</div>';

        if (!$is_regroupement) {
            print '</form>';
        }

        $hasMore = ($num > $limit) ? true : false;

        // -----------------------------------------------------------------------------
        // PAGINATION
        function build_query_preserve($overrides = array()) {
            $params = array();
            $keys = array('search_ref','search_type','search_entrepot','search_date_start','search_date_end','limit','page','regroupement');
            foreach ($keys as $k) {
                if (isset($_GET[$k]) && $_GET[$k] !== '') $params[$k] = $_GET[$k];
            }
            foreach ($overrides as $k=>$v) $params[$k] = $v;
            return http_build_query($params);
        }

        print '<div class="pagination">';
        if ($page > 0) {
            $prev_q = build_query_preserve(array('page' => $page-1));
            print '<a href="?'.$prev_q.'">⬅️ '.$langs->trans("Precedent").'</a>';
        }
        
        // Affichage des numéros de page
        if ($is_regroupement) {
            $total_sql = "SELECT COUNT(DISTINCT s.fk_bonsortie) as total FROM ".MAIN_DB_PREFIX."pech_sortie AS s WHERE s.entity = ".((int)$conf->entity)." AND s.is_regroupe = 1";
        } else {
            $total_sql = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."pech_sortie AS s WHERE s.entity = ".((int)$conf->entity)." AND s.is_regroupe = 0";
            if (!empty($entrepots_accessibles)) {
                $total_sql .= " AND s.fk_entrepot_source IN (".implode(',', $entrepots_accessibles).")";
            }
        }
        $total_res = $db->query($total_sql);
        $total_obj = $total_res ? $db->fetch_object($total_res) : null;
        $total_rows = $total_obj ? $total_obj->total : 0;
        $total_pages = ceil($total_rows / $limit);
        
        for ($p = 0; $p < $total_pages; $p++) {
            $url = '?'.build_query_preserve(array('page' => $p));
            if ($p == $page) {
                print '<a href="'.$url.'" style="font-weight:bold;">'.($p+1).'</a>';
            } else {
                print '<a href="'.$url.'">'.($p+1).'</a>';
            }
        }

        if (isset($hasMore) && $hasMore) {
            $next_q = build_query_preserve(array('page' => $page+1));
            print '<a href="?'.$next_q.'">'.$langs->trans("Suivant").' ➡️</a>';
        }
        print '</div>';

    } else {
        print '<div class="no-results">';
        print '<i class="fa fa-search"></i>';
        print '<h3>'.$langs->trans("AucunResultat").'</h3>';
        print '<p>'.$langs->trans("AucunBonSortieTrouve").'</p>';
        print '</div>';
    }
} else {
    print '<div class="error">'.$db->lasterror().'</div>';
}

print '</div>'; // .reception-list-container

if (!$is_regroupement) {
?>
<script>
function toggleAllSorties(master) {
    var checkboxes = document.querySelectorAll('.sortie-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = master.checked;
    });
    toggleGroupButton();
}

document.addEventListener('DOMContentLoaded', function() {
    var checkboxes = document.querySelectorAll('.sortie-checkbox');
    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', toggleGroupButton);
    });
    
    var form = document.getElementById('formRegrouper');
    if (form) {
        form.addEventListener('submit', function(e) {
            var checked = document.querySelectorAll('.sortie-checkbox:checked');
            if (checked.length < 2) {
                alert("Veuillez sélectionner au moins 2 sorties pour faire un regroupement.");
                e.preventDefault();
                return;
            }
            
            var firstType = checked[0].getAttribute('data-type');
            var firstClient = checked[0].getAttribute('data-client');
            var firstDest = checked[0].getAttribute('data-dest');
            
            for (var i = 1; i < checked.length; i++) {
                var type = checked[i].getAttribute('data-type');
                var client = checked[i].getAttribute('data-client');
                var dest = checked[i].getAttribute('data-dest');
                
                if (type !== firstType) {
                    alert("Toutes les sorties sélectionnées doivent être du même type (Vente ou Transfert).");
                    e.preventDefault();
                    return;
                }
                
                if (firstType === '1' && client !== firstClient) {
                    alert("Toutes les sorties sélectionnées doivent être destinées au même client.");
                    e.preventDefault();
                    return;
                }
                
                if (firstType === '0' && dest !== firstDest) {
                    alert("Toutes les sorties sélectionnées doivent être destinées au même entrepôt.");
                    e.preventDefault();
                    return;
                }
            }
        });
    }
});

function toggleGroupButton() {
    var checked = document.querySelectorAll('.sortie-checkbox:checked');
    var btnContainer = document.getElementById('group_action_container');
    if (btnContainer) {
        btnContainer.style.display = checked.length > 0 ? 'flex' : 'none';
    }
}
</script>
<?php
}

llxFooter();
$db->close();
?>