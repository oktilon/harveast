-- update gps_order_log ol
left join gps_joint_items ji ON ji.log_id = ol.id
left join gps_joint j ON j.id = ji.jnt_id
set ol.flags = ol.flags & ~8
where j.flags & 2 = 0 and ol.flags & 8


select * from gps_geofence gg where gg.id =5865 -- КМс-Мак-070.2.0

select * from techops t where t.id =233 -- Внесение удобрений


select * from gps_joint gj where techop =233 and geo =5865

select * from gps_joint_items gj where jnt_id =23766


-- 10756
-- 10760