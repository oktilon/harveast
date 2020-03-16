
function BeforeOpenTables(){
    if (window.openDatabase) {
    	kernelDB = openDatabase("WizXpert", "0.1", "", 200000);

    	var spr_table   = "kernel_modules";
    	var table_fields = [
    	        {name:"id",             type:"INTEGER PRIMARY KEY",    		idx:0},
    	        {name:"module",         type:"TEXT DEFAULT ('') UNIQUE",    idx:1},
    	        {name:"function",    	type:"TEXT DEFAULT ('')",    		idx:0},
    	        {name:"dt",            	type:"INTEGER DEFAULT (0)",    		idx:1},
    	   ];
        	 
    	var sql = ` CREATE TABLE IF NOT EXISTS ${spr_table} 
    		(
    		    ${(table_fields.map((e)=>{ return e.name+' '+e.type})).join(',')}
    		)`;

    	query(kernelDB,sql,[],(data)=>{ 

    	    table_fields.forEach((e)=>{
    	        if(e.idx==1)
    	    	    query(kernelDB,`CREATE INDEX IF NOT EXISTS idx_${spr_table}_${e.name} ON ${spr_table} (${e.name});`,[],()=>{},(err)=>{ console.log(err) });
    	    });
    	});
    } 
};

function ExecF(name = "", param = "", laucher = "") {
    BeforeOpenTables();
    if(name==="")return;
    if(laucher===""){
    	console.log(name,'arg:',param===""?"{}":param);
    }else{
    	console.log(laucher,'->',name,'arg:',param===""?"{}":param);
    }

    var _md5Name = calcMD5(name);
    var _name 	 = 'wx' + _md5Name;

    if(window.hasOwnProperty(_name)){
      	param === "" ? window[_name]() : window[_name](param);
      	return; 
    }
    if(window.hasOwnProperty(name)){
      	param === "" ? window[name]() : window[name](param);
      	return; 
    }
    if(window.openDatabase){
	    kernelDB = openDatabase("WizXpert", "0.1", "", 200000);
	    
	    query(kernelDB,`SELECT * FROM kernel_modules WHERE module = ?`,[_md5Name],(results)=>{ 
	    	var _dt = 0,
	    		_fn = "";
	    	if(results.rows.length>0){
	    		_dt = results.rows.item(0).dt;
	    		_fn = results.rows.item(0).function;
	    	}
	    	_activate(_dt,_fn);
		},(err)=>{console.log(err)
			_activate(0,'');
		}); 
	}else{
		_activate(0,'');
	}   
    function _activate(_dt,_fn){

        
        webix.ajax().post(`/${window.WORK_FOLDER}/api/app/getFunction`, {
            m: name,
            dt: _dt
        }, {
            error: function() {
                console.log("Ошибка загрузки функции " + name)
            },
            success: function(text, data, XmlHttpRequest) {

            	data = data.json()
                if (data.status=="ok"){
                     
                    

                    if(data.data!==""&&window.openDatabase){
                    	if(_dt===0){
                    		query(kernelDB,
                    			`INSERT INTO kernel_modules (module,function,dt) VALUES (?,?,?)`,
                    			[_md5Name,data.data,data.dt],
                    			(results)=>{},(err)=>{console.log(err)
                    		}); 
                    	}else{
                    		query(kernelDB,
                    			`UPDATE kernel_modules SET function = ?,dt=? WHERE module = ?`,
                    			[data.data,data.dt,_md5Name],
                    			(results)=>{},(err)=>{console.log(err)
                    		});
                    	}
                    }

                    if(data.data==="")data.data = _fn;
                    eval(_name + " = " + decodeURIComponent(escape(window.atob(data.data))));
                    "" === param ? window[_name]() : window[_name](param);

                }else{
                	 console.log("Функция: '" + name + "' не найдена");
              	}
            }
        });
	     
	}

}

function query(db,sql,val,onsuccess = '',onerror = ''){
    db.transaction(function(transaction) 
    {
        transaction.executeSql(sql, val, function(tx, results){
            if(typeof onsuccess == 'function') onsuccess(results);
        });
    },
    function(err){
            if(typeof onerror == 'function') onerror(err);
    });
}
