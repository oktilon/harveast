<?php
class JointOperation {
    /**
     * @var TechOperation
     */
    public $top;
    /**
     * @var JointZone[]
     */
    public $geos = [];

    public $d_beg;
    public $d_end;

    public $total = 0;
    public $area = 0;

    public $dbg = [];

    public function __construct(TechOperation $to = null) {
        $this->top = $to ? $to : new TechOperation();
        $this->d_beg = new DateTime('2999-01-01');
        $this->d_end = new DateTime('2000-01-01');
    }

    public function addLog(OrderLog $ol) {
        $jz = null;
        foreach($this->geos as $g) {
            if($g->geo == $ol->geo) {
                $jz = $g;
                break;
            }
        }
        if($jz == null) {
            $jz = new JointZone($ol->geo);
            $this->geos[] = $jz;
        }
        $jz->addLog($ol);
        if($ol->dt_beg < $this->d_beg) {
            $this->d_beg = $ol->dt_beg;
        }
        if($ol->dt_end > $this->d_end) {
            $this->d_end = $ol->dt_end;
        }
    }

    public function isNewOperation($toi) {
        if($this->top->id == 0) return true;
        return $this->top->id != $toi;
    }

    public function getCoWorkingLogs($max_gap) {
        if($this->d_beg->format('Hi') < 700) {
            $this->d_beg->sub(new DateInterval('P1D'))
                        ->setTime(7,0);
        }
        if($this->d_end->format('Hi') > 700) {
            $this->d_end->add(new DateInterval('P1D'))
                        ->setTime(6,59);
        }
        $dbeg = intval($this->d_beg->format('U'));
        $dend = intval($this->d_end->format('U'));

        $geos = [];
        foreach($this->geos as $jz) {
            $geos[] = $jz->geo;
        }

        $top = $this->top->id;

        $ok  = true;
        $gap = 0;
        $dtx = $dbeg;
        while($ok) {
            $dt = $dtx - OrderLog::ONE_DAY;
            $ok = OrderLog::getCount($dt, $geos, $top) > 0;
            if($ok) {
                $dbeg = $dt;
                $dtx = $dt;
            } else {
                $gap++;
                if($gap <= $max_gap) {
                    $ok = true;
                    $dtx = $dt;
                }
            }
        }

        $geos = implode(',', $geos);

        return OrderLog::getList([
            ['techop = :to', 'to', $top],
            // ['top_cond = :tc', 'tc', $topc],
            ['dt_beg >= :db', 'db', date('Y-m-d H:i:s', $dbeg)],
            ['dt_end <= :de', 'de', date('Y-m-d H:i:s', $dend)],
            "geo IN ($geos)"
        ], 'geo, dt_beg, ord');
    }

    public function evalOperationArea()
    {
        $logs = $this->getCoWorkingLogs(3);

        foreach($logs as $log) {
            $ord = WorkOrder::get($log->ord);
            if(!$ord->isAreaCalculated() && $log->canEvalArea()) {
                $a = $log->evalArea($ord->isDoubleTrack(), true, true);
                $this->dbg[] = sprintf("l:%d,g:%d,o:%d_eval = %.2f", $log->id, $log->geo, $log->ord, $a);
            } else {
                $this->dbg[] = sprintf("l:%d,g:%d,o:%d_read = %.2f", $log->id, $log->geo, $log->ord, $log->ord_area);
            }
            $this->dbg[] = PageManager::popDebug();

            $this->addLog($log);
        }
        $tot = 0;
        foreach ($this->geos as $jz) {
            $tot += $jz->runJoint();
        }
        $this->total = $tot;
        return $tot;
    }

    public function getJson() {
        $ret = new stdClass();

        $ret->to = $this->top->getJson(true);
        $ret->jnt = [];
        $ret->dbg = $this->dbg;
        $ret->tot = $this->total;
        $ret->a = 0;

        foreach($this->geos as $jz) {
            $ret->a += $jz->area;
            $ret->jnt[] = $jz->getJson();
        }
        return $ret;
    }
}