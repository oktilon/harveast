CREATE TABLE harveast.geofences (
	id int8 NOT NULL DEFAULT 0,
	own int8 NOT NULL DEFAULT 0,
	tp int4 NOT NULL DEFAULT 0,
	upd timestamp NOT NULL,
	poly geometry NOT NULL,
	pr float8 NOT NULL DEFAULT 0,
	ar float8 NOT NULL DEFAULT 0,
	del int4 NOT NULL DEFAULT 0,
	flags int2 NOT NULL DEFAULT 0,
	min_lng float8 NOT NULL DEFAULT 0.0,
	max_lng float8 NOT NULL DEFAULT 0.0,
	min_lat float8 NOT NULL DEFAULT 0.0,
	max_lat float8 NOT NULL DEFAULT 0.0,
	CONSTRAINT "PK_id" PRIMARY KEY (id)
);
CREATE INDEX "IX_owners" ON harveast.geofences USING btree (own);
CREATE INDEX "IX_types" ON harveast.geofences USING btree (tp);
CREATE INDEX "IX_upd" ON harveast.geofences USING btree (upd);
CREATE INDEX "IX_min_x" ON harveast.geofences USING btree (min_lng);
CREATE INDEX "IX_max_x" ON harveast.geofences USING btree (max_lng);
CREATE INDEX "IX_min_y" ON harveast.geofences USING btree (min_lat);
CREATE INDEX "IX_max_y" ON harveast.geofences USING btree (max_lat);

CREATE TABLE harveast.gps_points (
	id int4 NOT NULL,
	dt int8 NOT NULL,
	geo_id int4 NOT NULL DEFAULT 0,
	spd int4 NOT NULL DEFAULT 0,
	ang int4 NOT NULL DEFAULT 0,
	pt geometry NOT NULL,
	CONSTRAINT gps_points_pk PRIMARY KEY (id,dt)
)
PARTITION BY RANGE(dt);
CREATE INDEX gps_points_geo_id_idx ON harveast.gps_points (geo_id);

CREATE TABLE harveast.order_area (
	id int4 NOT NULL DEFAULT 0,
	dt int8 NOT NULL,
	poly geography NULL,
	ml geography NOT NULL,
	CONSTRAINT "PK_area" PRIMARY KEY (id, dt)
)
PARTITION BY RANGE(dt);

CREATE TABLE harveast.order_log_line (
	id serial NOT NULL,
	log_id int4 NOT NULL DEFAULT 0,
	dtb int8 NOT NULL DEFAULT 0,
	dte int8 NOT NULL DEFAULT 0,
	dst int4 NOT NULL DEFAULT 0,
	pts geometry NOT NULL,
	CONSTRAINT "PK_log_line" PRIMARY KEY (id,dtb)
)
PARTITION BY RANGE(dtb);
CREATE INDEX "IX_dte" ON harveast.order_log_line USING btree (dte);
CREATE INDEX "IX_log" ON harveast.order_log_line USING btree (log_id);

CREATE TABLE order_joint (
	id int4 NOT NULL DEFAULT 0,
	poly geometry NOT NULL,
	CONSTRAINT PK_order_joint PRIMARY KEY (id)
);