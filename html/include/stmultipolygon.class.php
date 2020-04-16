<?php
    class StMultiPolygon {
        public $polygons  = array();

        public $length = 0;

        public $area = FALSE;

        /**
        * Конструктор мультиполигона
        *
        * @param mixed Строка координат
        * @return StMultiPolygon
        */
        public function __construct($arg = null) {
            if(is_string($arg)) {
                $polys = [];
                if(preg_match('/^MULTIPOLYGON\(\(\((.*)\)\)\)$/', $arg, $m)) {
                    $polys = explode(')),((', $m[1]);
                    foreach($polys as $poly) {
                        $p = new StPolygon("POLYGON(($poly))");
                        $this->polygons[] = $p;
                        $this->length += $p->length;
                        $this->area += $p->area;
                    }
                } elseif(strpos($arg, 'POLYGON') === 0) {
                    $p = new StPolygon($arg);
                    $this->polygons[] = $p;
                    $this->length += $p->length;
                    $this->area += $p->area;
                } else {
                    $polys = explode('||', $arg);
                    foreach($polys as $poly) {
                        $p = new StPolygon($poly);
                        $this->polygons[] = $p;
                        $this->length += $p->length;
                        $this->area += $p->area;
                    }
                }
            }
        }

        public static function fastParse($poly) {//MULTIPOLYGON
            $ret = '';
            if(preg_match('/^MULTIPOLYGON\(\(\((.*)\)\)\)$/', $poly, $m)) {
                $ret = str_replace(')),((', '||', $m[1]);
                $ret = str_replace('),(', '|', $ret);
            } else {
                $ret = StPolygon::fastParse($poly);
            }
            return $ret;
        }

        public function isEmpty() {
            if(empty($this->polygons)) return true;
            foreach($this->polygons as $poly) {
                if(!$poly->isEmpty()) return false;
            }
            return true;
        }

        public function polyCount() { return count($this->polygons); }
        public function multiPoly() { return count($this->polygons) > 1; }

        public function addPoly($path) {
            $p = new StPolygon($path);
            $this->polygons[] = $p;
        }

        public function joinWith($join) {
            if($join instanceof StMultiPolygon) {
                if(!$join->isEmpty()) {
                    foreach($join->polygons as $p) {
                        if(!$p->isEmpty()) {
                            $this->polygons[] = $p;
                            $this->length += $p->length;
                            $this->area = FALSE;
                        }
                    }
                }
            } elseif($join instanceof StPolygon) {
                if(!$join->isEmpty()) {
                    $this->polygons[] = $join;
                    $this->length += $join->length;
                    $this->area = FALSE;
                }
            }
        }

        public function toPGString($loop = 0, $full = true) {
            /*$t = array();
            if(!isset($this->loops[$loop])) return '';
            foreach($this->loops[$loop] as $p) $t[] = $p->toCommaString();
            $ret = implode('),(', $t);
            if($full) {
                return "polygon(path'(($ret))')";
            } else {
                $ret = "($ret)";
            }
            return $ret;*/
            return '';
        }

        public function toGridString() {
            $t = array();
            foreach($this->polygons as $poly) {
                $t[] = $poly->toGridString();
            }
            $ret = implode('||', $t);
            return $ret;
        }

        public function toString() {
            $t = array();
            foreach($this->polygons as $poly) {
                $t[] = '(' . $poly->toMPString() . ')';
            }
            $ret = implode(',', $t);
            return "MULTIPOLYGON($ret)";
        }
    }