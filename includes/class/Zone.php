<?php

class Zone {
    public function __construct() {
        $this->id = '';
        $this->name = '';
        $this->kind = '';
        $this->url = '';
        $this->serial = '';
        $this->dnssec = '';
        $this->soa_edit = '';
        $this->soa_edit_api = '';
        $this->keyinfo = '';
        $this->account = '';
        $this->zone = FALSE;
        $this->nameservers = Array();
        $this->rrsets = Array();
        $this->masters = Array();
    }

    public function parse($data) {
        $this->setId($data['id']);
        $this->setName($data['name']);
        $this->setKind($data['kind']);
        $this->setDnssec($data['dnssec']);
        $this->setAccount($data['account']);
        $this->setSerial($data['serial']);
        $this->url = $data['url'];
        if (isset($data['soa_edit']))
            $this->setSoaEdit($data['soa_edit']);
        if (isset($data['soa_edit_api']))
            $this->setSoaEditApi($data['soa_edit_api']);

        foreach ($data['masters'] as $master) {
            $this->addMaster($master);
        }

        if (isset($data['rrsets'])) {
            foreach ($data['rrsets'] as $rrset) {
                $toadd = new RRSet($rrset['name'], $rrset['type']);
                foreach ($rrset['comments'] as $comment) {
                    $toadd->addComment($comment['content'], $comment['account'], $comment['modified_at']);
                }
                foreach ($rrset['records'] as $record) {
                    $toadd->addRecord($record['content'], $record['disabled']);
                }
                $toadd->setTtl($rrset['ttl']);
                array_push($this->rrsets, $toadd);
            }
        }
    }

    public function importData($data) {
        $this->zone = $data;
    }

    public function setKeyinfo($info) {
        $this->keyinfo = $info;
    }

    public function addNameserver($nameserver) {
        foreach ($this->nameservers as $ns) {
            if ($nameserver == $ns) {
                throw new Exception("We already have this as a nameserver");
            }
        }
        array_push($this->nameservers, $nameserver);

    }

    public function setSerial($serial) {
        $this->serial = $serial;
    }

    public function setSoaEdit($soaedit) {
        $this->soa_edit = $soaedit;
    }

    public function setSoaEditApi($soaeditapi) {
        $this->soa_edit_api = $soaeditapi;
    }
    public function setName($name) {
        $this->name = $name;
    }

    public function setKind($kind) {
        $this->kind = $kind;
    }

    public function setAccount($account) {
        $this->account = $account;
    }

    public function setDnssec($dnssec) {
        $this->dnssec = $dnssec;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function addMaster($ip) {
        foreach ($this->masters as $master) {
            if ($ip == $master) {
                throw new Exception("We already have this as a master");
            }
        }
        array_push($this->masters, $ip);
    }

    public function eraseMasters() {
        $this->masters = Array();
    }

    public function addRRSet($name, $type, $content, $disabled = FALSE, $ttl = 3600, $setptr = FALSE) {
        if ($this->getRRSet($name, $type) !== FALSE) {
            throw new Exception("This rrset already exists.");
        }
        $rrset = new RRSet($name, $type, $content, $disabled, $ttl, $setptr);
        array_push($this->rrsets, $rrset);
    }

    public function addRecord($name, $type, $content, $disabled = FALSE, $ttl = 3600, $setptr = FALSE) {
        $rrset = $this->getRRSet($name, $type);

        if ($rrset) {
            $rrset->addRecord($content, $disabled, $setptr);
            $rrset->setTtl($ttl);
        } else {
            $this->addRRSet($name, $type, $content, $disabled, $ttl, $setptr);
        }

        return $this->getRecord($name, $type, $content);
    }

    public function getRecord($name, $type, $content) {
        $rrset = $this->getRRSet($name, $type);
        foreach ($rrset->exportRecords() as $record) {
            if ($record['content'] == $content) {
                $record['name'] = $rrset->name;
                $record['ttl']  = $rrset->ttl;
                $record['type'] = $rrset->type;
                $id = json_encode($record);
                $record['id']   = $id;
                return $record;
            }
        }

    }

    public function getRRSet($name, $type) {
        foreach ($this->rrsets as $rrset) {
            if ($rrset->name == $name and $rrset->type == $type) {
                return $rrset;
            }
        }

        return FALSE;
    }

    public function rrsets2records() {
        $ret = Array();

        foreach ($this->rrsets as $rrset) {
            foreach ($rrset->exportRecords() as $record) {
                $record['name'] = $rrset->name;
                $record['ttl']  = $rrset->ttl;
                $record['type'] = $rrset->type;
                $id = json_encode($record);
                $record['id']   = $id;
                array_push($ret, $record);
            }
        }

        return $ret;
    }

    public function export() {
        $ret = Array();
        $ret['account'] = $this->account;
        $ret['nameservers'] = $this->nameservers;
        $ret['kind'] = $this->kind;
        $ret['name'] = $this->name;
        $ret['soa_edit'] = $this->soa_edit;
        $ret['soa_edit_api'] = ($this->soa_edit_api == "") ? $defaults['soa_edit_api'] : $this->soa_edit_api;
        if ($this->zone) {
            $ret['zone'] = $this->zone;
            return $ret;
        }

        $ret['dnssec'] = $this->dnssec;
        if ($this->dnssec) {
            $ret['keyinfo'] = $this->keyinfo;
        }
        $ret['id'] = $this->id;
        $ret['masters'] = $this->masters;
        $ret['rrsets'] = $this->exportRRSets();
        $ret['serial'] = $this->serial;
        $ret['url'] = $this->url;
        
        return $ret;
    }

    private function exportRRSets() {
        $ret = Array();
        foreach ($this->rrsets as $rrset) {
            array_push($ret, $rrset->export());
        }

        return $ret;
    }
}

class RRSet {
    public function __construct($name = '', $type = '', $content = '', $disabled = FALSE, $ttl = 3600, $setptr = FALSE) {
        $this->name = $name;
        $this->type = $type;
        $this->ttl  = $ttl;
        $this->changetype = 'REPLACE';
        $this->records = Array();
        $this->comments = Array();

        if (isset($content) and $content != '') {
            $this->addRecord($content, $disabled, $setptr);
        }
    }

    public function delete() {
        $this->changetype = 'DELETE';
    }

    public function setTtl($ttl) {
        $this->ttl = $ttl;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function addRecord($content, $disabled = FALSE, $setptr = FALSE) {
        foreach ($this->records as $record) {
            if ($record->content == $content) {
                throw new Exception($this->name."/".$this->type." has duplicate records.");
            }
        }

        $record = new Record($content, $disabled, $setptr);
        array_push($this->records, $record);
    }

    public function deleteRecord($content) {
        foreach ($this->records as $idx => $record) {
            if ($record->content == $content) {
                unset($this->records[$idx]);
            }
        }
    }
    public function addComment($content, $account, $modified_at = FALSE) {
        $comment = new Comment($content, $account, $modified_at);
        array_push($this->comments, $comment);
    }

    public function export() {
        $ret = Array();
        $ret['comments'] = $this->exportComments();
        $ret['name'] = $this->name;
        $ret['records'] = $this->exportRecords();
        if ($this->changetype != 'DELETE') {
            $ret['ttl'] = $this->ttl;
        }
        $ret['type'] = $this->type;
        $ret['changetype'] = $this->changetype;
        return $ret;
    }

    public function exportRecords() {
        $ret = Array();
        foreach ($this->records as $record) {
            if ($this->type != "A" and $this->type != "AAAA") {
                $record->setptr = FALSE;
            }
            array_push($ret, $record->export());
        }

        return $ret;
    }

    public function exportComments() {
        $ret = Array();
        foreach ($this->comments as $comment) {
            array_push($ret, $comment->export());
        }
        
        return $ret;
    }

}

class Record {
    public function __construct($content, $disabled = FALSE, $setptr = FALSE) {
        $this->content = $content;
        $this->disabled = $disabled;
        $this->setptr = $setptr;
    }

    public function export() {
        $ret;

        $ret['content'] = $this->content;
        $ret['disabled'] = ( bool ) $this->disabled;
        if ($this->setptr) {
            $ret['set-ptr'] = ( bool ) TRUE;
        }

        return $ret;
    }
}

class Comment {
    public function __construct($content, $account, $modified_at) {
        $this->content = $content;
        $this->account = $account;
        $this->modified_at = $modified_at;
    }

    public function export() {
        $ret;

        $ret['content'] = $this->content;
        $ret['account'] = $this->account;
        $ret['modified_at'] = $this->modified_at;
    }
}

?>
