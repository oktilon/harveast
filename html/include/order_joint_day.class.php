<?php
class OrderJointDay {
    /** @var OrderLog[] */
    public $logs = [];
    public $ids  = [];
    public $areas = [];
    public $tot  = 0;

    public function __construct() {
    }

    public function addItem(OrderJointItem $oji) {
        $ol = new OrderLog($oji->log_id);
        $o = WorkOrder::get($ol->ord);

        $this->logs[] = $ol;
        $this->ids[]  = $oji->log_id;
        $this->tot   += $ol->ord_area;
        $this->areas[] = 0; //$o->isLocked() ? $ol->jnt_area : 0;
    }

    public function isLocked() {
        $ret = true;
        foreach($this->areas as $a) {
            if($a == 0) {
                $ret = false;
                break;
            }
        }
        return $ret;
    }

    public function getArea() {
        $ret = 0;
        foreach($this->areas as $a) $ret += $a;
        return $ret;
    }

    public function isLockedItem($ix) {
        return isset($this->areas[$ix]) && $this->areas[$ix] > 0;
    }
}