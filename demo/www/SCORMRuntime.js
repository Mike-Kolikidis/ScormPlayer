
var API = {
    data: {},

    LMSInitialize: function(){

        console.log('initialize');
        this.data.lesson_mode = 'normal';
        this.data.lesson_status = 'not_attempted';

        //get from Local Storage
        /*
        var localStorageData = getLocalStorage();
        if(localStorageData !== null){
            data = localStorageData;
        }
        */
        return true;
    },
    LMSFinish: function(a){
        console.log('LMSFinish');
        console.log(a);
        console.log(this.data);
        return true;
    },
    LMSGetValue: function(strElement){
        console.log('getValue');
        console.log(strElement);
        var dataVar = strElement.match(/[a-z_]+$/g);
        if(dataVar.length && this.data.hasOwnProperty(dataVar[0])){

           return  (this.data[dataVar[0]].length)? this.data[dataVar[0]] : '' ;
        }

        return "";
    },
    LMSGetLastError: function(){
        console.log('GetLastError');
        return 0;
    },
    LMSGetErrorString: function(a){
        console.log('GetErrorString');
        console.log(a);
        return '';
    },
    LMSGetDiagnostic: function(a){
        console.log('GetDiagnostic');
        console.log(a);
        return true;
    },
    LMSSetValue: function(strElement, value){
        console.log('setValue');
        console.log(strElement + " : " + value);
        var dataVar = strElement.match(/[a-z_]+$/g);
        if(dataVar.length){
            this.data[dataVar[0]]=value;
        }
        console.log(this.data);
        return true;
    },
    LMSCommit: function(a){
        console.log('LmsCommit');
        console.log(a);
        //saveLocalStorage();
        return true;
    }



};


var API_1484_11 = {
    data: {},

    Initialize: function(){

        console.log('initialize SCORM 2004');
        this.data.lesson_mode = 'normal';
        this.data.lesson_status = 'not_attempted';

        //get from Local Storage
        /*
        var localStorageData = getLocalStorage();
        if(localStorageData !== null){
            data = localStorageData;
        }
        */
        return true;
    },
    Terminate: function(a){
        console.log('LMSFinish');
        console.log(a);
        console.log(this.data);
        return true;
    },
    GetValue: function(strElement){
        console.log('getValue');
        console.log(strElement);
        var dataVar = strElement.match(/[a-z_]+$/g);
        if(dataVar.length && this.data.hasOwnProperty(dataVar[0])){

           return  (this.data[dataVar[0]].length)? this.data[dataVar[0]] : '' ;
        }

        return "";
    },
    GetLastError: function(){
        console.log('GetLastError');
        return 0;
    },
    GetErrorString: function(a){
        console.log('GetErrorString');
        console.log(a);
        return '';
    },
    GetDiagnostic: function(a){
        console.log('GetDiagnostic');
        console.log(a);
        return true;
    },
    SetValue: function(strElement, value){
        console.log('setValue');
        console.log(strElement + " : " + value);
        var dataVar = strElement.match(/[a-z_]+$/g);
        if(dataVar.length){
            this.data[dataVar[0]]=value;
        }
        console.log(this.data);
        return true;
    },
    Commit: function(a){
        console.log('LmsCommit');
        console.log(a);
        //saveLocalStorage();
        return true;
    }



};

function saveLocalStorage(){
    if(typeof(Storage) !== 'undefined') {
        localStorage.setItem('data',JSON.stringify(data));
    }
}
function getLocalStorage(){
    if(typeof(Storage) !== 'undefined') {
        var storageData = localStorage.getItem('data');
        try
        {
               return JSON.parse(storageData);
        }
        catch(e)
        {
              console.log('Empty Storage');
        }
        return false;

    }
    return false;
}

