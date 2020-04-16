<?php
    class StPoint {
        /** Долгота LNG */
        public $x = 0.0;
        /** Широта LAT */
        public $y = 0.0;

        public function __construct($arg = null) {
            if(is_a($arg, 'StPoint')) {
                $this->x = $arg->x;
                $this->y = $arg->y;
            }elseif(is_object($arg)) {
                $this->x = $arg->x;
                $this->y = $arg->y;
            }elseif(is_array($arg)) {
                if(is_numeric($arg[0])) {
                    $this->x = floatval($arg[0]);
                    $this->y = floatval($arg[1]);
                } else {
                    $this->x = floatval(str_replace(',', '.', $arg[0]));
                    $this->y = floatval(str_replace(',', '.', $arg[1]));
                }
            }
            if(is_string($arg)) {
                if(preg_match('/(\d*[\.,]*\d+)[ ,](\d*[\.,]*\d+)/', $arg, $m)) {
                    $this->x = floatval(str_replace(',', '.', $m[1]));
                    $this->y = floatval(str_replace(',', '.', $m[2]));
                } elseif(preg_match('/^POINT\((\d*[\.,]*\d+)[ ,](\d*[\.,]*\d+)\)$/', $arg, $m)) {
                    $this->x = floatval(str_replace(',', '.', $m[1]));
                    $this->y = floatval(str_replace(',', '.', $m[2]));
                }
            }
        }

        public function swapCoord() {
            $t = $this->y;
            $this->y = $this->x;
            $this->x = $t;
        }

        public function equal(StPoint $p) {
            return $this->y == $p->y && $this->x == $p->x;
        }

        public function toString() {
            return str_replace(',', '.', $this->x) . ' ' . str_replace(',', '.', $this->y);
        }

        public function toCommaString() {
            return str_replace(',', '.', $this->x) . ',' . str_replace(',', '.', $this->y);
        }

        public function getLng() {
            return str_replace(',', '.', $this->x);
        }

        public function getLat() {
            return str_replace(',', '.', $this->y);
        }

        public function toWKText() {
            return 'POINT(' . $this->toString() . ')';
        }

        public function project($distance, $azimuth) {
            global $PG;
            $pt = $this->toCommaString();
            $ds = floatval($distance);
            $az = floatval($azimuth);
            return new StPoint($PG->select_scalar("SELECT ST_AsText(ST_Project(ST_Point($pt)::geography, $ds, radians($az)))"));
        }
    }
