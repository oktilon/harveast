<?php
    class StPolygon {
        public $loops  = array();

        public $length = 0;

        public $area = FALSE;

        private static $dist = 0;

        const pi_del_180 = 0.01745329251994;// pi/180
        const R = 6371000; // Earth radius in meters 6378137

        /**
        * Конструктор полигона (не замкнутные полигоны - замыкаются!!)
        *
        * @param mixed Строка координат, массив координат, или точка центра
        * @param boolean Рассчитывать периметр
        * @param integer Радиус в градусах (для создания овала)
        * @return StPolygon
        */
        public function __construct($arg = null, $type = 0, $evalLen = false, $rad = 0) {
            global $PG;
            self::$dist = 0;

            // TYPE_CIRCLE - create polygon
            if($arg && is_array($arg) && is_object($arg[0]) && $type == GeoFence::TYPE_CIRCLE) {
                $pt = $arg[0];
                $geo = $PG->prepare("SELECT ST_AsText(ST_Buffer(
                                    ST_GeogFromText(:p), :r))")
                            ->bind('p', "POINT({$pt->x} {$pt->y})")
                            ->bind('r', $pt->r)
                            ->execute_scalar();
                if($geo) $arg = $geo;
            }

            if(is_string($arg)) {
                $len = strlen($arg);
                $loops = [];
                if(strpos($arg, 'POLYGON((') !== FALSE && strrpos($arg, '))') == ($len-2)) {
                    $loops = substr($arg, 9, $len - 11);
                    $loops = explode('),(', $loops);
                } else {
                    $loops = explode('|', $arg);
                }
                foreach($loops as $loop) {
                    $lst = explode(',', $loop);
                    $l   = self::parseLoop($lst, $evalLen);
                    $this->loops[] = $l;
                }
                $this->length = self::$dist;
                return;
            }

            if(is_a($arg, 'StPoint') && is_numeric($rad)) {
                $max  = 2*pi();
                $step = pi() / 16;
                /** @var StPoint */
                $c   = $arg;
                $arg = array();
                for($i = 0; $i < $max; $i += $step) {
                    $p = new StPoint(array(
                        $c->y + cos($i),
                        $c->x + sin($i)
                    ));
                    $arg[] = $p;
                }
            }
            if(is_array($arg)) {
                $this->loops[] = self::parseLoop($arg, $evalLen);
                $this->length  = self::$dist;
            }
        }

        /** parse single loop
        * put your comment there...
        *
        * @param array
        * @param boolean
        *
        * @return StPoint[]
        */
        public static function parseLoop($arr, $evalLen) {
            $ret = array();
            $ptp = null;
            self::$dist = 0;

            foreach($arr as $pt) {
                $spt = new StPoint($pt);
                $ret[] = $spt;
                if($evalLen && $ptp) {
                    self::$dist += self::distance($ptp, $spt);
                }
                $ptp = $spt;
            }
            // Tail test
            $cnt = count($ret);
            if($cnt > 2) {
                if(!$ret[0]->equal($ret[$cnt-1])) {
                    $spt = new StPoint($ret[0]);
                    $ret[] = $spt;
                    if($evalLen && $ptp) {
                        self::$dist += self::distance($ptp, $spt);
                    }
                }
            }
            return $ret;
        }

        public static function distance($p1, $p2)
        {
            $f1 = $p1->y * self::pi_del_180;
            $f2 = $p2->y * self::pi_del_180;
            $df = ($p2->y - $p1->y) * self::pi_del_180;
            $dl = ($p2->x - $p1->x) * self::pi_del_180;

            $a = sin($df/2.0) * sin($df/2.0) +
                 cos($f1) * cos($f2) * sin($dl/2.0) * sin($dl/2.0);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));

            return self::R * $c;
        }

        public function swap() {
            /** @var StPoint */
            $pt = null;
            foreach($this->loops as $loop) {
                foreach($loop as $pt) {
                    $pt->swapCoord();
                }
            }
        }

        public static function fastParse($poly) {
            return preg_match('/^POLYGON\(\((.*)\)\)$/', $poly, $m) ? str_replace('),(', '|', $m[1]) : '';
        }

        public function area($add = FALSE) {
            if($this->area !== FALSE) return $this->area;
            $this->area   = 0;
            foreach($this->loops as $i => $loop) {
                $a = $this->loopArea($loop);
                $this->length = self::$dist;
                if($i == 0) {
                    $this->area += $a;
                } else {
                    if($add) $this->area += $a;
                    else     $this->area -= $a;
                }
            }
            return $this->area;
        }

        public function loopArea($loop) {
            $cnt = count($loop);
            self::$dist = 0;
            if($cnt < 3) return 0;
            $pi   = pi();
            $crc  = 2 * self::R * $pi;
            $lat0 = $loop[0]->y;
            $lon0 = $loop[0]->x;
            $x = 0;
            $y = 0;
            $a = 0;
            for($i = 1; $i < $cnt; $i++) {
                $yi = ($loop[$i]->y - $lat0) / 360 * $crc;
                $xi = ($loop[$i]->x - $lon0) / 360 * $crc * cos($pi * $loop[$i]->y / 180);
                $a += (($y*$xi)-($x*$yi))/2;
                self::$dist += sqrt(($xi-$x)*($xi-$x) + ($yi-$y)*($yi-$y));
                $x = $xi;
                $y = $yi;
            }
            return abs($a);
        }

        public function isEmpty() {
            if(empty($this->loops)) return true;
            foreach($this->loops as $loop) {
                if(empty($loop)) return true;
            }
            return false;
        }

        public function loopsCount() { return count($this->loops); }
        public function multiLoops() { return count($this->loops) > 1; }

        public function joinWith(StPolygon $join) {
            if(!$join->isEmpty()) {
                $this->loops = array_merge($this->loops, $join->loops);
                $this->area = FALSE;
            }
        }

        public function popLoop() {
            $ret = new StPolygon();
            if($this->loopsCount() > 1) {
                $ret->loops[] = array_pop($this->loops);
            }
            return $ret;
        }

        public function appendPoint(StPoint $pt, $loopIndex = 0) {
            $cnt = count($this->loops);
            if($loopIndex > $cnt) return;
            if($loopIndex == $cnt) $this->loops[] = array();
            $this->loops[$loopIndex][] = $pt;
        }

        public function toPGString($loop = 0, $full = true) {
            $t = array();
            if(!isset($this->loops[$loop])) return '';
            foreach($this->loops[$loop] as $p) $t[] = $p->toCommaString();
            $ret = implode('),(', $t);
            if($full) {
                return "polygon(path'(($ret))')";
            } else {
                $ret = "($ret)";
            }
            return $ret;
        }

        public function toGridString() {
            $t = array();
            foreach($this->loops as $loop) {
                $l = array();
                foreach($loop as $p) $l[] = $p->toString();
                $t[] = implode(',', $l);
            }
            $ret = implode('|', $t);
            return $ret;
        }

        public function toMPString() {
            $t = array();
            foreach($this->loops as $loop) {
                $l = array();
                foreach($loop as $p) $l[] = $p->toString();
                $t[] = '(' . implode(',', $l) . ')';
            }
            return implode(',', $t);
        }

        public function toString() {
            $ret = $this->toMPString();
            return "POLYGON($ret)";
        }

    }