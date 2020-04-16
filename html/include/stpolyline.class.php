<?php
    class StPolyline {
        /** @var StPoint[] */
        public $points  = array();

        public $length = 0;


        /**
        * Конструктор полилинии
        *
        * @param mixed Строка координат или массив координат
        * @param boolean Рассчитывать периметр
        * @return StPolyline
        */
        public function __construct($arg = null, $evalLen = false) {
            if(is_string($arg)) {
                $len = strlen($arg);
                if(strpos($arg, 'LINESTRING(') !== FALSE && strrpos($arg, ')') == ($len-1)) {
                    $loop = substr($arg, 11, $len - 12);
                } elseif(strpos($arg, 'POINT') !== FALSE) {
                    $loop = substr($arg, 6, $len - 7);
                } else {
                    $loop = $arg;
                }
                $arg  = explode(',', $loop);
            }
            if(is_array($arg)) {
                $last = null;
                foreach($arg as $pt) {
                    $p = new StPoint($pt);
                    if($last) $this->length += StPolygon::distance($last, $p);
                    $this->points[] = $p;
                    $last = $p;
                }
                return;
            }
        }

        public function addPoint($pt, $calcDist = true) {
            $opt = !is_a($pt, 'StPoint') ? new StPoint($pt) : $pt;
            if($calcDist) {
                $cnt = $this->pointsCount();
                if($cnt) $this->length +=
                        StPolygon::distance($this->points[$cnt-1], $opt);
            }
            $this->points[] = $opt;
        }

        public function append($pt, $len = 0) {
            $app = !is_a($pt, 'StPoint') ? new StPoint($pt) : $pt;
            $cnt = $this->pointsCount();
            if($cnt && $this->points[$cnt-1]->equal($app)) return;
            $this->points[] = $app;
            $this->length += $len;
        }

        public function clean() { $this->points = []; }

        public static function fastParse($poly) {
            return preg_match('/^LINESTRING\((.*)\)$/', $poly, $m) ? $m[1] : '';
        }

        public static function fastParseMulty($poly) {
            if(substr($poly, 0, 1) == 'L') return self::fastParse($poly);
            if(preg_match('/^MULTILINESTRING\(\((.*)\)\)$/', $poly, $m)) {
                return str_replace('),(', '|', $m[1]);
            }
            return '';
        }

        public function isEmpty() {
            return empty($this->points);
        }

        public function valid() { return count($this->points) > 1;}

        public function pointsCount() { return count($this->points); }

        public function toPGString($full = true) {
            $t = array();
            foreach($this->points as $p) {
                $t[] = $p->toString();
            }
            $ret = implode(',', $t);
            return $full ? $ret : $ret;
        }

        public function toGridString() {
            $t = array();
            foreach($this->points as $p) {
                $t[] = $p->toString();
            }
            return implode(',', $t);
        }

        public function toWKText() {
            $cnt = count($this->points);
            if($cnt == 0) throw new Exception("No points!");
            if($cnt == 1) return $this->points[0]->toWKText();
            return 'LINESTRING(' . $this->toGridString() . ')';
        }

        public function toString() { return $this->toWKText(); }
    }