<?php
/**
 * Vérifie si l'utilisateur connecté a accès à un entrepôt donné
 * Usage : include_once 'user_entrepot_access.php';
 *        check_user_entrepot_access($entrepotId);
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

function check_user_entrepot_access($fk_entrepot)
{
    global $db, $user;

    // Vérifier si utilisateur connecté
    if (!isset($user->id)) {
        accessforbidden('Utilisateur non connecté.');
    }

    // Récupérer la liste des entrepôts autorisés pour l'utilisateur
    $sql = "SELECT fk_entrepot 
            FROM ".MAIN_DB_PREFIX."user_entrepot
            WHERE fk_user = ".((int)$user->id);
    $res = $db->query($sql);

    if (!$res) {
        return true; // Si impossible de vérifier, autoriser par défaut
    }

    $entrepots_accessibles = [];
    while ($obj = $db->fetch_object($res)) {
        $entrepots_accessibles[] = (int)$obj->fk_entrepot;
    }

    // Si aucun entrepôt assigné, on autorise l'accès par défaut
    if (empty($entrepots_accessibles)) {
        return true;
    }

    // Vérifier l'accès à l'entrepôt demandé
    if (!in_array((int)$fk_entrepot, $entrepots_accessibles)) {
        accessforbidden('Accès interdit à cet entrepôt.');
    }

    // Tout est OK
    return true;
}
/**
 * Récupère tous les entrepôts accessibles pour l'utilisateur connecté
 */
function get_user_entrepots()
{
    global $db, $user;

    if (!isset($user->id)) return [];

    $sql = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."user_entrepot
            WHERE fk_user = ".((int)$user->id);
    $res = $db->query($sql);
    $entrepots_accessibles = [];
    while ($obj = $db->fetch_object($res)) {
        $entrepots_accessibles[] = (int)$obj->fk_entrepot;
    }
    return $entrepots_accessibles;
}
