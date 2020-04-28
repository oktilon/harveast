<?php
class WebixTreeNode {
    public $id = 0;
    public $value = '';
    public $icon = '';
    public $open = false;
    public $data = null;

    public function __construct($obj, $val = 'name', $icon = '', $open = false, $data = null, $prefix = '') {
        $id = $obj->id;
        if($prefix) $id = $prefix . $id;
        $this->id = $id;
        if(property_exists($obj, $val)) $this->value = $obj->$val;

        if(property_exists($obj, $icon)) $this->icon = $obj->$icon;
        elseif($icon) $this->icon = $icon;

        $this->open = $open;

        if($data) $this->data = $data;
    }

    public function addData($arr, $icon = '', $val = 'name', $open = false) {
        if(!$this->data) $this->data = [];
        foreach ($arr as $obj) {
            $nd = new WebixTreeNode($obj, $val, $icon, $open);
            foreach($obj as $k => $v) {
                if($k == 'id') $k = 'iid';
                $nd->$k = $v;
            }
            $this->data[] = $nd;
        }
    }
}