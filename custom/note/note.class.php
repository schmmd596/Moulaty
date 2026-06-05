<?php
class Note {
    public $db;
    public $error;
    
    public $rowid;
    public $libelle;
    public $note;
    public $datec;
    public $user;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function create() {
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "notes (libelle, note, datec, user)";
        $sql .= " VALUES ('" . $this->db->escape($this->libelle) . "',";
        $sql .= " '" . $this->db->escape($this->note) . "',";
        $sql .= " '" . $this->db->idate($this->datec) . "',";
        $sql .= " " . (int)$this->user . ")";
        
        if ($this->db->query($sql)) {
            $this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX . "notes");
            return $this->rowid;
        } else {
            $this->error = $this->db->lasterror();
            return false;
        }
    }
    
    public function update() {
        $sql = "UPDATE " . MAIN_DB_PREFIX . "notes SET";
        $sql .= " libelle = '" . $this->db->escape($this->libelle) . "',";
        $sql .= " note = '" . $this->db->escape($this->note) . "'";
        $sql .= " WHERE rowid = " . (int)$this->rowid;
        
        if ($this->db->query($sql)) {
            return true;
        } else {
            $this->error = $this->db->lasterror();
            return false;
        }
    }
    
    public function delete($id) {
        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "notes WHERE rowid = " . (int)$id;
        
        if ($this->db->query($sql)) {
            return true;
        } else {
            $this->error = $this->db->lasterror();
            return false;
        }
    }
    
    public function fetch($id) {
        $sql = "SELECT rowid, libelle, note, datec, user";
        $sql .= " FROM " . MAIN_DB_PREFIX . "notes";
        $sql .= " WHERE rowid = " . (int)$id;
        
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj) {
                $this->rowid = $obj->rowid;
                $this->libelle = $obj->libelle;
                $this->note = $obj->note;
                $this->datec = $this->db->jdate($obj->datec);
                $this->user = $obj->user;
                return true;
            }
        }
        return false;
    }
    
    public function fetchAll($sortfield = 'datec', $sortorder = 'DESC') {
        $sql = "SELECT n.rowid, n.libelle, n.note, n.datec, n.user, u.lastname, u.firstname";
        $sql .= " FROM " . MAIN_DB_PREFIX . "notes as n";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user as u ON n.user = u.rowid";
        $sql .= " ORDER BY " . $sortfield . " " . $sortorder;
        
        $resql = $this->db->query($sql);
        if ($resql) {
            $notes = array();
            while ($obj = $this->db->fetch_object($resql)) {
                $notes[] = $obj;
            }
            return $notes;
        } else {
            $this->error = $this->db->lasterror();
            return false;
        }
    }
    
    // Méthode utilitaire pour récupérer une note avec les infos utilisateur
    public function fetchWithUser($id) {
        $sql = "SELECT n.rowid, n.libelle, n.note, n.datec, n.user,";
        $sql .= " u.lastname, u.firstname, u.email";
        $sql .= " FROM " . MAIN_DB_PREFIX . "notes as n";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user as u ON n.user = u.rowid";
        $sql .= " WHERE n.rowid = " . (int)$id;
        
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj) {
                $this->rowid = $obj->rowid;
                $this->libelle = $obj->libelle;
                $this->note = $obj->note;
                $this->datec = $this->db->jdate($obj->datec);
                $this->user = $obj->user;
                $this->user_lastname = $obj->lastname;
                $this->user_firstname = $obj->firstname;
                $this->user_email = $obj->email;
                return true;
            }
        }
        return false;
    }
}