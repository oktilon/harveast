<?php
class JointZone {
    public $geo;
    /**
     * @var OrderLog[]
     */
    public $logs = [];

    /**
     * @var OrderJoint
     */
    public $jnt;
    public $eval = [];

    public $area = 0;

    public function __construct($geo) {
        $this->geo = $geo;
    }

    public function addLog(OrderLog $ol) {
        foreach($this->logs as $log) {
            if($log->id == $ol->id) {
                return;
            }
        }
        $this->logs[] = $ol;
    }

    public function runJoint() {
        ob_start();

        $this->jnt = OrderJoint::makeJoint($this->logs);
        $this->area = $this->jnt->evalJointAreaByLogs();

        $this->eval = explode("\n", ob_get_clean());

        return $this->jnt->area;
    }

    public function getJson() {
        $ret = new stdClass();
        $g = new GeoFence($this->geo, true, true);

        $ret->i = $this->jnt->id;
        $ret->t = $this->jnt->area;
        $ret->a = $this->area;
        $ret->e = $this->eval;
        $ret->g = $g->getJson();
        $ret->l = [];

        foreach($this->jnt->list as $oji) {
            $ret->l[] = $oji->getFull();
        }

        return $ret;
    }
}